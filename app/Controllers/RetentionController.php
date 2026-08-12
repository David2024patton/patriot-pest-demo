<?php
/**
 * RetentionController - first-party retention analytics endpoints (ORDER 3).
 *
 *   POST /api/track/view          page view event
 *   POST /api/track/event         custom interaction event (easter egg, links)
 *   POST /api/track/session_end   final heartbeat, closes session
 *   GET  /api/retention/summary   admin dashboard data (admin only)
 *
 * The three POSTs are anonymous ingestion beacons fired by navigator
 * sendBeacon, which cannot set custom headers, so they are CSRF-exempt by
 * design. They are write-only, payload-validated, and same-origin checked.
 * The summary GET requires an authenticated admin (401 JSON for others).
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\Retention;
use PPC\Core\Session;
use PPC\Core\Logger;
use PPC\Core\Config;

class RetentionController
{
    /** POST /api/track/view */
    public function view(): void
    {
        $this->track('view');
    }

    /** POST /api/track/event */
    public function event(): void
    {
        $this->track('event');
    }

    /** POST /api/track/session_end */
    public function sessionEnd(): void
    {
        $this->track('session_end');
    }

    /** GET /api/retention/summary (admin only, 401 JSON otherwise) */
    public function summary(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Session::isAdmin()) {
            http_response_code(401);
            echo json_encode(['error' => 'Admin access required.']);
            return;
        }
        echo json_encode(Retention::summary(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Ingest one tracking payload. Always answers 204 (per the contract) so
     * the beacon never sees an error page; bad payloads are dropped quietly.
     */
    private function track(string $kind): void
    {
        http_response_code(204);

        // Same-origin check: the beacon posts same-origin, so refuse any
        // Origin that does not match the Host header. Comparing against the
        // request Host (not APP_URL) keeps this correct on the test domain.
        // Normalize both sides through parse_url so an explicit port in Host
        // (e.g. 127.0.0.1:8899 on the dev server) does not false-negative.
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $self   = $_SERVER['HTTP_HOST'] ?? '';
        if ($origin !== '' && $self !== '') {
            $host = parse_url($origin, PHP_URL_HOST) ?? '';
            $selfHost = parse_url('//' . $self, PHP_URL_HOST) ?? '';
            if ($host !== '' && $selfHost !== '' && strcasecmp($host, $selfHost) !== 0) {
                return;
            }
        }

        $raw = (string) file_get_contents('php://input');
        $p   = json_decode($raw, true);
        if (!is_array($p)) {
            return;
        }

        try {
            switch ($kind) {
                case 'view':
                    Retention::recordView($p);
                    break;
                case 'event':
                    Retention::recordEvent($p);
                    break;
                case 'session_end':
                    Retention::recordSessionEnd($p);
                    break;
            }
        } catch (\Throwable $e) {
            Logger::warning('Retention track failed', ['kind' => $kind, 'error' => $e->getMessage()]);
        }
    }
}
