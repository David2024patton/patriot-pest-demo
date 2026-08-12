<?php
/**
 * admin/department-edit.php — edit department (admin only).
 * Vars: $department, $departments, $flash.
 */
$department = $data['department'] ?? [];
$departments = $data['departments'] ?? [];
$flash = $data['flash'] ?? null;

function flattenDepts($depts, $level = 0, $excludeId = null) {
    global $view;
    foreach ($depts as $dept) {
        if ($dept['id'] == $excludeId) continue; // Don't allow self as parent
        $indent = str_repeat('└─', $level);
        $indent = $level > 0 ? $indent . ' ' : '';
        echo '<option value="' . $view->e($dept['id']) . '">' . $view->e($indent . $dept['name']) . '</option>';
        if (!empty($dept['children'])) {
            flattenDepts($dept['children'], $level + 1, $excludeId);
        }
    }
}
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>Edit Department</h1>
        <div class="sub">Update department details and hierarchy.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin/departments">Back to Departments</a>
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

    <div class="panel" style="max-width:600px">
      <form method="post" action="/admin/departments/<?= $view->e($department['id']) ?>">
        <?= $view->csrf() ?>

        <div class="field" style="margin-bottom:1rem">
          <label for="name" style="color:var(--cream)">Department Name</label>
          <input type="text" id="name" name="name" value="<?= $view->e($department['name']) ?>" required maxlength="200"
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
        </div>

        <div class="field" style="margin-bottom:1rem">
          <label for="parent_id" style="color:var(--cream)">Parent Department</label>
          <select id="parent_id" name="parent_id" 
            style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
            <option value="">None (Top-level)</option>
            <?php flattenDepts($departments, 0, $department['id']); ?>
          </select>
          <div style="font-size:.75rem;color:var(--khaki);margin-top:.3rem">
            Set a parent department to create reporting hierarchy. Leave empty for top-level department.
          </div>
        </div>

        <div style="font-size:.75rem;color:var(--khaki);margin-bottom:1rem">
          Created: <?= $view->e(date('M j, Y', strtotime($department['created_at']))) ?>
        </div>

        <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.5rem 1.2rem;border:none">
          Save Changes
        </button>
      </form>
    </div>
  </div>
</div>