<section class="admin-settings">
  <h1>Tracking Settings</h1>
  <p class="muted">Configure tracking IDs for Google Analytics, Google Ads, Facebook Pixel, and Microsoft Clarity. Values saved here override the .env defaults.</p>

  <?php if (!empty($flash['success'])): ?>
    <div class="flash flash-success"><?= htmlspecialchars($flash['success']) ?></div>
  <?php endif; ?>

  <form method="post" action="/admin/settings" class="form-stack">
    <?= \PPC\Core\Csrf::field() ?>

    <?php
    $fields = [
      'gtag_id'     => ['Google Analytics GAIS Measurement ID', 'G-XXXXXXXXXX', 'https://analytics.google.com'],
      'gads_id'     => ['Google Ads ID', 'AW-XXXXXXXXXX', 'https://ads.google.com'],
      'fb_pixel_id' => ['Facebook Pixel ID', '123456789012345', 'https://business.facebook.com'],
      'clarity_id'  => ['Microsoft Clarity ID', 'abc1234567', 'https://clarity.microsoft.com'],
    ];
    foreach ($fields as $k => [$label, $placeholder, $url]):
      $val = htmlspecialchars($settings[$k] ?? '');
      $err = $flash['errors'][$k] ?? null;
    ?>
      <div class="form-group">
        <label for="<?= $k ?>"><?= htmlspecialchars($label) ?> <small><a href="<?= htmlspecialchars($url) ?>" target="_blank">via &rarr;</a></small></label>
        <input type="text" id="<?= $k ?>" name="<?= $k ?>" value="<?= $val ?>" placeholder="<?= htmlspecialchars($placeholder) ?>">
        <?php if ($err): ?><span class="error"><?= htmlspecialchars($err) ?></span><?php endif; ?>
      </div>
    <?php endforeach; ?>

    <button type="submit" class="btn btn-primary">Save Settings</button>
  </form>

  <p class="muted" style="margin-top:16px;">Blank fields clear the DB override and fall back to the <code>.env</code> default.</p>
</section>
