<?php
/**
 * dashboard/staff.php - the staff dashboard overview.
 * Vars: $name, $role, $isAdmin, $counts (openTickets/openCases/staff/customers).
 */
$name    = $data['name'] ?? 'Staff';
$role    = $data['role'] ?? 'staff';
$isAdmin = $data['isAdmin'] ?? false;
$counts  = $data['counts'] ?? [];
?>
<div class="app">
  <div class="wrap">
    <?php if (\PPC\Core\Session::pullFlash('analytics_event') === 'portal_login'): ?>
    <script>
      gtag('event', 'portal_login', {
        'event_category': 'engagement',
        'event_label': '<?= $view->e($name) ?>'
      });
    </script>
    <?php endif; ?>
    <div class="app-head">
      <div>
        <h1>Staff Dashboard</h1>
        <div class="sub">Signed in as <?= $view->e($name) ?> · <span class="badge role"><?= $view->e($role) ?></span></div>
      </div>
      <div class="actions">
        <?php if ($isAdmin): ?><a class="btn btn-primary" href="/admin">Open CMS ▸</a><?php endif; ?>
        <a class="btn btn-ghost" href="/staff-logout">Sign Out</a>
      </div>
    </div>

    <div class="stat-cards">
      <div class="stat-card"><div class="v"><?= (int)($counts['openTickets'] ?? 0) ?></div><div class="k">Open Tickets</div></div>
      <div class="stat-card"><div class="v"><?= (int)($counts['openCases'] ?? 0) ?></div><div class="k">Open Cases</div></div>
      <div class="stat-card"><div class="v"><?= (int)($counts['customers'] ?? 0) ?></div><div class="k">Customers</div></div>
      <div class="stat-card"><div class="v"><?= (int)($counts['staff'] ?? 0) ?></div><div class="k">Active Staff</div></div>
    </div>

    <div class="grid g2">
      <div class="panel">
        <h3>Quick Actions</h3>
        <div class="tile-grid" style="margin-top:.8rem">
          <a class="tile" href="/contact"><div class="ico">📥</div><h3>Inbox / Leads</h3><p>Review incoming contact requests.</p></a>
          <?php if ($isAdmin): ?>
          <a class="tile" href="/admin/posts"><div class="ico">📝</div><h3>Blog Posts</h3><p>Create &amp; edit field reports.</p></a>
          <a class="tile" href="/admin/media"><div class="ico">🖼️</div><h3>Media Library</h3><p>Manage the pest photo library.</p></a>
          <a class="tile" href="/admin/content"><div class="ico">🧩</div><h3>Content Blocks</h3><p>Edit page sections.</p></a>
          <?php endif; ?>
        </div>
      </div>

      <div class="panel">
        <h3>Operations</h3>
        <p class="muted" style="margin-top:.6rem;line-height:1.7">The full staff toolkit (customers, appointments, tickets, cases, messaging, reactivation campaigns, and phone lookup) is being rebuilt on the new secure core. Modules come online in phases.</p>
        <p class="muted" style="margin-top:.8rem;line-height:1.7">Need something now? The legacy tools remain available during the transition.</p>
      </div>
    </div>
  </div>
</div>
