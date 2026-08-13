<?php
/**
 * layouts/app.php - the LIGHT authenticated app shell.
 *
 * Wraps the staff dashboard, customer portal, and the CMS in a clean paper-white
 * workspace: a fixed left sidebar (desktop) that collapses to an off-canvas
 * drawer + a sticky 5-icon bottom bar (mobile). The center bottom icon is a
 * raised magnifier that opens the customer-search overlay (staff/admin) - or a
 * raised "request" action for customers.
 *
 * Deliberately OMITS the marketing nav/footer and main.js (which injects the
 * dark ambient layers). Vars are re-skinned to light by app.css on the body.
 *
 * Vars (from View::page): $title, $content, plus anything the page passed.
 */

$uType  = $view->userType();                 // 'staff' | 'customer' | null
$isAdmin = \PPC\Core\Session::isAdmin();
$uName  = $view->userName() ?? 'User';
$role   = \PPC\Core\Session::staffRole();
$initial = strtoupper(mb_substr(trim($uName), 0, 1) ?: 'U');

$__path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__path = '/' . trim($__path, '/');

// A nav link is "active" when the current path equals it OR falls under it
// (path begins with href + '/'). Because '/admin' is a prefix of every CMS child
// ('/admin/media', ...), we resolve a SINGLE active link = the longest matching
// href, so a parent never lights up alongside its child.
$__match = fn(string $href): bool => $__path === $href || str_starts_with($__path, $href . '/');

// ---- sidebar navigation (role-aware) ----
$navMain = [];
$navCms  = [];
$barItems = [];

if ($uType === 'staff') {
    $navMain = [
        ['/staff-dashboard', '★', 'Overview'],
        ['/staff/customers', '•', 'Customers'],
        ['/staff/leads/new',  '+', 'New Lead'],
        ['/staff/messages',  '•', 'Messages'],
    ];
    if ($isAdmin) {
        $navCms = [
            ['/admin',         '•', 'CMS Home'],
            ['/admin/posts',   '•', 'Posts'],
            ['/admin/media',   '•', 'Media'],
            ['/admin/content', '•', 'Content Blocks'],
            ['/admin/settings','•', 'Settings'],
            ['/admin/staff',   '•', 'Staff'],
            ['/admin/roles',   '•', 'Roles'],
            ['/admin/departments','•','Departments'],
            ['/admin/api-keys','•', 'API Keys'],
            ['/admin/ai',      '✦', 'AI & Agents'],
            ['/admin/audit-log','•', 'Audit Log'],
            ['/admin/system-logs','•', 'System Logs'],
            ['/admin/twilio',  '•', 'Twilio'],
            ['/admin/retention', '•', 'Retention'],
        ];
    }
    // 5-icon bottom bar; center = customer search magnifier
    $barItems = [
        ['href' => '/staff-dashboard', 'ico' => '★', 'label' => 'Home'],
        ['href' => '/staff/customers', 'ico' => '•', 'label' => 'Customers'],
        ['action' => 'search', 'ico' => '•', 'label' => 'Search'],
        ['href' => '/staff/leads/new', 'ico' => '+', 'label' => 'Lead'],
        ['href' => '/account', 'ico' => '•', 'label' => 'Account'],
    ];
    $signout = '/staff-logout';
    $roleLabel = $role ? ucfirst(str_replace('_', ' ', $role)) : 'Staff';
} else {
    // customer
    $navMain = [
        ['/customer-dashboard', '◧', 'My Account'],
        ['/contact',            '＋', 'Request Service'],
        ['/help',               '？', 'Help Center'],
    ];
    $barItems = [
        ['href' => '/customer-dashboard', 'ico' => '◧', 'label' => 'Account'],
        ['href' => '/customer-dashboard', 'ico' => '🎫', 'label' => 'Tickets'],
        ['href' => '/contact', 'ico' => '＋', 'label' => 'Request', 'center' => true],
        ['href' => '/help', 'ico' => '？', 'label' => 'Help'],
        ['href' => '/logout', 'ico' => '⏻', 'label' => 'Out'],
    ];
    $signout = '/logout';
    $roleLabel = 'Customer';
}

// Resolve the single active nav target = longest matching href (see $__match).
$__hrefs = array_merge(
    array_column($navMain, 0),
    array_column($navCms, 0),
    array_values(array_filter(array_column($barItems, 'href')))
);
$__activeHref = '';
$__activeLen  = -1;
foreach ($__hrefs as $__h) {
    if ($__match($__h) && strlen($__h) > $__activeLen) {
        $__activeHref = $__h;
        $__activeLen  = strlen($__h);
    }
}
$isActive = fn(string $href): bool => $href !== '' && $href === $__activeHref;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=5.0, user-scalable=yes">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $view->e($title ?? 'Patriot Pest Control') ?></title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Black+Ops+One&family=Barlow:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= $view->asset('styles.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('admin.css') ?>">
  <link rel="stylesheet" href="<?= $view->asset('app.css') ?>">
  <link rel="icon" href="<?= $view->asset('img/pests/ants.jpg') ?>" type="image/jpeg">

  <!-- PWA: manifest, theme color, home-screen icon -->
  <link rel="manifest" href="/manifest.webmanifest">
  <meta name="theme-color" content="#1c2415">
  <link rel="apple-touch-icon" href="<?= $view->asset('icons/apple-touch-icon.png') ?>">
  <link rel="stylesheet" href="<?= $view->asset('pwa-install.css') ?>">
</head>
<body class="appshell-body">

<div class="appshell">

  <!-- ===== LEFT SIDEBAR (desktop) / off-canvas drawer (mobile) ===== -->
  <aside class="appshell-side" id="appshell-side" aria-label="App navigation">
    <a class="appshell-brand" href="<?= $uType === 'staff' ? '/staff-dashboard' : '/customer-dashboard' ?>">
      <span class="star">★</span>
      <span>PATRIOT PEST<small>Operations Console</small></span>
    </a>

    <nav class="appshell-nav">
      <div class="grp">Workspace</div>
      <?php foreach ($navMain as [$href, $ico, $label]): ?>
        <a href="<?= $view->e($href) ?>" class="<?= $isActive($href) ? 'active' : '' ?>">
          <span class="ico"><?= $ico ?></span><?= $view->e($label) ?>
        </a>
      <?php endforeach; ?>

      <?php if ($navCms): ?>
        <div class="grp">Content Manager</div>
        <?php foreach ($navCms as [$href, $ico, $label]): ?>
          <a href="<?= $view->e($href) ?>" class="<?= $isActive($href) ? 'active' : '' ?>">
            <span class="ico"><?= $ico ?></span><?= $view->e($label) ?>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>

    <a class="appshell-user" href="/account">
      <span class="av"><?= $view->e($initial) ?></span>
      <span class="who"><b><?= $view->e($uName) ?></b><span><?= $view->e($roleLabel) ?></span></span>
    </a>
    <a class="appshell-signout" href="<?= $view->e($signout) ?>">
      <span class="ico">⏻</span>Sign Out
    </a>
  </aside>

  <!-- ===== MAIN COLUMN ===== -->
  <div class="appshell-main">

    <!-- mobile top bar -->
    <div class="appshell-topbar">
      <button class="burger" id="appshell-burger" aria-label="Open menu">☰</button>
      <span class="tbrand"><span class="star">★</span> PATRIOT PEST</span>
    </div>

    <main class="appshell-content">
      <?= $view->raw($content ?? '') ?>
    </main>
  </div>
</div>

<!-- scrim behind the mobile drawer -->
<div class="appshell-scrim" id="appshell-scrim"></div>

<!-- ===== MOBILE 5-ICON BOTTOM BAR ===== -->
<nav class="appshell-bar" aria-label="Quick navigation">
  <?php foreach ($barItems as $item): ?>
    <?php if (isset($item['action']) && $item['action'] === 'search'): ?>
      <button type="button" class="center" id="csearch-open" aria-label="Search customers">
        <span class="bi"><?= $item['ico'] ?></span><?= $view->e($item['label']) ?>
      </button>
    <?php else: ?>
      <a href="<?= $view->e($item['href']) ?>"
         class="<?= (!empty($item['center']) ? 'center ' : '') . (isset($item['href']) && $isActive($item['href']) ? 'active' : '') ?>">
        <span class="bi"><?= $item['ico'] ?></span><?= $view->e($item['label']) ?>
      </a>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>

<?php if ($uType === 'staff'): ?>
<!-- ===== CUSTOMER SEARCH OVERLAY ===== -->
<div class="csearch" id="csearch" role="dialog" aria-label="Customer search">
  <div class="csearch-box">
    <div class="csearch-head">
      <span class="mag">🔍</span>
      <input type="search" id="csearch-input" placeholder="Search customers by name, phone, email, or account #" autocomplete="off">
      <span class="esc">ESC</span>
    </div>
    <div class="csearch-results" id="csearch-results">
      <div class="csearch-empty">Start typing to search the customer book.</div>
    </div>
    <div class="csearch-foot">Local cache · FieldRoutes sync pending</div>
  </div>
</div>
<?php endif; ?>

<script>
  window.PPC_APP = { userType: <?= json_encode($uType) ?> };
</script>
<script src="<?= $view->asset('app.js') ?>"></script>
<?php if (\PPC\Core\Settings::bool('track_enabled', true)): ?>
<script src="<?= $view->asset('beacon.js') ?>"></script>
<?php endif; ?>
<?= $view->raw(\PPC\Core\View::render('partials/install-banner')) ?>
<script src="<?= $view->asset('pwa-install.js') ?>"></script>
</body>
</html>
