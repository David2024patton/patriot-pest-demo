<?php
/**
 * TwilioController — Admin interface for Twilio integration.
 *
 * Provides full admin access to Twilio features:
 * - SMS management (send, view logs, delivery status)
 * - Call logs viewer (recordings, transcriptions)
 * - Voicemail playback and management
 * - Webhook event monitoring
 *
 * All routes guarded ->auth('staff')->role('admin').
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\View;
use PPC\Core\Session;
use PPC\Core\Database;
use PPC\Core\Csrf;
use PPC\Core\Validator;
use PPC\Integrations\Twilio;

class TwilioController extends PageController
{
    /** Twilio admin home: overview and quick stats. */
    public function index(): void
    {
        $db = Database::instance();
        $stats = [
            'sms_total'       => (int) $db->scalar('SELECT COUNT(*) FROM sms_logs'),
            'sms_pending'     => (int) $db->scalar("SELECT COUNT(*) FROM sms_logs WHERE status = 'queued'"),
            'calls_total'     => (int) $db->scalar('SELECT COUNT(*) FROM call_logs'),
            'calls_active'    => (int) $db->scalar("SELECT COUNT(*) FROM call_logs WHERE status IN ('queued','ringing','in-progress')"),
            'voicemails'      => (int) $db->scalar('SELECT COUNT(*) FROM voicemails'),
            'voicemails_new'  => (int) $db->scalar("SELECT COUNT(*) FROM voicemails WHERE status = 'new'"),
            'webhooks'        => (int) $db->scalar('SELECT COUNT(*) FROM webhook_events'),
            'webhooks_pending'=> (int) $db->scalar("SELECT COUNT(*) FROM webhook_events WHERE processed = 0"),
        ];
        echo View::page('twilio/index', ['stats' => $stats, 'flash' => $this->flash()], $this->meta('Twilio | Admin', 'Twilio integration management.', '/admin/twilio'));
    }

    /** ==================== SMS MANAGEMENT ==================== */

    /** SMS logs viewer. */
    public function sms(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $logs = Twilio::getSmsLogs($limit, $offset);
        $total = Database::instance()->scalar('SELECT COUNT(*) FROM sms_logs');
        $pages = (int) ceil($total / $limit);

        echo View::page('twilio/sms', [
            'logs'   => $logs,
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
            'flash'  => $this->flash(),
        ], $this->meta('SMS Logs | Twilio', 'SMS message history and management.', '/admin/twilio/sms'));
    }

    /** Send new SMS form. */
    public function smsNew(): void
    {
        echo View::page('twilio/sms-send', [
            'flash' => $this->flash(),
        ], $this->meta('Send SMS | Twilio', 'Send a new SMS message.', '/admin/twilio/sms/new'));
    }

    /** Process SMS send. */
    public function smsSend(): void
    {
        Csrf::verifyOrDie();
        
        $errors = Validator::make($_POST, [
            'to'      => ['required', 'max:20'],
            'message' => ['required', 'max:1600'],
        ]);
        
        if ($errors) {
            Session::flash('twilio', ['errors' => $errors]);
            header('Location: /admin/twilio/sms/new');
            exit;
        }

        $to = Validator::clean($_POST['to']);
        $message = Validator::clean($_POST['message']);
        $mediaUrl = !empty($_POST['media_url']) ? Validator::clean($_POST['media_url']) : null;

        $result = Twilio::sendSms($to, $message, $mediaUrl);

        if ($result['success']) {
            Session::flash('twilio', ['success' => 'SMS queued successfully. SID: ' . $result['sid']]);
            header('Location: /admin/twilio/sms');
        } else {
            Session::flash('twilio', ['error' => 'SMS failed: ' . $result['error']]);
            header('Location: /admin/twilio/sms/new');
        }
        exit;
    }

    /** View SMS details. */
    public function smsView(string $id): void
    {
        $db = Database::instance();
        $sms = $db->fetch('SELECT * FROM sms_logs WHERE id = ?', [$id]);
        
        if ($sms === null) {
            \PPC\Core\Router::notFound();
        }

        echo View::page('twilio/sms-view', [
            'sms'   => $sms,
            'flash' => $this->flash(),
        ], $this->meta('SMS Details | Twilio', 'SMS message details.', "/admin/twilio/sms/$id"));
    }

    /** ==================== CALL MANAGEMENT ==================== */

    /** Call logs viewer. */
    public function calls(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $logs = Twilio::getCallLogs($limit, $offset);
        $total = Database::instance()->scalar('SELECT COUNT(*) FROM call_logs');
        $pages = (int) ceil($total / $limit);

        echo View::page('twilio/calls', [
            'logs'   => $logs,
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
            'flash'  => $this->flash(),
        ], $this->meta('Call Logs | Twilio', 'Voice call history and recordings.', '/admin/twilio/calls'));
    }

    /** Initiate new call form. */
    public function callNew(): void
    {
        echo View::page('twilio/call-new', [
            'flash' => $this->flash(),
        ], $this->meta('Initiate Call | Twilio', 'Start a new voice call.', '/admin/twilio/calls/new'));
    }

    /** Process call initiation. */
    public function callInitiate(): void
    {
        Csrf::verifyOrDie();
        
        $errors = Validator::make($_POST, [
            'to'  => ['required', 'max:20'],
            'url' => ['required', 'url'],
        ]);
        
        if ($errors) {
            Session::flash('twilio', ['errors' => $errors]);
            header('Location: /admin/twilio/calls/new');
            exit;
        }

        $to = Validator::clean($_POST['to']);
        $url = Validator::clean($_POST['url']);

        $result = Twilio::initiateCall($to, $url);

        if ($result['success']) {
            Session::flash('twilio', ['success' => 'Call initiated successfully. SID: ' . $result['sid']]);
            header('Location: /admin/twilio/calls');
        } else {
            Session::flash('twilio', ['error' => 'Call failed: ' . $result['error']]);
            header('Location: /admin/twilio/calls/new');
        }
        exit;
    }

    /** View call details. */
    public function callView(string $id): void
    {
        $db = Database::instance();
        $call = $db->fetch('SELECT * FROM call_logs WHERE id = ?', [$id]);
        
        if ($call === null) {
            \PPC\Core\Router::notFound();
        }

        echo View::page('twilio/call-view', [
            'call'  => $call,
            'flash' => $this->flash(),
        ], $this->meta('Call Details | Twilio', 'Voice call details and recording.', "/admin/twilio/calls/$id"));
    }

    /** ==================== VOICEMAIL MANAGEMENT ==================== */

    /** Voicemail inbox. */
    public function voicemail(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $voicemails = Twilio::getVoicemails($limit, $offset);
        $total = Database::instance()->scalar('SELECT COUNT(*) FROM voicemails');
        $pages = (int) ceil($total / $limit);

        echo View::page('twilio/voicemail', [
            'voicemails' => $voicemails,
            'page'       => $page,
            'pages'      => $pages,
            'total'      => $total,
            'flash'      => $this->flash(),
        ], $this->meta('Voicemail | Twilio', 'Voicemail inbox and management.', '/admin/twilio/voicemail'));
    }

    /** View voicemail details. */
    public function voicemailView(string $id): void
    {
        $db = Database::instance();
        $vm = $db->fetch('SELECT * FROM voicemails WHERE id = ?', [$id]);
        
        if ($vm === null) {
            \PPC\Core\Router::notFound();
        }

        // Mark as listened when viewed
        if ($vm['status'] === 'new') {
            Twilio::updateVoicemailStatus((int) $id, 'listened');
            $vm['status'] = 'listened';
        }

        echo View::page('twilio/voicemail-view', [
            'vm'    => $vm,
            'flash' => $this->flash(),
        ], $this->meta('Voicemail Details | Twilio', 'Voicemail playback and details.', "/admin/twilio/voicemail/$id"));
    }

    /** Update voicemail status. */
    public function voicemailUpdate(string $id): void
    {
        Csrf::verifyOrDie();
        
        $errors = Validator::make($_POST, [
            'status' => ['required', 'in:new,listened,archived,deleted'],
        ]);
        
        if ($errors) {
            Session::flash('twilio', ['errors' => $errors]);
            header('Location: /admin/twilio/voicemail/' . $id);
            exit;
        }

        $status = Validator::clean($_POST['status']);
        Twilio::updateVoicemailStatus((int) $id, $status);

        Session::flash('twilio', ['success' => 'Voicemail status updated.']);
        header('Location: /admin/twilio/voicemail');
        exit;
    }

    /** ==================== WEBHOOK MONITORING ==================== */

    /** Webhook event log viewer. */
    public function webhooks(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $events = Twilio::getWebhookEvents($limit, $offset);
        $total = Database::instance()->scalar('SELECT COUNT(*) FROM webhook_events');
        $pages = (int) ceil($total / $limit);

        echo View::page('twilio/webhooks', [
            'events' => $events,
            'page'   => $page,
            'pages'  => $pages,
            'total'  => $total,
            'flash'  => $this->flash(),
        ], $this->meta('Webhooks | Twilio', 'Webhook event monitoring and processing.', '/admin/twilio/webhooks'));
    }

    /** View webhook event details. */
    public function webhookView(string $id): void
    {
        $db = Database::instance();
        $event = $db->fetch('SELECT * FROM webhook_events WHERE id = ?', [$id]);
        
        if ($event === null) {
            \PPC\Core\Router::notFound();
        }

        $payload = json_decode($event['payload'], true);
        $payloadFormatted = json_encode($payload, JSON_PRETTY_PRINT);

        echo View::page('twilio/webhook-view', [
            'event'           => $event,
            'payload'         => $payload,
            'payloadFormatted'=> $payloadFormatted,
            'flash'           => $this->flash(),
        ], $this->meta('Webhook Details | Twilio', 'Webhook event payload and details.', "/admin/twilio/webhooks/$id"));
    }

    /** Process pending webhooks manually. */
    public function webhooksProcess(): void
    {
        Csrf::verifyOrDie();
        
        $processed = Twilio::processPendingWebhooks();
        
        Session::flash('twilio', ['success' => "Processed $processed webhook events."]);
        header('Location: /admin/twilio/webhooks');
        exit;
    }


    /** ==================== HELPERS ==================== */

    private function flash(): mixed
    {
        return Session::pullFlash('twilio');
    }
}