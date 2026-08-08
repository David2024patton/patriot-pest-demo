<?php
/**
 * pages/services.php — full service list. DB-driven off the pest library.
 * Vars: $pests (array of slug/name/filename/category).
 */
$pests = $data['pests'] ?? [];
?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">CAPABILITIES // SERVICES</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Every pest. <em>One team.</em></h1>
    <p class="lead">From ants to wildlife, we identify, treat, and prevent <?= count($pests) ?>+ pest categories across Washington, Idaho, Oregon, and Arizona. Methods are eco-friendly and safe for your family and pets.</p>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <div class="grid g3">
      <?php foreach ($pests as $p): ?>
      <a class="card" href="/pest/<?= $view->e($p['slug']) ?>" style="text-decoration:none;color:inherit">
        <span class="pphoto"><img src="<?= $view->asset('img/pests/' . $p['filename']) ?>" alt="<?= $view->e($p['name']) ?>" loading="lazy"><i class="ret" aria-hidden="true"></i></span>
        <h3 style="font-family:var(--display);color:var(--cream);margin-top:.7rem"><?= $view->e($p['name']) ?> ▸</h3>
        <span class="badge" style="margin-top:.4rem;color:var(--khaki)"><?= $view->e(ucfirst($p['category'])) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="block cta-band">
  <div class="wrap" style="text-align:center">
    <h2 style="font-family:var(--display);color:var(--cream)">Don't see your pest? <em>Call us anyway.</em></h2>
    <p class="lead">If it invades, we handle it. Free quotes, same-day service available.</p>
    <div class="hero-ctas" style="justify-content:center;margin-top:1.2rem">
      <a class="btn btn-primary" href="tel:+15094715767">☎ (509) 471-5767</a>
      <a class="btn btn-ghost" href="/contact">Request Service ▸</a>
    </div>
  </div>
</section>
