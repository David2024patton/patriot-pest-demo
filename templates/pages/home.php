<?php
/**
 * pages/home.php — the flagship landing page.
 *
 * The threat board is DB-driven: it renders EVERY pest in the photo library
 * ($pests), each with its real photo under the tactical treatment. Counters,
 * ticker, mission-brief, and the horizontal pin-scroll are all wired to the
 * ids main.js expects.
 */
$pests = $data['pests'] ?? [];
$pestCount = $data['pestCount'] ?? count($pests);
?>

<!-- ============ HERO ============ -->
<section id="hero">
  <div id="xh-v"></div><div id="xh-h"></div><div id="xh-ring"></div>
  <div class="hud-top">
    <span>PATRIOT PEST CONTROL <span class="live">SYSTEMS ONLINE</span></span>
    <span>47.6588° N / 117.4260° W — SPOKANE, WA</span>
    <span id="hud-clock">--:--:--</span>
  </div>
  <div class="wrap hero-grid">
    <div>
      <div class="kicker"><span>🇺🇸</span> A PROUD VETERAN-OWNED AMERICAN COMPANY</div>
      <h1>Hostile pests?<br><span class="strike">Neutralized.</span></h1>
      <p class="hero-sub">Military-precision pest control for homes and businesses across <b>Washington, Idaho, Oregon &amp; Arizona</b>. Eco-friendly treatments safe for your family and pets — with <b>same-day service</b> when it can't wait.</p>
      <div class="hero-ctas">
        <a class="btn btn-primary" href="<?= $view->phoneHref() ?>">☎ Call <?= $view->phone() ?> <small><?= $view->phoneLabel() ?></small></a>
        <a class="btn btn-ghost" href="/prices">View Plans ▸</a>
        <a class="btn btn-ghost" href="/contact" data-track="quote_request">Free Quote ▸</a>
      </div>
      <div class="hero-badges"><span>Same-Day Service</span><span>90-Day Warranty</span><span>Licensed &amp; Insured</span><span>100% Guaranteed</span></div>
    </div>
    <aside class="brief" aria-label="Mission brief"><div class="corner"></div>
      <h3>// Mission Brief</h3><div id="brief-lines"></div>
      <div class="coords">GRID: SPOKANE HQ · EST. BY VETERAN SKYLER ROSE · 24/7 LINE OPEN</div>
    </aside>
  </div>
  <div class="scroll-cue">Scroll to begin mission</div>
  <div class="hazard" style="margin-top:1rem"></div>
  <div class="ticker" aria-hidden="true"><div class="ticker-track" id="ticker-track"></div></div>
</section>

<!-- ============ THREAT BOARD (DB-driven, every pest) ============ -->
<section id="threats">
  <div class="wrap threats-head">
    <div class="eyebrow">SEC. 01 // THREAT ASSESSMENT</div>
    <h2 data-reveal>Know your <em>enemy.</em></h2>
    <p class="lead" data-reveal><?= $view->e($pestCount) ?> hostile categories operate in your region right now. Each one is identified, tracked, and eliminated with eco-friendly, family-safe treatments. Scroll the threat board.</p>
  </div>
  <div class="wrap" style="max-width:100%;padding-right:0"><div class="threat-pin" id="threat-track">
    <?php foreach ($pests as $i => $p): ?>
    <div class="threat-card" data-reveal>
      <span class="neutralized">Neutralized</span>
      <div class="file-no">Threat File #<?= str_pad((string)($i+1), 3, '0', STR_PAD_LEFT) ?> · <?= $view->e(strtoupper($p['category'])) ?></div>
      <a href="/pest/<?= $view->e($p['slug']) ?>" style="text-decoration:none;color:inherit">
        <span class="pphoto s-lg"><img src="<?= $view->asset('img/pests/' . $p['filename']) ?>" alt="<?= $view->e($p['name']) ?>" loading="lazy"><i class="ret" aria-hidden="true"></i></span>
      </a>
      <h3><a href="/pest/<?= $view->e($p['slug']) ?>" style="color:inherit;text-decoration:none"><?= $view->e($p['name']) ?></a></h3>
      <?php if (!empty($p['scientific_name'])): ?><div class="sci"><?= $view->e($p['scientific_name']) ?></div><?php endif; ?>
      <p><?= $view->e($p['description']) ?></p>
      <div class="meter"><div class="label"><span>Threat Level</span><span><?= $view->e($p['threat_level']) ?>%</span></div>
      <div class="bar"><div class="fill" data-lvl="<?= $view->e($p['threat_level']) ?>"></div></div></div>
    </div>
    <?php endforeach; ?>
    <div class="threat-end" data-reveal><p>Every target on this board is covered by Patriot Pest Control.</p><a class="btn btn-primary" href="/services">Full Service List ▸</a></div>
  </div></div>
</section>

<!-- ============ OPERATOR ============ -->
<section id="operator" class="block paper">
  <div class="wrap">
    <div class="eyebrow">SEC. 02 // PERSONNEL FILE</div>
    <div class="split" style="margin-top:1.5rem">
      <div class="dossier-file" data-reveal>
        <span class="stamp" style="color:var(--red);position:absolute;top:-16px;right:18px;background:#f6f0dd">Veteran</span>
        <div class="form-id"><span>FORM PPC-14 · PERSONNEL</span><span>FILE 001</span></div>
        <dl>
          <div class="drow"><dt>Name</dt><dd>SKYLER ROSE</dd></div>
          <div class="drow"><dt>Role</dt><dd>FOUNDER &amp; OPERATOR</dd></div>
          <div class="drow"><dt>Service</dt><dd>U.S. MILITARY VETERAN <span class="redact">██████</span></dd></div>
          <div class="drow"><dt>Theater</dt><dd>WA · ID · OR · AZ</dd></div>
          <div class="drow"><dt>Clearance</dt><dd>LICENSED · BONDED · INSURED</dd></div>
          <div class="drow"><dt>Status</dt><dd>ACTIVE — SAME-DAY RESPONSE</dd></div>
        </dl>
      </div>
      <div>
        <h2 data-reveal>The operator behind <em>the operation.</em></h2>
        <p class="lead" data-reveal>Patriot Pest Control was founded by <b>U.S. Military Veteran Skyler Rose</b> — bringing military discipline, integrity, and uncompromising excellence to pest control across four states. Over a decade of field experience. Thousands of homes and businesses protected.</p>
        <blockquote class="quote" data-reveal>
          <p>After serving our country, I founded Patriot Pest Control to continue serving American families and businesses with the same dedication, precision, and integrity I learned in the military. We're not just eliminating pests — we're protecting what matters most.</p>
          <footer><span class="medal">🎖️</span><div><b>Skyler Rose</b><small>FOUNDER &amp; VETERAN · PATRIOT PEST CONTROL</small></div></footer>
        </blockquote>
      </div>
    </div>
  </div>
</section>

<!-- ============ AREA OF OPERATIONS ============ -->
<section id="areas" class="block">
  <div class="wrap">
    <div class="eyebrow">SEC. 03 // AREA OF OPERATIONS</div>
    <h2 data-reveal>Four states. <em>One call.</em></h2>
    <div class="area-grid" data-reveal>
      <div class="area-col"><h3>WA</h3><div class="area-cities">
        <a href="/areas/spokane">Spokane</a><a href="/areas/spokane-valley">Spokane Valley</a><a href="/areas/cheney">Cheney</a><a href="/areas/liberty-lake">Liberty Lake</a><a href="/areas/airway-heights">Airway Heights</a><a href="/areas/medical-lake">Medical Lake</a><a href="/areas/deer-park">Deer Park</a><a href="/areas/mead">Mead</a>
      </div><span class="area-note">● 8 ZONES · SAME-DAY AVAILABLE</span></div>
      <div class="area-col"><h3>ID</h3><div class="area-cities">
        <a href="/areas/coeur-d-alene">Coeur d'Alene</a><a href="/areas/post-falls">Post Falls</a><a href="/areas/hayden">Hayden</a><a href="/areas/rathdrum">Rathdrum</a>
      </div><span class="area-note">● 4 ZONES · SAME-DAY AVAILABLE</span></div>
      <div class="area-col"><h3>OR</h3><div class="area-cities">
        <a href="/areas/hermiston">Hermiston</a><a href="/areas/milton-freewater">Milton-Freewater</a>
      </div><span class="area-note">● 2 ZONES · SAME-DAY AVAILABLE</span></div>
      <div class="area-col"><h3>AZ</h3><div class="area-cities">
        <a href="/areas/phoenix">Phoenix</a>
      </div><span class="area-note">● 1 ZONE · SAME-DAY AVAILABLE</span></div>
    </div>
  </div>
</section>

<!-- ============ SUPPLY MANIFEST (PRICING) ============ -->
<section id="pricing" class="block alt">
  <div class="wrap">
    <div class="eyebrow">SEC. 04 // SUPPLY MANIFEST</div>
    <h2 data-reveal>Choose your <em>coverage.</em></h2>
    <p class="lead" data-reveal>Transparent online pricing. No hidden fees. Free quotes, free re-treatments between visits, and a 90-day warranty on every plan.</p>
    <div class="grid g4">
      <div class="card plan" data-reveal><span class="plan-tier">BRONZE</span><h3>One-Time Treatment</h3><p>A single targeted strike on an active infestation. Fast, focused, guaranteed.</p><a class="more" href="/prices">View Pricing ▸</a></div>
      <div class="card plan" data-reveal><span class="plan-tier">SILVER</span><h3>Seasonal Protection</h3><p>Scheduled seasonal treatments that keep the perimeter secure through every pest season.</p><a class="more" href="/prices">View Pricing ▸</a></div>
      <div class="card plan featured" data-reveal><span class="rec">RECOMMENDED</span><span class="plan-tier">GOLD</span><h3>Year-Round Protection</h3><p>Our best-value plan — continuous year-round defense, priority scheduling, and free re-treatments.</p><a class="more" href="/prices">View Pricing ▸</a></div>
      <div class="card plan" data-reveal><span class="plan-tier">PLATINUM</span><h3>Premium Comprehensive</h3><p>Maximum coverage — perimeter, interior, and specialty pests, every angle locked down.</p><a class="more" href="/prices">View Pricing ▸</a></div>
    </div>
  </div>
</section>

<!-- ============ THE GUARANTEE (counters) ============ -->
<section id="guarantee" class="block">
  <div class="wrap">
    <div class="eyebrow">SEC. 05 // THE GUARANTEE</div>
    <h2 data-reveal>We stand behind <em>every mission.</em></h2>
    <div class="stat-grid">
      <div class="stat" data-reveal><span class="num" data-count="100">0</span><span class="unit">%</span><span class="cap">SATISFACTION GUARANTEED</span></div>
      <div class="stat" data-reveal><span class="num" data-count="90">0</span><span class="unit">-DAY</span><span class="cap">WARRANTY ON ALL TREATMENTS</span></div>
      <div class="stat" data-reveal><span class="num">24/7</span><span class="cap">CUSTOMER SERVICE LINE</span></div>
      <div class="stat" data-reveal><span class="num" data-count="4">0</span><span class="unit"> STATES</span><span class="cap">WA · ID · OR · AZ</span></div>
      <div class="stat" data-reveal><span class="num" data-count="10">0</span><span class="unit">+</span><span class="cap">YEARS OF EXPERIENCE</span></div>
      <div class="stat" data-reveal><span class="num" data-count="48">0</span><span class="unit">-48H</span><span class="cap">EMERGENCY RESPONSE WINDOW</span></div>
    </div>
    <div class="promise" data-reveal><b>🛡️ Our promise:</b> if pests return between scheduled visits, we re-treat at no additional cost. No hassles, no excuses. Licensed, bonded, and insured — with eco-friendly, low-toxicity products safe for kids, pets, and the environment.</div>
  </div>
</section>

<!-- ============ FIELD REPORTS ============ -->
<section id="reports" class="block alt">
  <div class="wrap">
    <div class="eyebrow">SEC. 06 // FIELD REPORTS</div>
    <h2 data-reveal>Debriefs from <em>the front line.</em></h2>
    <div class="grid g3">
      <div class="card report" data-reveal><span class="ver">VERIFIED</span><span class="rid">REPORT #WA-0117</span><div class="stars">★★★★★</div><p>"Patriot Pest Control saved our home from a serious ant infestation. Professional, thorough, and the results were immediate."</p><footer><span class="avatar">SM</span><div><b>Sarah M.</b><small>WASHINGTON</small></div></footer></div>
      <div class="card report" data-reveal><span class="ver">VERIFIED</span><span class="rid">REPORT #WA-0242</span><div class="stars">★★★★★</div><p>"We've used Patriot for our restaurant for 2 years. Reliable, discreet, always on time. Our health inspections have never been better."</p><footer><span class="avatar">JT</span><div><b>James T.</b><small>LIBERTY LAKE, WA</small></div></footer></div>
      <div class="card report" data-reveal><span class="ver">VERIFIED</span><span class="rid">REPORT #ID-0089</span><div class="stars">★★★★★</div><p>"Fast response and eco-friendly products safe for my kids and pets. The technician was knowledgeable and explained everything."</p><footer><span class="avatar">LR</span><div><b>Lisa R.</b><small>COEUR D'ALENE, ID</small></div></footer></div>
    </div>
  </div>
</section>

<!-- ============ FINAL ORDERS ============ -->
<section id="final" class="block cta-band">
  <div class="wrap" style="text-align:center">
    <div class="eyebrow">FINAL ORDERS</div>
    <h2 data-reveal>Ready to go <em>pest-free?</em></h2>
    <p class="lead" data-reveal>Book online in minutes or call the line — same-day service available across all four states. Free quotes, transparent pricing, zero hidden fees.</p>
    <div class="hero-ctas" style="justify-content:center">
      <a class="btn btn-primary" href="<?= $view->phoneHref() ?>">☎ <?= $view->phone() ?> <small><?= $view->phoneLabel() ?></small></a>
      <a class="btn btn-ghost" href="/contact" data-track="quote_request">Free Quote ▸</a>
      <a class="btn btn-ghost" href="/prices">View Plans ▸</a>
    </div>
  </div>
</section>
