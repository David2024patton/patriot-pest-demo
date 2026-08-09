<?php
/**
 * Cost Dashboard - Marketing Cost Explainer
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
  <title>Marketing Cost Explainer | Patriot Pest Control</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700;800&family=Black+Ops+One&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/cost.css?v=20260809c">
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

  <section class="cost-hero" id="cost-hero">
    <div id="xh-v"></div><div id="xh-h"></div><div id="xh-ring"></div>
    <div class="wrap">
      <div class="eyebrow" id="hero-eyebrow">// MISSION BRIEF</div>
      <h1 id="hero-title">What Does Marketing <em>Actually</em> Cost?</h1>
      <p class="sub" id="hero-sub">Real numbers from the digital marketing battlefield. No agency smoke, just data on what it takes to generate leads for a pest control company.</p>
    </div>
  </section>

  <section class="cost-content">
    <div class="wrap">

      <!-- SECTION 1: AGENCY INTEL BOARD (15-company recon, client-safe) -->
      <div class="explainer-section" id="intel-board">
        <div class="section-tag">SECTION 01 // AGENCY INTEL BOARD</div>
        <p class="intel-lede">We surveyed 15 marketing agencies, from SMB specialists to enterprise globals. Filter by tier, service, or AI strength. Tick two boxes to run a side-by-side compare. Estimated bands are management fees only; ad spend is always separate.</p>

        <div class="intel-controls" role="group" aria-label="Agency filters">
          <label class="ic-search"><span>SEARCH</span><input id="intel-q" type="search" placeholder="Agency name" autocomplete="off"></label>
          <label class="ic-select"><span>TIER</span><select id="intel-tier"><option value="">All tiers</option></select></label>
          <label class="ic-select"><span>AI STRENGTH</span><select id="intel-ai"><option value="">Any AI signal</option></select></label>
          <label class="ic-select"><span>SERVICE</span><select id="intel-svc"><option value="">All services</option></select></label>
          <label class="ic-check"><input type="checkbox" id="intel-published"> Published pricing only</label>
          <button class="ic-reset" id="intel-reset" type="button">Reset</button>
          <span class="intel-count" id="intel-count" aria-live="polite"></span>
        </div>

        <div class="intel-grid" id="intel-grid"><!-- Populated by JS --></div>

        <div class="compare-bar" id="compare-bar" hidden>
          <span id="compare-status">Select two agencies to compare.</span>
          <button class="ic-reset" id="compare-open" type="button" disabled>Compare side by side</button>
          <button class="ic-reset" id="compare-clear" type="button">Clear</button>
        </div>

        <div class="compare-wrap" id="compare-wrap" hidden>
          <div class="compare-head">
            <div class="section-tag">SIDE-BY-SIDE // FIELD REPORT</div>
            <button class="ic-reset" id="compare-close" type="button">Close</button>
          </div>
          <div class="compare-scroll">
            <table class="compare-table" id="compare-table"><!-- Populated by JS --></table>
          </div>
        </div>
      </div>

      <!-- SECTION 2: MONTHLY COST BREAKDOWN (receipt) -->
      <div class="section-tag">SECTION 02 // MONTHLY MARKETING COST BREAKDOWN</div>
      <div class="receipt">
        <div class="receipt-header">
          <div class="receipt-logo">★ PATRIOT PEST CONTROL</div>
          <div class="receipt-meta">
            <span>REPORT #: <span id="receipt-number">PPC-2026-002</span></span>
            <span>DATE: <span id="receipt-date"></span></span>
            <span id="receipt-classification">UNCLASSIFIED // MARKETING INTEL</span>
          </div>
          <div class="hazard" style="margin:0.8rem 0 0 0"></div>
        </div>

        <div class="receipt-body">
          <div class="receipt-headings">
            <span class="rh-cat">LINE ITEM</span>
            <span class="rh-desc">SCOPE</span>
            <span class="rh-cost">COST RANGE / MO</span>
            <span class="rh-meter">THREAT METER</span>
          </div>

          <div class="receipt-items" id="receipt-items">
            <!-- Populated by JS -->
          </div>

          <div class="receipt-totals">
            <div class="total-row">
              <span>SUBTOTAL (LOW ESTIMATE)</span>
              <span class="total-amt" id="subtotal-low">$5,350</span>
            </div>
            <div class="total-row">
              <span>SUBTOTAL (HIGH ESTIMATE)</span>
              <span class="total-amt" id="subtotal-high">$23,000</span>
            </div>
            <div class="total-divider"></div>
            <div class="total-row grand">
              <span>GRAND TOTAL RANGE</span>
              <span class="total-amt grand-amt" id="grand-total">$5,350 - $23,000</span>
            </div>
            <div class="total-row grand">
              <span id="realistic-label">REALISTIC GROWTH RANGE</span>
              <span class="total-amt grand-amt" id="realistic-range">$5,000-$8,500</span>
            </div>
            <div class="stamp-row">
              <span class="stamp" id="receipt-stamp">NO SMOKE<br>DETECTED</span>
            </div>
          </div>
        </div>

        <div class="receipt-footer" id="receipt-notes">
          <!-- Populated by JS -->
        </div>
      </div>

      <!-- SECTION 3: THE CHARTS -->
      <div class="explainer-section">
        <div class="section-tag">SECTION 03 // THE CHARTS</div>
        <div class="chart-grid">
          <div class="chart-panel">
            <h3>Fee vs Spend: Low End and High End</h3>
            <p class="chart-note">Ad spend is the bigger line item at both ends. The fee is just the cover charge.</p>
            <div class="chart-box"><canvas id="chart-stacked" aria-label="Stacked bar chart of agency fee versus ad spend"></canvas></div>
          </div>
          <div class="chart-panel">
            <h3>Monthly Categories, Ranked</h3>
            <p class="chart-note">High end of each range, largest to smallest. Google Ads wins the crown.</p>
            <div class="chart-box tall"><canvas id="chart-rank" aria-label="Horizontal bar chart of monthly marketing categories ranked by high end"></canvas></div>
          </div>
        </div>
      </div>

      <!-- SECTION 4: COMPETITIVE CONTEXT -->
      <div class="explainer-section">
        <div class="section-tag">SECTION 04 // WHERE PATRIOT FITS</div>
        <div class="summary-cards">
          <div class="summary-card">
            <div class="label">Patriot All-In</div>
            <div class="value" id="patriot-all-in">$6,250</div>
            <div class="sub">Management + Ad Spend, one number</div>
          </div>
          <div class="summary-card">
            <div class="label">Typical Agency At Same Price</div>
            <div class="value" id="agency-same">Fee Only</div>
            <div class="sub">Ad spend billed on top</div>
          </div>
        </div>
        <div class="comp-table-wrap">
          <table class="comp-table" id="comp-table">
            <thead>
              <tr><th>TIER</th><th>MONTHLY RANGE</th><th>WHAT IT COVERS</th></tr>
            </thead>
            <tbody><!-- Populated by JS --></tbody>
          </table>
        </div>
        <p class="comp-note" id="patriot-note"></p>
      </div>

      <!-- SECTION 5: LEAD RESPONSE -->
      <div class="lead-callout">
        <div class="lead-icon">⚡</div>
        <div class="lead-body">
          <h3 id="lead-headline">What about responding to leads instantly?</h3>
          <p id="lead-text">Patriot already has Twilio SMS and voice wired into the website. Leads hit the system the moment they submit, no 5-hour Facebook delay. Facebook advertising is already producing results. Google Ads is the next front to open, and the infrastructure is ready.</p>
          <a class="btn-lead" id="lead-cta" href="/contact">Run a test lead</a>
        </div>
      </div>

      <!-- SOURCES -->
      <div class="source-block" id="source-block">
        <!-- Populated by JS -->
      </div>

    </div>
  </section>

  <footer class="cost-footer">
    <div class="wrap">
      <div class="hazard"></div>
      <div class="footer-inner">
        <!-- footer CTA removed per David directive; stamp only -->
        <span class="stamp" id="footer-stamp">APPROVED<br>FOR DEPLOYMENT</span>
      </div>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <script src="./assets/js/cost.js?v=20260809c"></script>
  <script src="./assets/js/intel.js?v=20260809c"></script>
</body>
</html>
