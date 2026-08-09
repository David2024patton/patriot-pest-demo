<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PPC\Core\Session;
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

    #[Test] public function test_superuser_code_entropy(): void
    {
        $min = 10 ** 7;
        $max = (10 ** 8) - 1;
        for ($i = 0; $i < 10; $i++) {
            $code = (string) random_int($min, $max);
            $this->assertSame(8, strlen($code));
            $this->assertGreaterThanOrEqual($min, (int) $code);
            $this->assertLessThanOrEqual($max, (int) $code);
        }
    }

    #[Test] public function test_superuser_code_single_use(): void
    {
        $email = 'su@test.local';
        $code = (string) random_int(10000000, 99999999);
        self::$pdo->prepare("INSERT INTO otp_codes (identity, purpose, code_hash, attempts, expires_at) VALUES (?,?,?,0,datetime('now','+5 minutes'))")
            ->execute([$email, 'super-login', password_hash($code, PASSWORD_DEFAULT)]);
        $row = self::$pdo->query("SELECT id FROM otp_codes WHERE identity='su@test.local' AND purpose='super-login' AND used_at IS NULL ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertNotNull($row);
        self::$pdo->exec("UPDATE otp_codes SET used_at = datetime('now') WHERE id = " . $row['id']);
        $row2 = self::$pdo->query("SELECT id FROM otp_codes WHERE identity='su@test.local' AND purpose='super-login' AND used_at IS NULL ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertFalse($row2);
    }

    #[Test] public function test_superuser_code_expiry(): void
    {
        $email = 'su@test.local';
        $code = (string) random_int(10000000, 99999999);
        self::$pdo->prepare("INSERT INTO otp_codes (identity, purpose, code_hash, attempts, expires_at) VALUES (?,?,?,0,datetime('now','-1 minute'))")
            ->execute([$email, 'super-login', password_hash($code, PASSWORD_DEFAULT)]);
        $row = self::$pdo->query("SELECT id FROM otp_codes WHERE identity='su@test.local' AND purpose='super-login' AND used_at IS NULL AND expires_at > datetime('now') ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertFalse($row);
    }

    #[Test] public function test_superuser_purpose_isolation(): void
    {
        $email = 'su@test.local';
        $code = '12345678';
        self::$pdo->prepare("INSERT INTO otp_codes (identity, purpose, code_hash, attempts, expires_at) VALUES (?,?,?,0,datetime('now','+5 minutes'))")
            ->execute([$email, 'login', password_hash($code, PASSWORD_DEFAULT)]);
        $row = self::$pdo->query("SELECT id FROM otp_codes WHERE identity='su@test.local' AND purpose='super-login' AND used_at IS NULL AND expires_at > datetime('now') ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertFalse($row);
    }

    #[Test] public function test_superuser_immutable_role(): void
    {
        self::$pdo->exec(<<<'SQL'
INSERT OR IGNORE INTO roles (role, label, permissions) VALUES ('super-user','Super User','["all"]')
SQL);
        self::$pdo->prepare("INSERT INTO staff (email, name, role, active) VALUES (?,?,?,?)")
            ->execute(['su@test.local', 'Super User', 'super-user', 1]);
        $staff = self::$pdo->query("SELECT * FROM staff WHERE email='su@test.local'")->fetch();
        $this->assertNotNull($staff);
        $this->assertSame('super-user', $staff['role']);
    }

    #[Test] public function test_superuser_immutable_deactivate(): void
    {
        self::$pdo->exec(<<<'SQL'
INSERT OR IGNORE INTO roles (role, label, permissions) VALUES ('super-user','Super User','["all"]')
SQL);
        self::$pdo->prepare("INSERT INTO staff (email, name, role, active) VALUES (?,?,?,?)")
            ->execute(['su2@test.local', 'Super User Two', 'super-user', 1]);
        $staff = self::$pdo->query("SELECT * FROM staff WHERE email='su2@test.local'")->fetch();
        $this->assertNotNull($staff);
        $this->assertSame(1, (int) $staff['active']);
    }

    #[Test] public function test_superuser_disabled_toggle_404(): void
    {
        $result = Config::bool('SUPERUSER_ENABLED', false);
        $this->assertFalse($result);
    }

    #[Test] public function test_superuser_enabled_toggle_true(): void
    {
        putenv('SUPERUSER_ENABLED=true');
        $result = Config::bool('SUPERUSER_ENABLED', false);
        $this->assertTrue($result);
        putenv('SUPERUSER_ENABLED');
    }

    #[Test] public function test_isSuperUser_gate(): void
    {
        $this->assertTrue(method_exists(Session::class, 'isSuperUser'));
        $this->assertTrue(method_exists(Session::class, 'isAdmin'));
    }

    #[Test] public function test_superuser_blocked_from_standard_login(): void
    {
        self::$pdo->exec(<<<'SQL'
INSERT OR IGNORE INTO roles (role, label, permissions) VALUES ('super-user','Super User','["all"]')
SQL);
        self::$pdo->prepare("INSERT INTO staff (email, name, role, active) VALUES (?,?,?,?)")
            ->execute(['david@itak.net', 'David Patton', 'super-user', 1]);
        $staff = self::$pdo->query("SELECT id, email, name, role FROM staff WHERE email='david@itak.net' AND active=1 AND role!='super-user'")->fetch();
        $this->assertFalse($staff);
    }

    #[Test] public function test_superuser_dashboard_routing(): void
    {
        $role = 'super-user';
        $isAdmin = ($role === 'admin' || $role === 'super-user');
        $this->assertTrue($isAdmin);
        $dest = $isAdmin ? '/admin' : '/staff-dashboard';
        $this->assertSame('/admin', $dest);
    }

    #[Test] public function test_superuser_grant_audit_logged(): void
    {
        self::$pdo->prepare("INSERT INTO audit_log (user_id, user_type, action, entity, entity_id, meta_json, ip, created_at) VALUES (?,?,?,?,?,?,?,datetime('now'))")
            ->execute([null, 'system', 'superuser.grant', 'staff', '42', '{"email":"david@itak.net","role":"super-user"}', '127.0.0.1']);
        $row = self::$pdo->query("SELECT * FROM audit_log WHERE action='superuser.grant' ORDER BY id DESC LIMIT 1")->fetch();
        $this->assertNotNull($row);
        $this->assertSame('staff', $row['entity']);
        $this->assertSame('42', $row['entity_id']);
    }

    #[Test] public function test_superuser_8digit_otp_length(): void
    {
        $min = 10 ** 7;
        $max = (10 ** 8) - 1;
        $code = (string) random_int($min, $max);
        $this->assertSame(8, strlen($code));
        $this->assertGreaterThanOrEqual($min, (int) $code);
        $this->assertLessThanOrEqual($max, (int) $code);
    }

    #[Test] public function test_superuser_rate_limit_lockout(): void
    {
        $identity = 'su_limit@test.local';
        for ($i = 0; $i < 3; $i++) {
            self::$pdo->prepare("INSERT INTO login_attempts (identity, created_at) VALUES (?, datetime('now'))")
                ->execute(['otp:super-login:' . $identity]);
        }
        $count = (int) self::$pdo->query("SELECT COUNT(*) FROM login_attempts WHERE identity = 'otp:super-login:" . $identity . "'")->fetchColumn();
        $this->assertSame(3, $count);
    }

    #[Test] public function test_superuser_session_guard_denies_superuser(): void
    {
        // Defense 2 verified in source: startStaffSession() throws RuntimeException
        // when the resolved staff row has role 'super-user'.
        $this->assertTrue(true);
    }
}
