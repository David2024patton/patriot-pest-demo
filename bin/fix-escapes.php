<?php
/**
 * bin/fix-escapes.php — one-shot data repair.
 *
 * An earlier seed.php stored a few apostrophes as the literal six-character
 * sequence \u2019 (PHP single-quoted strings do not interpret \u escapes), so
 * that raw text leaked onto the live site (e.g. "Here\u2019s"). This script
 * rewrites those stored values to a plain straight apostrophe (house style)
 * using REPLACE(), in place, without touching any other data.
 *
 * Safe to re-run: once no row contains the bad sequence, every UPDATE is a no-op.
 *   php bin/fix-escapes.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();

// Build the search/replace bytes without any source-level escape ambiguity:
//   chr(92) = backslash, so $bad is the literal  \u2019  as it sits in the DB.
//   chr(39) = a straight apostrophe (the site's house style for contractions).
$bad  = chr(92) . 'u2019';
$good = chr(39);

$cols = [
    'pest_photos' => ['description'],
    'posts'       => ['title', 'excerpt', 'body_html'],
];

$total = 0;
foreach ($cols as $table => $fields) {
    foreach ($fields as $col) {
        $sql = "UPDATE $table SET $col = REPLACE($col, ?, ?) WHERE $col LIKE ?";
        $n   = $db->execute($sql, [$bad, $good, '%' . $bad . '%']);
        if ($n > 0) {
            echo str_pad($table . '.' . $col, 28) . " fixed $n row(s)\n";
        }
        $total += $n;
    }
}

echo $total === 0 ? "No \\u2019 sequences found — data already clean.\n" : "Done. $total column-value(s) repaired.\n";
