<?php
/**
 * templates/feeds/blog-rss.php - RSS 2.0 feed for the blog.
 *
 * Rendered via View::render (no page layout). Emits one <item> per
 * published post, newest first, with stable permalink GUIDs so feed
 * readers keep working as posts are added. Content is escaped for XML.
 * Vars: $posts (rows: slug, title, excerpt, published_at), $base (site URL).
 */
$posts = $data['posts'] ?? [];
$base  = $data['base'] ?? 'https://patriotpest.pro';
$esc   = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_XML1 | ENT_QUOTES, 'UTF-8');
?><?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title>Patriot Pest Control Blog</title>
  <link><?= $esc($base) ?>/blogs</link>
  <description>Expert pest control tips, seasonal guides, and identification help from the licensed technicians at Patriot Pest Control.</description>
  <language>en-us</language>
  <lastBuildDate><?= $esc(date('r', time())) ?></lastBuildDate>
  <atom:link href="<?= $esc($base) ?>/blogs/rss.xml" rel="self" type="application/rss+xml"/>
  <?php foreach ($posts as $p): ?>
  <item>
    <title><?= $esc($p['title']) ?></title>
    <link><?= $esc($base) ?>/blogs/<?= $esc($p['slug']) ?></link>
    <guid isPermaLink="true"><?= $esc($base) ?>/blogs/<?= $esc($p['slug']) ?></guid>
    <pubDate><?= $esc(date('r', strtotime((string) $p['published_at']))) ?></pubDate>
    <description><?= $esc($p['excerpt']) ?></description>
  </item>
  <?php endforeach; ?>
</channel>
</rss>
