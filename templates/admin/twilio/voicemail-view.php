<?php
$vm = $data['vm'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Voicemail Details</h1>
    <div class="sub">Voicemail playback and management.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio/voicemail">◂ Inbox</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="detail-card">
  <div class="detail-section">
    <h3>Voicemail Information</h3>
    <div class="detail-row">
      <span class="label">Phone Number:</span>
      <span class="value"><?= $view->e($vm['phone_number']) ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Duration:</span>
      <span class="value"><?= $vm['duration'] > 0 ? $view->e($vm['duration'] . ' seconds') : 'N/A' ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Status:</span>
      <span class="value badge badge-<?= $vm['status'] === 'new' ? 'success' : ($vm['status'] === 'listened' ? 'info' : 'warning') ?>">
        <?= $view->e(strtoupper($vm['status'])) ?>
      </span>
    </div>
    <?php if (!empty($vm['call_sid'])): ?>
    <div class="detail-row">
      <span class="label">Call SID:</span>
      <span class="value mono"><?= $view->e($vm['call_sid']) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="detail-section">
    <h3>Audio Playback</h3>
    <div class="audio-player">
      <audio controls src="<?= $view->e($vm['audio_url']) ?>"></audio>
      <a href="<?= $view->e($vm['audio_url']) ?>" target="_blank" class="btn btn-ghost">Download Audio ↗</a>
    </div>
  </div>

  <?php if (!empty($vm['transcription'])): ?>
  <div class="detail-section">
    <h3>Transcription</h3>
    <div class="transcription-content">
      <?= $view->e($vm['transcription']) ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="detail-section">
    <h3>Status Update</h3>
    <form method="POST" action="/admin/twilio/voicemail/<?= $vm['id'] ?>/update" class="inline-form">
      <?= $view->csrf() ?>
      <select name="status" class="form-select">
        <option value="new" <?= $vm['status'] === 'new' ? 'selected' : '' ?>>New</option>
        <option value="listened" <?= $vm['status'] === 'listened' ? 'selected' : '' ?>>Listened</option>
        <option value="archived" <?= $vm['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
        <option value="deleted" <?= $vm['status'] === 'deleted' ? 'selected' : '' ?>>Deleted</option>
      </select>
      <button type="submit" class="btn btn-primary">Update Status</button>
    </form>
  </div>

  <div class="detail-section">
    <h3>Timestamps</h3>
    <div class="detail-row">
      <span class="label">Received:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($vm['created_at']))) ?></span>
    </div>
  </div>
</div>