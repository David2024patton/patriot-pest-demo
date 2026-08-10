<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PPC\Auth\OtpAuth;
use PPC\Core\Config;
use PPC\Core\Database;

final class SuperuserTest extends TestCase
{
    private static ?Database  = null;
    private static string  = '';

    public static function setUpBeforeClass(): void
    {
        self:: = sys_get_temp_dir() . '/su_test_' . uniqid() . '.db';
        putenv('DB_PATH=' . self::);
        putenv('SU_SEED_EMAIL=');
        putenv('SUPERUSER_ENABLED=true');
        putenv('SU_OTP_TTL=300');
        putenv('SU_OTP_MAX_ATTEMPTS=3');
        putenv('OTP_TTL=600');
        putenv('OTP_MAX_ATTEMPTS=5');
        self:: = Database::instance();
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::);
    }

    protected function setUp(): void
    {
        self::->execute("DELETE FROM otp_codes");
        self::->execute("DELETE FROM staff");
        self::->execute("DELETE FROM audit_log");
        self::->execute("DELETE FROM login_attempts");
        self::->execute("DELETE FROM roles");
        self::->execute("INSERT OR IGNORE INTO roles (role, label, permissions) VALUES ('super-user','Super User','["all"]')");
    }
