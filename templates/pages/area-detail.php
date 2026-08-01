<?php
/**
 * pages/area-detail.php — a single service-area city landing page.
 * Vars: $city (city/state/stateName/slug).
 */
$city = $data['city'] ?? [];
$name = $city['city'] ?? 'Your Area';
$st   = $city['stateName'] ?? '';
$phone = ($city['state'] ?? '') === 'AZ' ? ['(602) 755-8414', '+16027558414'] : ['(509) 471-5767', '+15094715767'];
?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">AREA OF OPERATIONS // <?= $view->e(strtoupper($city['state'] ?? '')) ?></div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(1.8rem,5vw,2.8rem);margin:.4rem 0 .8rem">Pest Control in <?= $view->e($name) ?>, <?= $view->e($st) ?></h1>
    <p class="lead">Same-day, eco-friendly pest control for <?= $view->e($name) ?> homes and businesses. Veteran-owned, licensed and insured, backed by a 90-day warranty.</p>
    <div class="hero-ctas" style="margin-top:1.4rem">
      <a class="btn btn-primary" href="tel:<?= $view->e($phone[1]) ?>">☎ Call <?= $view->e($phone[0]) ?></a>
      <a class="btn btn-ghost" href="/contact">Get a Free Quote ▸</a>
    </div>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <div class="grid g3">
      <div class="card"><h3 style="font-family:var(--display);color:var(--cream)">⚡ Same-Day Service</h3><p style="color:var(--khaki);line-height:1.7;margin-top:.5rem">Fast response across <?= $view->e($name) ?> and surrounding communities when pests can't wait.</p></div>
      <div class="card"><h3 style="font-family:var(--display);color:var(--cream)">🌿 Family &amp; Pet Safe</h3><p style="color:var(--khaki);line-height:1.7;margin-top:.5rem">Low-toxicity, eco-friendly treatments that protect your household, not just eliminate pests.</p></div>
      <div class="card"><h3 style="font-family:var(--display);color:var(--cream)">🛡️ 90-Day Warranty</h3><p style="color:var(--khaki);line-height:1.7;margin-top:.5rem">If pests come back between visits, we re-treat free. No hassles, no excuses.</p></div>
    </div>
    <div class="promise" style="margin-top:1.6rem">Serving <?= $view->e($name) ?>, <?= $view->e($st) ?> with ants, spiders, rodents, wasps, bed bugs, termites &amp; more. <a href="/services" style="color:var(--orange)">See every pest we treat ▸</a></div>
  </div>
</section>

<section class="block cta-band">
  <div class="wrap" style="text-align:center">
    <h2 style="font-family:var(--display);color:var(--cream)">Ready in <?= $view->e($name) ?>? <em>Call now.</em></h2>
    <div class="hero-ctas" style="justify-content:center;margin-top:1.2rem">
      <a class="btn btn-primary" href="tel:<?= $view->e($phone[1]) ?>">☎ <?= $view->e($phone[0]) ?></a>
    </div>
  </div>
</section>
