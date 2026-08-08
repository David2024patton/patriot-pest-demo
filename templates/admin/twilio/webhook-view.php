<?php
$event = $data['event'] ?? [];
$payload = $data['payload'] ?? [];
$payloadFormatted = $data['payloadFormatted'] ?? '';
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Webhook Event Details</h1>
    <div class="sub">Webhook payload and processing information.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio/webhooks">◂ Webhooks</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="detail-card">
  <div class="detail-section">
    <h3>Event Information</h3>
    <div class="detail-row">
      <span class="label">Event Type:</span>
      <span class="value badge badge-info"><?= $view->e($event['event_type']) ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Twilio SID:</span>
      <span class="value mono"><?= $view->e($event['twilio_sid'] ?? 'N/A') ?></span>
    </div>
    <div class="detail-row">
      <span class="label">Processed:</span>
      <span class="value badge badge-<?= $event['processed'] ? 'success' : 'warning' ?>">
        <?= $event['processed'] ? 'Yes' : 'No' ?>
      </span>
    </div>
    <?php if (!empty($event['processed_at'])): ?>
    <div class="detail-row">
      <span class="label">Processed At:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($event['processed_at']))) ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div class="detail-section">
    <h3>Webhook Payload</h3>
    <div class="code-block">
      <pre><?= $view->e($payloadFormatted) ?></pre>
    </div>
  </div>

  <div class="detail-section">
    <h3>Timestamps</h3>
    <div class="detail-row">
      <span class="label">Received:</span>
      <span class="value"><?= $view->e(date('M j, Y H:i:s', strtotime($event['created_at']))) ?></span>
    </div>
  </div>
</div>