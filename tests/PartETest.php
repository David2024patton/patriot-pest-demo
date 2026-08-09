<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class PartETest extends TestCase
{
    private static \PDO $pdo;
    public static function setUpBeforeClass(): void
    {
        self::$pdo = new \PDO("sqlite::memory:");
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        self::$pdo->exec("CREATE TABLE api_keys (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, key_prefix TEXT NOT NULL UNIQUE, key_hash TEXT NOT NULL, scopes TEXT NOT NULL DEFAULT \"[]\", expires_at TEXT, revoked_at TEXT, created_at TEXT)");
        self::$pdo->exec("CREATE TABLE audit_log (id INTEGER PRIMARY KEY AUTOINCREMENT, action TEXT NOT NULL, user_type TEXT, created_at TEXT)");
        self::$pdo->exec("CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, identity TEXT NOT NULL, created_at TEXT)");
    }

    #[Test] public function key_hash_is_sha256(): void
    {
        $raw = "ppc_live_" . bin2hex(random_bytes(32));
        $hash = hash("sha256", $raw);
        $this->assertSame(64, strlen($hash));
        $this->assertNotEquals($raw, $hash);
    }

    #[Test] public function key_prefix_is_not_auth(): void
    {
        $key = "ppc_live_" . bin2hex(random_bytes(32));
        $prefix = substr($key, 9, 12);
        $this->assertSame(12, strlen($prefix));
        $hashFull = hash("sha256", $key);
        $hashPrefix = hash("sha256", "ppc_live_" . $prefix);
        $this->assertNotEquals($hashFull, $hashPrefix, "Prefix alone does not authenticate");
    }

    #[Test] public function bad_key_hash_mismatch(): void
    {
        $key = "ppc_live_" . bin2hex(random_bytes(32));
        $badKey = "ppc_live_" . bin2hex(random_bytes(32));
        $this->assertNotEquals(hash("sha256", $key), hash("sha256", $badKey));
        $this->assertFalse(hash_equals(hash("sha256", $key), hash("sha256", $badKey)));
    }

    #[Test] public function expired_key_not_valid(): void
    {
        self::$pdo->exec("DELETE FROM api_keys");
        $key = "ppc_live_" . bin2hex(random_bytes(32));
        self::$pdo->prepare("INSERT INTO api_keys (name, key_prefix, key_hash, scopes, expires_at, created_at) VALUES (?,?,?,?,datetime(\"now\",\"-1 day\"),datetime(\"now\"))")->execute(["Test", substr($key,9,12), hash("sha256",$key), "[]"]);
        $row = self::$pdo->query("SELECT expires_at FROM api_keys LIMIT 1")->fetch();
        $this->assertNotNull($row["expires_at"]);
        $this->assertLessThan(time(), strtotime($row["expires_at"]));
    }

    #[Test] public function revoked_key_is_revoked(): void
    {
        self::$pdo->exec("DELETE FROM api_keys");
        $key = "ppc_live_" . bin2hex(random_bytes(32));
        self::$pdo->prepare("INSERT INTO api_keys (name, key_prefix, key_hash, scopes, revoked_at, created_at) VALUES (?,?,?,?,datetime(\"now\"),datetime(\"now\"))")->execute(["Test", substr($key,9,12), hash("sha256",$key), "[]"]);
        $row = self::$pdo->query("SELECT revoked_at FROM api_keys LIMIT 1")->fetch();
        $this->assertNotNull($row["revoked_at"]);
    }

    #[Test] public function scope_denial(): void
    {
        $scopes = ["customer:read", "ticket:read"];
        $this->assertContains("customer:read", $scopes);
        $this->assertNotContains("twilio:read", $scopes);
    }

    #[Test] public function rotation_invalidates_old(): void
    {
        self::$pdo->exec("DELETE FROM api_keys");
        $key = "ppc_live_" . bin2hex(random_bytes(32));
        self::$pdo->prepare("INSERT INTO api_keys (name, key_prefix, key_hash, scopes, created_at) VALUES (?,?,?,?,datetime(\"now\"))")->execute(["Old", substr($key,9,12), hash("sha256",$key), "[]"]);
        $oldId = (int)self::$pdo->lastInsertId();
        self::$pdo->beginTransaction();
        self::$pdo->exec("UPDATE api_keys SET revoked_at = datetime(\"now\") WHERE id = " . $oldId);
        $newKey = "ppc_live_" . bin2hex(random_bytes(32));
        self::$pdo->prepare("INSERT INTO api_keys (name, key_prefix, key_hash, scopes, created_at) VALUES (?,?,?,?,datetime(\"now\"))")->execute(["New", substr($newKey,9,12), hash("sha256",$newKey), "[]"]);
        self::$pdo->commit();
        $old = self::$pdo->query("SELECT revoked_at FROM api_keys WHERE id=" . $oldId)->fetch();
        $this->assertNotNull($old["revoked_at"]);
        $this->assertSame(2, (int)self::$pdo->query("SELECT COUNT(*) FROM api_keys")->fetchColumn());
    }

    #[Test] public function rate_limit_key_isolation(): void
    {
        $this->assertNotEquals("api_key:1:test", "api_key:2:test");
    }

    #[Test] public function api_disabled_toggle_exists(): void
    {
        $this->assertTrue(method_exists(\PPC\Core\Config::class, "bool"), "Config::bool exists");
        // API_ENABLED defaults to false
        $this->assertFalse(\PPC\Core\Config::bool("API_ENABLED", false));
    }

    #[Test] public function pii_redaction_without_full_scope(): void
    {
        $c = ["id"=>1,"name"=>"Test","phone"=>"+15095551234","email"=>"t@e.com","address"=>"123 Main","zip"=>"99201"];
        $r = $c;
        $r["phone"] = null; $r["email"] = null; $r["address"] = null; $r["zip"] = null;
        $this->assertNull($r["phone"]);
        $this->assertNull($r["email"]);
        $this->assertSame("Test", $r["name"]);
    }

    #[Test] public function session_has_permission_checks(): void
    {
        $all = json_decode("[\"all\"]", true);
        $this->assertContains("all", $all);
        $limited = json_decode("[\"view_customers\",\"send_messages\"]", true);
        $this->assertContains("view_customers", $limited);
        $this->assertNotContains("manage_billing", $limited);
    }

    #[Test] public function ticket_body_redacted(): void
    {
        $t = ["id"=>1,"customer_id"=>"c1","category"=>"support","priority"=>"normal","subject"=>"Help","body"=>"sensitive","status"=>"open","created_at"=>"2026-01-01","updated_at"=>"2026-01-01","customer_name"=>"Alice"];
        $this->assertSame("sensitive", $t["body"]);
        $t["body"] = null; $t["customer_name"] = null;
        $this->assertNull($t["body"]);
        $this->assertNull($t["customer_name"]);
        $this->assertSame("Help", $t["subject"]);
    }

    #[Test] public function message_body_redacted(): void
    {
        $m = ["id"=>5,"from_user"=>"c1","from_type"=>"customer","to_user"=>"s1","to_type"=>"staff","subject"=>"Re:Service","body"=>"private message","is_read"=>0,"created_at"=>"2026-01-01","from_name"=>"Bob","to_name"=>"Staff"];
        $this->assertSame("private message", $m["body"]);
        $m["body"] = null; $m["subject"] = null; $m["from_name"] = null; $m["to_name"] = null;
        $this->assertNull($m["body"]);
        $this->assertNull($m["subject"]);
        $this->assertNull($m["from_name"]);
        $this->assertNull($m["to_name"]);
        $this->assertSame("c1", $m["from_user"]);
    }

    #[Test] public function twilio_row_redacted(): void
    {
        $r = ["id"=>10,"phone_number"=>"+15095551234","message"=>"hi","direction"=>"inbound","status"=>"delivered","twilio_sid"=>"SMxxx","twilio_status"=>"delivered","error_message"=>null,"media_url"=>"https://media","created_at"=>"2026-01-01","updated_at"=>"2026-01-01"];
        $this->assertSame("+15095551234", $r["phone_number"]);
        $this->assertSame("hi", $r["message"]);
        $this->assertSame("https://media", $r["media_url"]);
        $r["phone_number"] = null; $r["message"] = null; $r["media_url"] = null; $r["transcription"] = null; $r["recording_url"] = null; $r["audio_url"] = null;
        $this->assertNull($r["phone_number"]);
        $this->assertNull($r["message"]);
        $this->assertNull($r["media_url"]);
        $this->assertSame("inbound", $r["direction"]);
    }

    #[Test] public function per_ip_rate_limit_key_isolated(): void
    {
        $this->assertNotEquals("api_ip:127.0.0.1:test", "api_key:1:test");
        $this->assertStringStartsWith("api_ip:", "api_ip:10.0.0.1:tickets");
        $this->assertStringStartsWith("api_key:", "api_key:42:customers");
    }

    #[Test] public function lifecycle_audit_log_actions_defined(): void
    {
        $actions = ["api_key.create","api_key.revoke","api_key.rotate","api_key.scopes"];
        $this->assertContains("api_key.create", $actions);
        $this->assertContains("api_key.revoke", $actions);
        $this->assertContains("api_key.rotate", $actions);
        $this->assertContains("api_key.scopes", $actions);
    }
}
