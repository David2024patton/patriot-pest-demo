<?php
$stats = $data['stats'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Twilio Command Center</h1>
    <div class="sub">Telecommunications operations and monitoring.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/staff-dashboard">◂ Dashboard</a>
    <a class="btn btn-primary" href="/admin/twilio/sms/new">➕ Send SMS</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-label">SMS Total</div>
    <div class="stat-value"><?= $stats['sms_total'] ?? 0 ?></div>
    <div class="stat-sub"><?= $stats['sms_pending'] ?? 0 ?> pending</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Calls Total</div>
    <div class="stat-value"><?= $stats['calls_total'] ?? 0 ?></div>
    <div class="stat-sub"><?= $stats['calls_active'] ?? 0 ?> active</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Voicemails</div>
    <div class="stat-value"><?= $stats['voicemails'] ?? 0 ?></div>
    <div class="stat-sub"><?= $stats['voicemails_new'] ?? 0 ?> new</div>
  </div>
  <div class="stat-card">
    <div class="stat-label">Webhooks</div>
    <div class="stat-value"><?= $stats['webhooks'] ?? 0 ?></div>
    <div class="stat-sub"><?= $stats['webhooks_pending'] ?? 0 ?> pending</div>
  </div>
</div>

<div class="nav-grid">
  <a class="nav-card" href="/admin/twilio/sms">
    <div class="nav-icon">✉</div>
    <div class="nav-title">SMS Logs</div>
    <div class="nav-desc">Message history and delivery status</div>
  </a>
  <a class="nav-card" href="/admin/twilio/calls">
    <div class="nav-icon">📞</div>
    <div class="nav-title">Call Logs</div>
    <div class="nav-desc">Voice call recordings and transcriptions</div>
  </a>
  <a class="nav-card" href="/admin/twilio/voicemail">
    <div class="nav-icon">📼</div>
    <div class="nav-title">Voicemail</div>
    <div class="nav-desc">Inbox and voicemail management</div>
  </a>
  <a class="nav-card" href="/admin/twilio/webhooks">
    <div class="nav-icon">🔗</div>
    <div class="nav-title">Webhooks</div>
    <div class="nav-desc">Event monitoring and processing</div>
  </a>
</div>