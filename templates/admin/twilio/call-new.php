<?php
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Initiate Call</h1>
    <div class="sub">Start a new voice call via Twilio.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio/calls">◂ Call Logs</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>
<?php if (!empty($flash['errors'])): ?>
<div class="notice error"><strong>Please fix the following:</strong>
  <ul><?php foreach ($flash['errors'] as $e): ?><li><?= $view->e(is_array($e) ? implode(', ', $e) : $e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="/admin/twilio/calls/initiate" class="form-card">
  <?= $view->csrf() ?>
  
  <div class="form-group">
    <label for="to">To Phone Number (E.164 format)</label>
    <input type="text" id="to" name="to" placeholder="+12025551234" required>
    <div class="form-hint">Format: +CountryCode Number (e.g., +12025551234)</div>
  </div>

  <div class="form-group">
    <label for="url">TwiML URL</label>
    <input type="url" id="url" name="url" placeholder="https://your-domain.com/twiml.xml" required>
    <div class="form-hint">URL that returns TwiML instructions for call handling.</div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Initiate Call</button>
    <a class="btn btn-ghost" href="/admin/twilio/calls">Cancel</a>
  </div>
</form>