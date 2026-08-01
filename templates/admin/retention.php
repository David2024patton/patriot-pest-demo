<?php
/**
 * admin/retention.php - first-party retention dashboard (ORDER 3).
 * Vars: $summary (Retention::summary shape, locked contract), $eggEnabled,
 * $trackEnabled, $flash. Renders live; the only static data is the toggle form.
 */
$s    = $data['summary'] ?? [];
$t    = $s['totals'] ?? [];
$daily = $s['daily'] ?? [];
$topPages = $s['top_pages'] ?? [];
$entryPages = $s['entry_pages'] ?? [];
$flows = $s['top_flows'] ?? [];
$sources = $s['sources'] ?? [];
$eggEnabled   = $data['eggEnabled'] ?? true;
$trackEnabled = $data['trackEnabled'] ?? true;
$flash = $data['flash'] ?? null;

$fmtInt = fn($v) => number_format((float)($v ?? 0));
$fmtSec = function ($v) {
    $v = (float)($v ?? 0);
    $m = floor($v / 60); $s = round($v - $m * 60);
    return $m . 'm ' . str_pad((string)$s, 2, '0', STR_PAD_LEFT) . 's';
};
$fmtPct = fn($v) => number_format((float)($v ?? 0), 1) . '%';
$fmtRate = fn($v) => number_format((float)($v ?? 0) * 100, 1) . '%';

$maxDaily = 1;
foreach ($daily as $d) { $maxDaily = max($maxDaily, (int)($d['sessions'] ?? 0)); }
?>
<div class="app"><div class="wrap" style="max-width:1200px">
  <div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
    <div>
      <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Retention Ops</h1>
      <div class="sub">FIRST-PARTY RETENTION // LIVE LEDGER // <?= $view->e($s['window_start'] ?? '') ?> to <?= $view->e($s['window_end'] ?? '') ?></div>
    </div>
    <div class="actions">
      <a class="btn btn-ghost" href="/staff-dashboard">◂ Dashboard</a>
      <a class="btn btn-ghost" href="/api/retention/summary" target="_blank">Raw JSON ↗</a>
    </div>
  </div>

  <?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>

  <div class="stat-cards">
    <div class="stat-card"><div class="v"><?= $fmtInt($t['unique_visitors'] ?? 0) ?></div><div class="k">unique_visitors</div><div class="d up">TRAILING 14 DAYS</div></div>
    <div class="stat-card"><div class="v"><?= $fmtInt($t['sessions'] ?? 0) ?></div><div class="k">sessions</div><div class="d up">ENGAGED <?= $fmtRate($t['engaged_rate'] ?? 0) ?></div></div>
    <div class="stat-card alt"><div class="v"><?= $view->raw($fmtSec($t['avg_session_sec'] ?? 0)) ?></div><div class="k">avg_session_sec</div><div class="d up">avg_engaged_sec <?= $fmtSec($t['avg_engaged_sec'] ?? 0) ?></div></div>
    <div class="stat-card warn"><div class="v"><?= $fmtPct($t['bounce_pct'] ?? 0) ?></div><div class="k">bounce_pct</div><div class="d dn">UNDER 10S SINGLE PAGE</div></div>
    <div class="stat-card alt"><div class="v"><?= $fmtPct($t['returning_pct'] ?? 0) ?></div><div class="k">returning_pct</div><div class="d up">MULTI-DAY VISITORS</div></div>
  </div>

  <div class="panel" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.3rem 1.4rem;margin-bottom:1.4rem;position:relative">
    <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:repeating-linear-gradient(-45deg,var(--orange) 0 14px,transparent 14px 28px)"></div>
    <h2 style="font-family:var(--display);font-weight:400;font-size:1rem;margin:0 0 .2rem;color:var(--cream)">Daily // Last 14</h2>
    <div class="sub" style="font-family:var(--mono);font-size:.66rem;letter-spacing:.14em;color:var(--khaki);text-transform:uppercase;margin-bottom:1rem">day // <b style="color:var(--orange)">unique_visitors</b> // sessions // avg_session_sec</div>
    <?php if (!$daily): ?>
      <div class="empty">No sessions recorded yet. The beacon fires on every page view.</div>
    <?php else: ?>
      <div class="bars" style="display:flex;align-items:flex-end;gap:10px;height:140px;padding-top:1.2rem">
        <?php foreach ($daily as $d): $h = max(2, round(((int)($d['sessions'] ?? 0)) / $maxDaily * 100)); ?>
          <div class="bar" style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.4rem;min-width:0" title="<?= $view->e($d['day'] ?? '') ?>: <?= (int)($d['unique_visitors'] ?? 0) ?> visitors, <?= (int)($d['sessions'] ?? 0) ?> sessions, <?= $fmtSec($d['avg_session_sec'] ?? 0) ?>">
            <div class="fill" style="width:100%;background:linear-gradient(var(--orange-hot),var(--orange));border:1px solid var(--olive-700);position:relative;min-height:2px;height:<?= $h ?>%"></div>
            <div class="val" style="font-family:var(--mono);font-size:.66rem;color:var(--orange-hot)"><?= (int)($d['sessions'] ?? 0) ?></div>
            <div class="lbl" style="font-family:var(--mono);font-size:.62rem;color:var(--khaki);white-space:nowrap;transform:rotate(-38deg);transform-origin:top left;margin-top:.3rem"><?= $view->e(substr((string)($d['day'] ?? ''), 5)) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="two" style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem">
    <div class="panel" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.3rem 1.4rem;position:relative">
      <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:repeating-linear-gradient(-45deg,var(--orange) 0 14px,transparent 14px 28px)"></div>
      <h2 style="font-family:var(--display);font-weight:400;font-size:1rem;margin:0 0 .2rem;color:var(--cream)">Top Pages</h2>
      <div class="sub" style="font-family:var(--mono);font-size:.66rem;letter-spacing:.14em;color:var(--khaki);text-transform:uppercase;margin-bottom:1rem">PAGE_VIEWS // <b style="color:var(--orange)">SHARE OF TOTAL</b></div>
      <table class="data">
        <thead><tr><th>page_path</th><th class="num">views</th><th class="num">unique_visitors</th><th class="num">share_pct</th></tr></thead>
        <tbody>
        <?php if (!$topPages): ?><tr><td colspan="4" class="empty">No page views yet.</td></tr><?php endif; ?>
        <?php foreach ($topPages as $p): ?>
          <tr><td class="path" style="color:var(--orange)"><?= $view->e($p['page_path'] ?? '') ?></td><td class="num"><?= $fmtInt($p['views'] ?? 0) ?></td><td class="num"><?= $fmtInt($p['unique_visitors'] ?? 0) ?></td><td class="num"><?= $fmtPct($p['share_pct'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.3rem 1.4rem;position:relative">
      <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:repeating-linear-gradient(-45deg,var(--orange) 0 14px,transparent 14px 28px)"></div>
      <h2 style="font-family:var(--display);font-weight:400;font-size:1rem;margin:0 0 .2rem;color:var(--cream)">Top Flows // Click Paths</h2>
      <div class="sub" style="font-family:var(--mono);font-size:.66rem;letter-spacing:.14em;color:var(--khaki);text-transform:uppercase;margin-bottom:1rem">path // <b style="color:var(--orange)">pages_visited</b> // occurrences</div>
      <?php if (!$flows): ?><div class="empty">No click paths yet. Paths form once visitors move between pages.</div><?php endif; ?>
      <?php foreach ($flows as $f): $path = $f['path'] ?? []; $n = count($path); ?>
        <div class="flow" style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;margin-bottom:.4rem">
          <?php foreach ($path as $pi => $node): ?>
            <?php if ($pi > 0): ?><span class="arrow" style="color:var(--khaki);font-family:var(--mono);font-size:.7rem">▸</span><?php endif; ?>
            <span class="node" style="font-family:var(--mono);font-size:.78rem;background:var(--olive-950);border:1px solid var(--olive-700);padding:.3rem .6rem;color:<?= $pi === 0 ? 'var(--orange)' : ($pi === $n - 1 ? 'var(--orange-hot)' : 'var(--cream)') ?>"><?= $view->e($node) ?></span>
          <?php endforeach; ?>
          <span class="cnt" style="font-family:var(--mono);font-size:.7rem;color:var(--olive-300);margin-left:.2rem">occurrences <?= (int)($f['occurrences'] ?? 0) ?></span>
        </div>
        <div class="flow-meta" style="font-family:var(--mono);font-size:.64rem;color:var(--khaki);margin:-.2rem 0 .9rem 1.1rem">pages_visited <?= (int)($f['pages_visited'] ?? 0) ?></div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="two" style="display:grid;grid-template-columns:1fr 1fr;gap:1.4rem;margin-top:1.4rem">
    <div class="panel" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.3rem 1.4rem;position:relative">
      <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:repeating-linear-gradient(-45deg,var(--orange) 0 14px,transparent 14px 28px)"></div>
      <h2 style="font-family:var(--display);font-weight:400;font-size:1rem;margin:0 0 .2rem;color:var(--cream)">Entry Pages</h2>
      <div class="sub" style="font-family:var(--mono);font-size:.66rem;letter-spacing:.14em;color:var(--khaki);text-transform:uppercase;margin-bottom:1rem">SESSION STARTS // <b style="color:var(--orange)">LANDING MIX</b></div>
      <table class="data">
        <thead><tr><th>entry_page</th><th class="num">entries</th><th class="num">share_pct</th></tr></thead>
        <tbody>
        <?php if (!$entryPages): ?><tr><td colspan="3" class="empty">No sessions yet.</td></tr><?php endif; ?>
        <?php foreach ($entryPages as $p): ?>
          <tr><td class="path" style="color:var(--orange)"><?= $view->e($p['entry_page'] ?? '') ?></td><td class="num"><?= $fmtInt($p['entries'] ?? 0) ?></td><td class="num"><?= $fmtPct($p['share_pct'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="panel" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.3rem 1.4rem;position:relative">
      <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:repeating-linear-gradient(-45deg,var(--orange) 0 14px,transparent 14px 28px)"></div>
      <h2 style="font-family:var(--display);font-weight:400;font-size:1rem;margin:0 0 .2rem;color:var(--cream)">Referrer Sources</h2>
      <div class="sub" style="font-family:var(--mono);font-size:.66rem;letter-spacing:.14em;color:var(--khaki);text-transform:uppercase;margin-bottom:1rem">ATTRIBUTION // <b style="color:var(--orange)">WHERE TRAFFIC ORIGINATES</b></div>
      <table class="data">
        <thead><tr><th>source</th><th class="num">sessions</th><th class="num">share_pct</th></tr></thead>
        <tbody>
        <?php if (!$sources): ?><tr><td colspan="3" class="empty">No sessions yet.</td></tr><?php endif; ?>
        <?php foreach ($sources as $p): ?>
          <tr><td class="path" style="color:var(--orange)"><?= $view->e($p['source'] ?? '') ?></td><td class="num"><?= $fmtInt($p['sessions'] ?? 0) ?></td><td class="num"><?= $fmtPct($p['share_pct'] ?? 0) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="panel" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.3rem 1.4rem;margin-top:1.4rem;position:relative">
    <div style="position:absolute;top:0;left:0;width:100%;height:2px;background:repeating-linear-gradient(-45deg,var(--orange) 0 14px,transparent 14px 28px)"></div>
    <h2 style="font-family:var(--display);font-weight:400;font-size:1rem;margin:0 0 .2rem;color:var(--cream)">Beacon Controls</h2>
    <div class="sub" style="font-family:var(--mono);font-size:.66rem;letter-spacing:.14em;color:var(--khaki);text-transform:uppercase;margin-bottom:1rem">SETTINGS TOGGLE // <b style="color:var(--orange)">PER DOCTRINE</b></div>
    <form method="post" action="/admin/retention/settings">
      <?= $view->csrf() ?>
      <div class="form-stack" style="max-width:480px">
        <label class="field">
          <input type="checkbox" name="egg_enabled" value="1" <?= $eggEnabled ? 'checked' : '' ?> style="width:auto">
          <span class="hint" style="margin-left:.5rem">Easter egg beacon ($25 jackpot) on the marketing site</span>
        </label>
        <label class="field">
          <input type="checkbox" name="track_enabled" value="1" <?= $trackEnabled ? 'checked' : '' ?> style="width:auto">
          <span class="hint" style="margin-left:.5rem">First-party retention beacon + tracking endpoints</span>
        </label>
        <button class="btn" type="submit" style="background:var(--orange);color:var(--ink);border:0;padding:.7rem 1.4rem;font-family:var(--display);letter-spacing:.06em;cursor:pointer">SAVE SETTINGS</button>
      </div>
    </form>
  </div>
</div></div>
