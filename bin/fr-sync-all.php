<?php
/**
 * bin/fr-sync-all.php — full FieldRoutes → local sync (customers AND employees)
 * for every configured district (WA + AZ). FieldRoutes stays the source of
 * truth; this refreshes identity fields only and never overwrites local
 * opt-out flags (is_no_call / dnc_reason) or locally-managed staff roles.
 *
 * Change detection: upserts skip rows whose FR-sourced fields already match,
 * so repeated runs write nothing when the book hasn't moved.
 *
 * Usage (cron):  php bin/fr-sync-all.php
 *                php bin/fr-sync-all.php --districts=wa
 * Exit codes: 0 clean (or pre-creds), 1 district error (meta NOT advanced).
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;
use PPC\Integrations\FieldRoutes;

$only = '';
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--districts=')) {
        $only = strtolower(trim(substr($arg, 12)));
    }
}

if (!FieldRoutes::isConfigured()) {
    echo "FieldRoutes is NOT configured yet. No live pull performed.\n";
    echo "Set FIELDROUTES_BASE_URL + per-district KEY/TOKEN in .env and re-run.\n";
    exit(0);
}

$db = Database::instance();
$totals = [
    'customers' => ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
    'employees' => ['fetched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0],
];
$errors = 0;

function sync_rows(array $rows, callable $upsert, array &$stat): void
{
    $stat['fetched'] += count($rows);
    foreach ($rows as $row) {
        $r = $upsert($row);
        if (isset($stat[$r])) {
            $stat[$r]++;
        } else {
            $stat['skipped']++;
        }
    }
}

/** Delta of two stat snapshots (per-district = totals-after minus totals-before). */
function stat_delta(array $before, array $after): array
{
    $out = [];
    foreach ($after as $k => $v) {
        $out[$k] = $v - ($before[$k] ?? 0);
    }
    return $out;
}

foreach (FieldRoutes::districts() as $district) {
    $code = $district['code'];
    if ($only !== '' && $only !== $code) {
        continue;
    }
    try {
        $before = $totals['customers'];
        $rows = FieldRoutes::pullCustomersForDistrict($district);
        sync_rows($rows, [FieldRoutes::class, 'upsertCustomer'], $totals['customers']);
        $s = stat_delta($before, $totals['customers']);
        printf("%-4s | %-10s | fetched=%-5d inserted=%-4d updated=%-4d skipped=%-4d\n",
            strtoupper($code), 'customers', $s['fetched'], $s['inserted'], $s['updated'], $s['skipped']);
    } catch (\Throwable $e) {
        $errors++;
        $totals['customers']['errors']++;
        echo "  !! customers FAIL: " . mb_substr($e->getMessage(), 0, 120) . "\n";
    }

    // Employees
    try {
        $before = $totals['employees'];
        $rows = FieldRoutes::pullEmployeesForDistrict($district);
        sync_rows($rows, [FieldRoutes::class, 'upsertEmployee'], $totals['employees']);
        $s = stat_delta($before, $totals['employees']);
        printf("%-4s | %-10s | fetched=%-5d inserted=%-4d updated=%-4d skipped=%-4d\n",
            strtoupper($code), 'employees', $s['fetched'], $s['inserted'], $s['updated'], $s['skipped']);
    } catch (\Throwable $e) {
        $errors++;
        $totals['employees']['errors']++;
        echo "  !! employees FAIL: " . mb_substr($e->getMessage(), 0, 120) . "\n";
    }
}

$c = $totals['customers'];
$e = $totals['employees'];
printf("\nTOTAL customers: %d fetched, %d inserted, %d updated, %d skipped, %d errors\n",
    $c['fetched'], $c['inserted'], $c['updated'], $c['skipped'], $c['errors']);
printf("TOTAL employees: %d fetched, %d inserted, %d updated, %d skipped, %d errors\n",
    $e['fetched'], $e['inserted'], $e['updated'], $e['skipped'], $e['errors']);

$now = gmdate('Y-m-d H:i:s');
if ($errors === 0) {
    $db->execute("INSERT INTO meta (key, value) VALUES ('fr_last_sync', ?)
                  ON CONFLICT(key) DO UPDATE SET value = excluded.value", [$now]);
    $db->execute("INSERT INTO meta (key, value) VALUES ('fr_last_sync_summary', ?)
                  ON CONFLICT(key) DO UPDATE SET value = excluded.value",
        [json_encode(['customers' => $c, 'employees' => $e])]);
    echo "\nRecorded meta.fr_last_sync = {$now} (UTC)\n";
} else {
    echo "\nSync completed WITH errors. meta.fr_last_sync NOT advanced.\n";
    exit(1);
}

echo "\nLocal cache now: "
    . (int) $db->scalar('SELECT COUNT(*) FROM customers') . " customers, "
    . (int) $db->scalar('SELECT COUNT(*) FROM staff') . " staff\n";
