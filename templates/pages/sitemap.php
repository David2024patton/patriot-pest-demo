<?php
/**
 * pages/sitemap.php — HTML sitemap. Vars: $states (WA/ID/OR/AZ => name+cities).
 */
$states  = $data['states'] ?? [];
$slugify = fn(string $s): string => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)) ?? '', '-');
$pages = [['/', 'Home'], ['/about', 'About Us'], ['/services', 'Services'], ['/prices', 'Pricing'], ['/service-areas', 'Service Areas'], ['/blogs', 'Blog'], ['/faqs', 'FAQs'], ['/contact', 'Contact'], ['/referral', 'Referral Program'], ['/socials', 'Social Media'], ['/help', 'Help Center'], ['/links', 'All Links'], ['/privacy-policy', 'Privacy Policy'], ['/terms-of-use', 'Terms of Use']];
?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">NAVIGATION // SITEMAP</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Sitemap</h1>
    <p class="lead">Every page on the Patriot Pest Control website.</p>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <div class="grid g3">
      <div class="card">
        <h3 style="font-family:var(--display);color:var(--orange);margin-bottom:.8rem">Pages</h3>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <?php foreach ($pages as [$href, $label]): ?><a href="<?= $view->e($href) ?>" style="color:var(--cream);text-decoration:none"><?= $view->e($label) ?> ▸</a><?php endforeach; ?>
        </div>
      </div>
      <?php foreach ($states as $st => $s): ?>
      <div class="card">
        <h3 style="font-family:var(--display);color:var(--orange);margin-bottom:.8rem"><?= $view->e($s['name']) ?> (<?= $view->e($st) ?>)</h3>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <?php foreach ($s['cities'] as $city): ?><a href="/areas/<?= $view->e($slugify($city)) ?>" style="color:var(--cream);text-decoration:none"><?= $view->e($city) ?> ▸</a><?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
