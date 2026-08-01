<?php
/**
 * PestController — individual pest "threat file" pages.
 *
 * Fully DB-driven off the pest_photos library: any pest added to the library
 * automatically gets a page at /pest/{slug}. Uses the unified pest template so
 * every page has the same tactical photo treatment, signs, treatment, and
 * prevention structure (good for SEO: consistent, extractable answers).
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\Database;
use PPC\Core\Router;
use PPC\Core\View;

class PestController extends PageController
{
    /**
     * Render a pest page by slug.
     */
    public function show(string $slug): void
    {
        $db   = Database::instance();
        $pest = $db->fetch('SELECT * FROM pest_photos WHERE slug = ?', [$slug]);
        if ($pest === null) {
            Router::notFound();
        }

        // Related pests (same category first, then others) for cross-linking.
        $related = $db->fetchAll(
            'SELECT slug, name, filename FROM pest_photos WHERE slug != ? ORDER BY (category = ?) DESC, name LIMIT 6',
            [$slug, $pest['category']]
        );

        $meta = $this->meta(
            "{$pest['name']} Control Across 4 States | Patriot Pest Control",
            ($pest['description'] ?? "Professional {$pest['name']} control") . ' Veteran-owned, eco-friendly, 90-day warranty across WA, ID, OR, AZ.',
            "/pest/{$pest['slug']}",
            [
                'crumb'  => [['Home', '/'], ['Services', '/services'], [$pest['name'], "/pest/{$pest['slug']}"]],
                'jsonld' => [$this->ldBusiness(), $this->ldService($pest)],
            ]
        );

        echo View::page('pages/pest', [
            'pest'    => $pest,
            'related' => $related,
        ], $meta);
    }

    /** Service JSON-LD for this specific pest (areaServed + provider). */
    private function ldService(array $pest): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => 'Service',
            'name'        => "{$pest['name']} Control",
            'serviceType' => "{$pest['name']} Control",
            'description' => $pest['description'] ?? '',
            'provider'    => ['@id' => 'https://patriotpest.pro/#business'],
            'areaServed'  => [
                ['@type' => 'State', 'name' => 'Washington'],
                ['@type' => 'State', 'name' => 'Idaho'],
                ['@type' => 'State', 'name' => 'Oregon'],
                ['@type' => 'State', 'name' => 'Arizona'],
            ],
        ];
    }
}
