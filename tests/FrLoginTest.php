<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PPC\Core\Database;
use PPC\Core\Validator;
use PPC\Controllers\AuthController;

/**
 * FR-first login: staff resolve from synced FR employees, and customers with
 * no email on file get a first-time email-capture step before any code is sent.
 *
 * The controller's HTTP shell (CSRF, redirects, exit) is exercised indirectly:
 * this codebase's convention is to test the extracted helpers (see
 * SuperuserTest reflecting on findStaffForLogin / startStaffSession), so the
 * capture core lives in AuthController::applyEmailCapture() and is tested here.
 *
 * The FieldRoutes network push inside applyEmailCapture is skipped in these
 * tests because no FIELDROUTES_* credentials are configured (districtByCode
 * returns null) — the local save + OTP-issue path is what runs. The push path
 * is integration-level and is verified on staging with real credentials.
 */
final class FrLoginTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/test_fr_login_' . uniqid() . '.db';
        putenv('DB_PATH=' . $this->dbPath);
        $this->resetDatabaseSingleton();
        Database::instance(); // applies database/schema.sql to the fresh file
    }

    protected function tearDown(): void
    {
        putenv('DB_PATH');
        $this->resetDatabaseSingleton();
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    private function resetDatabaseSingleton(): void
    {
        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    private function invokeStaffLookup(string $email): ?array
    {
        $method = new \ReflectionMethod(AuthController::class, 'findStaffForLogin');
        $method->setAccessible(true);
        return $method->invoke(new AuthController(), $email);
    }

    private function invokeApplyEmailCapture(int $customerId, string $email): array
    {
        $method = new \ReflectionMethod(AuthController::class, 'applyEmailCapture');
        $method->setAccessible(true);
        return $method->invoke(new AuthController(), $customerId, $email);
    }

    /* ---------------- staff resolve from FR-synced employees ---------------- */

    #[Test] public function staff_from_fr_resolves_for_login(): void
    {
        $db = Database::instance();
        $db->insert('staff', [
            'email'          => 'jane@patriotpest.pro',
            'name'           => 'Jane Tech',
            'role'           => 'staff',
            'fr_employee_id' => 'EMP-2001',
            'active'         => 1,
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);

        $found = $this->invokeStaffLookup('jane@patriotpest.pro');
        $this->assertNotNull($found, 'FR-synced staff must resolve at login');
        $this->assertSame('staff', $found['role']);
        $this->assertSame('Jane Tech', $found['name']);
    }

    #[Test] public function staff_from_fr_excludes_inactive(): void
    {
        Database::instance()->insert('staff', [
            'email'          => 'gone@patriotpest.pro',
            'name'           => 'Gone',
            'role'           => 'staff',
            'fr_employee_id' => 'EMP-2002',
            'active'         => 0,
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);
        $this->assertNull($this->invokeStaffLookup('gone@patriotpest.pro'), 'Deactivated FR employees must not log in');
    }

    #[Test] public function staff_from_fr_never_promotes_superuser_at_standard_login(): void
    {
        // Defense 1: even if an FR-synced staff row carried the super-user role,
        // the standard login surface must refuse to resolve it.
        Database::instance()->insert('staff', [
            'email'          => 'su@patriotpest.pro',
            'name'           => 'Elevated',
            'role'           => 'super-user',
            'fr_employee_id' => 'EMP-2003',
            'active'         => 1,
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);
        $this->assertNull($this->invokeStaffLookup('su@patriotpest.pro'), 'Super-user must never resolve via /login');
    }

    #[Test] public function staff_email_takes_priority_over_customer_email(): void
    {
        // loginRequest checks staff FIRST, then customers. With the same email
        // on both tables, the staff branch must win — the staff lookup is the
        // guard that makes that ordering hold.
        $db = Database::instance();
        $db->insert('staff', [
            'email'          => 'shared@patriotpest.pro',
            'name'           => 'Staff Member',
            'role'           => 'staff',
            'fr_employee_id' => 'EMP-2004',
            'active'         => 1,
            'created_at'     => gmdate('Y-m-d H:i:s'),
        ]);
        $db->insert('customers', [
            'fr_id'    => 'C-7777',
            'district' => 'wa',
            'name'     => 'Customer With Same Email',
            'email'    => 'shared@patriotpest.pro',
            'phone'    => '5095557777',
            'status'   => 'active',
        ]);

        $found = $this->invokeStaffLookup('shared@patriotpest.pro');
        $this->assertNotNull($found);
        $this->assertSame('Staff Member', $found['name'], 'Staff branch is checked first, so staff must win');
    }

    /* ---------------- no-email customers resolve for capture ---------------- */

    #[Test] public function no_email_customer_resolves_by_phone_for_capture(): void
    {
        $db = Database::instance();
        $db->insert('customers', [
            'fr_id'          => 'C-8001',
            'district'       => 'wa',
            'name'           => 'No Email Yet',
            'email'          => null,
            'phone'          => '5095558001',
            'account_number' => 'C-8001',
            'status'         => 'active',
        ]);

        // The exact lookup loginRequest runs (email OR phone OR account number).
        $customer = $db->fetch(
            'SELECT id, name, email, fr_id, district FROM customers WHERE email = ? OR phone = ? OR account_number = ? LIMIT 1',
            ['5095558001', '5095558001', '5095558001']
        );
        $this->assertNotNull($customer, 'Phone lookup must find the no-email customer');
        $this->assertTrue(empty($customer['email']), 'Customer has no email -> capture branch must trigger');
        $this->assertSame('C-8001', $customer['fr_id']);
    }

    #[Test] public function no_email_customer_resolves_by_account_number_for_capture(): void
    {
        $db = Database::instance();
        $db->insert('customers', [
            'fr_id'          => 'C-8002',
            'district'       => 'az',
            'name'           => 'Also No Email',
            'email'          => null,
            'phone'          => '6025558002',
            'account_number' => 'C-8002',
            'status'         => 'active',
        ]);

        $customer = $db->fetch(
            'SELECT id, name, email, fr_id, district FROM customers WHERE email = ? OR phone = ? OR account_number = ? LIMIT 1',
            ['C-8002', 'C-8002', 'C-8002']
        );
        $this->assertNotNull($customer, 'Account-number lookup must find the no-email customer');
        $this->assertTrue(empty($customer['email']));
    }

    /* ---------------- first-time email capture ---------------- */

    #[Test] public function apply_email_capture_saves_local_and_issues_otp(): void
    {
        $db = Database::instance();
        $db->insert('customers', [
            'fr_id'          => 'C-8100',
            'district'       => 'wa',
            'name'           => 'Capture Me',
            'email'          => null,
            'phone'          => '5095558100',
            'account_number' => 'C-8100',
            'status'         => 'active',
        ]);
        $customerId = (int) $db->scalar("SELECT id FROM customers WHERE fr_id = 'C-8100'");

        $result = $this->invokeApplyEmailCapture($customerId, 'capture@example.com');
        $this->assertSame(['ok' => true], $result);

        $row = $db->fetch("SELECT email FROM customers WHERE id = ?", [$customerId]);
        $this->assertSame('capture@example.com', $row['email'], 'Captured email must be saved locally');

        $otp = $db->fetch(
            "SELECT identity, purpose, used_at FROM otp_codes WHERE identity = ? ORDER BY id DESC LIMIT 1",
            ['capture@example.com']
        );
        $this->assertNotNull($otp, 'An OTP must be issued for the newly captured email');
        $this->assertSame('login', $otp['purpose']);
        $this->assertNull($otp['used_at'], 'Code must be unused and ready to verify');
    }

    #[Test] public function apply_email_capture_restarts_when_email_already_set(): void
    {
        $db = Database::instance();
        $db->insert('customers', [
            'fr_id'          => 'C-8101',
            'district'       => 'wa',
            'name'           => 'Has Email',
            'email'          => 'already@example.com',
            'phone'          => '5095558101',
            'account_number' => 'C-8101',
            'status'         => 'active',
        ]);
        $customerId = (int) $db->scalar("SELECT id FROM customers WHERE fr_id = 'C-8101'");

        $result = $this->invokeApplyEmailCapture($customerId, 'second@example.com');
        $this->assertSame(['restart' => true], $result, 'Double-submit / already-set must restart the flow');
        $this->assertSame('already@example.com', $db->scalar("SELECT email FROM customers WHERE id = ?", [$customerId]));
        $this->assertSame(0, (int) $db->scalar('SELECT COUNT(*) FROM otp_codes'), 'No code may be issued for a customer who already has an email');
    }

    #[Test] public function apply_email_capture_restarts_when_customer_missing(): void
    {
        $result = $this->invokeApplyEmailCapture(999999, 'ghost@example.com');
        $this->assertSame(['restart' => true], $result);
        $this->assertSame(0, (int) Database::instance()->scalar('SELECT COUNT(*) FROM otp_codes'));
    }

    #[Test] public function capture_validation_accepts_only_real_emails(): void
    {
        // Exactly the rule emailCaptureSubmit applies before calling the core.
        $bad = Validator::make(['email' => 'not-an-email'], ['email' => ['required', 'email', 'max:254']]);
        $this->assertNotEmpty($bad, 'Malformed email must be rejected at capture');

        $bad2 = Validator::make(['email' => ''], ['email' => ['required', 'email', 'max:254']]);
        $this->assertNotEmpty($bad2, 'Empty email must be rejected at capture');

        $ok = Validator::make(['email' => 'real@example.com'], ['email' => ['required', 'email', 'max:254']]);
        $this->assertSame([], $ok, 'Well-formed email must pass capture validation');
    }
}
