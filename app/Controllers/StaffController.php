<?php
/**
 * StaffController — the staff dashboard (the "real admin" of the old site).
 *
 * Phase 1 renders an overview with role-aware nav. Later phases rebuild the
 * full feature set (customers, appointments, tickets, cases, messages,
 * reactivation, phone lookup, analytics) on top of the clean core. Access is
 * guarded at the route level (->auth('staff')); admin-only views additionally
 * check Session::isAdmin().
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\View;
use PPC\Core\Session;
use PPC\Core\Database;
use PPC\Core\Csrf;
use PPC\Core\Logger;
use PPC\Integrations\FieldRoutes;

class StaffController extends PageController
{
    public function dashboard(): void
    {
        $db   = Database::instance();
        $role = Session::staffRole();

        // Quick counts for the overview cards.
        $counts = [
            'openTickets' => (int) $db->scalar("SELECT COUNT(*) FROM tickets WHERE status = 'open'"),
            'openCases'   => (int) $db->scalar("SELECT COUNT(*) FROM cases WHERE status = 'open'"),
            'staff'       => (int) $db->scalar('SELECT COUNT(*) FROM staff WHERE active = 1'),
            'customers'   => (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE source != 'seed'"),
        ];

        echo View::page('dashboard/staff', [
            'name'   => Session::get('display_name', 'Staff'),
            'role'   => $role,
            'isAdmin' => Session::isAdmin(),
            'counts' => $counts,
        ], $this->meta('Staff Dashboard | Patriot Pest Control', 'Internal staff dashboard.', '/staff-dashboard'));
    }

    /**
     * Customer book — searchable list of the local customer cache. FieldRoutes
     * stays the source of truth; this is the offline-capable cache plus local
     * flags (no-call / status). Supports ?q= (name/phone/email/account) and
     * ?status= filtering.
     */
    public function customers(): void
    {
        $db = Database::instance();
        $q  = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $perPage = 50;
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $where  = ["source != 'seed'"];   // seed fixtures never count toward the real book
        $params = [];
        if ($q !== '') {
            $where[]  = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR account_number LIKE ? OR city LIKE ?)';
            $like     = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // Total matching this filter (drives the pager), then the page slice.
        $matchCount = (int) $db->scalar('SELECT COUNT(*) FROM customers' . $whereSql, $params);
        $pageCount  = max(1, (int) ceil($matchCount / $perPage));
        if ($page > $pageCount) {
            $page = $pageCount; // never land on an empty page
        }
        $offset = ($page - 1) * $perPage;

        $customers = $db->fetchAll(
            'SELECT * FROM customers' . $whereSql . ' ORDER BY name LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );

        // FieldRoutes sync status (the cache is filled by the Sync button / bin/fr-sync-customers.php).
        $frLastSync = $db->scalar("SELECT value FROM meta WHERE key = 'fr_last_sync'");
        $frConfigured = \PPC\Integrations\FieldRoutes::isConfigured();

        echo View::page('staff/customers', [
            'customers'    => $customers,
            'q'            => $q,
            'status'       => $status,
            'total'        => (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE source != 'seed'"),
            'matchCount'   => $matchCount,
            'page'         => $page,
            'perPage'      => $perPage,
            'pageCount'    => $pageCount,
            'isAdmin'      => Session::isAdmin(),
            'frLastSync'   => $frLastSync,
            'frConfigured' => $frConfigured,
        ], $this->meta('Customers | Patriot Pest Control', 'Internal customer book.', '/staff/customers'));
    }

    /**
     * A single customer profile ("like the original"): identity + status flags,
     * tickets, messages, and internal notes. FieldRoutes-backed panels
     * (appointments, subscriptions, billing) plug in here once credentials land.
     */
    public function customerProfile(string $id): void
    {
        $db = Database::instance();
        $customer = $db->fetch('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$customer) {
            \PPC\Core\Router::notFound();
        }

        $tickets  = $db->fetchAll('SELECT * FROM tickets WHERE customer_id = ? ORDER BY created_at DESC LIMIT 25', [(string) $customer['id']]);
        $messages = $db->fetchAll('SELECT * FROM messages WHERE to_user = ? OR from_user = ? ORDER BY created_at DESC LIMIT 25', [(string) $customer['id'], (string) $customer['id']]);
        $notes    = $db->fetchAll('SELECT * FROM customer_notes WHERE customer_id = ? ORDER BY updated_at DESC LIMIT 50', [(string) $customer['id']]);

        // Live FieldRoutes panel (appointments + subscriptions). Only fetched when
        // the integration is configured AND this record is linked to an FR id; the
        // seeded fixtures (no fr_id) just show the "pending" state. Failures never
        // break the page — they surface as a notice via $fr['error'].
        $fr = ['configured' => FieldRoutes::isConfigured(), 'linked' => false, 'appointments' => [], 'subscriptions' => [], 'error' => null];
        $frId = $customer['fr_id'] ?? null;
        if ($fr['configured'] && $frId !== null && $frId !== '') {
            $dist = FieldRoutes::districtByCode((string) ($customer['district'] ?? ''));
            if ($dist) {
                $fr['linked'] = true;
                try {
                    $live = FieldRoutes::pullCustomerLive($dist, (string) $frId);
                    $fr['appointments']  = $live['appointments'];
                    $fr['subscriptions'] = $live['subscriptions'];
                } catch (\Throwable $e) {
                    $fr['error'] = $e->getMessage();
                    Logger::warning('FieldRoutes live pull failed', ['fr_id' => $frId, 'err' => $e->getMessage()]);
                }
            }
        }

        echo View::page('staff/customer-profile', [
            'customer' => $customer,
            'tickets'  => $tickets,
            'messages' => $messages,
            'notes'    => $notes,
            'fr'       => $fr,
            'isAdmin'  => Session::isAdmin(),
        ], $this->meta(($customer['name'] ?? 'Customer') . ' | Patriot Pest Control', 'Customer profile.', '/staff/customers/' . $customer['id']));
    }

    /**
     * Staff message center — conversations to/from staff. Phase 1 lists the
     * local messages table; Twilio SMS/conversations plug in here later.
     */
    public function messages(): void
    {
        $db = Database::instance();
        $messages = $db->fetchAll("SELECT * FROM messages ORDER BY created_at DESC LIMIT 100");

        echo View::page('staff/messages', [
            'messages' => $messages,
            'isAdmin'  => Session::isAdmin(),
        ], $this->meta('Messages | Patriot Pest Control', 'Internal message center.', '/staff/messages'));
    }

    /**
     * JSON customer search for the app-shell magnifier overlay. Staff/admin
     * only (route-guarded). Returns a compact array the overlay renders live.
     */
    public function searchCustomers(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $db = Database::instance();
        $q  = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($q) < 2) {
            echo json_encode([]);
            return;
        }
        $like = '%' . $q . '%';
        $rows = $db->fetchAll(
            'SELECT id, name, phone, email, account_number, city, state, status
             FROM customers
             WHERE source != \'seed\' AND (name LIKE ? OR phone LIKE ? OR email LIKE ? OR account_number LIKE ? OR city LIKE ?)
             ORDER BY name LIMIT 12',
            [$like, $like, $like, $like, $like]
        );
        echo json_encode($rows);
    }

    /**
     * "Sync now" — pull every WA + AZ customer from FieldRoutes into the local
     * cache from inside the console (no CLI, no FR login). CSRF-guarded, with a
     * 60s cooldown so a stray double-click can't burn the per-minute rate limit.
     * Degrades gracefully: if the keys aren't in .env yet it just flashes that
     * fact instead of erroring. One failing district never aborts the other.
     */
    public function syncCustomers(): void
    {
        Csrf::verifyOrDie();
        @set_time_limit(180); // hundreds of customers, batched over the network

        if (!FieldRoutes::isConfigured()) {
            Session::flash('fr_sync', ['type' => 'info', 'msg' => 'FieldRoutes is not configured yet — fill the WA & AZ keys in .env, then sync.']);
            header('Location: /staff/customers');
            exit;
        }

        $db   = Database::instance();
        $last = $db->scalar("SELECT value FROM meta WHERE key = 'fr_last_sync'");
        if ($last && (time() - strtotime((string) $last)) < 60) {
            $ago = time() - strtotime((string) $last);
            Session::flash('fr_sync', ['type' => 'info', 'msg' => "Synced {$ago}s ago — wait a moment before syncing again (FieldRoutes rate-limits reads)."]);
            header('Location: /staff/customers');
            exit;
        }

        $totals = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'errors' => 0];
        $parts  = [];
        foreach (FieldRoutes::districts() as $district) {
            $code = strtoupper($district['code']);
            try {
                $rows = FieldRoutes::pullCustomersForDistrict($district);
                $ins = $upd = 0;
                foreach ($rows as $row) {
                    $r = FieldRoutes::upsertCustomer($row);
                    if ($r === 'inserted') {
                        $ins++;
                    } elseif ($r === 'updated') {
                        $upd++;
                    }
                }
                $n = is_array($rows) ? count($rows) : 0;
                $totals['fetched'] += $n;
                $totals['inserted'] += $ins;
                $totals['updated']  += $upd;
                $parts[] = "{$code}: {$n} pulled ({$ins} new, {$upd} updated)";
            } catch (\Throwable $e) {
                $totals['errors']++;
                $parts[] = "{$code}: error (" . mb_substr($e->getMessage(), 0, 60) . ")";
                Logger::error('FieldRoutes sync district failed', ['district' => $district['code'], 'err' => $e->getMessage()]);
            }
        }

        if ($totals['errors'] === 0) {
            $now = gmdate('Y-m-d H:i:s');
            $db->execute("INSERT INTO meta (key, value) VALUES ('fr_last_sync', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value", [$now]);
        }

        $cache = (int) $db->scalar('SELECT COUNT(*) FROM customers');
        $msg   = 'FieldRoutes sync done — ' . implode(' · ', $parts) . ". Cache now {$cache} customer(s).";
        Session::flash('fr_sync', ['type' => $totals['errors'] ? 'error' : 'success', 'msg' => $msg]);
        header('Location: /staff/customers');
        exit;
    }

    /**
     * Self-service account profile for the signed-in user (staff OR customer).
     * Route is guarded ->auth('*'). Shows the user's own record + session info.
     */
    public function account(): void
    {
        $db    = Database::instance();
        $type  = Session::userType();
        $userId = Session::get('user_id');

        $record = null;
        if ($type === 'staff') {
            $record = $db->fetch('SELECT id, name, email, role, active, last_login, created_at FROM staff WHERE id = ?', [$userId]);
        } elseif ($type === 'customer' && $userId) {
            $record = $db->fetch('SELECT * FROM customers WHERE id = ?', [$userId]);
        }

        echo View::page('account', [
            'type'    => $type,
            'record'  => $record,
            'name'    => Session::get('display_name', 'User'),
            'role'    => Session::staffRole(),
            'isAdmin' => Session::isAdmin(),
        ], $this->meta('My Account | Patriot Pest Control', 'Your account details.', '/account'));
    }
}
