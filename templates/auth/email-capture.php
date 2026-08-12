<?php
/**
 * auth/email-capture.php - first-time login step for customers who exist in
 * FieldRoutes but have no email on file. They enter an email, it's saved to
 * FR (source of truth) + the local cache, then the sign-in code goes there.
 * Vars: $identifier (what they logged in with), $flash ('error' | 'need_email').
 */
$identifier = $data['identifier'] ?? '';
$flash      = $data['flash'] ?? null;
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
      <h1 class="authx-headline">One more <em>step.</em></h1>
      <p class="authx-lede">Your account has no email on file yet. Add one and we'll send your secure sign-in code straight there.</p>
      <p class="authx-new">Prefer to skip? Call your local line and we'll help you get signed in another way.</p>

      <div class="authx-photo" aria-hidden="true">
        <img src="<?= $view->asset('img/pests/rodents.jpg') ?>" alt="">
        <div class="scan"></div>
        <span class="tag">FIRST-TIME SETUP // OPEN</span>
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

  <!-- ===== right / email panel ===== -->
  <div class="authx-form">
    <div class="authx-card2">
      <span class="step">WELCOME</span>
      <h1>Add your email</h1>
      <p class="sub">
        We found your account<?php if ($identifier !== ''): ?> via <span class="sent"><?= $view->e($identifier) ?></span><?php endif; ?>.
        Add an email and we'll use it for your sign-in code and service updates.
      </p>

      <?php if (!empty($flash['error'])): ?>
        <div class="notice error"><?= $view->e($flash['error']) ?></div>
      <?php endif; ?>

      <form method="post" action="/login/email" novalidate>
        <?= $view->csrf() ?>
        <input type="email" name="email" placeholder="you@example.com" required maxlength="254"
               autocomplete="email" autofocus>
        <button type="submit" class="btn btn-primary" style="width:100%;margin-top:1rem">Send my code</button>
      </form>

      <p class="sub" style="margin-top:1.2rem">
        <a href="/login" style="color:var(--orange)">← Back to sign in</a>
      </p>
    </div>
  </div>
</div>
