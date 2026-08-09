<?php
/**
 * Front controller — every HTTP request enters here.
 *
 * The old site relied on .htaccess rewriting to loose .php files plus a
 * router.php shim. This single entry point gives us one place to:
 *   - boot the framework (bootstrap),
 *   - declare every route explicitly (readable map of the whole app),
 *   - enforce auth/role guards declaratively,
 *   - return consistent 404s.
 *
 * Route groups:
 *   - marketing pages   (public)
 *   - pest / area / blog detail pages (public, DB-driven)
 *   - customer auth + portal (customer-guarded)
 *   - staff auth + dashboard (staff-guarded)
 *   - admin CMS (admin-guarded)
 *   - api endpoints (various guards)
 */

declare(strict_types=1);

// Cost dashboard route - serve directly without framework bootstrap
// Doctrine: MODULAR FIRST - this can be unplugged without affecting main app
if (strpos($_SERVER['REQUEST_URI'], '/cost') === 0) {
    $costPath = __DIR__ . '/cost/index.php';
    if (file_exists($costPath)) {
        require $costPath;
        exit;
    }
}

require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Router;
use PPC\Core\Config;

// ---------- Force HTTPS in production ----------
// Behind the Dokploy TLS terminator we trust X-Forwarded-Proto. Any plain-HTTP
// request in production is 301-redirected so credentials and OTP codes never
// cross the wire unencrypted.
if (Config::isProduction()) {
    $fwdProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $fwdProto === 'https';
    if (!$isHttps) {
        header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? ''), true, 301);
        exit;
    }
}

use PPC\Controllers\PageController;
use PPC\Controllers\PestController;
use PPC\Controllers\BlogController;
use PPC\Controllers\AuthController;
use PPC\Controllers\PortalController;
use PPC\Controllers\StaffController;
use PPC\Controllers\AdminController;
use PPC\Controllers\TwilioController;
use PPC\Controllers\WebhookController;

// ---------- Marketing pages (public) ----------
Router::get('/',                 [PageController::class, 'home']);
Router::get('/about',            [PageController::class, 'about']);
Router::get('/services',         [PageController::class, 'services']);
Router::get('/prices',           [PageController::class, 'prices']);
Router::get('/service-areas',    [PageController::class, 'areas']);
Router::get('/faqs',             [PageController::class, 'faqs']);
Router::get('/contact',          [PageController::class, 'contact']);
Router::post('/contact',         [PageController::class, 'contactSubmit']);
Router::get('/referral',         [PageController::class, 'referral']);
Router::get('/socials',          [PageController::class, 'socials']);
Router::get('/help',             [PageController::class, 'help']);
Router::get('/links',            [PageController::class, 'links']);
Router::get('/sitemap',          [PageController::class, 'sitemap']);
Router::get('/privacy-policy',   [PageController::class, 'privacy']);
Router::get('/terms-of-use',     [PageController::class, 'terms']);

// ---------- Pest / area detail pages (public, DB-driven) ----------
Router::get('/pest/{slug}',      [PestController::class, 'show']);
Router::get('/areas/{slug}',     [PageController::class, 'areaDetail']);

// ---------- Blog (public, unified template) ----------
Router::get('/blogs',            [BlogController::class, 'index']);
Router::get('/blogs/{slug}',     [BlogController::class, 'show']);

// ---------- Unified auth (passwordless email OTP — one login for everyone) ----------
// The single login identifies the user (staff or customer), emails a code, then
// routes them to the dashboard matching their authority level.
Router::get('/login',         [AuthController::class, 'loginForm']);
Router::post('/login',        [AuthController::class, 'loginRequest']);   // step 1: identify + send code
Router::get('/login/verify',  [AuthController::class, 'loginVerifyForm']);
Router::post('/login/verify', [AuthController::class, 'loginVerify']);    // step 2: verify + route by role
Router::get('/logout',        [AuthController::class, 'logout']);

// Legacy login URLs → the single login page (keeps old bookmarks/links working).
$toLogin       = function () { header('Location: /login'); exit; };
$toLoginVerify = function () { header('Location: /login/verify'); exit; };
Router::get('/customer-auth',   $toLogin);
Router::get('/staff',           $toLogin);
Router::get('/dashboard',       $toLogin);  // convenience alias → login (redirects to correct dashboard after auth)
Router::get('/staff-logout',    [AuthController::class, 'logout']);
Router::get('/customer-verify', $toLoginVerify);
Router::get('/staff-verify',    $toLoginVerify);

// ---------- Customer portal (customer-guarded) ----------
Router::get('/customer-dashboard', [PortalController::class, 'dashboard'])->auth('customer');

// ---------- Staff dashboard (staff-guarded) ----------
Router::get('/staff-dashboard',  [StaffController::class, 'dashboard'])->auth('staff');

// ---------- Staff tools: customer book, profiles, messages, search ----------
Router::get('/staff/customers',      [StaffController::class, 'customers'])->auth('staff')->permission('view_customers');
Router::get('/staff/customers/{id}', [StaffController::class, 'customerProfile'])->auth('staff')->permission('view_customers');
Router::get('/staff/messages',       [StaffController::class, 'messages'])->auth('staff')->permission('send_messages');
Router::post('/staff/customers/sync', [StaffController::class, 'syncCustomers'])->auth('staff')->permission('view_customers');
Router::get('/api/customer-search',  [StaffController::class, 'searchCustomers'])->auth('staff')->permission('search_customers');

// ---------- Self-service account (any authenticated user) ----------
Router::get('/account', [StaffController::class, 'account'])->auth('*');

// ---------- Admin CMS (admin-guarded) ----------
Router::get('/admin',            [AdminController::class, 'index'])->auth('staff')->role('admin');
Router::get('/admin/posts',      [AdminController::class, 'posts'])->auth('staff')->role('admin');
Router::get('/admin/posts/new',  [AdminController::class, 'postNew'])->auth('staff')->role('admin');
Router::post('/admin/posts',     [AdminController::class, 'postStore'])->auth('staff')->role('admin');
Router::get('/admin/posts/{id}', [AdminController::class, 'postEdit'])->auth('staff')->role('admin');
Router::post('/admin/posts/{id}',[AdminController::class, 'postUpdate'])->auth('staff')->role('admin');
Router::get('/admin/media',      [AdminController::class, 'media'])->auth('staff')->role('admin');
Router::get('/admin/content',    [AdminController::class, 'content'])->auth('staff')->role('admin');

// ---------- Staff CRUD (admin-guarded) ----------
Router::get('/admin/staff',          [StaffController::class, 'staffList'])->auth('staff')->role('admin');
Router::get('/admin/staff/new',      [StaffController::class, 'staffNew'])->auth('staff')->role('admin');
Router::post('/admin/staff',         [StaffController::class, 'staffCreate'])->auth('staff')->role('admin');
Router::get('/admin/staff/{id}',     [StaffController::class, 'staffEdit'])->auth('staff')->role('admin');
Router::post('/admin/staff/{id}',    [StaffController::class, 'staffUpdate'])->auth('staff')->role('admin');
Router::post('/admin/staff/{id}/toggle', [StaffController::class, 'staffToggle'])->auth('staff')->role('admin');

// ---------- Twilio admin (admin-guarded) ----------
Router::get('/admin/twilio',                  [TwilioController::class, 'index'])->auth('staff')->role('admin');
Router::get('/admin/twilio/sms',              [TwilioController::class, 'sms'])->auth('staff')->role('admin');
Router::get('/admin/twilio/sms/new',          [TwilioController::class, 'smsNew'])->auth('staff')->role('admin');
Router::post('/admin/twilio/sms/send',        [TwilioController::class, 'smsSend'])->auth('staff')->role('admin');
Router::get('/admin/twilio/sms/{id}',         [TwilioController::class, 'smsView'])->auth('staff')->role('admin');
Router::get('/admin/twilio/calls',            [TwilioController::class, 'calls'])->auth('staff')->role('admin');
Router::get('/admin/twilio/calls/new',        [TwilioController::class, 'callNew'])->auth('staff')->role('admin');
Router::post('/admin/twilio/calls/initiate',  [TwilioController::class, 'callInitiate'])->auth('staff')->role('admin');
Router::get('/admin/twilio/calls/{id}',       [TwilioController::class, 'callView'])->auth('staff')->role('admin');
Router::get('/admin/twilio/voicemail',        [TwilioController::class, 'voicemail'])->auth('staff')->role('admin');
Router::get('/admin/twilio/voicemail/{id}',   [TwilioController::class, 'voicemailView'])->auth('staff')->role('admin');
Router::post('/admin/twilio/voicemail/{id}/update', [TwilioController::class, 'voicemailUpdate'])->auth('staff')->role('admin');
Router::get('/admin/twilio/webhooks',         [TwilioController::class, 'webhooks'])->auth('staff')->role('admin');
Router::get('/admin/twilio/webhooks/{id}',    [TwilioController::class, 'webhookView'])->auth('staff')->role('admin');
Router::post('/admin/twilio/webhooks/process', [TwilioController::class, 'webhooksProcess'])->auth('staff')->role('admin');

// ---------- Health check (public, no auth) ----------
Router::get('/health', function () {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'service' => 'patriot-pest-app',
        'env' => \PPC\Core\Config::get('APP_ENV', 'unknown'),
        'time' => date('c'),
        'php' => PHP_VERSION,
    ]);
});

// ---------- Twilio webhooks (public, HMAC signature-validated) ----------
// Every handler first verifies X-Twilio-Signature; unsigned or spoofed
// payloads get 401 before any state change.
Router::post('/webhooks/twilio/sms',       [WebhookController::class, 'sms']);
Router::post('/webhooks/twilio/status',    [WebhookController::class, 'status']);
Router::post('/webhooks/twilio/voice',     [WebhookController::class, 'voice']);
Router::post('/webhooks/twilio/voicemail', [WebhookController::class, 'voicemail']);

// ---------- Public unsubscribe (HMAC-signed token, CSRF-exempt by design) ----------
// The signed token in the URL is the proof of consent; it cannot be forged.
Router::get('/unsubscribe', [WebhookController::class, 'unsubscribe']);

// ---------- Dispatch ----------
Router::dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
