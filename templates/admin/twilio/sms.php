<?php
$logs = $data['logs'] ?? [];
$page = $data['page'] ?? 1;
$pages = $data['pages'] ?? 1;
$total = $data['total'] ?? 0;
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">SMS Logs</h1>
    <div class="sub">Message history and delivery tracking.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio">◂ Twilio</a>
    <a class="btn btn-primary" href="/admin/twilio/sms/new">➕ Send SMS</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="table-wrapper">
  <table class="data-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>Direction</th>
        <th>Phone</th>
        <th>Message</th>
        <th>Status</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($logs as $log): ?>
      <tr>
        <td><?= $view->e(date('M j, H:i', strtotime($log['created_at']))) ?></td>
        <td>
          <span class="badge badge-<?= $log['direction'] === 'inbound' ? 'info' : 'success' ?>">
            <?= $view->e(strtoupper($log['direction'])) ?>
          </span>
        </td>
        <td><?= $view->e($log['phone_number']) ?></td>
        <td class="truncate"><?= $view->e(substr($log['message'], 0, 50)) ?><?= strlen($log['message']) > 50 ? '...' : '' ?></td>
        <td>
          <span class="badge badge-<?= $log['status'] === 'delivered' ? 'success' : ($log['status'] === 'failed' ? 'error' : 'warning') ?>">
            <?= $view->e(strtoupper($log['status'])) ?>
          </span>
        </td>
        <td>
          <a class="btn btn-sm" href="/admin/twilio/sms/<?= $log['id'] ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($logs)): ?>
      <tr>
        <td colspan="6" class="empty">No SMS logs found.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a class="btn btn-ghost" href="/admin/twilio/sms?page=<?= $page - 1 ?>">◀ Previous</a>
  <?php endif; ?>
  <span class="page-info">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> total)</span>
  <?php if ($page < $pages): ?>
    <a class="btn btn-ghost" href="/admin/twilio/sms?page=<?= $page + 1 ?>">Next ▶</a>
  <?php endif; ?>
</div>
<?php endif; ?>