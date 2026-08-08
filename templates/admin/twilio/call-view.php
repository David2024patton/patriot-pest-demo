<?php
$call = $data['call'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Call Details</h1>
    <div class="sub">Voice call details and recording.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio/calls">◂ Call Logs</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="detail-card">
  <div class="detail-section">
    <h3>Call Information</h3>
    <div class="detail-row">
      <span class="label">Direction:</span>
      <span class="value badge badge-<?= $call['direction'] === 'inbound' ? 'info' : 'success' ?>">
        <?= $view->e(strtoupper($call['direction'])) ?>
      </span>
    </div>
    <div class="detail-row">
      <span class="label">Phone Number:</span>
      <span class="value"><?= $view->e($call['phone_number']) ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Duration:</span>
      <span class="value"><?= $call['duration'] > 0 ? $view->e($call['duration'] . ' seconds') : 'N/A' ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Status:</span>
      <span class="value badge badge-<?= $call['status'] === 'completed' ? 'success' : ($call['status'] === 'failed' ? 'error' : 'warning') ?>">
        <?= $view->e(strtoupper($call['status'])) ?>
      </span>
    </div>
    <div class="detail-row">
      <span class="label">Twilio SID:</span>
      <span class="value mono"><?= $view->e($call['twilio_sid'] ?? 'N/A') ?></span>
    </div>
  </div>

  <?php if (!empty($call['recording_url'])): ?>
  <div class="detail-section">
    <h3>Call Recording</h3>
    <div class="audio-player">
      <audio controls src="<?= $view->e($call['recording_url']) ?>"></audio>
      <a href="<?= $view->e($call['recording_url']) ?>" target="_blank" class="btn btn-ghost">Download Recording ↗</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($call['transcription'])): ?>
  <div class="detail-section">
    <h3>Transcription</h3>
    <div class="transcription-content">
      <?= $view->e($call['transcription']) ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($call['error_message'])): ?>
  <div class="detail-section">
    <h3>Error Information</h3>
    <div class="error-message">
      <?= $view->e($call['error_message']) ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="detail-section">
    <h3>Timestamps</h3>
    <div class="detail-row">
      <span class="label">Created:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($call['created_at']))) ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Updated:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($call['updated_at']))) ?></span>
    </div>
  </div>
</div>