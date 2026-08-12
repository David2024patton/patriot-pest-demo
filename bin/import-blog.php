<?php
/**
 * bin/import-blog.php - migrate the 81-article blog corpus from the main
 * site (patriotpest.pro) into the new site's `posts` table.
 *
 * Safe to re-run (idempotent, seed.php conventions): a slug that already
 * exists is skipped, nothing is deleted or overwritten. The corpus JSON
 * lives next to this script so the container can run it at every boot.
 *
 *   php bin/import-blog.php
 *
 * Migrated fields:
 *   - slug, title, excerpt: preserved exactly from the main site index.
 *   - published_at / date_modified: original dates from article JSON-LD.
 *   - pest_photo_id: mapped from the article pest tag to the 25-photo
 *     library where a tag matches; NULL when it is generic (General,
 *     Other insects, Wildlife).
 *   - season: derived from the original publish month.
 *   - body_html: generated from the title/excerpt in the seed's short
 *     paragraph + list style. The main site's article pages were empty
 *     shells, so there was no body to copy; the new site is richer.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;

$db = Database::instance();

$corpusFile = __DIR__ . '/data/blog-corpus.json';
if (!is_readable($corpusFile)) {
    fwrite(STDERR, "FATAL: corpus file missing: $corpusFile\n");
    exit(1);
}
$corpus = json_decode((string) file_get_contents($corpusFile), true);
if (!is_array($corpus) || count($corpus) === 0) {
    fwrite(STDERR, "FATAL: corpus file did not decode to a non-empty array\n");
    exit(1);
}

/* Pest tag (main site) -> photo-library slug (new site). */
$pestSlug = [
    'Ants'          => 'ants',
    'Bed bugs'      => 'bed-bugs',
    'Cockroaches'   => 'cockroaches',
    'Fleas ticks'   => 'fleas-ticks',
    'Flies'         => 'fruit-flies',
    'Mosquitoes'    => 'mosquitoes',
    'Rodents'       => 'rodents',
    'Spiders'       => 'spiders',
    'Termites'      => 'termites',
    'Wasps bees'    => 'wasps',
];

/* Month (1-12) -> season, matching the seed vocabulary. */
function seasonFor(int $month): string
{
    if ($month >= 3 && $month <= 5)  { return 'spring'; }
    if ($month >= 6 && $month <= 8)  { return 'summer'; }
    if ($month >= 9 && $month <= 11) { return 'fall';   }
    return 'winter';
}

/**
 * Build a short body in the seed's style from the article's own fields.
 * The main site article pages had no body content, so this generates
 * honest, on-brand copy from the real title/excerpt/category.
 */
function bodyFor(array $p): string
{
    $excerpt  = trim((string) ($p['excerpt'] ?? ''));
    $title    = trim((string) ($p['title'] ?? ''));
    $category = trim((string) ($p['category'] ?? 'Education'));

    $lead = $excerpt !== ''
        ? $excerpt
        : 'A practical guide to keeping your property protected.';

    $heading = match ($category) {
        'Prevention'     => 'Prevention',
        'Identification' => 'Identification',
        default          => 'What to know',
    };

    $bullets = match ($category) {
        'Prevention' => [
            'Inspect your property for entry points and seal gaps around doors, windows, and the foundation.',
            'Remove food, water, and shelter sources that attract pests in the first place.',
            'Schedule regular seasonal treatments before pest pressure peaks.',
        ],
        'Identification' => [
            'Look for droppings, damage, or activity patterns to confirm what you are dealing with.',
            'Take note of where and when you see activity; the location points to the source.',
            'A professional inspection identifies the species and the right treatment plan.',
        ],
        default => [
            'Understanding the pest life cycle explains why activity spikes at certain times of year.',
            'Knowing what attracts pests to a property makes prevention far more effective.',
            'Professional treatment removes the problem at the source instead of just the symptoms.',
        ],
    };

    $html  = '<p>' . htmlspecialchars($lead, ENT_QUOTES, 'UTF-8') . '</p>';
    $html .= '<h3>' . $heading . '</h3>';
    $html .= '<ul>';
    foreach ($bullets as $b) {
        $html .= '<li>' . htmlspecialchars($b, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    $html .= '</ul>';
    $html .= '<p>Contact Patriot Pest Control for a free inspection and a treatment plan built around your property.</p>';

    return $html;
}

/* Warm the photo-slug lookup once. */
$photoBySlug = [];
foreach ($db->fetchAll('SELECT id, slug FROM pest_photos') as $row) {
    $photoBySlug[$row['slug']] = (int) $row['id'];
}

$now     = date('Y-m-d H:i:s');
$inserted = 0;
$skipped  = 0;
$noPhoto  = 0;
$errors   = [];

foreach ($corpus as $i => $p) {
    $slug = trim((string) ($p['slug'] ?? ''));
    if ($slug === '') {
        $errors[] = "entry #$i: empty slug";
        continue;
    }
    if ($db->fetch('SELECT id FROM posts WHERE slug = ?', [$slug])) {
        $skipped++;
        continue;
    }

    $title   = trim((string) ($p['title'] ?? $slug));
    $excerpt = trim((string) ($p['excerpt'] ?? ''));
    $tag     = trim((string) ($p['pest_label'] ?? 'General'));

    /* Published date: preserve the original from the article JSON-LD. */
    $publishedTs = strtotime((string) ($p['date_published'] ?? '')) ?: time();
    $modifiedTs  = strtotime((string) ($p['date_modified'] ?? '')) ?: $publishedTs;
    $published   = date('Y-m-d H:i:s', $publishedTs);
    $modified    = date('Y-m-d H:i:s', $modifiedTs);

    /* Photo mapping: matched tag -> photo id; generic tags stay NULL. */
    $photoId = null;
    $photoSlug = $pestSlug[$tag] ?? null;
    if ($photoSlug !== null && isset($photoBySlug[$photoSlug])) {
        $photoId = $photoBySlug[$photoSlug];
    } else {
        $noPhoto++;
    }

    /* pest_category: photo slug when mapped, otherwise a label slug. */
    $catSlug = $photoSlug ?? strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $tag));
    $catSlug = trim($catSlug, '-');

    try {
        $db->insert('posts', [
            'slug'           => $slug,
            'title'          => $title,
            'excerpt'        => $excerpt,
            'body_html'      => bodyFor($p),
            'pest_photo_id'  => $photoId,
            'season'         => seasonFor((int) date('n', $publishedTs)),
            'pest_category'  => $catSlug,
            'status'         => 'published',
            'author'         => 'Skyler Rose',
            'published_at'   => $published,
            'date_modified'  => $modified,
            'created_at'     => $now,
            'updated_at'     => $now,
        ]);
        $inserted++;
    } catch (\Throwable $e) {
        $errors[] = "{$slug}: " . $e->getMessage();
    }
}

printf("Blog import complete.\n");
printf("  corpus entries : %d\n", count($corpus));
printf("  inserted       : %d\n", $inserted);
printf("  skipped (exist): %d\n", $skipped);
printf("  generic (NULL) : %d\n", $noPhoto);
printf("  total posts    : %d\n", (int) $db->scalar('SELECT COUNT(*) FROM posts'));
if ($errors) {
    printf("  errors         : %d\n", count($errors));
    foreach ($errors as $e) { printf("    - %s\n", $e); }
    exit(2);
}
echo "  OK: no errors\n";
