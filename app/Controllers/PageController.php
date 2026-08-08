<?php
/**
 * PageController — public marketing pages.
 *
 * Each action assembles the page's data (from the DB where content is
 * editable, e.g. the pest threat board) plus its SEO meta, then renders
 * through the shared layout. Meta follows the GEO/AEO playbook: specific
 * PestControl schema, geo/areaServed/sameAs, stable @id, answer-first copy.
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\Database;
use PPC\Core\View;
use PPC\Core\Csrf;
use PPC\Core\Session;
use PPC\Core\Logger;
use PPC\Core\Validator;

class PageController
{
    /**
     * Shared SEO/meta builder. Keeps every page consistent and gives AI
     * crawlers a single, stable entity (@id) plus rich local-business data.
     */
    protected function meta(string $title, string $description, string $path, array $extra = []): array
    {
        $site = 'https://patriotpest.pro'; // canonical base (production)
        return array_merge([
            'title'       => $title,
            'description' => $description,
            'keywords'    => 'pest control, Spokane, Washington, Idaho, Oregon, Arizona, veteran-owned',
            'canonical'   => $site . $path,
            'ogImage'     => $site . '/assets/img/og.png',
            'robots'      => 'index, follow, max-snippet:-1, max-image-preview:large',
            'jsonld'      => [$this->ldBusiness()],
            'crumb'       => [['Home', '/']],
        ], $extra);
    }

    /**
     * LocalBusiness JSON-LD (PestControl subtype) — the stable entity block
     * emitted on every page. geo + areaServed + sameAs are the fields AI
     * answer engines weight most for local businesses.
     */
    protected function ldBusiness(): array
    {
        return [
            '@context'    => 'https://schema.org',
            '@type'       => ['LocalBusiness', 'HomeAndConstructionBusiness'],
            '@id'         => 'https://patriotpest.pro/#business',
            'name'        => 'Patriot Pest Control',
            'legalName'   => 'Patriot Pest Control LLC',
            'url'         => 'https://patriotpest.pro',
            'telephone'   => '+15094715767',
            'email'       => 'info@patriotpest.pro',
            'image'       => 'https://patriotpest.pro/assets/img/og.png',
            'logo'        => 'https://patriotpest.pro/assets/img/og.png',
            'description' => 'Veteran-owned pest control serving Washington, Idaho, Oregon & Arizona. Same-day service, eco-friendly family & pet safe treatments, 90-day warranty.',
            'priceRange'  => '$$',
            'address'     => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Spokane',
                'addressRegion'   => 'WA',
                'postalCode'      => '99201',
                'addressCountry'  => 'US',
            ],
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 47.6588, 'longitude' => -117.426],
            'areaServed' => [
                ['@type' => 'State', 'name' => 'Washington'],
                ['@type' => 'State', 'name' => 'Idaho'],
                ['@type' => 'State', 'name' => 'Oregon'],
                ['@type' => 'State', 'name' => 'Arizona'],
            ],
            'openingHoursSpecification' => [
                ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday'], 'opens' => '09:00', 'closes' => '17:00'],
                ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => ['Saturday','Sunday'], 'opens' => '10:00', 'closes' => '16:00'],
            ],
            'founder' => ['@type' => 'Person', 'name' => 'Skyler Rose', 'jobTitle' => 'Founder & U.S. Military Veteran'],
            'sameAs' => [
                'https://www.facebook.com/pestmgtpros',
                'https://www.instagram.com/patriot_pest/',
            ],
        ];
    }

    /**
     * Homepage — the flagship. The threat board is DB-driven: it renders every
     * pest in the photo library (real photos, tactical treatment), not a
     * hardcoded nine.
     */
    public function home(): void
    {
        $db    = Database::instance();
        // All pests in the library, ordered for display. The board scrolls,
        // so showing the full catalog directly serves "show every pest we treat."
        $pests = $db->fetchAll('SELECT slug, name, scientific_name, filename, description, threat_level, category FROM pest_photos ORDER BY sort_order, name');

        $meta = $this->meta(
            'Pest Control in Washington, Idaho, Oregon & Arizona | Patriot Pest Control',
            'Veteran-owned pest control across WA, ID, OR & AZ. Same-day service, eco-friendly family & pet safe treatments, 90-day warranty. Ants, spiders, rodents, bed bugs, termites & more.',
            '/',
            ['crumb' => [['Home', '/']]]
        );

        echo View::page('pages/home', [
            'pests'      => $pests,
            'pestCount'  => count($pests),
        ], $meta);
    }

    /** About page. */
    public function about(): void
    {
        echo View::page('pages/about', [], $this->meta(
            'About Us: Veteran-Owned | Patriot Pest Control',
            'Founded by U.S. Military Veteran Skyler Rose. Military discipline, integrity, and eco-friendly pest control across Washington, Idaho, Oregon & Arizona.',
            '/about'
        ));
    }

    /** Services page. */
    public function services(): void
    {
        $db    = Database::instance();
        $pests = $db->fetchAll('SELECT slug, name, filename, category FROM pest_photos ORDER BY name');
        echo View::page('pages/services', ['pests' => $pests], $this->meta(
            'Pest Control Services: Every Pest We Treat | Patriot Pest Control',
            'Complete pest control: ants, spiders, rodents, bed bugs, termites, mosquitoes, wasps, roaches, scorpions, wildlife & more across WA, ID, OR, AZ.',
            '/services'
        ));
    }

    /** Pricing page. */
    public function prices(): void
    {
        echo View::page('pages/prices', [], $this->meta(
            'Pricing & Plans: Transparent Online Pricing | Patriot Pest Control',
            'Transparent pest control pricing. One-time, seasonal, year-round & premium plans. Free quotes, no hidden fees, 90-day warranty.',
            '/prices'
        ));
    }

    /** Service areas overview. */
    public function areas(): void
    {
        echo View::page('pages/areas', ['states' => $this->states()], $this->meta(
            'Service Areas: WA, ID, OR & AZ | Patriot Pest Control',
            'Pest control service areas across Spokane WA, Coeur d\'Alene ID, Hermiston OR, Phoenix AZ and surrounding communities.',
            '/service-areas'
        ));
    }

    /** A single service-area city page. */
    public function areaDetail(string $slug): void
    {
        // Look up the city from the known list; 404 if unknown.
        $city = $this->findCity($slug);
        if ($city === null) {
            \PPC\Core\Router::notFound();
        }
        echo View::page('pages/area-detail', ['city' => $city], $this->meta(
            "Pest Control in {$city['city']}, {$city['state']} | Patriot Pest Control",
            "Same-day pest control in {$city['city']}, {$city['stateName']}. Eco-friendly treatments, 90-day warranty, veteran-owned.",
            "/areas/$slug"
        ));
    }

    /** FAQs page. */
    public function faqs(): void
    {
        echo View::page('pages/faqs', [], $this->meta(
            'Pest Control FAQs | Patriot Pest Control',
            'Answers to common pest control questions: safety, pricing, guarantees, preparation, and what to expect.',
            '/faqs'
        ));
    }

    /** Contact page. */
    public function contact(): void
    {
        echo View::page('pages/contact', [
            'success' => Session::pullFlash('success'),
            'errors'  => Session::pullFlash('errors'),
            'old'     => Session::pullFlash('old', []),
            'analytics_event' => Session::pullFlash('analytics_event'),
        ], $this->meta(
            'Contact Us: Free Quotes & Same-Day Service | Patriot Pest Control',
            'Call (509) 471-5767 (WA/ID/OR) or (602) 755-8414 (AZ). Free quotes, same-day pest control service, 24/7 line.',
            '/contact'
        ));
    }

    /** Contact form submission (CSRF-protected, validated, logged). */
    public function contactSubmit(): void
    {
        Csrf::verifyOrDie();
        $errors = Validator::make($_POST, [
            'name'    => ['required', 'max:120'],
            'email'   => ['required', 'email', 'max:254'],
            'phone'   => ['phone'],
            'message' => ['required', 'max:5000'],
        ]);
        if ($errors) {
            Session::flash('errors', $errors);
            Session::flash('old', $_POST);
            header('Location: /contact');
            exit;
        }
        // TODO (integrations phase): persist + notify + auto-reply via mailer.
        Logger::info('Contact form submitted', ['email' => $_POST['email'] ?? '']);
        Session::flash('success', 'Thanks. We received your message and will respond within one business day.');
        Session::flash('analytics_event', 'generate_lead');
        header('Location: /contact');
        exit;
    }

    public function referral(): void
    {
        echo View::page('pages/referral', [], $this->meta('Referral Program: Earn $25 | Patriot Pest Control', 'Refer a neighbor, both get $25. Patriot Pest Control referral program.', '/referral'));
    }

    public function socials(): void
    {
        echo View::page('pages/socials', [], $this->meta('Social Media | Patriot Pest Control', 'Follow Patriot Pest Control on Facebook and Instagram.', '/socials'));
    }

    public function help(): void
    {
        echo View::page('pages/help', [], $this->meta('Help Center | Patriot Pest Control', 'Support, accessibility, and account help for Patriot Pest Control customers.', '/help'));
    }

    public function links(): void
    {
        echo View::page('pages/links', [], $this->meta('All Links | Patriot Pest Control', 'Complete directory of Patriot Pest Control pages, services, and resources.', '/links'));
    }

    public function sitemap(): void
    {
        echo View::page('pages/sitemap', ['states' => $this->states()], $this->meta('Sitemap | Patriot Pest Control', 'Every page on the Patriot Pest Control website.', '/sitemap'));
    }

    public function privacy(): void
    {
        echo View::page('pages/legal', ['kind' => 'privacy'], $this->meta('Privacy Policy | Patriot Pest Control', 'How Patriot Pest Control collects, uses, and protects your information.', '/privacy-policy'));
    }

    public function terms(): void
    {
        echo View::page('pages/legal', ['kind' => 'terms'], $this->meta('Terms of Use | Patriot Pest Control', 'Terms of use for the Patriot Pest Control website and services.', '/terms-of-use'));
    }

    /** The four-state coverage map data. */
    protected function states(): array
    {
        return [
            'WA' => ['name' => 'Washington', 'cities' => ['Spokane','Spokane Valley','Cheney','Liberty Lake','Airway Heights','Medical Lake','Deer Park','Mead']],
            'ID' => ['name' => 'Idaho', 'cities' => ["Coeur d'Alene",'Post Falls','Hayden','Rathdrum']],
            'OR' => ['name' => 'Oregon', 'cities' => ['Hermiston','Milton-Freewater']],
            'AZ' => ['name' => 'Arizona', 'cities' => ['Phoenix']],
        ];
    }

    /** Find a city by slug across all states; null if not served. */
    protected function findCity(string $slug): ?array
    {
        foreach ($this->states() as $st => $s) {
            foreach ($s['cities'] as $city) {
                if ($this->slugify($city) === $slug) {
                    return ['city' => $city, 'state' => $st, 'stateName' => $s['name'], 'slug' => $slug];
                }
            }
        }
        return null;
    }

    protected function slugify(string $s): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
    }
}
