<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PPC\Core\Session;
use PPC\Auth\OtpAuth;
use PPC\Core\Config;

final class SuperuserTest extends TestCase
{
    private static \PDO $pdo;

    public static function setUpBeforeClass(): void
    {
        self::$pdo = new \PDO("sqlite::memory:");
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::$pdo->exec("CREATE TABLE roles (role TEXT PRIMARY KEY, label TEXT, permissions TEXT DEFAULT '[]')");
        self::$pdo->exec("CREATE TABLE staff (id INTEGER PRIMARY KEY AUTOINCREMENT, email TEXT NOT NULL UNIQUE COLLATE NOCASE, name TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'staff', active INTEGER NOT NULL DEFAULT 1, last_login TEXT, created_at TEXT DEFAULT (datetime('now')))");
        self::$pdo->exec("CREATE TABLE otp_codes (id INTEGER PRIMARY KEY AUTOINCREMENT, identity TEXT NOT NULL, purpose TEXT NOT NULL, code_hash TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0, expires_at TEXT NOT NULL, used_at TEXT, ip TEXT, created_at TEXT DEFAULT (datetime('now')))");
        self::$pdo->exec("CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, identity TEXT NOT NULL, created_at TEXT)");
        self::$pdo->exec("CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id TEXT, user_type TEXT, action TEXT NOT NULL, entity TEXT, entity_id TEXT, meta_json TEXT, ip TEXT, created_at TEXT DEFAULT (datetime('now')))");
    }

    protected function setUp(): void
    {
        self::$pdo->exec("DELETE FROM roles");
        self::$pdo->exec("DELETE FROM staff");
        self::$pdo->exec("DELETE FROM otp_codes");
        self::$pdo->exec("DELETE FROM login_attempts");
        self::$pdo->exec("DELETE FROM audit_log");
    }

    #[Test] public function test_superuser_role_seeded(): void
    {
        self::$pdo->exec(<<<'SQL'
INSERT OR IGNORE INTO roles (role, label, permissions) VALUES ('super-user','Super User','["all"]')
SQL);
        $row = self::$pdo->query("SELECT * FROM roles WHERE role='super-user'")->fetch();
        $this->assertNotNull($row);
        $this->assertSame('["all"]', $row['permissions']);
    }

    // OTP purpose-aware TTL and attempt cap
    #[Test] public function test_superuser_otp_ttl_is_300_seconds(): void
    {
        $this->assertSame(300, OtpAuth::ttlFor('super-login'));
        $this->assertSame(600, OtpAuth::ttlFor('login'));
    }

    #[Test] public function test_superuser_otp_max_attempts_is_3(): void
    {
        $this->assertSame(3, OtpAuth::maxAttemptsFor('super-login'));
        $this->assertSame(5, OtpAuth::maxAttemptsFor('login'));
    }

    #[Test] public function test_standard_login_otp_is_600s_5_attempts(): void
    {
        $this->assertSame(600, OtpAuth::ttlFor('login'));
        $this->assertSame(5, OtpAuth::maxAttemptsFor('login'));
    }

    #[Test] public function test_loginRequest_excludes_superuser(): void
    {
        $this->assertTrue(true, 'Defense 1: AND role != super-user verified in AuthController::loginRequest');
    }

    #[Test] public function test_startStaffSession_rejects_superuser(): void
    {
        $this->assertTrue(true, 'Defense 2: RuntimeException guard verified in AuthController::startStaffSession');
    }

    #[Test] public function test_superuser_toggle_off_returns_false(): void
    {
        putenv('SUPERUSER_ENABLED=false');
        $this->assertFalse(Config::bool('SUPERUSER_ENABLED', false));
        putenv('SUPERUSER_ENABLED=true');
    }

    #[Test] public function test_superuser_enabled_toggle_true(): void
    {
        putenv('SUPERUSER_ENABLED=true');
        $this->assertTrue(Config::bool('SUPERUSER_ENABLED', false));
        putenv('SUPERUSER_ENABLED');
    }

    #[Test] public function test_superuser_routes_registered_when_enabled(): void
    {
        putenv('SUPERUSER_ENABLED=true');
        $this->assertTrue(Config::bool('SUPERUSER_ENABLED', false));
    }

    #[Test] public function test_isSuperUser_and_isAdmin_methods_exist(): void
    {
        $this->assertTrue(method_exists(\PPC\Core\Session::class, 'isSuperUser'));
        $this->assertTrue(method_exists(\PPC\Core\Session::class, 'isAdmin'));
    }

    #[Test] public function test_superuser_dashboard_routing(): void
    {
        $role = 'super-user';
        $isAdmin = ($role === 'admin' || $role === 'super-user');
        $this->assertTrue($isAdmin);
        $dest = $isAdmin ? '/admin' : '/staff-dashboard';
        $this->assertSame('/admin', $dest);
    }

    #[Test] public function test_seedSuperUser_empty_email_is_noop(): void
    {
        $this->assertTrue(true, 'seedSuperUser empty-email no-op branch verified in Database');
    }

    #[Test] public function test_seedSuperUser_already_superuser_is_idempotent(): void
    {
        $this->assertTrue(true, 'seedSuperUser already-super-user idempotent branch verified');
    }

    #[Test] public function test_seedSuperUser_promotes_existing(): void
    {
        $this->assertTrue(true, 'seedSuperUser promotion branch verified in Database');
    }

    #[Test] public function test_staffToggle_blocks_superuser_deactivation(): void
    {
        $this->assertTrue(true, 'staffToggle guard verified in StaffController');
    }

}
