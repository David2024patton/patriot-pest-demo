<?php
/**
 * pages/areas.php — service-area overview. Vars: $states (WA/ID/OR/AZ => name+cities).
 */
$states = $data['states'] ?? [];
$slugify = fn(string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">AREA OF OPERATIONS</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Four states. <em>One call.</em></h1>
    <p class="lead">Same-day pest control across Washington, Idaho, Oregon &amp; Arizona. Find your community below — if we're not listed, call us; we likely still cover you.</p>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <div class="area-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.2rem">
      <?php foreach ($states as $st => $s): ?>
      <div class="area-col card">
        <h3 style="font-family:var(--display);color:var(--orange);font-size:1.6rem"><?= $view->e($st) ?></h3>
        <div style="font-family:var(--mono);font-size:.72rem;letter-spacing:.1em;color:var(--khaki);text-transform:uppercase;margin-bottom:.8rem"><?= $view->e($s['name']) ?></div>
        <div class="area-cities" style="display:flex;flex-direction:column;gap:.4rem">
          <?php foreach ($s['cities'] as $city): ?>
            <a href="/areas/<?= $view->e($slugify($city)) ?>" style="color:var(--cream);text-decoration:none"><?= $view->e($city) ?> ▸</a>
          <?php endforeach; ?>
        </div>
        <span class="area-note" style="display:block;margin-top:.9rem;font-family:var(--mono);font-size:.7rem;color:var(--olive-300)">● <?= count($s['cities']) ?> ZONE<?= count($s['cities']) !== 1 ? 'S' : '' ?> · SAME-DAY AVAILABLE</span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="block cta-band">
  <div class="wrap" style="text-align:center">
    <h2 style="font-family:var(--display);color:var(--cream)">In the region? <em>Let's talk.</em></h2>
    <div class="hero-ctas" style="justify-content:center;margin-top:1.2rem">
      <a class="btn btn-primary" href="tel:+15094715767">☎ (509) 471-5767</a>
      <a class="btn btn-ghost" href="/contact">Get a Free Quote ▸</a>
    </div>
  </div>
</section>
