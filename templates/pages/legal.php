<?php
/**
 * pages/legal.php - privacy policy & terms of use. Vars: $kind ('privacy'|'terms').
 */
$kind = $data['kind'] ?? 'privacy';
$isPrivacy = $kind === 'privacy';
$title = $isPrivacy ? 'Privacy Policy' : 'Terms of Use';
?>
<section class="block">
  <div class="wrap" style="max-width:840px">
    <div class="eyebrow">LEGAL // <?= strtoupper($kind) ?></div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(1.8rem,5vw,2.6rem);margin:.4rem 0 1rem"><?= $title ?></h1>
    <p class="muted mono" style="font-size:.8rem">Last updated: <?= date('F j, Y') ?></p>

    <?php if ($isPrivacy): ?>
    <div class="prose" style="margin-top:1.6rem">
      <p>Patriot Pest Control LLC ("we," "us") respects your privacy. This policy explains what information we collect, how we use it, and your choices.</p>
      <h3>Information We Collect</h3>
      <p>We collect information you provide directly, such as your name, contact details, address, and service information, when you request a quote, schedule service, or create an account. We also collect limited technical data (like browser type and pages visited) to improve our site.</p>
      <h3>How We Use Information</h3>
      <ul>
        <li>To provide, schedule, and manage pest control services</li>
        <li>To communicate about appointments, billing, and service reminders</li>
        <li>To respond to inquiries and provide customer support</li>
        <li>To improve our website and services</li>
      </ul>
      <h3>Sharing</h3>
      <p>We do not sell your personal information. We may share data with trusted service providers (such as our scheduling and payment systems) solely to operate our business and serve you, and where required by law.</p>
      <h3>Communications &amp; Opt-Out</h3>
      <p>You may opt out of non-essential communications at any time by contacting us or using the unsubscribe link in our emails. If you request no further contact, we will flag your account accordingly and honor that request.</p>
      <h3>Data Security</h3>
      <p>We use reasonable administrative, technical, and physical safeguards to protect your information. No method is 100% secure, but we work to protect your data.</p>
      <h3>Your Choices</h3>
      <p>You may request access to, correction of, or deletion of your personal information by contacting us at <a href="mailto:info@patriotpest.pro">info@patriotpest.pro</a>.</p>
      <h3>Contact</h3>
      <p>Questions about this policy? Contact Patriot Pest Control LLC, Spokane, WA 99201, <a href="mailto:info@patriotpest.pro">info@patriotpest.pro</a>, (509) 471-5767.</p>
    </div>
    <?php else: ?>
    <div class="prose" style="margin-top:1.6rem">
      <p>These Terms of Use govern your access to and use of the Patriot Pest Control website and services. By using this site, you agree to these terms.</p>
      <h3>Use of the Site</h3>
      <p>You agree to use this site only for lawful purposes. You may not misuse the site, interfere with its operation, or attempt to access it using automated means in ways that could damage or impair it.</p>
      <h3>Content</h3>
      <p>The content on this site, including text, graphics, logos, and images, is the property of Patriot Pest Control LLC or its licensors and is protected by applicable law. You may not reproduce or distribute it without permission.</p>
      <h3>Service Quotes &amp; Estimates</h3>
      <p>Quotes provided through this site are estimates. Final pricing depends on an assessment of your specific situation, including property size, pest type, and severity.</p>
      <h3>Warranty</h3>
      <p>Treatments are backed by our 90-day warranty and 100% satisfaction guarantee, subject to the terms provided at the time of service.</p>
      <h3>Disclaimer</h3>
      <p>This site is provided "as is" without warranties of any kind. We do not guarantee that the site will be uninterrupted or error-free.</p>
      <h3>Limitation of Liability</h3>
      <p>To the fullest extent permitted by law, Patriot Pest Control LLC is not liable for indirect or consequential damages arising from your use of this site.</p>
      <h3>Changes</h3>
      <p>We may update these terms from time to time. Continued use of the site after changes constitutes acceptance of the revised terms.</p>
      <h3>Contact</h3>
      <p>Questions? Contact Patriot Pest Control LLC, Spokane, WA 99201, <a href="mailto:info@patriotpest.pro">info@patriotpest.pro</a>, (509) 471-5767.</p>
    </div>
    <?php endif; ?>
  </div>
</section>
