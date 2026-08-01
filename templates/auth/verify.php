<?php
/**
 * auth/verify.php - the code-entry step, styled to match the sign-in page.
 * Vars: $purpose, $action (POST endpoint), $sentTo (flash: 'sent' | 'to' | 'error').
 */
$action = $data['action'] ?? '/login/verify';
$sentTo = $data['sentTo'] ?? null;
?>
<div class="authx">

  <!-- ===== left / brand panel ===== -->
  <div class="authx-brand">
    <div class="authx-radar" aria-hidden="true"></div>

    <div class="authx-topline">
      <span><span class="dot"></span>PATRIOT PEST CONTROL</span>
      <span id="authx-clock" aria-hidden="true">--:--</span>
    </div>

    <div>
      <h1 class="authx-headline">Check your <em>inbox.</em></h1>
      <p class="authx-lede">We've sent a secure 6-digit code to the contact on file. Enter it here and you're in.</p>
      <p class="authx-new">Didn't get it? Give it a minute, check spam, or <a href="/login" style="color:var(--orange)">request a new code</a>.</p>

      <div class="authx-photo" aria-hidden="true">
        <img src="<?= $view->asset('img/pests/spiders.jpg') ?>" alt="">
        <div class="scan"></div>
        <span class="tag">SECURE CHANNEL // OPEN</span>
      </div>

      <div class="authx-trust">
        <span>Licensed &amp; Insured</span><span>90-Day Warranty</span><span>Family &amp; Pet Safe</span>
      </div>
    </div>

    <div class="authx-call">
      <span class="lbl">Prefer to talk? Your local line</span><br>
      <a href="<?= $view->phoneHref() ?>">☎ <?= $view->phone() ?> <small><?= $view->phoneLabel() ?></small></a>
    </div>
  </div>

  <!-- ===== right / code panel ===== -->
  <div class="authx-form">
    <div class="authx-card2">
      <span class="step">ALMOST THERE</span>
      <h1>Enter your code</h1>
      <p class="sub">
        <?php if (!empty($sentTo['to'])): ?>
          We sent a 6-digit code to <span class="sent"><?= $view->e($sentTo['to']) ?></span>. It expires in a few minutes and works once.
        <?php else: ?>
          Type the 6-digit code we sent you. It expires in a few minutes and works once.
        <?php endif; ?>
      </p>

      <?php if (!empty($sentTo['error'])): ?>
        <div class="notice error"><?= $view->e($sentTo['error']) ?></div>
      <?php endif; ?>

      <form method="post" action="<?= $view->e($action) ?>" novalidate>
        <?= $view->csrf() ?>
        <div class="authx-field">
          <label for="code">6-digit code</label>
          <input type="text" id="code" name="code" class="authx-code" inputmode="numeric" autocomplete="one-time-code"
                 pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="••••••">
          <div class="hint">Can't find it? Check your spam folder.</div>
        </div>
        <button type="submit" class="authx-btn">Verify &amp; Sign In ▸</button>
      </form>

      <p class="authx-foot"><a href="/login">◂ Use a different email or phone</a></p>
    </div>
  </div>
</div>

<script>
(function () {
  var clock = document.getElementById('authx-clock');
  function tick() {
    var d = new Date();
    clock.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  if (clock) { tick(); setInterval(tick, 30000); }
})();
</script>
