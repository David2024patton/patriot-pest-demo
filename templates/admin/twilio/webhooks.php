<?php
$events = $data['events'] ?? [];
$page = $data['page'] ?? 1;
$pages = $data['pages'] ?? 1;
$total = $data['total'] ?? 0;
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Webhook Events</h1>
    <div class="sub">Twilio webhook event monitoring and processing.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio">◂ Twilio</a>
    <form method="POST" action="/admin/twilio/webhooks/process" class="inline-form">
      <?= $view->csrf() ?>
      <button type="submit" class="btn btn-primary">Process Pending</button>
    </form>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="table-wrapper">
  <table class="data-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>Event Type</th>
        <th>Twilio SID</th>
        <th>Processed</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($events as $event): ?>
      <tr>
        <td><?= $view->e(date('M j, H:i', strtotime($event['created_at']))) ?></td>
        <td>
          <span class="badge badge-info"><?= $view->e($event['event_type']) ?></span>
        </td>
        <td class="mono"><?= $view->e(substr($event['twilio_sid'] ?? 'N/A', 0, 20)) ?><?= strlen($event['twilio_sid'] ?? '') > 20 ? '...' : '' ?></td>
        <td>
          <span class="badge badge-<?= $event['processed'] ? 'success' : 'warning' ?>">
            <?= $event['processed'] ? 'Yes' : 'No' ?>
          </span>
        </td>
        <td>
          <a class="btn btn-sm" href="/admin/twilio/webhooks/<?= $event['id'] ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($events)): ?>
      <tr>
        <td colspan="5" class="empty">No webhook events found.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a class="btn btn-ghost" href="/admin/twilio/webhooks?page=<?= $page - 1 ?>">◀ Previous</a>
  <?php endif; ?>
  <span class="page-info">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> total)</span>
  <?php if ($page < $pages): ?>
    <a class="btn btn-ghost" href="/admin/twilio/webhooks?page=<?= $page + 1 ?>">Next ▶</a>
  <?php endif; ?>
</div>
<?php endif; ?>