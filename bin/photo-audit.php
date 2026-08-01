<?php
/**
 * bin/photo-audit.php - READ-ONLY audit of the pest photo library.
 *
 * Prints one line per pest_photos row: slug | name | filename | category |
 * threat | exists | WxH | aspect  so we can see, at a glance, which assets are
 * missing and which have an extreme aspect ratio (a wide/tall source cropped by
 * object-fit:cover is a common cause of "you can only see a leg" framing).
 *
 * Usage: php bin/photo-audit.php
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;

$db   = Database::instance();
$rows = $db->fetchAll('SELECT slug, name, filename, category, threat_level FROM pest_photos ORDER BY sort_order, name');
$dir  = dirname(__DIR__) . '/public/assets/img/pests/';

printf("%-16s | %-22s | %-22s | %-9s | thr | exists | dimensions | aspect\n", 'SLUG', 'NAME', 'FILE', 'CAT');
echo str_repeat('-', 120) . "\n";
foreach ($rows as $r) {
    $file = $dir . $r['filename'];
    $exists = is_file($file);
    $dims = 'N/A';
    $aspect = 'N/A';
    if ($exists) {
        $sz = @getimagesize($file);
        if ($sz) {
            $dims = $sz[0] . 'x' . $sz[1];
            $aspect = $sz[1] ? round($sz[0] / $sz[1], 2) : 'N/A';
        }
    }
    printf("%-16s | %-22s | %-22s | %-9s | %3s | %-6s | %-10s | %s\n",
        $r['slug'],
        mb_substr($r['name'], 0, 22),
        $r['filename'],
        $r['category'],
        (int) $r['threat_level'],
        $exists ? 'YES' : 'NO!!',
        $dims,
        $aspect
    );
}
echo "\nTotal rows: " . count($rows) . "\n";
