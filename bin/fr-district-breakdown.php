<?php
/**
 * bin/fr-district-breakdown.php — pull FRESH data from BOTH FieldRoutes
 * districts (WA + AZ, independent keys) and report the status distribution
 * per district, straight from the API (not the 17-day-old cache).
 *
 * Read-only against the live API. Does NOT write to the local cache (the sync
 * lane belongs to Front One). Answers the 99.7%-cancelled question per district.
 *
 *   php bin/fr-district-breakdown.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use PPC\Integrations\FieldRoutes;

foreach (FieldRoutes::districts() as $district) {
    $code = strtoupper($district['code']);
    echo "=== DISTRICT {$code} ===\n";
    $rows = FieldRoutes::pullCustomersForDistrict($district);
    $n = count($rows);
    echo "  fetched: {$n}\n";
    if (!$n) {
        echo "  (empty or API error; see logs)\n";
        continue;
    }
    $byStatus = [];
    foreach ($rows as $r) {
        $s = (string) ($r['status'] ?? 'unknown');
        $byStatus[$s] = ($byStatus[$s] ?? 0) + 1;
    }
    foreach ($byStatus as $s => $c) {
        printf("  %-10s %5d  (%s of district)\n", $s, $c, number_format($c / $n * 100, 1) . '%');
    }
    $active = (int) ($byStatus['active'] ?? 0);
    printf("  -> active rate: %s\n", number_format($active / $n * 100, 1) . '%');
    echo "\n";
}
echo "Done.\n";
