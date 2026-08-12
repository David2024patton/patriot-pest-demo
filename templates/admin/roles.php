<?php
/**
 * admin/roles.php — roles management list (admin only).
 * Vars: $roles, $roleCounts, $flash.
 */
$roles = $data['roles'] ?? [];
$roleCounts = $data['roleCounts'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>Roles & Permissions</h1>
        <div class="sub">Manage system roles and their access permissions.</div>
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
      <div class="table-wrap"><table class="data">
        <thead><tr>
          <th>Role</th><th>Label</th><th>Staff Count</th><th>Permissions</th><th></th>
        </tr></thead>
        <tbody>
          <?php foreach ($roles as $r): ?>
          <tr>
            <td><span class="badge"><?= $view->e($r['role']) ?></span></td>
            <td><?= $view->e($r['label']) ?></td>
            <td class="num"><?= $view->e($roleCounts[$r['role']] ?? 0) ?></td>
            <td>
              <?php 
              $perms = json_decode($r['permissions'], true) ?: [];
              if (in_array('all', $perms)): ?>
                <span class="badge active">Full Access</span>
              <?php else: ?>
                <?= $view->e(implode(', ', array_slice($perms, 0, 3))) ?>
                <?php if (count($perms) > 3): ?>
                  +<?= count($perms) - 3 ?> more
                <?php endif; ?>
              <?php endif; ?>
            </td>
            <td>
              <a class="btn btn-ghost" href="/admin/roles/<?= $view->e($r['role']) ?>" style="font-size:.75rem;padding:.2rem .6rem">
                Edit Permissions
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>

    <div class="panel" style="margin-top:1.6rem">
      <h3>Permission Reference</h3>
      <div style="margin-top:.8rem;font-size:.85rem;color:var(--khaki)">
        <p><strong>System Permissions:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><code>all</code> — Full system access (admin/super-user only)</li>
          <li><code>manage_roles</code> — Manage roles and permissions</li>
          <li><code>manage_staff</code> — Manage staff accounts</li>
          <li><code>api_access</code> — API access</li>
        </ul>
        <p style="margin-top:.8rem"><strong>Customer Permissions:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><code>view_customers</code> — View customer information</li>
          <li><code>search_customers</code> — Search customers</li>
          <li><code>create_customers</code> — Create new customers</li>
          <li><code>edit_customers</code> — Edit customer records</li>
          <li><code>delete_customers</code> — Delete customer records</li>
        </ul>
        <p style="margin-top:.8rem"><strong>Operations Permissions:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><code>manage_billing</code> — Manage billing and payments</li>
          <li><code>manage_appointments</code> — Manage appointments</li>
          <li><code>view_tickets</code> — View support tickets</li>
          <li><code>respond_tickets</code> — Respond to tickets</li>
          <li><code>manage_tickets</code> — Manage all tickets</li>
        </ul>
        <p style="margin-top:.8rem"><strong>Communication Permissions:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><code>send_messages</code> — Send messages</li>
          <li><code>view_messages</code> — View message history</li>
        </ul>
        <p style="margin-top:.8rem"><strong>Content & Marketing Permissions:</strong></p>
        <ul style="margin-left:1.5rem;margin-top:.4rem;line-height:1.6">
          <li><code>manage_content</code> — Manage website content</li>
          <li><code>manage_marketing</code> — Manage marketing campaigns</li>
          <li><code>view_analytics</code> — View analytics and reports</li>
        </ul>
      </div>
    </div>
  </div>
</div>