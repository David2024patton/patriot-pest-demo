<?php
/**
 * AuthController — ONE passwordless login for everyone.
 *
 * There is a single login page (/login). The user enters any identifier they
 * have — email, phone, or account number. We figure out who they are:
 *
 *   1. Match the identifier against active STAFF first (by email).
 *   2. Otherwise match against CUSTOMERS (by email, phone, or account number).
 *
 * Whoever matches gets a 6-digit code emailed to their address on file. After
 * they enter it, we open the right session and send them to the dashboard that
 * fits their authority level:
 *
 *   - staff with role 'admin'  → /admin            (the CMS)
 *   - any other staff          → /staff-dashboard  (operations)
 *   - customers                → /customer-dashboard (their account)
 *
 * No passwords exist anywhere; the emailed code IS the credential. The flow is
 * timing-safe, single-use, expiry-bound, and brute-force limited (see OtpAuth).
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\View;
use PPC\Core\Session;
use PPC\Core\Csrf;
use PPC\Core\Database;
use PPC\Core\Logger;
use PPC\Core\RateLimiter;
use PPC\Core\Validator;
use PPC\Core\Config;
use PPC\Auth\OtpAuth;
use PPC\Auth\Mailer;

class AuthController extends PageController
{
    /** OTP purpose shared by the unified login (keyed per email). */
    private const PURPOSE = 'login';
    /** OTP purpose for the super-user elevated surface (isolated from standard login). */
    private const SU_PURPOSE = 'super-login';

    /* ============================ STEP 1: IDENTIFY ============================ */

    /** The single login form. */
    public function loginForm(): void
    {
        // Already signed in? Send them straight to their dashboard.
        if (Session::authenticated()) {
            header('Location: ' . $this->dashboardFor(Session::userType(), Session::staffRole()));
            exit;
        }
        echo View::page('auth/login', [
            'flash' => Session::pullFlash('auth'),
        ], $this->meta('Sign In | Patriot Pest Control', 'One secure sign-in for customers and staff. No password — we email you a code and send you to the right dashboard.', '/login'));
    }

    /**
     * Identify the user and email a code. Staff are matched first (by email),
     * then customers (by email/phone/account). The resolved identity + type are
     * remembered in the session for STEP 2.
     */
    public function loginRequest(): void
    {
        Csrf::verifyOrDie();

        $identifier = trim((string) ($_POST['identifier'] ?? ''));
        $errors = Validator::make(['identifier' => $identifier], ['identifier' => ['required', 'max:120']]);
        if ($errors) {
            Session::flash('auth', ['error' => 'Please enter your email, phone number, or account number.']);
            header('Location: /login');
            exit;
        }

        // --- Rate limit: 5 attempts per minute per client IP ---
        // Uses X-Forwarded-For behind the reverse proxy (REMOTE_ADDR is the
        // proxy itself there). Per-identity limiting lives in OtpAuth.
        $ip = RateLimiter::clientIp();
        $rateKey = 'login:' . $ip;
        if (RateLimiter::tooMany($rateKey, 5, 60)) {
            $wait = RateLimiter::retryAfter($rateKey, 5, 60);
            Session::flash('auth', ['error' => 'Too many login attempts. Please wait ' . ceil($wait / 60) . ' minute(s) before trying again.']);
            header('Location: /login');
            exit;
        }
        RateLimiter::hit($rateKey, false, $ip);

        $db   = Database::instance();
        $dest = '/login/verify';

        // 1) Staff (by email) - staff take priority so an admin email never
        //    accidentally resolves to a customer row.
        // Defense 1: super-user accounts are excluded from the standard login
        // flow. They must use the dedicated /su elevated surface instead.
        $staff = $this->findStaffForLogin($identifier);
        if ($staff !== null) {
            RateLimiter::clear($rateKey);
            $this->issueAndEmail($staff['email'], 'staff');
            Session::put('pending_login_email', $staff['email']);
            Session::put('pending_login_type', 'staff');
            Session::put('pending_login_id', $staff['id']);
            Session::flash('auth', ['sent' => true, 'to' => $this->maskEmail($staff['email'])]);
            header('Location: ' . $dest);
            exit;
        }

        // 2) Customer (by email, phone, or account number).
        $customer = $db->fetch(
            'SELECT id, name, email, fr_id, district FROM customers WHERE email = ? OR phone = ? OR account_number = ? LIMIT 1',
            [$identifier, $identifier, $identifier]
        );
        if ($customer !== null && !empty($customer['email'])) {
            RateLimiter::clear($rateKey);
            $this->issueAndEmail($customer['email'], 'customer');
            Session::put('pending_login_email', $customer['email']);
            Session::put('pending_login_type', 'customer');
            Session::put('pending_login_id', $customer['id']);
            Session::flash('auth', ['sent' => true, 'to' => $this->maskEmail($customer['email'])]);
            header('Location: ' . $dest);
            exit;
        }

        // 2b) Customer found but no email on file (FR has none): they cannot
        // receive an emailed code yet. Send them to first-time email capture.
        // The code is issued only AFTER the email is saved (to FR + locally).
        if ($customer !== null) {
            RateLimiter::clear($rateKey);
            Session::put('pending_login_identifier', $identifier);
            Session::put('pending_login_customer_id', $customer['id']);
            Session::flash('auth', ['need_email' => true]);
            header('Location: /login/email');
            exit;
        }

        // No match. Don't reveal whether an account exists (enumeration defense):
        // show the same "code sent" screen, but no code was actually issued, so
        // verification will simply fail.
        Session::flash('auth', ['sent' => true, 'to' => $this->maskEmail($identifier)]);
        header('Location: ' . $dest);
        exit;
    }

    /* ================== FIRST-TIME EMAIL CAPTURE ================== */

    /** Step 1.5 (no email on file): collect the customer's email address. */
    public function emailCaptureForm(): void
    {
        $identifier = (string) (Session::get('pending_login_identifier') ?? '');
        if ($identifier === '') {
            header('Location: /login');
            exit;
        }
        echo View::page('auth/email-capture', [
            'identifier' => $identifier,
            'flash'      => Session::pullFlash('auth'),
        ], $this->meta('Add your email | Patriot Pest Control', 'Add an email to receive your sign-in code.', '/login/email'));
    }

    /** Step 1.5 submit: validate, dedupe, push to FieldRoutes, then issue OTP. */
    public function emailCaptureSubmit(): void
    {
        Csrf::verifyOrDie();

        $identifier = (string) (Session::get('pending_login_identifier') ?? '');
        $customerId = (int) (Session::get('pending_login_customer_id') ?? 0);
        if ($identifier === '' || $customerId <= 0) {
            header('Location: /login');
            exit;
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $errors = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:254']]);
        if ($errors) {
            Session::flash('auth', ['error' => 'Please enter a valid email address.', 'need_email' => true]);
            header('Location: /login/email');
            exit;
        }

        $result = $this->applyEmailCapture($customerId, $email);
        if (isset($result['restart'])) {
            // Account gone or email already set (e.g. double submit) — restart.
            Session::forget('pending_login_identifier');
            Session::forget('pending_login_customer_id');
            header('Location: /login');
            exit;
        }
        if (isset($result['error'])) {
            Session::flash('auth', ['error' => $result['error'], 'need_email' => true]);
            header('Location: /login/email');
            exit;
        }

        Session::forget('pending_login_identifier');
        Session::forget('pending_login_customer_id');
        Session::put('pending_login_email', $email);
        Session::put('pending_login_type', 'customer');
        Session::put('pending_login_id', $customerId);
        Session::flash('auth', ['sent' => true, 'to' => $this->maskEmail($email)]);
        header('Location: /login/verify');
        exit;
    }

    /**
     * Core of the email-capture step (shared with tests): confirm the customer
     * still exists with no email on file, push the new address to FieldRoutes
     * when a district is configured (FR is the source of truth — otherwise the
     * next sync would wipe the local value), update the local cache, then issue
     * the OTP for the new address.
     *
     * @return array{ok?:true, restart?:true, error?:string}
     *   ['restart' => true] → account gone or already has an email; start over
     *   ['error' => string] → FieldRoutes rejected the email; retry capture
     *   ['ok' => true]      → email saved (FR + local) and OTP issued
     */
    private function applyEmailCapture(int $customerId, string $email): array
    {
        $db = Database::instance();
        $customer = $db->fetch(
            'SELECT id, name, email, fr_id, district FROM customers WHERE id = ?',
            [$customerId]
        );
        if (!$customer || !empty($customer['email'])) {
            return ['restart' => true];
        }

        // FR is the source of truth: push the email there first, then update
        // the local cache. Otherwise the next sync would wipe the local value.
        if (!empty($customer['fr_id']) && !empty($customer['district'])) {
            $district = \PPC\Integrations\FieldRoutes::districtByCode($customer['district']);
            if ($district !== null) {
                $push = \PPC\Integrations\FieldRoutes::updateCustomerEmail($district, $customer['fr_id'], $email);
                if (!$push['success']) {
                    return ['error' => $push['error'] ?: 'We could not save your email right now. Please try again or call us.'];
                }
            }
        }

        $db->update('customers', ['email' => $email, 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $customerId]);
        $this->issueAndEmail($email, 'customer');
        return ['ok' => true];
    }

    /* ============================ STEP 2: VERIFY ============================ */

    /** The code-entry form (shared). */
    public function loginVerifyForm(): void
    {
        echo View::page('auth/verify', [
            'purpose' => 'login',
            'action'  => '/login/verify',
            'sentTo'  => Session::pullFlash('auth'),
        ], $this->meta('Enter Your Code | Patriot Pest Control', 'Enter the 6-digit code we emailed you.', '/login/verify'));
    }

    /** Verify the code, open the right session, route by authority level. */
    public function loginVerify(): void
    {
        Csrf::verifyOrDie();

        $email = Session::get('pending_login_email');
        $type  = Session::get('pending_login_type');
        $code  = trim((string) ($_POST['code'] ?? ''));

        if (!$email || !$type || $code === '') {
            Session::flash('auth', ['error' => 'Please start over and request a new code.']);
            header('Location: /login');
            exit;
        }

        if (!OtpAuth::verify($email, self::PURPOSE, $code)) {
            $wait = OtpAuth::retryAfter($email, self::PURPOSE);
            Session::flash('auth', ['error' => $wait > 0
                ? 'Too many attempts. Try again in about ' . ceil($wait / 60) . ' minute(s).'
                : 'That code is incorrect or has expired. Please try again.']);
            header('Location: /login/verify');
            exit;
        }

        // Success → build the session for the resolved identity.
        $dest = $type === 'staff'
            ? $this->startStaffSession($email)
            : $this->startCustomerSession($email);

        // Clear the pending-login state.
        Session::forget('pending_login_email');
        Session::forget('pending_login_type');
        Session::forget('pending_login_id');

        header('Location: ' . $dest);
        exit;
    }

    /* ============================== LOGOUT ============================== */

    /** One logout for everyone. */
    public function logout(): void
    {
        $this->audit('logout', Session::userType() ?? 'guest', Session::get('user_id'));
        Session::destroy();
        header('Location: /');
        exit;
    }

    /* ============================== HELPERS ============================== */

    /** Issue a code and email it (dev mode logs it to storage/logs/mail-*.log). */
    /** Issue a code and email it (dev mode logs it to storage/logs/mail-*.log). */
    private function issueAndEmail(string $email, string $who): void
    {
        // --- Rate limit: 3 codes per identity per 5 minutes ---
        $limitKey = 'otp_issue:' . $email;
        if (RateLimiter::tooMany($limitKey, 3, 300)) {
            $wait = RateLimiter::retryAfter($limitKey, 3, 300);
            Session::flash('auth', ['error' => 'Too many code requests. Please wait ' . ceil($wait / 60) . ' minute(s) before requesting another.']);
            header('Location: /login');
            exit;
        }
        RateLimiter::hit($limitKey, false);

        $code = OtpAuth::issue($email, self::PURPOSE);
        $mins = (int) (Config::int('OTP_TTL', 600) / 60);
        Mailer::send(
            $email,
            'Your Patriot Pest Control sign-in code',
            Mailer::template(
                'Your sign-in code',
                '<p style="font-size:32px;letter-spacing:8px;font-weight:bold;color:#c8a24a">' . $code . '</p>'
                . '<p>This code expires in ' . $mins . ' minutes and works once. If you didn\'t request it, you can safely ignore this email.</p>'
            )
        );
    }

    /** Build a staff session; return the dashboard they should land on. */
    private function startStaffSession(string $email): string
    {
        $db    = Database::instance();
        $staff = $db->fetch('SELECT id, email, name, role FROM staff WHERE email = ? AND active = 1', [$email]);
        if ($staff === null) {
            Session::flash('auth', ['error' => 'This account is no longer active.']);
            return '/login';
        }

        // Defense 2: fail-closed — super-user must authenticate via the
        // dedicated /su surface. Standard /login is not a path to /admin
        // for the elevated role.
        if (($staff['role'] ?? '') === 'super-user') {
            Logger::critical('Super-user attempted standard login (blocked)', ['email' => $email]);
            throw new \RuntimeException('Super-user accounts must use the dedicated /su login surface.');
        }

        Session::regenerate();                       // new id (fixation defense)
        Session::put('user_id', $staff['id']);
        Session::put('user_type', 'staff');
        Session::put('staff_id', $staff['id']);
        Session::put('staff_role', $staff['role']);
        Session::put('display_name', $staff['name']);
        Session::put('_last_activity', time());

        $db->execute("UPDATE staff SET last_login = datetime('now') WHERE id = ?", [$staff['id']]);
        $this->audit('login', 'staff', $staff['id'], ['role' => $staff['role']]);
        Logger::info('Staff logged in', ['email' => $email, 'role' => $staff['role']]);
        Session::flash('analytics_event', 'portal_login');

        return $this->dashboardFor('staff', $staff['role']);
    }

    /** Build a customer session; return their dashboard. */
    private function startCustomerSession(string $email): string
    {
        $db       = Database::instance();
        $customer = $db->fetch('SELECT id, name, email FROM customers WHERE email = ?', [$email]);

        Session::regenerate();
        Session::put('user_id', $customer['id'] ?? null);
        Session::put('user_type', 'customer');
        Session::put('display_name', $customer['name'] ?? 'Customer');
        Session::put('customer_email', $email);
        Session::put('_last_activity', time());

        $this->audit('login', 'customer', $customer['id'] ?? null);
        Logger::info('Customer logged in', ['email' => $email]);
        Session::flash('analytics_event', 'portal_login');

        return $this->dashboardFor('customer', null);
    }

    /**
     * The dashboard for a given authority level:
     *   admin staff → CMS; other staff → staff dashboard; customer → portal.
     */
    private function dashboardFor(?string $userType, ?string $role): string
    {
        if ($userType === 'staff') {
            return ($role === 'admin' || $role === 'super-user') ? '/admin' : '/staff-dashboard';
        }
        return '/customer-dashboard';
    }

    /**
     * Testable seam: staff lookup with super-user exclusion for standard login.
     * Used by loginRequest(); extracted so tests can verify the exclusion clause.
     */
    protected function findStaffForLogin(string $email): ?array
    {
        return Database::instance()->fetch(
            "SELECT id, email, name, role FROM staff WHERE email = ? AND active = 1 AND role != 'super-user'",
            [$email]
        );
    }

    /** Mask an email for display (j•••@example.com) — privacy in the UI. */
    private function maskEmail(string $email): string
    {
        // Only mask things that look like emails; leave phones/accounts as-is.
        if (!str_contains($email, '@')) {
            return $email;
        }
        $parts  = explode('@', $email);
        $local  = $parts[0] ?? '';
        $masked = substr($local, 0, 1) . str_repeat('•', max(3, strlen($local) - 1));
        return $masked . '@' . ($parts[1] ?? '');
    }

    /** Write an audit-log entry (security trail). */
    private function audit(string $action, string $userType, mixed $userId, array $meta = []): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'    => $userId !== null ? (string) $userId : null,
                'user_type'  => $userType,
                'action'     => $action,
                'meta_json'  => $meta ? json_encode($meta) : null,
                'ip'         => RateLimiter::clientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Audit write failed', ['action' => $action]);
        }
    }

    /* ======================= SUPERUSER LOGIN ======================== */

    /** Superuser login form (GET /su). */
    public function superLoginForm(): void
    {
        if (Session::authenticated() && Session::isSuperUser()) {
            header('Location: /admin');
            exit;
        }
        echo View::page('auth/super-login', [
            'flash' => Session::pullFlash('auth'),
        ], $this->meta('Superuser Sign In | Patriot Pest Control', 'Elevated authentication for system administrators.', '/su'));
    }

    /** Superuser login request (POST /su). */
    public function superLoginRequest(): void
    {
        Csrf::verifyOrDie();

        $email = trim((string) ($_POST['email'] ?? ''));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || preg_match("/[\r\n]/", $email)) {
            Session::flash('auth', ['error' => 'Please enter a valid email address.']);
            header('Location: /su');
            exit;
        }
        if (strlen($email) > 254) {
            Session::flash('auth', ['error' => 'Please enter a valid email address.']);
            header('Location: /su');
            exit;
        }

        $ip = RateLimiter::clientIp();
        $rateKey = 'su_login:' . $ip;
        if (RateLimiter::tooMany($rateKey, 3, 60)) {
            $wait = RateLimiter::retryAfter($rateKey, 3, 60);
            Session::flash('auth', ['error' => 'Too many attempts. Please wait ' . ceil($wait / 60) . ' minute(s).']);
            header('Location: /su');
            exit;
        }
        RateLimiter::hit($rateKey, false, $ip);

        $db = Database::instance();
        $staff = $db->fetch(
            "SELECT id, email, name, role FROM staff WHERE email = ? AND role = 'super-user' AND active = 1",
            [$email]
        );

        if ($staff !== null) {
            RateLimiter::clear($rateKey);
            $this->issueAndEmailSuper($staff['email']);
            Session::put('pending_login_email', $staff['email']);
            Session::put('pending_login_type', 'staff');
            Session::put('pending_login_id', $staff['id']);
        }

        Session::flash('auth', ['sent' => true, 'to' => $this->maskEmail($email)]);
        header('Location: /su/verify');
        exit;
    }

    /** Superuser code-entry form (GET /su/verify). */
    public function superLoginVerifyForm(): void
    {
        echo View::page('auth/verify', [
            'purpose' => 'super-login',
            'action'  => '/su/verify',
            'sentTo'  => Session::pullFlash('auth'),
        ], $this->meta('Enter Your Code | Patriot Pest Control', 'Enter the 8-digit code we emailed you.', '/su/verify'));
    }

    /** Verify superuser code, establish session. */
    public function superLoginVerify(): void
    {
        Csrf::verifyOrDie();

        $email = Session::get('pending_login_email');
        $type  = Session::get('pending_login_type');
        $code  = trim((string) ($_POST['code'] ?? ''));

        if (!$email || !$type || $code === '') {
            Session::flash('auth', ['error' => 'Please start over and request a new code.']);
            header('Location: /su');
            exit;
        }

        if (!OtpAuth::verify($email, self::SU_PURPOSE, $code)) {
            $wait = OtpAuth::retryAfter($email, self::SU_PURPOSE);
            Session::flash('auth', ['error' => $wait > 0
                ? 'Too many attempts. Try again in about ' . ceil($wait / 60) . ' minute(s).'
                : 'That code is incorrect or has expired. Please try again.']);
            header('Location: /su/verify');
            exit;
        }

        $dest = $this->startSuperSession($email);

        Session::forget('pending_login_email');
        Session::forget('pending_login_type');
        Session::forget('pending_login_id');

        header('Location: ' . $dest);
        exit;
    }

    /** Issue and email a superuser code (8 digits, shorter TTL). */
    private function issueAndEmailSuper(string $email): void
    {
        $limitKey = 'su_otp_issue:' . $email;
        if (RateLimiter::tooMany($limitKey, 2, 300)) {
            $wait = RateLimiter::retryAfter($limitKey, 2, 300);
            Session::flash('auth', ['error' => 'Too many code requests. Please wait ' . ceil($wait / 60) . ' minute(s).']);
            header('Location: /su');
            exit;
        }
        RateLimiter::hit($limitKey, false);

        $code = OtpAuth::issue($email, self::SU_PURPOSE, 8);
        $mins = (int) (Config::int('SU_OTP_TTL', 300) / 60);
        Mailer::send(
            $email,
            'Your Patriot Pest Control superuser sign-in code',
            Mailer::template(
                'Your superuser sign-in code',
                '<p style="font-size:32px;letter-spacing:8px;font-weight:bold;color:#c8a24a">' . $code . '</p>'
                . '<p>This code expires in ' . $mins . ' minutes and works once. If you didn' . "'" . 't request it, you can safely ignore this email.</p>'
            )
        );
    }

    /** Build a superuser session; return /admin. */
    private function startSuperSession(string $email): string
    {
        $db = Database::instance();
        $staff = $db->fetch(
            "SELECT id, email, name, role FROM staff WHERE email = ? AND role = 'super-user' AND active = 1",
            [$email]
        );
        if ($staff === null) {
            Session::flash('auth', ['error' => 'This account is no longer active.']);
            return '/su';
        }

        Session::regenerate();
        Session::put('user_id', $staff['id']);
        Session::put('user_type', 'staff');
        Session::put('staff_id', $staff['id']);
        Session::put('staff_role', $staff['role']);
        Session::put('display_name', $staff['name']);
        Session::put('_last_activity', time());

        $db->execute("UPDATE staff SET last_login = datetime('now') WHERE id = ?", [$staff['id']]);
        $this->audit('super-login', 'staff', $staff['id'], ['role' => $staff['role']]);
        Logger::info('Super-user logged in', ['email' => $email]);

        return '/admin';
    }
}
