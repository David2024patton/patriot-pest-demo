<?php
$sms = $data['sms'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">SMS Details</h1>
    <div class="sub">Message details and delivery information.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio/sms">◂ SMS Logs</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="detail-card">
  <div class="detail-section">
    <h3>Message Information</h3>
    <div class="detail-row">
      <span class="label">Direction:</span>
      <span class="value badge badge-<?= $sms['direction'] === 'inbound' ? 'info' : 'success' ?>">
        <?= $view->e(strtoupper($sms['direction'])) ?>
      </span>
    </div>
    <div class="detail-row">
      <span class="label">Phone Number:</span>
      <span class="value"><?= $view->e($sms['phone_number']) ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Status:</span>
      <span class="value badge badge-<?= $sms['status'] === 'delivered' ? 'success' : ($sms['status'] === 'failed' ? 'error' : 'warning') ?>">
        <?= $view->e(strtoupper($sms['status'])) ?>
      </span>
    </div>
    <div class="detail-row">
      <span class="label">Twilio SID:</span>
      <span class="value mono"><?= $view->e($sms['twilio_sid'] ?? 'N/A') ?></span>
    </div>
  </div>

  <div class="detail-section">
    <h3>Message Content</h3>
    <div class="message-content">
      <?= $view->e($sms['message']) ?>
    </div>
  </div>

  <?php if (!empty($sms['media_url'])): ?>
  <div class="detail-section">
    <h3>Media Attachment</h3>
    <div class="media-preview">
      <a href="<?= $view->e($sms['media_url']) ?>" target="_blank" class="btn btn-ghost">View Media ↗</a>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($sms['error_message'])): ?>
  <div class="detail-section">
    <h3>Error Information</h3>
    <div class="error-message">
      <?= $view->e($sms['error_message']) ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="detail-section">
    <h3>Timestamps</h3>
    <div class="detail-row">
      <span class="label">Created:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($sms['created_at']))) ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Updated:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($sms['updated_at']))) ?></span>
    </div>
  </div>
</div>