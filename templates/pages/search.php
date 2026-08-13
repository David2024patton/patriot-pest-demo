<?php
/**
 * pages/search.php - site search. Vars: $q, $results (posts/pests/areas).
 */
$q       = $data['q'] ?? '';
$results = $data['results'] ?? [];
$slugify = fn(string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
?>
<section class="block">
  <div class="wrap" style="max-width:860px">
    <div class="eyebrow">SEARCH // SITE</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Search</h1>
    <form action="/search" method="get" style="display:flex;gap:.6rem;margin-bottom:2rem">
      <input type="text" name="q" value="<?= $view->e($q) ?>" placeholder="Pests, guides, cities…" aria-label="Search" style="flex:1;min-height:48px;background:var(--olive-800);border:1px solid var(--olive-700);color:var(--cream);padding:0 1rem;font-family:var(--mono);font-size:.9rem">
      <button type="submit" style="min-height:48px;background:var(--orange);color:var(--ink);border:0;padding:0 1.4rem;font-family:var(--display);text-transform:uppercase;cursor:pointer">Search</button>
    </form>

    <?php if ($q === ''): ?>
      <p class="lead" style="color:var(--khaki)">Type a pest, a topic, or a city to search the blog, pest library, and service areas.</p>
    <?php elseif (empty($results['posts']) && empty($results['pests']) && empty($results['areas'])): ?>
      <p class="lead" style="color:var(--khaki)">No results for "<?= $view->e($q) ?>". Try a different term or <a href="/contact" style="color:var(--orange)">ask us directly</a>.</p>
    <?php else: ?>

    <?php if (!empty($results['pests'])): ?>
      <div class="eyebrow">PEST LIBRARY</div>
      <div class="grid g3" style="margin:1rem 0 2rem">
        <?php foreach ($results['pests'] as $p): ?>
        <a class="card" href="/pest/<?= $view->e($p['slug']) ?>" style="text-decoration:none;color:inherit;display:flex;gap:.9rem;align-items:center">
          <span class="pphoto" style="width:70px;height:52px"><img src="<?= $view->asset('img/pests/' . $p['filename']) ?>" alt="<?= $view->e($p['name']) ?>" loading="lazy"></span>
          <div>
            <h3 style="font-family:var(--display);color:var(--cream);font-size:.95rem"><?= $view->e($p['name']) ?></h3>
            <p style="color:var(--khaki);font-size:.8rem;line-height:1.4"><?= $view->e(mb_strimwidth(strip_tags($p['description'] ?? ''), 0, 80, '…')) ?></p>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($results['posts'])): ?>
      <div class="eyebrow">BLOG GUIDES</div>
      <div class="grid g3" style="margin:1rem 0 2rem">
        <?php foreach ($results['posts'] as $p): ?>
        <a class="card" href="/blogs/<?= $view->e($p['slug']) ?>" style="text-decoration:none;color:inherit">
          <h3 style="font-family:var(--display);color:var(--cream);font-size:1rem;line-height:1.3"><?= $view->e($p['title']) ?></h3>
          <p style="color:var(--khaki);font-size:.85rem;line-height:1.55;margin-top:.4rem"><?= $view->e($p['excerpt'] ?? '') ?></p>
        </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($results['areas'])): ?>
      <div class="eyebrow">SERVICE AREAS</div>
      <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin:1rem 0">
        <?php foreach ($results['areas'] as $a): ?>
        <a href="/areas/<?= $view->e($slugify($a['city'])) ?>" style="background:var(--olive-800);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem .9rem;text-decoration:none;font-family:var(--mono);font-size:.8rem"><?= $view->e($a['city']) ?> · <?= $view->e($a['state']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</section>
