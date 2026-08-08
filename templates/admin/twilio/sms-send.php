<?php
$flash = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Send SMS</h1>
    <div class="sub">Compose and send a new SMS message.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/admin/twilio/sms">◂ SMS Logs</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['error'])): ?><div class="notice error"><?= $view->e($flash['error']) ?></div><?php endif; ?>
<?php if (!empty($flash['errors'])): ?>
<div class="notice error"><strong>Please fix the following:</strong>
  <ul><?php foreach ($flash['errors'] as $e): ?><li><?= $view->e(is_array($e) ? implode(', ', $e) : $e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" action="/admin/twilio/sms/send" class="form-card">
  <?= $view->csrf() ?>
  
  <div class="form-group">
    <label for="to">To Phone Number (E.164 format)</label>
    <input type="text" id="to" name="to" placeholder="+12025551234" required>
    <div class="form-hint">Format: +CountryCode Number (e.g., +12025551234)</div>
  </div>

  <div class="form-group">
    <label for="message">Message</label>
    <textarea id="message" name="message" rows="4" maxlength="1600" required placeholder="Enter your message here..."></textarea>
    <div class="form-hint">Max 1600 characters. Standard SMS is 160 characters.</div>
  </div>

  <div class="form-group">
    <label for="media_url">Media URL (Optional)</label>
    <input type="url" id="media_url" name="media_url" placeholder="https://example.com/image.jpg">
    <div class="form-hint">For MMS messages, provide a publicly accessible media URL.</div>
  </div>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary">Send Message</button>
    <a class="btn btn-ghost" href="/admin/twilio/sms">Cancel</a>
  </div>
</form>