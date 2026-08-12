<?php
/**
 * bin/db-inspect.php - read-only snapshot of the live SQLite database.
 *
 * Uses the exact same Database singleton the web app uses, so this is the
 * authoritative view of what the dashboards/CMS are reading. SELECTs only.
 *   php bin/db-inspect.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();

function dump(string $title, array $rows): void
{
    echo "\n=== $title (" . count($rows) . " row(s)) ===\n";
    if (!$rows) { echo "  (empty)\n"; return; }
    $cols = array_keys($rows[0]);
    echo '  ' . implode(' | ', $cols) . "\n";
    foreach ($rows as $r) {
        echo '  ' . implode(' | ', array_map(fn($v) => $v === null ? '∅' : (string) $v, $r)) . "\n";
    }
}

dump('STAFF (login identities)',
    $db->fetchAll('SELECT id, email, name, role, active, last_login FROM staff ORDER BY id'));

dump('CUSTOMERS (portal identities)',
    $db->fetchAll('SELECT id, account_number, name, email, phone, city, state, status FROM customers ORDER BY id'));

dump('POSTS (blog / CMS)',
    $db->fetchAll('SELECT id, slug, title, season, pest_category, status, views FROM posts ORDER BY id'));

dump('PEST PHOTOS (count only)',
    $db->fetchAll('SELECT COUNT(*) AS total, COUNT(DISTINCT category) AS categories FROM pest_photos'));

dump('OTP CODES (recent - passwordless login trail)',
    $db->fetchAll("SELECT id, identity, purpose, attempts, used_at IS NOT NULL AS used, created_at FROM otp_codes ORDER BY id DESC LIMIT 6"));

dump('AUDIT LOG (recent security trail)',
    $db->fetchAll('SELECT id, user_type, user_id, action, ip, created_at FROM audit_log ORDER BY id DESC LIMIT 8'));

echo "\nDone.\n";
