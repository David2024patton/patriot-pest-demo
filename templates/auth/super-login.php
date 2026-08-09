<?php
/**
 * Superuser login form — dedicated elevated surface at /su.
 * Single email field; no identifier matching, no customer lookup.
 * Separate from the unified /login page.
 */
declare(strict_types=1);
?>
<div class="auth-container">
    <h1>Superuser Sign In</h1>
    <p class="auth-subtitle">Enter your email to receive a secure sign-in code.</p>

    <?php if (isset($flash['error'])): ?>
        <div class="auth-alert auth-alert--error"><?= htmlspecialchars($flash['error']) ?></div>
    <?php endif; ?>

    <?php if (isset($flash['sent'])): ?>
        <div class="auth-alert auth-alert--success">
            Code sent to <strong><?= htmlspecialchars($flash['to']) ?></strong>.
            Check your email and enter the code below.
        </div>
    <?php endif; ?>

    <form method="post" action="/su" class="auth-form">
        <?= \PPC\Core\Csrf::field() ?>
        <div class="form-group">
            <label for="email">Email address</label>
            <input type="email" id="email" name="email" required autofocus
                   autocomplete="email" placeholder="you@example.com" maxlength="254">
        </div>
        <button type="submit" class="btn btn--primary btn--block">Send Code</button>
    </form>

    <p class="auth-footer">
        <a href="/login">Staff &amp; customer sign in</a>
    </p>
</div>
