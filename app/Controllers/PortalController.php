<?php
/**
 * PortalController — the customer portal.
 *
 * Phase 1 renders the account overview. Later phases add FieldRoutes-backed
 * views (appointments, subscriptions, billing) and messaging/tickets from the
 * local DB. Access is guarded at the route level (->auth('customer')).
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\View;
use PPC\Core\Session;
use PPC\Core\Database;

class PortalController extends PageController
{
    public function dashboard(): void
    {
        $db = Database::instance();
        $customerId = Session::get('user_id');

        $customer = $customerId
            ? $db->fetch('SELECT * FROM customers WHERE id = ?', [$customerId])
            : null;

        // Open tickets + unread messages for the overview.
        $tickets  = $customerId ? $db->fetchAll('SELECT * FROM tickets WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5', [(string) $customerId]) : [];
        $messages = $customerId ? $db->fetchAll('SELECT * FROM messages WHERE to_user = ? AND to_type = "customer" ORDER BY created_at DESC LIMIT 5', [(string) $customerId]) : [];

        // Appointments: try FR live first, fall back to local cache.
        $appointments = [];
        $subscriptions = [];
        $frData = ['configured' => false, 'linked' => false, 'error' => null];
        if ($customer && !empty($customer['fr_id'])) {
            // Try live FR for appointments (subscriptions use local DB due to scoping bug).
            $dist = \PPC\Integrations\FieldRoutes::districtByCode((string) ($customer['district'] ?? ''));
            if ($dist && \PPC\Integrations\FieldRoutes::isConfigured()) {
                $frData['configured'] = true;
                $frData['linked'] = true;
                try {
                    $live = \PPC\Integrations\FieldRoutes::pullCustomerLive($dist, (string) $customer['fr_id']);
                    $appointments = $live['appointments'];
                    // Subscriptions from local cache (FR scoping bug prevents reliable per-customer API pull)
                } catch (\Throwable $e) {
                    $frData['error'] = $e->getMessage();
                    \PPC\Core\Logger::warning('Portal live FR pull failed', ['fr_id' => $customer['fr_id'], 'err' => $e->getMessage()]);
                }
            }
            // Always query local cache for subscriptions; fall back for appointments if FR failed.
            $subscriptions = $db->fetchAll('SELECT * FROM subscriptions WHERE customer_id = ? ORDER BY next_service DESC LIMIT 10', [(string) $customerId]);
            if (!$appointments) {
                $appointments = $db->fetchAll('SELECT * FROM appointments WHERE customer_id = ? ORDER BY scheduled DESC LIMIT 10', [(string) $customerId]);
            }
        }

        // Billing: local cache only (no payment processing in-app yet).
        $paymentMethods = $customerId ? $db->fetchAll('SELECT * FROM payment_methods WHERE customer_id = ?', [(string) $customerId]) : [];
        $invoices = $customerId ? $db->fetchAll('SELECT * FROM invoices WHERE customer_id = ? ORDER BY due_date DESC LIMIT 10', [(string) $customerId]) : [];
        $payments = $customerId ? $db->fetchAll('SELECT * FROM payments WHERE customer_id = ? ORDER BY payment_date DESC LIMIT 10', [(string) $customerId]) : [];

        echo View::page('dashboard/customer', [
            'customer'        => $customer,
            'tickets'         => $tickets,
            'messages'        => $messages,
            'appointments'    => $appointments,
            'subscriptions'   => $subscriptions,
            'paymentMethods'  => $paymentMethods,
            'invoices'        => $invoices,
            'payments'        => $payments,
            'frData'          => $frData,
            'name'            => Session::get('display_name', 'Customer'),
        ], $this->meta('My Account | Patriot Pest Control', 'Your Patriot Pest Control account dashboard.', '/customer-dashboard'));
    }
}
