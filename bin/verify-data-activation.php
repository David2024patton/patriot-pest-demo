<?php
/**
 * bin/verify-data-activation.php — Gate 2-3 data activation verification.
 *
 * Answers, from the live SQLite cache:
 *   1. Customer status distribution (the 99.7% cancelled question).
 *   2. Source tagging: seed fixtures tagged, real book excludes them.
 *   3. Active-customer count with seed excluded (aggregate contract).
 *   4. last_service coverage (recency signal for reactivation).
 *   5. Reactivation engine content (templates/campaigns/sends).
 *   6. Phone format stats (E.164 readiness for Twilio).
 *
 * Read-only. Safe to run anytime.
 *   php bin/verify-data-activation.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();
$pct = fn(int $n, int $t): string => $t ? number_format($n / $t * 100, 1) . '%' : 'n/a';

echo "=== 1. CUSTOMER STATUS DISTRIBUTION (raw) ===\n";
$total = 0;
foreach ($db->fetchAll('SELECT status, COUNT(*) c FROM customers GROUP BY status ORDER BY c DESC') as $r) {
    $total += (int) $r['c'];
    printf("  %-10s %5d\n", $r['status'], $r['c']);
}
echo "  TOTAL     {$total}\n";

echo "\n=== 2. SOURCE TAGGING ===\n";
foreach ($db->fetchAll('SELECT source, COUNT(*) c FROM customers GROUP BY source ORDER BY c DESC') as $r) {
    printf("  %-12s %5d\n", $r['source'], $r['c']);
}
echo "\n  Seed fixtures by name:\n";
foreach ($db->fetchAll("SELECT name, email, status, source FROM customers WHERE source = 'seed' ORDER BY id") as $r) {
    printf("    - %-12s %-28s %-9s %s\n", $r['name'], $r['email'], $r['status'], $r['source']);
}

echo "\n=== 3. ACTIVE CUSTOMERS (seed excluded) ===\n";
$activeAll   = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE status = 'active'");
$activeReal  = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE status = 'active' AND source != 'seed'");
$activeSeed  = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE status = 'active' AND source = 'seed'");
printf("  active (all)        %d\n", $activeAll);
printf("  active (real book)  %d   <-- aggregate contract: this is the number to report\n", $activeReal);
printf("  active (seed only)  %d   (excluded from all aggregates)\n", $activeSeed);

echo "\n=== 4. LAST_SERVICE COVERAGE ===\n";
$lsNull = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE last_service IS NULL OR last_service = ''");
$lsAll  = (int) $db->scalar('SELECT COUNT(*) FROM customers');
printf("  populated %d of %d (%s)  recency signal %s\n", $lsAll - $lsNull, $lsAll, $pct($lsAll - $lsNull, $lsAll),
    ($lsAll - $lsNull) > 0 ? 'AVAILABLE' : 'ABSENT (needs fresh FR sync, Chief Engineering lane)');

echo "\n=== 5. REACTIVATION ENGINE ===\n";
printf("  templates  %d\n", (int) $db->scalar('SELECT COUNT(*) FROM reactivation_templates'));
printf("  campaigns  %d\n", (int) $db->scalar('SELECT COUNT(*) FROM reactivation_campaigns'));
printf("  sends      %d\n", (int) $db->scalar('SELECT COUNT(*) FROM reactivation_sends'));
echo "  templates by season:\n";
foreach ($db->fetchAll('SELECT season, COUNT(*) c FROM reactivation_templates GROUP BY season ORDER BY season') as $r) {
    printf("    - %-8s %d\n", $r['season'], $r['c']);
}

echo "\n=== 6. PHONE FORMAT (E.164 readiness) ===\n";
$e164   = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE phone LIKE '+%'");
$ten    = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE phone GLOB '[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]'");
$empty  = (int) $db->scalar("SELECT COUNT(*) FROM customers WHERE phone IS NULL OR phone = ''");
printf("  E.164 (+1...)   %4d\n", $e164);
printf("  bare 10-digit   %4d  (normalized on next FR sync via normalizePhone)\n", $ten);
printf("  empty/missing   %4d\n", $empty);

echo "\n=== CACHE FRESHNESS ===\n";
$last = $db->scalar("SELECT value FROM meta WHERE key = 'fr_last_sync'");
printf("  fr_last_sync: %s (%d days ago)\n", $last, $last ? (int) floor((time() - strtotime((string) $last)) / 86400) : -1);
echo "Done.\n";
