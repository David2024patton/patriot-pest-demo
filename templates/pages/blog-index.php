<?php
/**
 * pages/blog-index.php — the blog landing page.
 *
 * Lists every published post (newest first) with its pest photo under the same
 * tactical treatment used site-wide. Vars: $posts (array of post rows w/ photo).
 */
$posts = $data['posts'] ?? [];
?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">FIELD INTEL // THE BLOG</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Pest Control <em>Intel &amp; Guides.</em></h1>
    <p class="lead">Identification help, seasonal guides, and prevention tips from our licensed technicians. Written for homeowners across Washington, Idaho, Oregon, and Arizona.</p>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <?php if (!$posts): ?>
      <p class="empty">New field reports are being prepared. Check back soon.</p>
    <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($posts as $p): ?>
      <a class="card blog-card" href="/blogs/<?= $view->e($p['slug']) ?>" style="text-decoration:none;color:inherit;display:flex;flex-direction:column">
        <?php if (!empty($p['photo'])): ?>
          <span class="pphoto"><img src="<?= $view->asset('img/pests/' . $p['photo']) ?>" alt="<?= $view->e($p['pest_name'] ?? $p['title']) ?>" loading="lazy"><i class="ret" aria-hidden="true"></i></span>
        <?php endif; ?>
        <div class="post-meta" style="margin-top:.8rem">
          <?php if (!empty($p['season'])): ?><span><?= $view->e(ucfirst($p['season'])) ?></span><?php endif; ?>
          <?php if (!empty($p['published_at'])): ?><span><?= $view->e(date('M j, Y', strtotime($p['published_at']))) ?></span><?php endif; ?>
        </div>
        <h3 style="font-family:var(--display);color:var(--cream);font-size:1.1rem;line-height:1.25;margin:.2rem 0 .5rem"><?= $view->e($p['title']) ?></h3>
        <p style="color:var(--khaki);font-size:.9rem;line-height:1.6;flex:1"><?= $view->e($p['excerpt']) ?></p>
        <span class="more" style="color:var(--orange);font-family:var(--mono);font-size:.8rem;margin-top:.8rem">READ REPORT ▸</span>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
