<?php
/**
 * bin/fr-status-raw.php — TRUE status distribution straight from the FieldRoutes
 * API, bypassing the local normalize() mapping (which misclassifies the
 * '0000-00-00 00:00:00' sentinel as a real cancellation date).
 *
 * Counts raw statusText per district and flags the zero-date sentinel bug.
 * Read-only.   php bin/fr-status-raw.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use PPC\Core\Config;

function frGet(string $url): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'PatriotPest/1.0 (+fr-status-raw)',
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }
    $d = json_decode((string) $body, true);
    return is_array($d) ? $d : null;
}

$base = rtrim((string) Config::get('FIELDROUTES_BASE_URL', ''), '/');
$districts = [
    'wa' => ['key' => Config::get('FIELDROUTES_WA_KEY'), 'token' => Config::get('FIELDROUTES_WA_TOKEN')],
    'az' => ['key' => Config::get('FIELDROUTES_AZ_KEY'), 'token' => Config::get('FIELDROUTES_AZ_TOKEN')],
];

$grand = ['statusText' => [], 'sentinel_hits' => 0, 'active_with_real_cancel' => 0, 'total' => 0];
foreach ($districts as $code => $d) {
    $q = http_build_query(['authenticationKey' => $d['key'], 'authenticationToken' => $d['token']]);
    $search = frGet($base . '/api/customer/search?' . $q);
    $ids = array_map('strval', (array) ($search['customerIDs'] ?? []));
    $n = count($ids);
    echo "=== DISTRICT " . strtoupper($code) . ": {$n} customers ===\n";
    if (!$ids) { echo "  (fetch failed)\n"; continue; }

    $st = [];
    $sentinel = 0;
    $activeRealCancel = 0;
    $total = 0;
    foreach (array_chunk($ids, 200) as $chunk) {
        $get = frGet($base . '/api/customer/get?' . $q . '&customerIDs=' . implode(',', $chunk));
        foreach (($get['customers'] ?? []) as $c) {
            $total++;
            $raw  = strtolower(trim((string) ($c['statusText'] ?? '')));
            $dc   = trim((string) ($c['dateCancelled'] ?? ''));
            $zero = ($dc === '' || str_starts_with($dc, '0000-00-00'));
            if ($zero) { $sentinel++; } else { $grand['sentinel_hits'] = ($grand['sentinel_hits'] ?? 0) + 1; }
            if (!$zero && $raw === 'active') { $activeRealCancel++; }
            $label = $raw === '' ? '(empty)' : $raw;
            $st[$label] = ($st[$label] ?? 0) + 1;
            $grand['statusText'][$label] = ($grand['statusText'][$label] ?? 0) + 1;
        }
    }
    foreach ($st as $label => $c) {
        printf("  %-12s %5d  (%s)\n", $label, $c, number_format($c / max(1, $total) * 100, 1) . '%');
    }
    printf("  rows with zero-date sentinel: %d of %d\n", $sentinel, $total);
    if ($activeRealCancel) {
        printf("  !! %d rows statusText=active BUT real dateCancelled (data conflict)\n", $activeRealCancel);
    }
    $grand['total'] += $total;
    echo "\n";
}

echo "=== COMBINED (both districts) ===\n";
foreach ($grand['statusText'] as $label => $c) {
    printf("  %-12s %5d  (%s)\n", $label, $c, number_format($c / max(1, $grand['total']) * 100, 1) . '%');
}
printf("  TOTAL %d | rows with real (non-sentinel) dateCancelled: %d\n", $grand['total'], $grand['sentinel_hits']);
echo "Done.\n";
