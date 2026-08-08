<?php
/**
 * Cost Dashboard - Project Valuation Receipt
 * Patriot Pest Control - Tactical Theme
 *
 * Doctrine: FEATURE TOGGLES IN SETTINGS
 */

declare(strict_types=1);

// Check if cost dashboard is enabled via environment variable
$enabled = getenv('COST_DASHBOARD_ENABLED');
if ($enabled === 'false' || $enabled === '0') {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Feature Disabled</title></head><body><h1>404 - Feature Not Available</h1></body></html>';
    exit;
}

// Allow access if enabled or toggle not set (default to enabled for safety)
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Project Valuation Receipt | Patriot Pest Control</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Black+Ops+One&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/cost.css">
</head>
<body class="cost-shell">
  <canvas id="bugfield" aria-hidden="true"></canvas>
  <div class="grain" aria-hidden="true"></div>

  <nav class="cost-nav">
    <a href="/" class="brand">
      <span class="star">★</span>
      PATRIOT PEST CONTROL
    </a>
    <a href="/" class="back-link">← Return to Main Site</a>
  </nav>

  <section class="cost-hero">
    <div class="wrap">
      <div class="eyebrow">// QUARTERMASTER REPORT</div>
      <h1>Project <em>Valuation</em> Receipt</h1>
      <p class="sub">Hostile invoice incoming. This is what a custom web application with CRM integration, payment processing, and tactical design actually costs on the open market. No hidden fees, no surprises. Just the raw numbers.</p>
    </div>
  </section>

  <section class="cost-content">
    <div class="wrap">

      <!-- Two big summary cards -->
      <div class="summary-cards">
        <div class="summary-card">
          <div class="label">Agency Floor</div>
          <div class="value" id="val-min">$75,000</div>
          <div class="sub">Entry-Level Quote</div>
        </div>
        <div class="summary-card">
          <div class="label">Agency Ceiling</div>
          <div class="value" id="val-max">$150,000</div>
          <div class="sub">Premium Market Rate</div>
        </div>
      </div>

      <!-- Receipt card -->
      <div class="receipt">
        <div class="receipt-header">
          <div class="receipt-logo">★ PATRIOT PEST CONTROL</div>
          <div class="receipt-meta">
            <span>REPORT #: PPC-2026-001</span>
            <span>DATE: <span id="receipt-date"></span></span>
            <span>CLASSIFICATION: UNCLASSIFIED</span>
          </div>
          <div class="hazard" style="margin:0.8rem 0 0 0"></div>
        </div>

        <div class="receipt-body">
          <div class="receipt-headings">
            <span class="rh-cat">LINE ITEM</span>
            <span class="rh-desc">SCOPE OF WORK</span>
            <span class="rh-cost">COST RANGE</span>
            <span class="rh-meter">THREAT METER</span>
          </div>

          <div class="receipt-items" id="receipt-items">
            <!-- Populated by JS -->
          </div>

          <div class="receipt-totals">
            <div class="total-row">
              <span>SUBTOTAL (LOW ESTIMATE)</span>
              <span class="total-amt" id="subtotal-low">$75,000</span>
            </div>
            <div class="total-row">
              <span>SUBTOTAL (HIGH ESTIMATE)</span>
              <span class="total-amt" id="subtotal-high">$150,000</span>
            </div>
            <div class="total-divider"></div>
            <div class="total-row grand">
              <span>GRAND TOTAL RANGE</span>
              <span class="total-amt grand-amt" id="grand-total">$75,000 to $150,000</span>
            </div>
            <div class="stamp-row">
              <span class="stamp">APPROVED<br>FOR REVIEW</span>
            </div>
          </div>
        </div>

        <div class="receipt-footer">
          <p><strong>PAYMENT TERMS:</strong> Net 30 upon contract signing. 50% upfront, 50% on delivery.</p>
          <p><strong>TIMELINE:</strong> <span id="receipt-timeline">3-6 months</span>. Full agency team deployment.</p>
          <p><strong>WARRANTY:</strong> 90-day bug-free guarantee. Pests and pixel bugs eliminated.</p>
        </div>
      </div>

      <!-- Pricing factors -->
      <div class="factors-section">
        <h3>Field Notes: Agency Pricing Factors</h3>
        <ul class="factors-list" id="factors-list">
          <!-- Populated by JS -->
        </ul>
      </div>

    </div>
  </section>

  <script src="./assets/js/cost.js"></script>
</body>
</html>
