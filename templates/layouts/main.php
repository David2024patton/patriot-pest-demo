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

<nav aria-label="Main navigation">
  <a class="brand" href="/"><span class="star">★</span> PATRIOT PEST CONTROL</a>
  <button id="menu-btn" aria-label="Toggle menu">☰ Menu</button>
  <div class="navlinks">
    <a class="nl" href="/">Home</a><a class="nl" href="/about">About</a><a class="nl" href="/services">Services</a><a class="nl" href="/prices">Prices</a><a class="nl" href="/service-areas">Areas</a><a class="nl" href="/blogs">Blog</a><a class="nl" href="/faqs">FAQs</a><a class="nl" href="/contact">Contact</a>
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
  <a href="/" aria-label="Home">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
    <span class="label">Home</span>
  </a>
  <a href="<?= $view->phoneHref() ?>" aria-label="Call">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
    <span class="label">Call</span>
  </a>
  <button class="center-btn" onclick="window.location.href='/contact'" aria-label="Request Service">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
  </button>
  <a href="/contact" aria-label="Contact">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
    <span class="label">Contact</span>
  </a>
  <a href="/login" aria-label="Login">
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
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
