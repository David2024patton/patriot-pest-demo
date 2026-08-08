<?php
/**
 * bin/migrate-add-source-column.php — add customers.source (seed|fieldroutes|manual)
 * and tag the seed fixtures.
 *
 * Fresh installs get the column from database/schema.sql; this migration brings
 * existing databases (dev + deployed volumes) up to the same shape. Idempotent:
 * safe to re-run any number of times.
 *
 *   php bin/migrate-add-source-column.php
 */
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();
$cols = $db->fetchAll('PRAGMA table_info(customers)');
$has  = false;
foreach ($cols as $c) {
    if (($c['name'] ?? '') === 'source') {
        $has = true;
        break;
    }
}
if (!$has) {
    $db->execute("ALTER TABLE customers ADD COLUMN source TEXT NOT NULL DEFAULT 'fieldroutes'");
    echo "Added customers.source (default 'fieldroutes').\n";
} else {
    echo "customers.source already exists.\n";
}

// Tag the known seed fixtures (bin/seed.php inserts these; fr_id 1001-1003).
$n = $db->execute(
    "UPDATE customers SET source = 'seed'
     WHERE fr_id IN ('1001','1002','1003') AND email LIKE '%@example.com' AND source != 'seed'"
);
echo "Seed fixtures tagged: {$n}\n";

// Anything else that was never FR-synced (no fr_id) is manual/test — tag it so
// aggregates can exclude it too.
$m = $db->execute(
    "UPDATE customers SET source = 'manual'
     WHERE (fr_id IS NULL OR fr_id = '') AND source = 'fieldroutes'"
);
echo "Rows without fr_id retagged 'manual': {$m}\n";

foreach ($db->fetchAll('SELECT source, COUNT(*) c FROM customers GROUP BY source ORDER BY c DESC') as $r) {
    printf("  %-12s %d\n", $r['source'], $r['c']);
}
echo "Done.\n";
