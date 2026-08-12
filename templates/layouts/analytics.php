<?php
/**
 * layouts/analytics.php — Google Analytics 4 + Google Ads + FB Pixel + MS Clarity.
 *
 * Ported from the original site (includes/header.php). IDs come from .env:
 *   GTAG_ID    — GA4 measurement id (G-…).
 *   GADS_ID    — Google Ads conversion id (AW-…).
 *   FB_PIXEL_ID — Meta/Facebook Pixel id (13-16 digits).
 *   MS_CLARITY_ID — Microsoft Clarity project id (8-12 chars).
 *
 * Database overrides (site_settings table): per-instance values set by the
 * admin at /admin/settings. Any key set in site_settings takes precedence
 * over the .env default.

 *
 * Emitted only when at least one id is configured AND we're not in local dev
 * (set ANALYTICS_ENABLED=true to force it locally).
 */
use PPC\Core\Config;
use PPC\Core\Database;

// Try DB overrides first (admin settings), fall back to .env
try {
    $dbSettings = [];
    $rows = Database::instance()->fetchAll(
        "SELECT key, value FROM site_settings WHERE key IN ('gtag_id','gads_id','fb_pixel_id','clarity_id')"
    );
    foreach ($rows as $r) {
        $dbSettings[$r['key']] = $r['value'];
    }
} catch (\Throwable $e) {
    $dbSettings = [];
}

$__gtag    = $dbSettings['gtag_id'] ?? Config::get('GTAG_ID');
$__gads    = $dbSettings['gads_id'] ?? Config::get('GADS_ID');
$__fbPixel = $dbSettings['fb_pixel_id'] ?? Config::get('FB_PIXEL_ID');
$__clarity = $dbSettings['clarity_id'] ?? Config::get('MS_CLARITY_ID');
$__on      = ($__gtag || $__gads || $__fbPixel || $__clarity)
             && (!Config::isLocal() || Config::bool('ANALYTICS_ENABLED'));
if (!$__on) {
    return;
}
$__loaderId = $__gtag ?: $__gads;  // gtag.js loader needs one id
?>
<!-- Google tag (gtag.js) — Analytics & Ads -->
<?php if ($__loaderId): ?>

<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $view->e($__loaderId) ?>"></script>
<?php endif; ?>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  <?php if ($__gtag): ?>
  gtag('config', <?= json_encode($__gtag) ?>, { 'send_page_view': true, 'anonymize_ip': true });
  <?php endif; ?>
  <?php if ($__gads): ?>
  gtag('config', <?= json_encode($__gads) ?>);
  <?php endif; ?>

  // Track phone clicks (ported from the original site).
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a[href^="tel:"]').forEach(function (link) {
      link.addEventListener('click', function () {
        gtag('event', 'phone_call', { 'event_category': 'engagement', 'event_label': 'Phone Click' });
      });
    });

    // Track data-track CTAs (quote_request, etc.)
    document.querySelectorAll('[data-track]').forEach(function (el) {
      el.addEventListener('click', function () {
        gtag('event', el.dataset.track, { 'event_category': 'engagement' });
      });
    });
  });
</script>
<?php if ($__fbPixel): ?>
<!-- Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?= json_encode($__fbPixel) ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= $view->e($__fbPixel) ?>&ev=PageView&noscript=1"></noscript>
<?php endif; ?>
<?php if ($__clarity): ?>
<!-- Microsoft Clarity -->
<script>
(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,'clarity','script',<?= json_encode($__clarity) ?>);
</script>
<?php endif; ?>
