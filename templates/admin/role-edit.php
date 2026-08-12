<?php
/**
 * admin/role-edit.php — edit role permissions (admin only).
 * Vars: $role, $availablePermissions, $currentPerms, $flash.
 */
$role = $data['role'] ?? [];
$availablePermissions = $data['availablePermissions'] ?? [];
$currentPerms = $data['currentPerms'] ?? [];
$flash = $data['flash'] ?? null;
$isImmutable = ($role['role'] === 'super-user');
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>Edit Role: <?= $view->e($role['label']) ?></h1>
        <div class="sub">Role code: <span class="badge"><?= $view->e($role['role']) ?></span></div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin/roles">Back to Roles</a>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="flash <?= !empty($flash['success']) ? 'success' : 'error' ?>">
      <?php if (!empty($flash['success'])): ?>
        <?= $view->e($flash['success']) ?>
      <?php elseif (!empty($flash['error'])): ?>
        <?= $view->e($flash['error']) ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($isImmutable): ?>
    <div class="panel" style="border:2px solid var(--orange);background:var(--olive-800)">
      <h3 style="color:var(--orange)">⚠️ Immutable Role</h3>
      <p style="margin-top:.4rem;color:var(--cream)">The Super User role cannot be modified. It has full system access by design for security and recovery purposes.</p>
    </div>
    <?php else: ?>
    <div class="panel" style="max-width:800px">
      <form method="post" action="/admin/roles/<?= $view->e($role['role']) ?>">
        <?= $view->csrf() ?>

        <div style="margin-bottom:1.5rem">
          <h3 style="margin-bottom:.8rem">System Permissions</h3>
          <?php 
          $systemPerms = ['all', 'manage_roles', 'manage_staff', 'api_access'];
          foreach ($systemPerms as $perm): 
            if (isset($availablePermissions[$perm])): ?>
          <div style="margin-bottom:.4rem">
            <label style="display:flex;align-items:center;gap:.5rem;color:var(--cream);cursor:pointer">
              <input type="checkbox" name="permissions[]" value="<?= $view->e($perm) ?>" 
                <?= in_array($perm, $currentPerms) ? 'checked' : '' ?>
                style="width:1.2rem;height:1.2rem">
              <span><code><?= $view->e($perm) ?></code> — <?= $view->e($availablePermissions[$perm]) ?></span>
            </label>
          </div>
          <?php endif; endforeach; ?>
        </div>

        <div style="margin-bottom:1.5rem">
          <h3 style="margin-bottom:.8rem">Customer Permissions</h3>
          <?php 
          $customerPerms = ['view_customers', 'search_customers', 'create_customers', 'edit_customers', 'delete_customers'];
          foreach ($customerPerms as $perm): 
            if (isset($availablePermissions[$perm])): ?>
          <div style="margin-bottom:.4rem">
            <label style="display:flex;align-items:center;gap:.5rem;color:var(--cream);cursor:pointer">
              <input type="checkbox" name="permissions[]" value="<?= $view->e($perm) ?>" 
                <?= in_array($perm, $currentPerms) ? 'checked' : '' ?>
                style="width:1.2rem;height:1.2rem">
              <span><code><?= $view->e($perm) ?></code> — <?= $view->e($availablePermissions[$perm]) ?></span>
            </label>
          </div>
          <?php endif; endforeach; ?>
        </div>

        <div style="margin-bottom:1.5rem">
          <h3 style="margin-bottom:.8rem">Operations Permissions</h3>
          <?php 
          $opsPerms = ['manage_billing', 'manage_appointments', 'view_tickets', 'respond_tickets', 'manage_tickets'];
          foreach ($opsPerms as $perm): 
            if (isset($availablePermissions[$perm])): ?>
          <div style="margin-bottom:.4rem">
            <label style="display:flex;align-items:center;gap:.5rem;color:var(--cream);cursor:pointer">
              <input type="checkbox" name="permissions[]" value="<?= $view->e($perm) ?>" 
                <?= in_array($perm, $currentPerms) ? 'checked' : '' ?>
                style="width:1.2rem;height:1.2rem">
              <span><code><?= $view->e($perm) ?></code> — <?= $view->e($availablePermissions[$perm]) ?></span>
            </label>
          </div>
          <?php endif; endforeach; ?>
        </div>

        <div style="margin-bottom:1.5rem">
          <h3 style="margin-bottom:.8rem">Communication Permissions</h3>
          <?php 
          $commPerms = ['send_messages', 'view_messages'];
          foreach ($commPerms as $perm): 
            if (isset($availablePermissions[$perm])): ?>
          <div style="margin-bottom:.4rem">
            <label style="display:flex;align-items:center;gap:.5rem;color:var(--cream);cursor:pointer">
              <input type="checkbox" name="permissions[]" value="<?= $view->e($perm) ?>" 
                <?= in_array($perm, $currentPerms) ? 'checked' : '' ?>
                style="width:1.2rem;height:1.2rem">
              <span><code><?= $view->e($perm) ?></code> — <?= $view->e($availablePermissions[$perm]) ?></span>
            </label>
          </div>
          <?php endif; endforeach; ?>
        </div>

        <div style="margin-bottom:1.5rem">
          <h3 style="margin-bottom:.8rem">Content & Marketing Permissions</h3>
          <?php 
          $contentPerms = ['manage_content', 'manage_marketing', 'view_analytics'];
          foreach ($contentPerms as $perm): 
            if (isset($availablePermissions[$perm])): ?>
          <div style="margin-bottom:.4rem">
            <label style="display:flex;align-items:center;gap:.5rem;color:var(--cream);cursor:pointer">
              <input type="checkbox" name="permissions[]" value="<?= $view->e($perm) ?>" 
                <?= in_array($perm, $currentPerms) ? 'checked' : '' ?>
                style="width:1.2rem;height:1.2rem">
              <span><code><?= $view->e($perm) ?></code> — <?= $view->e($availablePermissions[$perm]) ?></span>
            </label>
          </div>
          <?php endif; endforeach; ?>
        </div>

        <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.5rem 1.2rem;border:none">
          Save Permissions
        </button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>