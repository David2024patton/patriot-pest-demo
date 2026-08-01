<?php
/**
 * bin/fix-flea-tick-split.php - split the combined "Fleas & Ticks" library entry
 * into two distinct pests (Fleas + Ticks), each with its own photo.
 *
 * The old single asset was a portrait SEM of a flea only (no tick), which on a
 * landscape card cropped to "a leg". We now have a landscape flea crop
 * (fleas.jpg) and a clear adult deer-tick photo (ticks.jpg).
 *
 * Idempotent: re-running is safe. The existing row keeps its id (renamed to
 * fleas) so any post that referenced it still resolves; ticks is inserted fresh.
 *
 * Usage: php bin/fix-flea-tick-split.php
 */
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();

$old = $db->fetch("SELECT * FROM pest_photos WHERE slug = 'fleas-ticks'");
$sortFleas = $old ? (int) $old['sort_order'] : 0;
$sortTicks = $sortFleas + 1;

$fleasDesc = 'Fleas ride in on pets and breed fast in carpet, bedding and baseboards. We treat the pet zone, the home and the yard perimeter with pet-safe products to break the life cycle at every stage.';
$ticksDesc = 'Ticks wait in grass, brush and leaf litter and can carry Lyme disease and Rocky Mountain spotted fever. Yard and perimeter treatments plus pet protection keep your family and animals safe across WA, ID, OR and AZ.';

$db->begin();
try {
    if ($old) {
        // Rename the combined row -> Fleas (keep its id + sort slot).
        $db->execute(
            "UPDATE pest_photos SET slug = 'fleas', name = 'Fleas',
                scientific_name = 'Ctenocephalides spp.', filename = 'fleas.jpg',
                category = 'insect', threat_level = 66, description = ?
              WHERE slug = 'fleas-ticks'",
            [$fleasDesc]
        );
        echo "UPDATED fleas-ticks -> fleas (id {$old['id']})\n";
    } else {
        $db->execute(
            "INSERT OR IGNORE INTO pest_photos (slug, name, scientific_name, filename, category, threat_level, sort_order, description)
             VALUES ('fleas','Fleas','Ctenocephalides spp.','fleas.jpg','insect',66,?,?)",
            [$sortFleas, $fleasDesc]
        );
        echo "INSERTED fleas (no combined row existed)\n";
    }

    // Ticks as its own entry, slotted right after fleas.
    $have = $db->fetch("SELECT id FROM pest_photos WHERE slug = 'ticks'");
    if ($have) {
        $db->execute(
            "UPDATE pest_photos SET name = 'Ticks', scientific_name = 'Ixodes / Dermacentor spp.',
                filename = 'ticks.jpg', category = 'insect', threat_level = 64,
                sort_order = ?, description = ? WHERE slug = 'ticks'",
            [$sortTicks, $ticksDesc]
        );
        echo "UPDATED ticks (id {$have['id']})\n";
    } else {
        $db->execute(
            "INSERT INTO pest_photos (slug, name, scientific_name, filename, category, threat_level, sort_order, description)
             VALUES ('ticks','Ticks','Ixodes / Dermacentor spp.','ticks.jpg','insect',64,?,?)",
            [$sortTicks, $ticksDesc]
        );
        echo "INSERTED ticks\n";
    }

    // Nudge any rows that sat after the old slot so ordering stays clean.
    $db->execute(
        "UPDATE pest_photos SET sort_order = sort_order + 1
         WHERE slug NOT IN ('fleas','ticks') AND sort_order >= ?",
        [$sortTicks]
    );

    $db->commit();
} catch (\Throwable $e) {
    $db->rollback();
    echo "ROLLBACK: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n--- pest_photos now ---\n";
$rows = $db->fetchAll("SELECT slug, name, filename, threat_level, sort_order FROM pest_photos ORDER BY sort_order, name");
foreach ($rows as $r) {
    printf("%-16s | %-12s | %-18s | thr %3s | sort %2d\n", $r['slug'], $r['name'], $r['filename'], $r['threat_level'], $r['sort_order']);
}
echo "\nTotal: " . count($rows) . "\n";
