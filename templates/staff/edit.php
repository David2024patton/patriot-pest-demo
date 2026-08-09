<?php
/**
 * staff/edit.php — new/edit staff form (admin only).
 * Vars: $staffMember (null for new, row for edit), $roles, $isAdmin, $flash.
 */
$staffMember = $data['staffMember'] ?? null;
$roles       = $data['roles'] ?? [];
$isAdmin     = $data['isAdmin'] ?? false;
$flash       = $data['flash'] ?? null;
$isNew       = $staffMember === null;
$action      = $isNew ? '/admin/staff' : '/admin/staff/' . $staffMember['id'];
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1><?= $isNew ? 'Add Staff Member' : 'Edit Staff Member' ?></h1>
        <div class="sub"><?= $isNew ? 'Create a new team account. They log in with their email — no password needed.' : 'Update account details and role.' ?></div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin/staff">Back to Staff List</a>
      </div>
    </div>

    <?php if ($flash): ?>
    <div class="flash <?= !empty($flash['success']) ? 'success' : 'error' ?>">
      <?php if (!empty($flash['success'])): ?>
        <?= $view->e($flash['success']) ?>
      <?php elseif (!empty($flash['errors'])): ?>
        <?php foreach ($flash['errors'] as $field => $msgs): ?>
          <div><?= $view->e(ucfirst($field)) ?>: <?= $view->e(implode(' ', $msgs)) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="panel" style="max-width:600px">
      <form method="post" action="<?= $view->e($action) ?>">
        <input type="hidden" name="csrf_token" value="<?= $view->e(\PPC\Core\Csrf::token()) ?>">

        <div class="field" style="margin-bottom:1rem">
          <label for="name" style="color:var(--cream)">Name</label>
          <input type="text" id="name" name="name" value="<?= $view->e($staffMember['name'] ?? '') ?>" required maxlength="200"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
        </div>

        <div class="field" style="margin-bottom:1rem">
          <label for="email" style="color:var(--cream)">Email</label>
          <input type="email" id="email" name="email" value="<?= $view->e($staffMember['email'] ?? '') ?>" required maxlength="254"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          <?php if ($isNew): ?>
          <div style="font-size:.75rem;color:var(--khaki);margin-top:.3rem">This is the login identifier. They receive a one-time code via email.</div>
          <?php endif; ?>
        </div>

        <div class="field" style="margin-bottom:1rem">
          <label for="role" style="color:var(--cream)">Role</label>
          <select id="role" name="role" required
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
            <?php foreach ($roles as $r): ?>
            <option value="<?= $view->e($r['role']) ?>" <?= ($staffMember['role'] ?? '') === $r['role'] ? 'selected' : '' ?>>
              <?= $view->e($r['label']) ?> (<?= $view->e($r['role']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <div style="font-size:.75rem;color:var(--khaki);margin-top:.3rem">
            Each role has different permissions. Only Admins have full access.
          </div>
        </div>

        <?php if (!$isNew): ?>
        <div class="field" style="margin-bottom:1rem">
          <label style="color:var(--cream)">Status</label>
          <div style="color:var(--cream);margin-top:.3rem">
            <span class="badge <?= $staffMember['active'] ? 'active' : 'cancelled' ?>"><?= $staffMember['active'] ? 'Active' : 'Inactive' ?></span>
            · Use the toggle on the staff list to change status.
          </div>
        </div>

        <div style="font-size:.75rem;color:var(--khaki);margin-bottom:1rem">
          Created: <?= $view->e(date('M j, Y', strtotime($staffMember['created_at']))) ?>
          <?php if ($staffMember['last_login']): ?>
          · Last login: <?= $view->e(date('M j, Y g:i A', strtotime($staffMember['last_login']))) ?>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.5rem 1.2rem;border:none">
          <?= $isNew ? 'Add Staff Member' : 'Save Changes' ?>
        </button>
      </form>
    </div>
  </div>
</div>
