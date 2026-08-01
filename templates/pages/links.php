<?php /** pages/links.php — complete link directory. Vars: none. */
$groups = [
    'Main' => [['/', 'Home'], ['/about', 'About Us'], ['/services', 'Services'], ['/prices', 'Pricing'], ['/service-areas', 'Service Areas'], ['/faqs', 'FAQs'], ['/contact', 'Contact']],
    'Top Services' => [['/pest/ants', 'Ant Control'], ['/pest/spiders', 'Spider Control'], ['/pest/rodents', 'Rodent Control'], ['/pest/bed-bugs', 'Bed Bug Treatment'], ['/pest/termites', 'Termite Control'], ['/pest/mosquitoes', 'Mosquito Control'], ['/pest/wasps', 'Wasp Removal'], ['/pest/scorpions', 'Scorpion Control']],
    'Resources' => [['/blogs', 'Blog & Guides'], ['/referral', 'Referral Program'], ['/socials', 'Social Media'], ['/help', 'Help Center'], ['/sitemap', 'Sitemap']],
    'Account' => [['/login', 'Sign In'], ['/customer-dashboard', 'Customer Dashboard'], ['/staff-dashboard', 'Staff Dashboard']],
    'Legal' => [['/privacy-policy', 'Privacy Policy'], ['/terms-of-use', 'Terms of Use']],
];
?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">DIRECTORY // ALL LINKS</div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3rem);margin:.4rem 0 .8rem">Everything, <em>one place.</em></h1>
    <p class="lead">A complete directory of Patriot Pest Control pages, services, and resources.</p>
  </div>
</section>

<section class="block alt">
  <div class="wrap">
    <div class="grid g3">
      <?php foreach ($groups as $title => $links): ?>
      <div class="card">
        <h3 style="font-family:var(--display);color:var(--orange);font-size:1.05rem;margin-bottom:.8rem"><?= $view->e($title) ?></h3>
        <div style="display:flex;flex-direction:column;gap:.5rem">
          <?php foreach ($links as [$href, $label]): ?>
            <a href="<?= $view->e($href) ?>" style="color:var(--cream);text-decoration:none"><?= $view->e($label) ?> ▸</a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
