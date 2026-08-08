<?php
/**
 * MarketingController: the Marketing Command Center (admin-only).
 *
 *   - /admin/marketing            dashboard: campaign overview, social
 *                                 quick-post, analytics snapshot
 *   - /admin/marketing/campaigns  reactivation campaign launcher: segment
 *                                 select, template preview with merge tags
 *                                 filled, test send (email always; SMS only
 *                                 when TWILIO_SMS_ENABLED=true), draft save
 *
 * Doctrine: FEATURE TOGGLES IN SETTINGS (TWILIO_SMS_ENABLED gates SMS),
 * DEBUGGING IS A FEATURE (audit log on every state change), MODULAR FIRST
 * (this controller + its two templates unplug cleanly).
 *
 * Copy (microcopy, segment labels, social starter posts, template overlays)
 * ships in app/Content/marketing-copy.json, authored by the Marketing agent.
 * Hard rules: test customers only (source = 'seed'), no production sends.
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\View;
use PPC\Core\Session;
use PPC\Core\Database;
use PPC\Core\Csrf;
use PPC\Core\Logger;
use PPC\Core\Config;
use PPC\Core\Compliance;
use PPC\Integrations\Twilio;
use PPC\Auth\Mailer;

class MarketingController extends PageController
{
    /** District phone numbers for the {{phone}} merge tag. */
    private const DISTRICT_PHONES = ['wa' => '(509) 471-5767', 'az' => '(602) 755-8414'];

    /** Dashboard: campaign overview + social quick-post + analytics snapshot. */
    public function index(): void
    {
        $db = Database::instance();

        $campaigns = $db->fetchAll(
            'SELECT c.*, t.name AS template_name, t.season, t.channel
             FROM reactivation_campaigns c
             LEFT JOIN reactivation_templates t ON t.id = c.template_id
             ORDER BY c.created_at DESC LIMIT 10'
        );
        $templates = $db->fetchAll('SELECT * FROM reactivation_templates WHERE active = 1 ORDER BY id');
        $templates = array_map([$this, 'overlayCopy'], $templates);

        $sendStats = $db->fetch(
            "SELECT COUNT(*) AS total,
                    SUM(status = 'sent' OR status = 'delivered') AS sent,
                    SUM(status = 'opened') AS opened,
                    SUM(status = 'unsubscribed') AS unsubscribed
             FROM reactivation_sends"
        );

        echo View::page('admin/marketing', [
            'campaigns'   => $campaigns,
            'templates'   => $templates,
            'sendStats'   => $sendStats,
            'copy'        => $this->copy(),
            'smsEnabled'  => $this->smsEnabled(),
            'platforms'   => $this->platformStatus(),
            'ga4Id'       => Config::get('GA4_MEASUREMENT_ID', 'G-GZV90ZCT4E'),
            'flash'       => $this->flash(),
        ], $this->meta('Marketing | Patriot Pest Control', 'Marketing command center.', '/admin/marketing'));
    }

    /** Campaign launcher form: segments + template select + live preview. */
    public function campaigns(): void
    {
        $db = Database::instance();

        $templates = $db->fetchAll('SELECT * FROM reactivation_templates WHERE active = 1 ORDER BY id');
        $templates = array_map([$this, 'overlayCopy'], $templates);

        // Segment counts over the TEST book only (source = 'seed').
        $districts = $db->fetchAll(
            "SELECT district, COUNT(*) AS n FROM customers
             WHERE source = 'seed' AND is_no_call = 0 GROUP BY district"
        );
        $statuses = $db->fetchAll(
            "SELECT status, COUNT(*) AS n FROM customers
             WHERE source = 'seed' AND is_no_call = 0 GROUP BY status"
        );

        // Live preview per template: merge tags filled against the first seed
        // customer so admins see exactly what a test send will say.
        $seed     = $db->fetch("SELECT * FROM customers WHERE source = 'seed' AND is_no_call = 0 ORDER BY id LIMIT 1");
        $previews = [];
        foreach ($templates as $t) {
            $previews[$t['id']] = [
                'subject' => $this->fillTags((string) $t['subject'], $seed ?: [], $t),
                'sms'     => $this->fillTags((string) ($t['body_sms'] ?? ''), $seed ?: [], $t),
                'html'    => $this->fillTags((string) ($t['body_html'] ?? ''), $seed ?: [], $t),
            ];
        }
        $seeds = $db->fetchAll("SELECT id, name, email, phone, district, status FROM customers WHERE source = 'seed' AND is_no_call = 0 ORDER BY id");

        echo View::page('admin/marketing-campaigns', [
            'templates'  => $templates,
            'districts'  => $districts,
            'statuses'   => $statuses,
            'previews'   => $previews,
            'seeds'      => $seeds,
            'copy'       => $this->copy(),
            'smsEnabled' => $this->smsEnabled(),
            'flash'      => $this->flash(),
        ], $this->meta('Campaign Launcher | Admin', 'Launch a reactivation wave.', '/admin/marketing/campaigns'));
    }

    /**
     * POST: save a campaign draft. Segment + template validated; audience
     * counted over test customers only. No sends happen here.
     */
    public function campaignStore(): void
    {
        Csrf::verifyOrDie();
        $db = Database::instance();

        $templateId = (int) ($_POST['template_id'] ?? 0);
        $template   = $db->fetch('SELECT * FROM reactivation_templates WHERE id = ? AND active = 1', [$templateId]);
        if ($template === null) {
            Session::flash('admin', ['errors' => ['Pick a template for the campaign.']]);
            header('Location: /admin/marketing/campaigns');
            exit;
        }

        $district = $_POST['district'] ?? 'all';
        $status   = $_POST['status'] ?? 'all';
        if (!in_array($district, ['all', 'wa', 'az'], true)) {
            $district = 'all';
        }
        if (!in_array($status, ['all', 'active', 'cancelled', 'inactive'], true)) {
            $status = 'all';
        }

        $audience = $this->audience($district, $status);
        $name     = trim($_POST['name'] ?? '') ?: ($template['name'] . ' (' . date('M j') . ')');

        $id = $db->insert('reactivation_campaigns', [
            'name'         => $name,
            'template_id'  => $templateId,
            'status'       => 'draft',
            'schedule'     => json_encode(['district' => $district, 'status' => $status, 'audience' => count($audience)]),
            'segment_json' => json_encode(['district' => $district, 'status' => $status, 'audience' => count($audience)]),
            'created_by'   => Session::get('display_name', 'admin'),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);

        $this->audit('campaign.draft', 'reactivation_campaign', $id, [
            'template' => $template['name'], 'district' => $district, 'status' => $status,
            'audience' => count($audience),
        ]);
        Session::flash('admin', ['success' => 'Draft saved. ' . count($audience) . ' test customers in audience.']);
        header('Location: /admin/marketing');
        exit;
    }

    /**
     * POST: send ONE test message to ONE seed customer. Email always allowed;
     * SMS only when TWILIO_SMS_ENABLED=true. Server-side re-checks the flag so
     * a tampered form cannot force SMS.
     */
    public function campaignTest(): void
    {
        Csrf::verifyOrDie();
        $db = Database::instance();

        $templateId = (int) ($_POST['template_id'] ?? 0);
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $channel    = ($_POST['channel'] ?? 'email') === 'sms' ? 'sms' : 'email';

        $template = $db->fetch('SELECT * FROM reactivation_templates WHERE id = ? AND active = 1', [$templateId]);
        $template = $template !== null ? $this->overlayCopy($template) : null;
        // HARD RULE: test customers only. source = 'seed' is the only legal target.
        $customer = $db->fetch("SELECT * FROM customers WHERE id = ? AND source = 'seed'", [$customerId]);
        if ($template === null || $customer === null) {
            Session::flash('admin', ['errors' => ['Test sends require a valid template and a test (seed) customer.']]);
            header('Location: /admin/marketing/campaigns');
            exit;
        }

        if ($channel === 'sms') {
            if (!$this->smsEnabled()) {
                Session::flash('admin', ['errors' => ['SMS is disabled in this environment. Test sends are limited to email.']]);
                header('Location: /admin/marketing/campaigns');
                exit;
            }
            if (Compliance::isBlocked('sms', $customer['email'], $customer['phone']) !== null) {
                Session::flash('admin', ['errors' => ['That test customer is on the do-not-contact list.']]);
                header('Location: /admin/marketing/campaigns');
                exit;
            }
            $body = $this->fillTags($template['body_sms'] ?? $template['subject'], $customer, $template);
            $res  = Twilio::sendSms((string) $customer['phone'], $body);
            $ok   = !empty($res['success']);
        } else {
            if (empty($customer['email'])) {
                Session::flash('admin', ['errors' => ['That test customer has no email on file.']]);
                header('Location: /admin/marketing/campaigns');
                exit;
            }
            if (Compliance::isBlocked('email', $customer['email'], $customer['phone']) !== null) {
                Session::flash('admin', ['errors' => ['That test customer is on the do-not-contact list.']]);
                header('Location: /admin/marketing/campaigns');
                exit;
            }
            $subject = $this->fillTags((string) $template['subject'], $customer, $template);
            $body    = $this->fillTags((string) ($template['body_html'] ?? $template['body_sms']), $customer, $template);
            $body   .= "\n\n---\nTest send from the Marketing Command Center. Not a live campaign.";
            $ok      = Mailer::send((string) $customer['email'], $subject, $body);
        }

        $this->audit('campaign.test_send', 'customer', $customerId, [
            'template' => $template['name'], 'channel' => $channel, 'ok' => $ok,
        ]);

        if ($ok) {
            Session::flash('admin', ['success' => 'Test ' . $channel . ' sent to ' . $customer['name'] . '.']);
        } else {
            Session::flash('admin', ['errors' => ['Test ' . $channel . ' failed. Check storage/logs for detail.']]);
        }
        header('Location: /admin/marketing/campaigns');
        exit;
    }

    /* ============================ helpers ============================ */

    /** Marketing copy pack (authored by the Marketing agent). */
    private function copy(): array
    {
        static $copy = null;
        if ($copy === null) {
            $raw  = file_get_contents(BASE_PATH . '/app/Content/marketing-copy.json');
            $copy = json_decode((string) $raw, true) ?: [];
        }
        return $copy;
    }

    private function smsEnabled(): bool
    {
        return Config::bool('TWILIO_SMS_ENABLED', false);
    }

    /** Platform connect statuses from env config. */
    private function platformStatus(): array
    {
        return [
            'facebook'  => Config::get('FB_PAGE_ID') !== null && Config::get('FB_ACCESS_TOKEN') !== null,
            'x'         => Config::get('TWITTER_API_KEY') !== null,
            'instagram' => Config::get('INSTAGRAM_BUSINESS_ID') !== null,
            'linkedin'  => Config::get('LINKEDIN_PAGE_ID') !== null,
        ];
    }

    /**
     * Overlay Marketing-authored copy onto a DB template row. DB rows stay
     * authoritative for structure; the pack refreshes subject/SMS/HTML when
     * the pack's name matches.
     */
    private function overlayCopy(array $t): array
    {
        foreach ($this->copy()['templates'] ?? [] as $p) {
            if (($p['name'] ?? '') === $t['name']) {
                $t['subject']   = $p['subject'] ?? $t['subject'];
                $t['body_sms']  = $p['body_sms'] ?? ($t['body_sms'] ?? '');
                $t['body_html'] = $p['body_html'] ?? ($t['body_html'] ?? '');
                $t['cta']       = $p['cta'] ?? '';
                $t['angle']     = $p['angle'] ?? '';
                break;
            }
        }
        return $t;
    }

    /** Test audience: seed customers only, DNC excluded, segment filters applied. */
    private function audience(string $district, string $status): array
    {
        $db  = Database::instance();
        $sql = "SELECT * FROM customers WHERE source = 'seed' AND is_no_call = 0";
        $par = [];
        if ($district !== 'all') {
            $sql .= ' AND district = ?';
            $par[] = $district;
        }
        if ($status !== 'all') {
            $sql .= ' AND status = ?';
            $par[] = $status;
        }
        return $db->fetchAll($sql, $par);
    }

    /**
     * Fill merge tags. {{phone}} resolves per-district so AZ prospects never
     * see the WA number; copy that hardcodes a district line is swapped to the
     * customer's district number at render time for the same reason.
     * {{season}}/{{pest}} come from the template row, not the calendar.
     * {{unsubscribe_url}} uses the signed Compliance link.
     */
    private function fillTags(?string $text, array $customer, array $template = []): string
    {
        $text     = (string) $text;
        $district = strtolower((string) ($customer['district'] ?? 'wa'));
        $phone    = self::DISTRICT_PHONES[$district] ?? self::DISTRICT_PHONES['wa'];
        $tel      = $district === 'az' ? 'tel:+16027558414' : 'tel:+15094715767';
        $unsub    = Compliance::unsubscribeUrl((string) $customer['email'], $customer['phone'], 'all');

        $text = strtr($text, [
            '{{name}}'            => $customer['name'] ?? 'Neighbor',
            '{{city}}'            => $customer['city'] ?? 'your area',
            '{{phone}}'           => $phone,
            '{{pest}}'            => (string) ($template['pest_type'] ?? 'pests'),
            '{{season}}'          => (string) ($template['season'] ?? ''),
            '{{unsubscribe_url}}' => $unsub,
        ]);

        // Copy that hardcodes a district phone number gets the customer's
        // district line instead (Marketing ask: never leak the wrong number).
        return strtr($text, [
            self::DISTRICT_PHONES['wa'] => $phone,
            self::DISTRICT_PHONES['az'] => $phone,
            'tel:+15094715767'          => $tel,
            'tel:+16027558414'          => $tel,
        ]);
    }

    private function flash(): mixed
    {
        return Session::pullFlash('admin');
    }

    private function audit(string $action, string $entity, mixed $entityId, array $meta = []): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'    => (string) (Session::get('user_id') ?? ''),
                'user_type'  => Session::userType() ?? 'system',
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId !== null ? (string) $entityId : null,
                'meta_json'  => $meta ? json_encode($meta) : null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Audit write failed', ['action' => $action]);
        }
    }
}
