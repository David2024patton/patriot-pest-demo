<?php
/**
 * pages/blog-post.php - the unified single-post template.
 *
 * Every post renders here, so the selected pest photo gets the identical tactical
 * treatment as the rest of the site. body_html was sanitized on save, so it's
 * emitted via raw(). Vars: $post (full row + photo/pest_name/pest_slug), $related.
 */
$post    = $data['post'] ?? [];
$related = $data['related'] ?? [];
?>
<article class="block">
  <div class="wrap" style="max-width:820px">
    <div class="post-meta">
      <?php if (!empty($post['season'])): ?><span><?= $view->e(ucfirst($post['season'])) ?></span><?php endif; ?>
      <?php if (!empty($post['published_at'])): ?><span><?= $view->e(date('F j, Y', strtotime($post['published_at']))) ?></span><?php endif; ?>
      <?php if (!empty($post['author'])): ?><span>By <?= $view->e($post['author']) ?></span><?php endif; ?>
    </div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(1.8rem,5vw,2.8rem);line-height:1.1;margin:.4rem 0 1rem"><?= $view->e($post['title']) ?></h1>

    <?php if (!empty($post['photo'])): ?>
      <span class="pphoto s-lg" style="margin-bottom:1.6rem"><img src="<?= $view->asset('img/pests/' . $post['photo']) ?>" alt="<?= $view->e($post['pest_name'] ?? $post['title']) ?>" loading="eager"><i class="ret" aria-hidden="true"></i></span>
    <?php endif; ?>

    <?php if (!empty($post['excerpt'])): ?>
      <p class="lead" style="margin-bottom:1.6rem"><?= $view->e($post['excerpt']) ?></p>
    <?php endif; ?>

    <div class="prose">
      <?= $view->raw($post['body_html'] ?? '') ?>
    </div>

    <?php if (!empty($post['pest_slug'])): ?>
      <div class="panel" style="margin-top:2rem">
        <strong style="color:var(--cream)">Dealing with <?= $view->e(strtolower($post['pest_name'])) ?>?</strong>
        <span class="muted"> Get the full threat file and a free quote.</span>
        <a href="/pest/<?= $view->e($post['pest_slug']) ?>" style="color:var(--orange)">View <?= $view->e($post['pest_name']) ?> Control ▸</a>
      </div>
    <?php endif; ?>
  </div>
</article>

<?php if ($related): ?>
<section class="block alt">
  <div class="wrap">
    <div class="eyebrow">RELATED INTEL</div>
    <h2 style="font-family:var(--display);color:var(--cream);margin:.4rem 0 1.4rem">Keep <em>reading.</em></h2>
    <div class="grid g3">
      <?php foreach ($related as $r): ?>
      <a class="card" href="/blogs/<?= $view->e($r['slug']) ?>" style="text-decoration:none;color:inherit">
        <h3 style="font-family:var(--display);color:var(--cream);font-size:1.05rem;line-height:1.3"><?= $view->e($r['title']) ?></h3>
        <p style="color:var(--khaki);font-size:.88rem;line-height:1.6;margin-top:.5rem"><?= $view->e($r['excerpt']) ?></p>
        <span class="more" style="color:var(--orange);font-family:var(--mono);font-size:.78rem">READ ▸</span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
