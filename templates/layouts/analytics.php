<?php
/**
 * layouts/analytics.php — Google Analytics 4 + Google Ads (gtag.js).
 *
 * Ported from the original site (includes/header.php). IDs come from .env:
 *   GTAG_ID  — GA4 measurement id (G-…). The original shipped a placeholder
 *              (G-XXXXXXXXXX); put the real id here when you have it.
 *   GADS_ID  — Google Ads conversion id (AW-18082646992 in the original).
 *
 * Emitted only when at least one id is configured AND we're not in local dev
 * (set ANALYTICS_ENABLED=true to force it locally). This keeps localhost
 * traffic out of the production reports. Also wires the original's phone-click
 * event so every tel: tap is tracked as an engagement.
 */
use PPC\Core\Config;

$__gtag = Config::get('GTAG_ID');
$__gads = Config::get('GADS_ID');
$__on   = ($__gtag || $__gads) && (!Config::isLocal() || Config::bool('ANALYTICS_ENABLED'));
if (!$__on) {
    return;
}
// The gtag.js loader needs one id in the src; config() the rest below.
$__loaderId = $__gtag ?: $__gads;
?>
<!-- Google tag (gtag.js) — Analytics & Ads -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $view->e($__loaderId) ?>"></script>
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
