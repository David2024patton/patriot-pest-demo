<?php
/**
 * bin/fr-sync-customers.php - pull customers from FieldRoutes into the local
 * cache, for EVERY configured district (WA + AZ). FieldRoutes stays the source
 * of truth; this only refreshes identity fields and never overwrites local
 * opt-out flags (is_no_call / dnc_reason).
 *
 * Behaviour:
 *   - If no district is configured (keys blank in .env), it prints exactly which
 *     env vars to fill and exits 0 (graceful - that is the expected pre-creds
 *     state, not an error).
 *   - Otherwise it syncs each district independently; one failing district does
 *     not abort the other. On success it stamps meta.fr_last_sync.
 *
 * Usage:
 *   php bin/fr-sync-customers.php            # all configured districts
 *   php bin/fr-sync-customers.php wa         # one district only
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;
use PPC\Integrations\FieldRoutes;

$only = strtolower(trim($argv[1] ?? ''));

if (!FieldRoutes::isConfigured()) {
    $missing = FieldRoutes::missingDistricts();
    echo "FieldRoutes is NOT configured yet. No live pull performed.\n\n";
    echo "To enable dual-district sync, set these in .env (the base URL is shared):\n";
    echo "  FIELDROUTES_BASE_URL=https://patriotpestc.fieldroutes.com\n";
    foreach ($missing as $code) {
        $u = strtoupper($code);
        echo "  FIELDROUTES_{$u}_KEY=<{$code} api key>\n";
        echo "  FIELDROUTES_{$u}_TOKEN=<{$code} api token>\n";
    }
    echo "\nOnce set, re-run this command; it will pull WA + AZ customers into the\n";
    echo "local cache so staff see everyone from inside this console (no FR login).\n";
    exit(0);
}

$db = Database::instance();
$totals = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0];
$ran = 0;

printf("%-8s | %-8s | %-8s | %-8s | %-8s | %s\n", 'DISTRICT', 'FETCHED', 'INSERTED', 'UPDATED', 'SKIPPED', 'STATUS');
echo str_repeat('-', 78) . "\n";

foreach (FieldRoutes::districts() as $district) {
    if ($only !== '' && $only !== $district['code']) {
        continue;
    }
    $code = $district['code'];
    $stat = ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0];
    $status = 'ok';
    try {
        $rows = FieldRoutes::pullCustomersForDistrict($district);
        $stat['fetched'] = count($rows);
        foreach ($rows as $row) {
            $r = FieldRoutes::upsertCustomer($row);
            $stat[$r]++;
        }
    } catch (\Throwable $e) {
        $status = 'FAIL: ' . $e->getMessage();
        $totals['errors']++;
    }

    printf("%-8s | %-8d | %-8d | %-8d | %-8d | %s\n",
        strtoupper($code), $stat['fetched'], $stat['inserted'], $stat['updated'], $stat['skipped'], $status);

    foreach (['fetched', 'inserted', 'updated', 'skipped'] as $k) {
        $totals[$k] += $stat[$k];
    }
    $ran++;
}

echo str_repeat('-', 78) . "\n";
printf("TOTAL    | %-8d | %-8d | %-8d | %-8d | %s\n",
    $totals['fetched'], $totals['inserted'], $totals['updated'], $totals['skipped'],
    $totals['errors'] ? ($totals['errors'] . ' district error(s)') : 'clean');

$now = gmdate('Y-m-d H:i:s');
if ($ran > 0 && $totals['errors'] === 0) {
    $db->execute("INSERT INTO meta (key, value) VALUES ('fr_last_sync', ?)
                  ON CONFLICT(key) DO UPDATE SET value = excluded.value", [$now]);
    $db->execute("INSERT INTO meta (key, value) VALUES ('fr_last_sync_summary', ?)
                  ON CONFLICT(key) DO UPDATE SET value = excluded.value",
        [json_encode($totals)]);
    echo "\nRecorded meta.fr_last_sync = {$now} (UTC)\n";
} elseif ($totals['errors'] > 0) {
    echo "\nSync completed WITH errors. meta.fr_last_sync NOT advanced.\n";
}

echo "\nLocal customer cache now: " . (int) $db->scalar('SELECT COUNT(*) FROM customers') . " row(s)\n";
