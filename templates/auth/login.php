<?php
/**
 * auth/login.php — the customer sign-in page.
 *
 * Customer-facing by design: it welcomes new and existing customers and never
 * describes the internal auth machinery. One field (email / phone / account #),
 * then a secure emailed code. The brand panel is alive — ambient grid, a slow
 * radar sweep, a rotating threat photo, and the visitor's local phone line.
 * Vars: $flash (array|null: 'error' | 'sent' | 'to').
 */
$flash = $data['flash'] ?? null;
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
      <h1 class="authx-headline">Welcome <em>back.</em></h1>
      <p class="authx-lede">Your appointments, your technician, your plan — everything about your pest-free home, right here.</p>
      <p class="authx-new"><b>New around here?</b> Welcome aboard — enter your details and we'll get you set up in seconds.</p>

      <div class="authx-photo" aria-hidden="true">
        <img id="authx-img" src="<?= $view->asset('img/pests/ants.jpg') ?>" alt="">
        <div class="scan"></div>
        <span class="tag" id="authx-tag">ON WATCH // ANTS</span>
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

  <!-- ===== right / form panel ===== -->
  <div class="authx-form">
    <div class="authx-card2">
      <span class="step">ACCOUNT ACCESS</span>
      <h1>Sign in to your account</h1>
      <p class="sub">Enter the email, phone number, or account number on your account and we'll send you a secure code. No password to remember.</p>

      <?php if (!empty($flash['error'])): ?>
        <div class="notice error"><?= $view->e($flash['error']) ?></div>
      <?php endif; ?>

      <form method="post" action="/login" novalidate>
        <?= $view->csrf() ?>
        <div class="authx-field">
          <label for="identifier">Email, phone, or account number</label>
          <input type="text" id="identifier" name="identifier" autocomplete="username" required autofocus
                 placeholder="you@example.com  ·  (509) 555-0101  ·  1001">
          <div class="hint">Use whichever you have on file — we'll recognize you and email a one-time code.</div>
        </div>
        <button type="submit" class="authx-btn">Send My Secure Code ▸</button>
      </form>

      <p class="authx-foot">Having trouble? <a href="/help">Get help signing in</a></p>
    </div>
  </div>
</div>

<script>
(function () {
  // Live clock in the brand panel.
  var clock = document.getElementById('authx-clock');
  function tick() {
    var d = new Date();
    clock.textContent = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  if (clock) { tick(); setInterval(tick, 30000); }

  // Rotate the "on watch" threat photo with a soft crossfade.
  var shots = [
    ['ants.jpg', 'ANTS'], ['spiders.jpg', 'SPIDERS'],
    ['rodents.jpg', 'RODENTS'], ['scorpions.jpg', 'SCORPIONS']
  ];
  var img = document.getElementById('authx-img');
  var tag = document.getElementById('authx-tag');
  if (img && tag) {
    var i = 0;
    setInterval(function () {
      img.style.opacity = '0';
      setTimeout(function () {
        i = (i + 1) % shots.length;
        img.src = '/assets/img/pests/' + shots[i][0];
        tag.textContent = 'ON WATCH // ' + shots[i][1];
        img.onload = function () { img.style.opacity = '1'; };
      }, 350);
    }, 4200);
  }
})();
</script>
