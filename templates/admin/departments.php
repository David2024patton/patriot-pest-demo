<?php
/**
 * admin/departments.php — departments management (admin only).
 * Vars: $departments (tree structure), $flash.
 */
$departments = $data['departments'] ?? [];
$flash = $data['flash'] ?? null;

function renderDepartmentTree($dept, $level = 0) {
    global $view;
    $indent = str_repeat('└─', $level);
    $indent = $level > 0 ? $indent . ' ' : '';
    ?>
    <tr>
      <td>
        <?= $view->e($indent) ?><?= $view->e($dept['name']) ?>
        <?php if ($level > 0): ?>
        <span style="font-size:.75rem;color:var(--khaki)">(sub-department)</span>
        <?php endif; ?>
      </td>
      <td class="num"><?= $view->e($dept['staff_count']) ?> staff</td>
      <td>
        <a class="btn btn-ghost" href="/admin/departments/<?= $view->e($dept['id']) ?>" style="font-size:.75rem;padding:.2rem .6rem">
          Edit
        </a>
        <?php if ($dept['staff_count'] == 0 && empty($dept['children'])): ?>
        <form method="post" action="/admin/departments/<?= $view->e($dept['id']) ?>/delete" style="display:inline;margin-left:.4rem">
          <?= $view->csrf() ?>
          <button type="submit" class="btn btn-ghost" style="font-size:.75rem;padding:.2rem .6rem;color:var(--red)" 
            onclick="return confirm('Delete this department?')">
            Delete
          </button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
    <?php
    foreach ($dept['children'] as $child) {
        renderDepartmentTree($child, $level + 1);
    }
}
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>Departments</h1>
        <div class="sub">Organizational structure and reporting hierarchy.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin">Admin Home</a>
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

    <div class="panel">
      <form method="post" action="/admin/departments" style="margin-bottom:1.5rem;padding-bottom:1.5rem;border-bottom:1px solid var(--olive-700)">
        <?= $view->csrf() ?>
        <div style="display:flex;gap:1rem;align-items:flex-end">
          <div style="flex:1">
            <label for="dept_name" style="color:var(--cream);font-size:.85rem">New Department Name</label>
            <input type="text" id="dept_name" name="name" required maxlength="200"
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
          </div>
          <div style="flex:1">
            <label for="parent_dept" style="color:var(--cream);font-size:.85rem">Parent Department (optional)</label>
            <select id="parent_dept" name="parent_id" 
              style="width:100%;padding:.5rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.3rem">
              <option value="">None (Top-level)</option>
              <?php 
              function flattenDepts($depts, $level = 0) {
                  global $view;
                  foreach ($depts as $dept) {
                      $indent = str_repeat('└─', $level);
                      $indent = $level > 0 ? $indent . ' ' : '';
                      echo '<option value="' . $view->e($dept['id']) . '">' . $view->e($indent . $dept['name']) . '</option>';
                      if (!empty($dept['children'])) {
                          flattenDepts($dept['children'], $level + 1);
                      }
                  }
              }
              flattenDepts($departments);
              ?>
            </select>
          </div>
          <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.5rem 1.2rem;border:none">
            + Add Department
          </button>
        </div>
      </form>

      <div class="table-wrap"><table class="data">
        <thead><tr>
          <th>Department</th><th>Staff</th><th>Actions</th>
        </tr></thead>
        <tbody>
          <?php if (empty($departments)): ?>
          <tr><td colspan="3" class="empty">No departments yet. Create one above to get started.</td></tr>
          <?php else: ?>
            <?php foreach ($departments as $dept): ?>
              <?php renderDepartmentTree($dept); ?>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table></div>
    </div>

    <div class="panel" style="margin-top:1.6rem">
      <h3>Department Hierarchy</h3>
      <div style="margin-top:.8rem;font-size:.85rem;color:var(--khaki)">
        <p>Departments can be organized in a hierarchy for reporting structure:</p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><strong>Top-level departments</strong> are main divisions (e.g., Operations, Sales, Marketing)</li>
          <li><strong>Sub-departments</strong> report to parent departments (e.g., Inside Sales under Sales)</li>
          <li>Staff members can be assigned to any department in the hierarchy</li>
          <li>Departments with staff or sub-departments cannot be deleted</li>
        </ul>
      </div>
    </div>
  </div>
</div>