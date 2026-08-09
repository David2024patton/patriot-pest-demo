<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PPC\Core\Session;
use PPC\Core\Config;
use PPC\Core\Database;
use PPC\Core\Router;
use PPC\Auth\OtpAuth;
use PPC\Controllers\AuthController;
use PPC\Controllers\StaffController;

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
        $dbPath = sys_get_temp_dir() . '/test_su_def1_' . uniqid() . '.db';
        putenv('DB_PATH=' . $dbPath);
        $this->resetDatabaseSingleton();
        $db = Database::instance();
        $db->insert('staff', ['email' => 'super@itak.net', 'name' => 'Super User', 'role' => 'super-user', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $db->insert('staff', ['email' => 'staff@itak.net', 'name' => 'Staff User', 'role' => 'staff', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $controller = new AuthController();
        $method = new \ReflectionMethod(AuthController::class, 'findStaffForLogin');
        $method->setAccessible(true);
        $result = $method->invoke($controller, 'super@itak.net');
        $this->assertNull($result, 'Super-user must be excluded from standard login');
        $result = $method->invoke($controller, 'staff@itak.net');
        $this->assertNotNull($result, 'Regular staff must be found');
        $this->assertSame('staff', $result['role']);
        @unlink($dbPath);
    }

    #[Test] public function test_startStaffSession_rejects_superuser(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_su_def2_' . uniqid() . '.db';
        putenv('DB_PATH=' . $dbPath);
        $this->resetDatabaseSingleton();
        $db = Database::instance();
        $db->insert('staff', ['email' => 'super@itak.net', 'name' => 'Super User', 'role' => 'super-user', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        $controller = new AuthController();
        $method = new \ReflectionMethod(AuthController::class, 'startStaffSession');
        $method->setAccessible(true);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Super-user accounts must use the dedicated /su login surface.');
        $method->invoke($controller, 'super@itak.net');
        @unlink($dbPath);
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
        Router::reset();
        $prevSu = getenv('SUPERUSER_ENABLED');
        $prevApi = getenv('API_ENABLED');
        $prevEnv = getenv('APP_ENV');
        try {
            putenv('SUPERUSER_ENABLED=true');
            putenv('API_ENABLED=true');
            putenv('APP_ENV=local');
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/su-route-test';
            if (!defined('PPC_ROUTES_ONLY')) { define('PPC_ROUTES_ONLY', true); }
            require BASE_PATH . '/public/index.php';
            $suRoutes = array_filter(Router::routes(), fn($r) => str_starts_with($r->regex, '#^/su'));
            $this->assertNotEmpty($suRoutes, 'Superuser routes must be registered when enabled');
        } finally {
            if ($prevSu === false) { putenv('SUPERUSER_ENABLED'); } else { putenv('SUPERUSER_ENABLED=' . $prevSu); }
            if ($prevApi === false) { putenv('API_ENABLED'); } else { putenv('API_ENABLED=' . $prevApi); }
            if ($prevEnv === false) { putenv('APP_ENV'); } else { putenv('APP_ENV=' . $prevEnv); }
        }
    }

    #[Test] public function test_superuser_routes_absent_when_disabled(): void
    {
        Router::reset();
        $prevSu = getenv('SUPERUSER_ENABLED');
        $prevApi = getenv('API_ENABLED');
        $prevEnv = getenv('APP_ENV');
        try {
            putenv('SUPERUSER_ENABLED=false');
            putenv('API_ENABLED=true');
            putenv('APP_ENV=local');
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['REQUEST_URI'] = '/su-route-test-off';
            if (!defined('PPC_ROUTES_ONLY')) { define('PPC_ROUTES_ONLY', true); }
            require BASE_PATH . '/public/index.php';
            $suRoutes = array_filter(Router::routes(), fn($r) => str_starts_with($r->regex, '#^/su'));
            $this->assertEmpty($suRoutes, 'Superuser routes must NOT be registered when disabled');
        } finally {
            if ($prevSu === false) { putenv('SUPERUSER_ENABLED'); } else { putenv('SUPERUSER_ENABLED=' . $prevSu); }
            if ($prevApi === false) { putenv('API_ENABLED'); } else { putenv('API_ENABLED=' . $prevApi); }
            if ($prevEnv === false) { putenv('APP_ENV'); } else { putenv('APP_ENV=' . $prevEnv); }
        }
    }

    #[Test] public function test_isSuperUser_and_isAdmin_methods_exist(): void
    {
        $this->assertTrue(method_exists(\PPC\Core\Session::class, 'isSuperUser'));
        $this->assertTrue(method_exists(\PPC\Core\Session::class, 'isAdmin'));
    }

    #[Test] public function test_superuser_dashboard_routing(): void
    {
        $controller = new AuthController();
        $method = new \ReflectionMethod(AuthController::class, 'dashboardFor');
        $method->setAccessible(true);
        $this->assertSame('/admin', $method->invoke($controller, 'staff', 'super-user'));
        $this->assertSame('/admin', $method->invoke($controller, 'staff', 'admin'));
        $this->assertSame('/staff-dashboard', $method->invoke($controller, 'staff', 'tech_support'));
        $this->assertSame('/staff-dashboard', $method->invoke($controller, 'staff', 'accounts'));
        $this->assertSame('/customer-dashboard', $method->invoke($controller, 'customer', null));
        $this->assertSame('/customer-dashboard', $method->invoke($controller, null, null));
    }

    #[Test] public function test_seedSuperUser_empty_email_is_noop(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_su_seed_empty_' . uniqid() . '.db';
        putenv('SU_SEED_EMAIL=');
        putenv('DB_PATH=' . $dbPath);
        $this->resetDatabaseSingleton();
        $db = Database::instance();
        $count = (int) $db->scalar("SELECT COUNT(*) FROM staff WHERE role = 'super-user'");
        $this->assertSame(0, $count, 'No super-user when SU_SEED_EMAIL is empty');
        $auditCount = (int) $db->scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'superuser.grant'");
        $this->assertSame(0, $auditCount);
        @unlink($dbPath);
    }

    #[Test] public function test_seedSuperUser_already_superuser_is_idempotent(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_su_seed_existing_' . uniqid() . '.db';
        putenv('SU_SEED_EMAIL=david@itak.net');
        putenv('SU_SEED_NAME=Super User');
        putenv('DB_PATH=' . $dbPath);
        $this->resetDatabaseSingleton();
        $db = Database::instance();
        $this->assertSame(1, (int) $db->scalar("SELECT COUNT(*) FROM staff WHERE email = 'david@itak.net' AND role = 'super-user'"));
        $before = (int) $db->scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'superuser.grant'");
        $ref = new \ReflectionClass(Database::class);
        $method = $ref->getMethod('seedSuperUser');
        $method->setAccessible(true);
        $method->invoke($db);
        $this->assertSame(1, (int) $db->scalar("SELECT COUNT(*) FROM staff WHERE email = 'david@itak.net'"));
        $this->assertSame('super-user', $db->scalar("SELECT role FROM staff WHERE email = 'david@itak.net'"));
        $after = (int) $db->scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'superuser.grant'");
        $this->assertSame($before, $after, 'No new audit entry on idempotent re-seed');
        @unlink($dbPath);
    }

    #[Test] public function test_seedSuperUser_promotes_existing(): void
    {
        $dbPath = sys_get_temp_dir() . '/test_su_seed_promote_' . uniqid() . '.db';
        putenv('SU_SEED_EMAIL=');
        putenv('DB_PATH=' . $dbPath);
        $this->resetDatabaseSingleton();
        $db = Database::instance();
        $db->insert('staff', ['email' => 'david@itak.net', 'name' => 'David', 'role' => 'staff', 'active' => 1, 'created_at' => gmdate('Y-m-d H:i:s')]);
        putenv('SU_SEED_EMAIL=david@itak.net');
        putenv('SU_SEED_NAME=David');
        $ref = new \ReflectionClass(Database::class);
        $method = $ref->getMethod('seedSuperUser');
        $method->setAccessible(true);
        $method->invoke($db);
        $row = $db->fetch("SELECT id, role FROM staff WHERE email = 'david@itak.net'");
        $this->assertNotNull($row);
        $this->assertSame('super-user', $row['role'], 'Existing staff must be promoted');
        $audit = $db->fetch("SELECT * FROM audit_log WHERE action = 'superuser.grant' AND entity_id = ?", [(string) $row['id']]);
        $this->assertNotNull($audit, 'superuser.grant audit entry must be written');
        $this->assertSame('system', $audit['user_type']);
        $before = (int) $db->scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'superuser.grant'");
        $method->invoke($db);
        $after = (int) $db->scalar("SELECT COUNT(*) FROM audit_log WHERE action = 'superuser.grant'");
        $this->assertSame($before, $after, 'No duplicate audit on re-promotion');
        @unlink($dbPath);
    }

    #[Test] public function test_staffToggle_blocks_superuser_deactivation(): void
    {
        $controller = new StaffController();
        $method = new \ReflectionMethod(StaffController::class, 'isStaffDeactivatable');
        $method->setAccessible(true);
        $this->assertFalse($method->invoke($controller, ['role' => 'super-user', 'id' => 1, 'name' => 'Super']), 'Super-user must not be deactivatable');
        $this->assertTrue($method->invoke($controller, ['role' => 'admin', 'id' => 2, 'name' => 'Admin']), 'Admin must be deactivatable');
        $this->assertTrue($method->invoke($controller, ['role' => 'staff', 'id' => 3, 'name' => 'Staff']), 'Staff must be deactivatable');
        $this->assertTrue($method->invoke($controller, ['id' => 4, 'name' => 'NoRole']), 'Missing role must be deactivatable');
    }

    private function resetDatabaseSingleton(): void
    {
        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }
}
