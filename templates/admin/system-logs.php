<?php
/** admin/system-logs.php - server/application log viewer with filters. */
$lines = $data['lines'] ?? [];
$date  = $data['date'] ?? date('Y-m-d');
$level = $data['level'] ?? 'ALL';
$q     = $data['q'] ?? '';
$files = $data['files'] ?? [];
$dates = array_unique(array_map(fn($f) => preg_replace('/^.*?(\d{4}-\d{2}-\d{2})\.log$/', '$1', $f), $files));
$qs = fn($extra) => http_build_query(array_merge(['date'=>$date,'level'=>$level,'q'=>$q], $extra));
$colors = ['CRITICAL' => 'var(--red)', 'ERROR' => 'var(--red)', 'WARNING' => 'var(--orange)', 'INFO' => 'var(--olive-300)', 'DEBUG' => 'var(--khaki)'];
?>
<div class="wrap">
  <div class="eyebrow">ADMIN // SYSTEM LOGS</div>
  <h1 style="font-family:var(--display);color:var(--cream);font-size:1.8rem;margin:.4rem 0 1rem">System Logs</h1>

  <form method="get" action="/admin/system-logs" style="display:flex;gap:.6rem;flex-wrap:wrap;background:var(--olive-800);border:1px solid var(--olive-700);padding:1rem;margin-bottom:1rem">
    <select name="date" style="background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem">
      <?php foreach ($dates as $d): ?><option value="<?= $d ?>" <?= $date === $d ? 'selected' : '' ?>><?= $d ?></option><?php endforeach; ?>
    </select>
    <select name="level" style="background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem">
      <?php foreach (['ALL','CRITICAL','ERROR','WARNING','INFO','DEBUG'] as $lv): ?><option value="<?= $lv ?>" <?= $level === $lv ? 'selected' : '' ?>><?= $lv ?></option><?php endforeach; ?>
    </select>
    <input type="text" name="q" value="<?= $view->e($q) ?>" placeholder="Search log text…" style="background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem;width:280px">
    <button type="submit" style="background:var(--orange);color:var(--olive-950);border:0;padding:.5rem 1.2rem;cursor:pointer">Filter</button>
  </form>

  <div class="table-wrap" style="background:var(--olive-800);border:1px solid var(--olive-700)"><table class="data" style="width:100%;font-size:.75rem">
    <thead><tr style="color:var(--khaki)"><th style="width:110px">Source</th><th>Entry</th></tr></thead>
    <tbody>
    <?php foreach ($lines as $l): $m = []; preg_match('/\[([^\]]+)\]?\s*([A-Z]+):\s*(.*)$/', $l['line'], $m); ?>
      <tr>
        <td class="mono" style="color:var(--khaki)"><?= $view->e($l['src']) ?></td>
        <td class="mono" style="white-space:pre-wrap;color:var(--cream)"><?= $view->e(mb_substr($l['line'], 0, 600)) ?><?php if (mb_strlen($l['line']) > 600): ?> <span style="color:var(--khaki)">…</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$lines): ?><tr><td colspan="2" style="text-align:center;color:var(--khaki);padding:2rem">No log entries for this filter.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>
