<?php
/**
 * staff/list.php — staff management list (admin only).
 * Vars: $staff, $roles, $isAdmin, $flash.
 */
$staffList = $data['staff'] ?? [];
$roles     = $data['roles'] ?? [];
$isAdmin   = $data['isAdmin'] ?? false;
$flash     = $data['flash'] ?? null;
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>Staff Management</h1>
        <div class="sub">Manage team accounts and roles.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin/staff/new">+ Add Staff</a>
        <a class="btn btn-ghost" href="/admin">Admin Home</a>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="flash <?= $view->e($flash['success'] ? 'success' : 'error') ?>">
      <?php if (!empty($flash['success'])): ?>
        <?= $view->e($flash['success']) ?>
      <?php elseif (!empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $field => $msgs): ?>
          <?= $view->e(implode(' ', $msgs)) ?>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="panel">
      <div class="table-wrap"><table class="data">
        <thead><tr>
          <th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($staffList as $s): ?>
          <tr>
            <td><a href="/admin/staff/<?= $view->e($s['id']) ?>" style="color:var(--orange);text-decoration:none"><?= $view->e($s['name']) ?></a></td>
            <td><?= $view->e($s['email']) ?></td>
            <td><span class="badge"><?= $view->e($s['role_label'] ?? $s['role']) ?></span></td>
            <td><span class="badge <?= $s['active'] ? 'active' : 'cancelled' ?>"><?= $s['active'] ? 'Active' : 'Inactive' ?></span></td>
            <td class="num"><?= $view->e($s['last_login'] ? date('M j, Y', strtotime($s['last_login'])) : 'Never') ?></td>
            <td>
              <form method="post" action="/admin/staff/<?= $view->e($s['id']) ?>/toggle" style="display:inline">
                <?= $view->csrf() ?>
                <button class="btn btn-ghost" style="font-size:.75rem;padding:.2rem .6rem" type="submit">
                  <?= $s['active'] ? 'Deactivate' : 'Activate' ?>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$staffList): ?>
          <tr><td colspan="6" class="empty">No staff members yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
