<?php
/**
 * admin/index.php — CMS home. Vars: $stats (posts/published/photos/blocks), $flash.
 */
$stats = $data['stats'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app"><div class="wrap">
  <?= $view->raw(\PPC\Core\View::render('admin/_nav', ['active' => 'index', 'flash' => $flash])) ?>

  <div class="stat-cards">
    <div class="stat-card"><div class="v"><?= (int)($stats['posts'] ?? 0) ?></div><div class="k">Total Posts</div></div>
    <div class="stat-card"><div class="v"><?= (int)($stats['published'] ?? 0) ?></div><div class="k">Published</div></div>
    <div class="stat-card"><div class="v"><?= (int)($stats['photos'] ?? 0) ?></div><div class="k">Pest Photos</div></div>
    <div class="stat-card"><div class="v"><?= (int)($stats['blocks'] ?? 0) ?></div><div class="k">Content Blocks</div></div>
  </div>

  <div class="tile-grid">
    <a class="tile" href="/admin/posts"><div class="ico">📝</div><h3>Blog Posts</h3><p>List, create, and edit field reports. All posts share one template.</p></a>
    <a class="tile" href="/admin/posts/new"><div class="ico">＋</div><h3>New Post</h3><p>Write a new post and pick a pest photo from the library.</p></a>
    <a class="tile" href="/admin/media"><div class="ico">🖼️</div><h3>Media Library</h3><p>The pest photo library used across posts and pages.</p></a>
    <a class="tile" href="/admin/content"><div class="ico">🧩</div><h3>Content Blocks</h3><p>Edit per-page sections without touching code.</p></a>
  </div>
</div></div>
