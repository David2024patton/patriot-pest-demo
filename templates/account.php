<?php
/**
 * account.php - self-service profile for the signed-in user (staff OR customer).
 * Vars: $type ('staff'|'customer'), $record (row|null), $name, $role, $isAdmin.
 */
$type   = $data['type'] ?? null;
$record = $data['record'] ?? null;
$name   = $data['name'] ?? 'User';
$role   = $data['role'] ?? null;
$signout = $type === 'staff' ? '/staff-logout' : '/logout';
?>
<div class="app"><div class="wrap">
  <div class="app-head">
    <div>
      <h1>My Account</h1>
      <div class="sub">Signed in as <?= $view->e($name) ?> · <span class="badge role"><?= $view->e($type === 'staff' ? ($role ? ucfirst(str_replace('_', ' ', $role)) : 'Staff') : 'Customer') ?></span></div>
    </div>
    <div class="actions">
      <a class="btn btn-ghost" href="<?= $type === 'staff' ? '/staff-dashboard' : '/customer-dashboard' ?>">◂ Dashboard</a>
      <a class="btn btn-ghost" href="<?= $view->e($signout) ?>">Sign Out</a>
    </div>
  </div>

  <?php if (!$record): ?>
    <div class="panel"><p class="empty">We couldn't load your account record.</p></div>
  <?php elseif ($type === 'staff'): ?>
    <div class="panel">
      <h3>Staff Profile</h3>
      <dl class="kv" style="margin-top:.8rem">
        <dt>Name</dt><dd><?= $view->e($record['name'] ?? 'N/A') ?></dd>
        <dt>Email</dt><dd><?= $view->e($record['email'] ?? 'N/A') ?></dd>
        <dt>Role</dt><dd><span class="badge role"><?= $view->e(ucfirst(str_replace('_', ' ', $record['role'] ?? 'staff'))) ?></span></dd>
        <dt>Status</dt><dd><span class="badge <?= (int)($record['active'] ?? 1) === 1 ? 'active' : 'cancelled' ?>"><?= (int)($record['active'] ?? 1) === 1 ? 'Active' : 'Inactive' ?></span></dd>
        <dt>Last Sign-In</dt><dd><?= $view->e(!empty($record['last_login']) ? date('M j, Y H:i', strtotime($record['last_login'])) : 'N/A') ?></dd>
        <dt>Member Since</dt><dd><?= $view->e(!empty($record['created_at']) ? date('M j, Y', strtotime($record['created_at'])) : 'N/A') ?></dd>
      </dl>
    </div>
    <p class="muted" style="margin-top:1rem;line-height:1.7">Sign-in is passwordless: we email a one-time code each time. To change your name or email, contact an administrator.</p>
  <?php else: ?>
    <div class="panel">
      <h3>Account Details</h3>
      <dl class="kv" style="margin-top:.8rem">
        <dt>Account #</dt><dd class="mono"><?= $view->e($record['account_number'] ?? 'N/A') ?></dd>
        <dt>Name</dt><dd><?= $view->e($record['name'] ?? 'N/A') ?></dd>
        <dt>Email</dt><dd><?= $view->e($record['email'] ?? 'N/A') ?></dd>
        <dt>Phone</dt><dd><?= $view->e($record['phone'] ?? 'N/A') ?></dd>
        <dt>Service Address</dt><dd><?= $view->e(trim(($record['address'] ?? '') . ', ' . ($record['city'] ?? '') . ', ' . ($record['state'] ?? '') . ' ' . ($record['zip'] ?? ''), ', ')) ?></dd>
        <dt>Status</dt><dd><span class="badge <?= $view->e($record['status']) ?>"><?= $view->e(ucfirst($record['status'] ?? 'active')) ?></span></dd>
      </dl>
    </div>
    <p class="muted" style="margin-top:1rem;line-height:1.7">Need to update your contact info or service address? Call <a href="tel:+15094715767">(509) 471-5767</a> or use the <a href="/contact">contact form</a>.</p>
  <?php endif; ?>
</div></div>
