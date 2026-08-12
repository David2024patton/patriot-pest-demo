<?php
/**
 * StaffController - the staff dashboard (the "real admin" of the old site).
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
use PPC\Core\Validator;
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
     * Customer book - searchable list of the local customer cache. FieldRoutes
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
        // break the page - they surface as a notice via $fr['error'].
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
     * Staff message center - conversations to/from staff. Phase 1 lists the
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
     * "Sync now" - pull every WA + AZ customer from FieldRoutes into the local
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
            Session::flash('fr_sync', ['type' => 'info', 'msg' => 'FieldRoutes is not configured yet. Fill the WA & AZ keys in .env, then sync.']);
            header('Location: /staff/customers');
            exit;
        }

        $db   = Database::instance();
        $last = $db->scalar("SELECT value FROM meta WHERE key = 'fr_last_sync'");
        if ($last && (time() - strtotime((string) $last)) < 60) {
            $ago = time() - strtotime((string) $last);
            Session::flash('fr_sync', ['type' => 'info', 'msg' => "Synced {$ago}s ago. Wait a moment before syncing again (FieldRoutes rate-limits reads)."]);
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
        $msg   = 'FieldRoutes sync done: ' . implode(' · ', $parts) . ". Cache now {$cache} customer(s).";
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

    // ========================== STAFF CRUD (admin-only) ==========================

    /** List all staff with roles. Admin-only via route guard. */
    public function staffList(): void
    {
        $db = Database::instance();
        $staff = $db->fetchAll(
            'SELECT s.id, s.email, s.name, s.role, s.active, s.last_login, s.created_at, r.label AS role_label
             FROM staff s LEFT JOIN roles r ON r.role = s.role ORDER BY s.name'
        );
        $roles = $db->fetchAll('SELECT * FROM roles ORDER BY label');

        echo View::page('staff/list', [
            'staff'   => $staff,
            'roles'   => $roles,
            'isAdmin' => Session::isAdmin(),
            'flash'   => Session::pullFlash('staff_crud'),
        ], $this->meta('Staff | Patriot Pest Control', 'Manage staff accounts.', '/admin/staff'));
    }

    /** New staff form (admin only). */
    public function staffNew(): void
    {
        $db = Database::instance();
        $roles = $db->fetchAll('SELECT * FROM roles ORDER BY label');
        echo View::page('staff/edit', [
            'staffMember' => null,
            'roles'       => $roles,
            'isAdmin'     => Session::isAdmin(),
            'flash'       => Session::pullFlash('staff_crud'),
        ], $this->meta('New Staff | Patriot Pest Control', 'Add a staff member.', '/admin/staff/new'));
    }

    /** Create a staff member. */
    public function staffCreate(): void
    {
        Csrf::verifyOrDie();

        $errors = Validator::make($_POST, [
            'name'  => ['required', 'max:200'],
            'email' => ['required', 'email', 'max:254'],
            'role'  => ['required', 'in:admin,tech_support,accounts,sales,staff'],
        ]);
        if ($errors) {
            Session::flash('staff_crud', ['errors' => $errors]);
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/staff'));
            exit;
        }

        $db = Database::instance();
        $email = trim(strtolower(Validator::clean($_POST['email'])));
        $existing = $db->fetch('SELECT id FROM staff WHERE email = ? COLLATE NOCASE', [$email]);
        if ($existing) {
            Session::flash('staff_crud', ['errors' => ['email' => ['A staff member with that email already exists.']]]);
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/staff/new'));
            exit;
        }

        $id = $db->insert('staff', [
            'name'       => Validator::clean($_POST['name']),
            'email'      => $email,
            'role'       => Validator::clean($_POST['role']),
            'active'     => 1,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        Logger::info('Staff created', ['staff_id' => $id, 'by' => Session::get('user_id')]);
        Session::flash('staff_crud', ['success' => 'Staff member added. They can log in with their email - no password needed.']);
        header('Location: /admin/staff');
        exit;
    }

    /** Edit form for a staff member (admin only). */
    public function staffEdit(string $id): void
    {
        $db = Database::instance();
        $staffMember = $db->fetch('SELECT * FROM staff WHERE id = ?', [$id]);
        if (!$staffMember) {
            \PPC\Core\Router::notFound();
        }
        $roles = $db->fetchAll('SELECT * FROM roles ORDER BY label');
        echo View::page('staff/edit', [
            'staffMember' => $staffMember,
            'roles'       => $roles,
            'isAdmin'     => Session::isAdmin(),
            'flash'       => Session::pullFlash('staff_crud'),
        ], $this->meta('Edit Staff | Patriot Pest Control', 'Edit staff member.', "/admin/staff/{$id}"));
    }

    /** Update a staff member. */
    public function staffUpdate(string $id): void
    {
        Csrf::verifyOrDie();
        $db = Database::instance();
        $staffMember = $db->fetch('SELECT * FROM staff WHERE id = ?', [$id]);
        if (!$staffMember) {
            \PPC\Core\Router::notFound();
        }

        // Immutability guard: super-user accounts cannot be modified through staff CRUD.
        if (($staffMember['role'] ?? '') === 'super-user') {
            Session::flash('staff_crud', ['errors' => ['role' => ['Super-user accounts cannot be modified.']]]);
            header('Location: /admin/staff');
            exit;
        }

        $errors = Validator::make($_POST, [
            'name'  => ['required', 'max:200'],
            'email' => ['required', 'email', 'max:254'],
            'role'  => ['required', 'in:admin,tech_support,accounts,sales,staff'],
        ]);
        if ($errors) {
            Session::flash('staff_crud', ['errors' => $errors]);
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "/admin/staff/{$id}"));
            exit;
        }

        $email = trim(strtolower(Validator::clean($_POST['email'])));
        $existing = $db->fetch('SELECT id FROM staff WHERE email = ? COLLATE NOCASE AND id != ?', [$email, $id]);
        if ($existing) {
            Session::flash('staff_crud', ['errors' => ['email' => ['Another staff member already uses that email.']]]);
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? "/admin/staff/{$id}"));
            exit;
        }

        $db->update('staff', [
            'name'  => Validator::clean($_POST['name']),
            'email' => $email,
            'role'  => Validator::clean($_POST['role']),
        ], ['id' => $id]);

        Logger::info('Staff updated', ['staff_id' => $id, 'by' => Session::get('user_id')]);
        Session::flash('staff_crud', ['success' => 'Staff member updated.']);
        header('Location: /admin/staff');
        exit;
    }

    /**
     * Testable seam: checks whether a staff member can be deactivated.
     * Super-user accounts are immutable — extracted so tests can verify the guard.
     */
    protected function isStaffDeactivatable(array $staffMember): bool
    {
        return ($staffMember['role'] ?? '') !== 'super-user';
    }

    /** Toggle staff active/inactive (admin only). */
    public function staffToggle(string $id): void
    {
        Csrf::verifyOrDie();
        $db = Database::instance();
        $staffMember = $db->fetch('SELECT * FROM staff WHERE id = ?', [$id]);
        if (!$staffMember) {
            \PPC\Core\Router::notFound();
        }

        // Immutability guard: super-user accounts cannot be deactivated.
        if (!$this->isStaffDeactivatable($staffMember)) {
            Session::flash('staff_crud', ['errors' => ['name' => ['Super-user accounts cannot be deactivated.']]]);
            header('Location: /admin/staff');
            exit;
        }

        // Prevent deactivating yourself.
        if ((int) $id === (int) (Session::get('user_id') ?? 0)) {
            Session::flash('staff_crud', ['errors' => ['name' => ['You cannot deactivate your own account.']]]);
            header('Location: /admin/staff');
            exit;
        }

        $newActive = $staffMember['active'] ? 0 : 1;
        $db->update('staff', ['active' => $newActive], ['id' => $id]);

        $action = $newActive ? 'activated' : 'deactivated';
        Logger::info("Staff {$action}", ['staff_id' => $id, 'by' => Session::get('user_id')]);
        Session::flash('staff_crud', ['success' => "Staff member {$action}."]);
        header('Location: /admin/staff');
        exit;
    }
}
