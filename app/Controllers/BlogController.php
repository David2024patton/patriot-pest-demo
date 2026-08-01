<?php
/**
 * BlogController - the blog, rendered through ONE unified template.
 *
 * Posts live in the `posts` table and are managed from the admin CMS. Every
 * post uses the same template (pages/blog-post) so any selected pest photo
 * gets the identical tactical treatment as the rest of the site. Only
 * published posts are public; drafts/scheduled are visible only in the CMS.
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\Config;
use PPC\Core\Database;
use PPC\Core\Router;
use PPC\Core\View;

class BlogController extends PageController
{
    /** Blog index: all published posts, newest first, with their photo. */
    public function index(): void
    {
        $db    = Database::instance();
        $posts = $db->fetchAll(
            "SELECT p.id, p.slug, p.title, p.excerpt, p.season, p.pest_category, p.published_at,
                    pp.filename AS photo, pp.name AS pest_name
             FROM posts p
             LEFT JOIN pest_photos pp ON pp.id = p.pest_photo_id
             WHERE p.status = 'published'
             ORDER BY p.published_at DESC"
        );

        echo View::page('pages/blog-index', ['posts' => $posts], $this->meta(
            'Pest Control Blog & Tips - Seasonal Guides | Patriot Pest Control',
            'Expert pest control tips, seasonal guides, and identification help for WA, ID, OR, AZ. Written by licensed technicians.',
            '/blogs',
            ['crumb' => [['Home', '/'], ['Blog', '/blogs']]]
        ));
    }

    /** A single post through the unified template. */
    public function show(string $slug): void
    {
        $db   = Database::instance();
        $post = $db->fetch(
            "SELECT p.*, pp.filename AS photo, pp.name AS pest_name, pp.slug AS pest_slug
             FROM posts p
             LEFT JOIN pest_photos pp ON pp.id = p.pest_photo_id
             WHERE p.slug = ? AND p.status = 'published'",
            [$slug]
        );
        if ($post === null) {
            Router::notFound();
        }

        // Count the view (fire-and-forget; never block the render).
        try {
            $db->execute('UPDATE posts SET views = views + 1 WHERE id = ?', [$post['id']]);
        } catch (\Throwable $e) {
            // non-fatal
        }

        // Related posts in the same season or pest category.
        $related = $db->fetchAll(
            "SELECT slug, title, excerpt FROM posts
             WHERE status = 'published' AND id != ? AND (season = ? OR pest_category = ?)
             ORDER BY published_at DESC LIMIT 3",
            [$post['id'], $post['season'], $post['pest_category']]
        );

        $meta = $this->meta(
            $post['title'] . ' | Patriot Pest Control Blog',
            $post['excerpt'] ?? '',
            "/blogs/{$post['slug']}",
            [
                'crumb'  => [['Home', '/'], ['Blog', '/blogs'], [$post['title'], "/blogs/{$post['slug']}"]],
                'jsonld' => [$this->ldBusiness(), $this->ldArticle($post)],
            ]
        );

        echo View::page('pages/blog-post', ['post' => $post, 'related' => $related], $meta);
    }

    /** Article JSON-LD with accurate dateModified (freshness signal AI uses). */
    private function ldArticle(array $post): array
    {
        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'Article',
            'headline'      => $post['title'],
            'description'   => $post['excerpt'] ?? '',
            'author'        => ['@type' => 'Organization', 'name' => 'Patriot Pest Control'],
            'publisher'     => ['@id' => 'https://patriotpest.pro/#business'],
            'datePublished' => $post['published_at'] ?? '',
            'dateModified'  => $post['date_modified'] ?? $post['updated_at'] ?? '',
            'mainEntityOfPage' => 'https://patriotpest.pro/blogs/' . $post['slug'],
        ];
    }

    /** RSS 2.0 feed: every published post, newest first, stable GUIDs. */
    public function rss(): void
    {
        $db    = Database::instance();
        $posts = $db->fetchAll(
            "SELECT slug, title, excerpt, published_at
             FROM posts
             WHERE status = 'published'
             ORDER BY published_at DESC"
        );

        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo View::render('feeds/blog-rss', [
            'posts' => $posts,
            'base'  => (string) (Config::get('APP_URL', 'https://patriotpest.pro') ?? 'https://patriotpest.pro'),
        ]);
    }
}
