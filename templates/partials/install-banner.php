<?php
/**
 * partials/install-banner.php - PWA install prompt (mobile/tablet only).
 * Rendered by layouts/main.php and layouts/app.php. The banner is hidden
 * by default (pwa-install.css) and revealed by pwa-install.js only when
 * beforeinstallprompt fires on a mobile/tablet viewport and the user has
 * not dismissed it. Never blocks content: fixed bottom bar, dismissible.
 * No em dashes (U+2014) anywhere in the copy.
 */
?>
<div class="ppc-install" id="ppc-install-banner" role="dialog" aria-live="polite" aria-label="Install app">
  <div class="ppc-install-card">
    <img class="ppc-install-ico" src="<?= $view->asset('icons/icon-192.png') ?>" alt="" width="48" height="48">
    <div class="ppc-install-copy">
      <span class="ppc-install-tag">APP</span>
      <strong>Install Patriot Pest</strong>
      <span>One tap adds it to your home screen for instant access.</span>
    </div>
    <div class="ppc-install-actions">
      <button type="button" class="ppc-install-btn" id="ppc-install-btn">Install</button>
      <button type="button" class="ppc-install-x" id="ppc-install-dismiss" aria-label="Dismiss install prompt">&times;</button>
    </div>
  </div>
</div>
