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

        echo View::page('dashboard/customer', [
            'customer' => $customer,
            'tickets'  => $tickets,
            'messages' => $messages,
            'name'     => Session::get('display_name', 'Customer'),
        ], $this->meta('My Account | Patriot Pest Control', 'Your Patriot Pest Control account dashboard.', '/customer-dashboard'));
    }
}
