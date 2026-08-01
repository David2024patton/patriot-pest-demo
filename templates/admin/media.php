<?php
/**
 * admin/media.php — the pest photo library. Vars: $photos, $flash.
 * This library is the single source of pest imagery used across posts and pages.
 */
$photos = $data['photos'] ?? [];
$flash  = $data['flash'] ?? null;
?>
<div class="app"><div class="wrap">
  <?= $view->raw(\PPC\Core\View::render('admin/_nav', ['active' => 'media', 'flash' => $flash])) ?>

  <div class="app-head">
    <div><h1 style="font-size:1.4rem">Media Library</h1><div class="sub"><?= count($photos) ?> pest photo<?= count($photos) !== 1 ? 's' : '' ?> · used across the threat board, pest pages &amp; blog</div></div>
  </div>

  <div class="panel"><p class="muted">Photos are seeded from <span class="mono">public/assets/img/pests/</span> and tracked in the <span class="mono">pest_photos</span> table. Every pest here automatically gets a page at <span class="mono">/pest/&lt;slug&gt;</span> and is available in the post editor's photo picker.</p></div>

  <div class="media-grid">
    <?php foreach ($photos as $ph): ?>
    <div class="media-item">
      <div class="thumb"><img src="<?= $view->asset('img/pests/' . $ph['filename']) ?>" alt="<?= $view->e($ph['name']) ?>" loading="lazy"></div>
      <div class="meta">
        <div class="n"><?= $view->e($ph['name']) ?></div>
        <div class="s"><?= $view->e($ph['scientific_name'] ?? '') ?></div>
        <div class="s" style="margin-top:.2rem"><?= $view->e(ucfirst($ph['category'])) ?> · threat <?= (int)$ph['threat_level'] ?>% · /pest/<?= $view->e($ph['slug']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div></div>
