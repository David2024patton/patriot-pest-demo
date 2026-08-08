<?php
$voicemails = $data['voicemails'] ?? [];
$page = $data['page'] ?? 1;
$pages = $data['pages'] ?? 1;
$total = $data['total'] ?? 0;
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Voicemail Inbox</h1>
    <div class="sub">Voicemail messages and management.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio">◂ Twilio</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="table-wrapper">
  <table class="data-table">
    <thead>
      <tr>
        <th>Time</th>
        <th>Phone</th>
        <th>Duration</th>
        <th>Status</th>
        <th>Transcription</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($voicemails as $vm): ?>
      <tr>
        <td><?= $view->e(date('M j, H:i', strtotime($vm['created_at']))) ?></td>
        <td><?= $view->e($vm['phone_number']) ?></td>
        <td><?= $vm['duration'] > 0 ? $view->e($vm['duration'] . 's') : '—' ?></td>
        <td>
          <span class="badge badge-<?= $vm['status'] === 'new' ? 'success' : ($vm['status'] === 'listened' ? 'info' : 'warning') ?>">
            <?= $view->e(strtoupper($vm['status'])) ?>
          </span>
        </td>
        <td class="truncate"><?= !empty($vm['transcription']) ? $view->e(substr($vm['transcription'], 0, 30)) . '...' : '—' ?></td>
        <td>
          <a class="btn btn-sm" href="/admin/twilio/voicemail/<?= $vm['id'] ?>">View</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($voicemails)): ?>
      <tr>
        <td colspan="6" class="empty">No voicemails found.</td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($pages > 1): ?>
<div class="pagination">
  <?php if ($page > 1): ?>
    <a class="btn btn-ghost" href="/admin/twilio/voicemail?page=<?= $page - 1 ?>">◀ Previous</a>
  <?php endif; ?>
  <span class="page-info">Page <?= $page ?> of <?= $pages ?> (<?= $total ?> total)</span>
  <?php if ($page < $pages): ?>
    <a class="btn btn-ghost" href="/admin/twilio/voicemail?page=<?= $page + 1 ?>">Next ▶</a>
  <?php endif; ?>
</div>
<?php endif; ?>