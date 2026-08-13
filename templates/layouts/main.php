<?php
/**
 * layouts/main.php - the shared page shell.
 *
 * Wraps every page template. Emits the full SEO/GEO head (title, description,
 * keywords, robots, canonical, Open Graph, Twitter, and any JSON-LD blocks),
 * the site nav (login-aware), the page content, and the footer.
 *
 * Available vars (from View::page): $title, $description, $keywords, $robots,
 * $canonical, $ogImage, $jsonld (array), $crumb, $content.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?= $view->raw(\PPC\Core\View::render('layouts/analytics')) ?>
  <meta charset="UTF-8">
  <script>document.documentElement.classList.add('js');setTimeout(function(){if(!document.documentElement.classList.contains('gsap-ok')){document.querySelectorAll('[data-reveal]').forEach(function(el){el.style.opacity='1';el.style.transform='none';});}},4000);</script>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0, user-scalable=yes">
  <title><?= $view->e($title ?? 'Patriot Pest Control') ?></title>
  <meta name="description" content="<?= $view->e($description ?? '') ?>">
  <?php if (!empty($keywords)): ?><meta name="keywords" content="<?= $view->e($keywords) ?>"><?php endif; ?>
  <meta name="robots" content="<?= $view->e($robots ?? 'index, follow, max-snippet:-1') ?>">
  <link rel="canonical" href="<?= $view->e($canonical ?? $view->url('/')) ?>">

  <!-- PWA: manifest, theme color, home-screen icon -->
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#1c2415">
  <link rel="apple-touch-icon" href="<?= $view->asset('icons/apple-touch-icon.png') ?>">

  <!-- Open Graph / Twitter -->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="Patriot Pest Control">
  <meta property="og:title" content="<?= $view->e($title ?? '') ?>">
  <meta property="og:description" content="<?= $view->e($description ?? '') ?>">
  <meta property="og:url" content="<?= $view->e($canonical ?? $view->url('/')) ?>">
  <meta property="og:image" content="<?= $view->e($ogImage ?? $view->url('/assets/img/og.png')) ?>">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= $view->e($title ?? '') ?>">
  <meta name="twitter:description" content="<?= $view->e($description ?? '') ?>">

  <!-- Structured data (server-rendered; AI crawlers don't run JS) -->
  <?php foreach (($jsonld ?? []) as $ld): ?>
  <script type="application/ld+json"><?= $view->raw(json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></script>
  <?php endforeach; ?>

  <!-- Fonts: Black Ops One (display) + Barlow (body) + IBM Plex Mono (data) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= $view->asset('styles.css') ?>">
  <?php
    // Authenticated areas (login, dashboards, CMS) get the app UI stylesheet.
    $__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__appUi = (bool) preg_match('#^/(admin|staff-dashboard|customer-dashboard|login)(/|$)#', $__path);
  ?>
  <?php if ($__appUi): ?><link rel="stylesheet" href="<?= $view->asset('admin.css') ?>"><?php endif; ?>
  <?php if ($__appUi): ?><link rel="stylesheet" href="<?= $view->asset('app.css') ?>"><?php endif; ?>
  <?php if (\PPC\Core\Settings::bool('egg_enabled', true)): ?><link rel="stylesheet" href="<?= $view->asset('beacon.css') ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?= $view->asset('pwa-install.css') ?>">
  <link rel="icon" href="<?= $view->asset('img/pests/ants.jpg') ?>" type="image/jpeg">
</head>
<body>

<!-- Crosshair HUD (desktop only; Skyler-approved) -->
<div id="xh-v"></div><div id="xh-h"></div><div id="xh-ring"></div>

<nav class="top-nav" aria-label="Main navigation">
  <a class="brand" href="/"><span class="star">★</span> PATRIOT PEST CONTROL</a>
  <button id="menu-btn" aria-label="Toggle menu">☰ Menu</button>
  <div class="navlinks">
    <a class="nl" href="/">Home</a><a class="nl" href="/about">About</a><a class="nl" href="/services">Services</a><a class="nl" href="/prices">Prices</a><a class="nl" href="/service-areas">Areas</a><a class="nl" href="/blogs">Blog</a><a class="nl" href="/faqs">FAQs</a><a class="nl" href="/contact">Contact</a><a class="nl" href="/links">🔗 All Links</a>
    <?php if ($view->userType() === 'customer'): ?>
      <a class="nl" href="/customer-dashboard">My Account</a>
    <?php elseif ($view->userType() === 'staff'): ?>
      <a class="nl" href="/staff-dashboard">Dashboard</a>
      <?php if (\PPC\Core\Session::isAdmin()): ?><a class="nl" href="/admin">Admin</a><?php endif; ?>
    <?php else: ?>
      <a class="nl" href="/login">Sign In</a>
    <?php endif; ?>
    <a class="nav-cta" href="<?= $view->phoneHref() ?>">☎ <?= $view->phone() ?></a>
  </div>
</nav>

<main>
<?php if (!empty($crumb) && count($crumb) > 1): ?>
  <div class="wrap" style="padding-top:1.4rem">
    <nav class="crumb" aria-label="Breadcrumb">
      <?php foreach ($crumb as $i => $c): ?>
        <?php if ($i > 0): ?><span class="sep">/</span><?php endif; ?>
        <?php if ($i < count($crumb) - 1): ?><a href="<?= $view->e($c[1]) ?>"><?= $view->e($c[0]) ?></a><?php else: ?><span><?= $view->e($c[0]) ?></span><?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>
<?php endif; ?>
<?= $view->raw($content ?? '') ?>
</main>

<!-- Mobile sticky navigation (marketing pages only) -->
<?php if (!$__appUi): ?>
<nav class="mobile-sticky-nav" aria-label="Mobile quick navigation">
  <a href="javascript:history.back()" aria-label="Back">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/></svg>
    <span class="label">Back</span>
  </a>
  <a href="javascript:history.forward()" aria-label="Forward">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
    <span class="label">Next</span>
  </a>
  <a href="/" aria-label="Home">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M8.707 1.5a1 1 0 0 0-1.414 0L.646 8.146a.5.5 0 0 0 .708.708L2 8.207V13.5A1.5 1.5 0 0 0 3.5 15h9a1.5 1.5 0 0 0 1.5-1.5V8.207l.646.647a.5.5 0 0 0 .708-.708L13 5.793V2.5a.5.5 0 0 0-.5-.5h-1a.5.5 0 0 0-.5.5v1.293z"/></svg>
    <span class="label">Home</span>
  </a>
  <a href="/search" aria-label="Search">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>
    <span class="label">Search</span>
  </a>
  <a href="<?= $view->phoneHref() ?>" aria-label="Call">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M1.885.511a1.745 1.745 0 0 1 2.61.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.745 1.745 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/></svg>
    <span class="label">Call</span>
  </a>
  <a href="/contact" aria-label="Contact">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M.05 3.555A2 2 0 0 1 2 2h12a2 2 0 0 1 1.95 1.555L8 8.414zM0 4.697v7.104l5.803-3.558zM6.761 8.83l-6.57 4.027A2 2 0 0 0 2 14h12a2 2 0 0 0 1.808-1.143l-6.57-4.027L8 9.586zm1.964.372 6.57 4.027A2 2 0 0 0 16 13.802V4.697l-5.803 3.546z"/></svg>
    <span class="label">Contact</span>
  </a>
  <a href="/login" aria-label="Login">
    <svg width="22" height="22" viewBox="0 0 16 16" fill="currentColor"><path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/></svg>
    <span class="label">Login</span>
  </a>
</nav>
<div class="mobile-nav-spacer"></div>
<?php endif; ?>

<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div>
        <div class="foot-brand"><span style="color:var(--orange)">★</span> PATRIOT PEST CONTROL</div>
        <p>Veteran-owned pest control for homes &amp; businesses across Washington, Idaho, Oregon &amp; Arizona. Founded by U.S. Military Veteran Skyler Rose. Eco-friendly, family &amp; pet safe, 100% satisfaction guaranteed.</p>
      </div>
      <div>
        <h4>Quick Links</h4>
        <a href="/about">About Us</a><a href="/services">Services</a>
        <a href="/prices">Pricing</a><a href="/blogs">Blog</a>
        <a href="/faqs">FAQs</a><a href="/referral">Referral Program</a>
        <a href="/help">Help Center</a>
      </div>
      <div>
        <h4>Top Services</h4>
        <a href="/pest/ants">Ant Control</a><a href="/pest/termites">Termite Control</a>
        <a href="/pest/bed-bugs">Bed Bug Treatment</a><a href="/pest/rodents">Rodent Control</a>
        <a href="/pest/mosquitoes">Mosquito Control</a><a href="/pest/wasps">Wasp Removal</a>
      </div>
      <div>
        <h4>Contact</h4>
        <?php
          // Show the visitor's local line first, then the other one.
          $__primary = \PPC\Core\Geo::region();
          $__order   = [$__primary, \PPC\Core\Geo::otherRegion()];
        ?>
        <?php foreach ($__order as $__r): $__line = \PPC\Core\Geo::REGIONS[$__r]; ?>
          <a href="tel:<?= $view->e($__line['tel']) ?>"><?= $view->e($__line['display']) ?> - <?= $view->e($__line['label']) ?></a>
        <?php endforeach; ?>
        <a href="mailto:info@patriotpest.pro">info@patriotpest.pro</a>
        <a href="/contact">Spokane, WA 99201, United States</a>
        <a href="/socials">Social Media</a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> PATRIOT PEST CONTROL · ALL RIGHTS RESERVED</span>
      <span><a href="/privacy-policy">PRIVACY</a> · <a href="/terms-of-use">TERMS</a> · <a href="/sitemap">SITEMAP</a></span>
      <span>🇺🇸 VETERAN-OWNED AMERICAN COMPANY</span>
    </div>
  </div>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="<?= $view->asset('main.js') ?>"></script>
<?php if (\PPC\Core\Settings::bool('track_enabled', true)): ?>
<?= $view->raw(\PPC\Core\View::render('partials/egg')) ?>
<script src="<?= $view->asset('beacon.js') ?>"></script>
<?php endif; ?>
<?= $view->raw(\PPC\Core\View::render('partials/install-banner')) ?>
<script src="<?= $view->asset('pwa-install.js') ?>"></script>

<!-- AI Chat Widget (marketing pages only) -->
<?php if (!$__appUi): ?>
<?= $view->raw(\PPC\Core\View::render('partials/ai-chat-widget')) ?>
<?php endif; ?>
</body>
</html>
