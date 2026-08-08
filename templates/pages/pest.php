<?php
/**
 * pages/pest.php — the unified "threat file" page for a single pest.
 *
 * Every pest in the library renders through this ONE template, so each page has
 * the same tactical photo treatment and the same Signs / Treatment / Prevention
 * structure (consistent, extractable answers for search + AI engines). Content
 * is DB-driven off pest_photos; category-specific copy fills the standard slots.
 *
 * Vars: $pest (full pest_photos row), $related (array of slug/name/filename).
 */
$pest    = $data['pest'] ?? [];
$related = $data['related'] ?? [];

// Category-specific field copy so each file reads as tailored, not generic.
$byCat = [
    'insect'   => [
        'signs' => ['Live insects or shed skins near entry points, kitchens, or baseboards', 'Small droppings, smear marks, or nesting material in hidden corners', 'Damaged food packaging, wood, or fabrics', 'Unusual odors or faint rustling sounds at night'],
        'treat' => 'Targeted crack-and-crevice and baiting programs that reach the colony at its source, not just the bugs you see. Products are low-toxicity and safe for your family and pets. We follow up to confirm they are gone.',
        'prev'  => ['Seal cracks around the foundation, windows, and utility entry points', 'Store food in airtight containers and clean up crumbs and spills promptly', 'Eliminate standing water and fix leaky fixtures', 'Keep vegetation and mulch pulled back from the foundation'],
    ],
    'rodent'   => [
        'signs' => ['Droppings along walls, in cabinets, or near food sources', 'Gnaw marks on wiring, wood, or food packaging', 'Grease rub marks along baseboards and entry routes', 'Scratching or scurrying noises in walls or attics at night'],
        'treat' => 'A complete exclusion-plus-removal program: we trap and remove active rodents, then seal entry points larger than a quarter-inch so new ones can’t get in. Attic and crawlspace decontamination available.',
        'prev'  => ['Seal gaps around pipes, vents, and the foundation with steel wool and caulk', 'Store food and pet food in sealed, rodent-proof containers', 'Keep garbage in tightly sealed bins and remove clutter', 'Trim tree branches and vegetation away from the roofline'],
    ],
    'wildlife' => [
        'signs' => ['Noises in the attic, walls, or chimney at dawn and dusk', 'Entry holes, torn vents, or damaged soffits and fascia', 'Droppings or nesting material in attics and crawlspaces', 'Damaged insulation, wiring, or stored items'],
        'treat' => 'Humane removal and exclusion. We safely remove the animals, then install one-way doors and seal entry points so they can’t return. We clean up and decontaminate the areas they used.',
        'prev'  => ['Cap chimneys and vent openings with wildlife-proof covers', 'Repair damaged soffits, fascia, and roof vents', 'Keep trash secured and remove outdoor food sources', 'Trim overhanging branches that provide roof access'],
    ],
];
$cat   = $pest['category'] ?? 'insect';
$copy  = $byCat[$cat] ?? $byCat['insect'];
$fileNo = str_pad((string) (($pest['sort_order'] ?? 0) + 1), 3, '0', STR_PAD_LEFT);
?>

<!-- ===== THREAT FILE HEADER ===== -->
<section class="block">
  <div class="wrap">
    <div class="eyebrow">THREAT FILE #<?= $view->e($fileNo) ?> // <?= $view->e(strtoupper($cat)) ?></div>
    <div class="split" style="margin-top:1.2rem;align-items:start">
      <div>
        <h1 style="font-family:var(--display);color:var(--cream);font-size:clamp(2rem,6vw,3.2rem);line-height:1.05"><?= $view->e($pest['name']) ?> <span style="color:var(--orange)">Control</span></h1>
        <?php if (!empty($pest['scientific_name'])): ?><div class="sci" style="font-family:var(--mono);font-style:italic;color:var(--khaki);margin:.4rem 0 1rem"><?= $view->e($pest['scientific_name']) ?></div><?php endif; ?>
        <p class="lead"><?= $view->e($pest['description']) ?></p>
        <div class="meter" style="max-width:420px;margin:1.4rem 0">
          <div class="label"><span>Regional Threat Level</span><span><?= $view->e($pest['threat_level']) ?>%</span></div>
          <div class="bar"><div class="fill" data-lvl="<?= $view->e($pest['threat_level']) ?>"></div></div>
        </div>
        <div class="hero-ctas" style="margin-top:1.6rem">
          <a class="btn btn-primary" href="tel:+15094715767">☎ Get a Free Quote</a>
          <a class="btn btn-ghost" href="/contact">Book Service ▸</a>
        </div>
      </div>
      <div>
        <span class="pphoto s-lg"><img src="<?= $view->asset('img/pests/' . $pest['filename']) ?>" alt="<?= $view->e($pest['name']) ?>" loading="eager"><i class="ret" aria-hidden="true"></i></span>
      </div>
    </div>
  </div>
</section>

<!-- ===== SIGNS / TREATMENT / PREVENTION ===== -->
<section class="block alt">
  <div class="wrap">
    <div class="grid g3">
      <div class="card">
        <h3 style="font-family:var(--display);color:var(--cream)">⚠ Signs of Activity</h3>
        <ul style="margin:.6rem 0 0 1.1rem;color:var(--khaki);line-height:1.7">
          <?php foreach ($copy['signs'] as $s): ?><li><?= $view->e($s) ?></li><?php endforeach; ?>
        </ul>
      </div>
      <div class="card">
        <h3 style="font-family:var(--display);color:var(--cream)">🎯 Our Treatment</h3>
        <p style="color:var(--khaki);line-height:1.7;margin-top:.6rem"><?= $view->e($copy['treat']) ?></p>
      </div>
      <div class="card">
        <h3 style="font-family:var(--display);color:var(--cream)">🛡 Prevention</h3>
        <ul style="margin:.6rem 0 0 1.1rem;color:var(--khaki);line-height:1.7">
          <?php foreach ($copy['prev'] as $p): ?><li><?= $view->e($p) ?></li><?php endforeach; ?>
        </ul>
      </div>
    </div>
    <div class="promise" style="margin-top:1.6rem"><b>🛡️ 90-Day Warranty:</b> if <?= $view->e(strtolower($pest['name'])) ?> return between scheduled visits, we re-treat at no additional cost. Licensed, bonded, and insured across Washington, Idaho, Oregon &amp; Arizona.</div>
  </div>
</section>

<!-- ===== RELATED THREATS ===== -->
<?php if ($related): ?>
<section class="block">
  <div class="wrap">
    <div class="eyebrow">RELATED THREAT FILES</div>
    <h2 style="font-family:var(--display);color:var(--cream);margin:.4rem 0 1.4rem">Also operating <em>in your region.</em></h2>
    <div class="grid g3">
      <?php foreach ($related as $r): ?>
      <a class="card" href="/pest/<?= $view->e($r['slug']) ?>" style="text-decoration:none;color:inherit">
        <span class="pphoto"><img src="<?= $view->asset('img/pests/' . $r['filename']) ?>" alt="<?= $view->e($r['name']) ?>" loading="lazy"><i class="ret" aria-hidden="true"></i></span>
        <h3 style="font-family:var(--display);color:var(--cream);margin-top:.7rem"><?= $view->e($r['name']) ?> ▸</h3>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ===== CTA ===== -->
<section class="block cta-band">
  <div class="wrap" style="text-align:center">
    <div class="eyebrow">FINAL ORDERS</div>
    <h2 style="font-family:var(--display);color:var(--cream);margin:.4rem 0 1rem">Seeing <?= $view->e(strtolower($pest['name'])) ?>? <em>Let's end it.</em></h2>
    <p class="lead">Same-day service available. Free quotes, transparent pricing, 90-day warranty.</p>
    <div class="hero-ctas" style="justify-content:center;margin-top:1.4rem">
      <a class="btn btn-primary" href="tel:+15094715767">☎ (509) 471-5767 <small>WA, ID, OR</small></a>
      <a class="btn btn-ghost" href="tel:+16027558414">☎ (602) 755-8414 <small>ARIZONA</small></a>
    </div>
  </div>
</section>
