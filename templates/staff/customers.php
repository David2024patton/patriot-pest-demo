<?php
/**
 * staff/customers.php — the customer book (searchable list).
 * Vars: $customers, $q, $status, $total, $isAdmin.
 */
$customers = $data['customers'] ?? [];
$q         = $data['q'] ?? '';
$status    = $data['status'] ?? '';
$total     = $data['total'] ?? 0;
$frLast    = $data['frLastSync'] ?? null;
$frOn      = (bool) ($data['frConfigured'] ?? false);
?>
<div class="app"><div class="wrap">
  <div class="app-head">
    <div>
      <h1>Customers</h1>
      <div class="sub">
        <?= (int) $total ?> in the local cache
        · <?php if ($frLast): ?>
            last FieldRoutes sync <?= $view->e(date('M j, Y H:i', strtotime($frLast))) ?> UTC
          <?php elseif ($frOn): ?>
            FieldRoutes connected · not yet synced
          <?php else: ?>
            FieldRoutes not connected
          <?php endif; ?>
      </div>
    </div>
    <div class="actions">
      <?php if ($frOn): ?>
      <form method="post" action="/staff/customers/sync" style="display:inline">
        <?= $view->csrf() ?>
        <button class="btn btn-primary" type="submit" title="Pull every WA + AZ customer from FieldRoutes into this console now">⟳ Sync FieldRoutes</button>
      </form>
      <?php endif; ?>
      <a class="btn btn-ghost" href="/staff-dashboard">◂ Dashboard</a>
    </div>
  </div>

  <?php $sf = \PPC\Core\Session::pullFlash('fr_sync'); if ($sf): ?>
    <div class="notice <?= $view->e($sf['type'] ?? 'info') ?>"><?= $view->e($sf['msg'] ?? '') ?></div>
  <?php endif; ?>

  <?php if (!$frOn): ?>
    <div class="notice info">Customers will populate from FieldRoutes (both WA &amp; AZ districts) once the API keys are set and <code>bin/fr-sync-customers.php</code> is run. Until then this shows the local cache only.</div>
  <?php elseif (!$frLast): ?>
    <div class="notice info">FieldRoutes is connected. Run <code>bin/fr-sync-customers.php</code> to pull every WA &amp; AZ customer into this console.</div>
  <?php endif; ?>

  <form class="panel" method="get" action="/staff/customers" style="display:flex;flex-wrap:wrap;gap:.8rem;align-items:flex-end">
    <div class="field" style="flex:1;min-width:220px">
      <label for="cq">Search</label>
      <input id="cq" type="search" name="q" value="<?= $view->e($q) ?>" placeholder="Name, phone, email, account #, or city">
    </div>
    <div class="field" style="min-width:160px">
      <label for="cs">Status</label>
      <select id="cs" name="status">
        <option value="">All statuses</option>
        <?php foreach (['active', 'cancelled', 'inactive'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn btn-primary" type="submit">Filter</button>
    <?php if ($q !== '' || $status !== ''): ?><a class="btn btn-ghost" href="/staff/customers">Clear</a><?php endif; ?>
  </form>

  <?php if (!$customers): ?>
    <p class="empty">No customers match<?= $q !== '' ? ' “' . $view->e($q) . '”' : '' ?>. <a href="/staff/customers">Show all ▸</a></p>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Customer</th><th>Account #</th><th>Phone</th><th>Location</th><th>Status</th><th>Flags</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($customers as $c): ?>
      <tr>
        <td><a href="/staff/customers/<?= (int) $c['id'] ?>"><?= $view->e($c['name'] ?? '—') ?></a>
            <div class="mono" style="font-size:.7rem;color:var(--olive-300)"><?= $view->e($c['email'] ?? '') ?></div></td>
        <td class="num"><?= $view->e($c['account_number'] ?? '—') ?></td>
        <td class="num"><?= $view->e($c['phone'] ?? '—') ?></td>
        <td class="muted"><?= $view->e(trim(($c['city'] ?? '') . ', ' . ($c['state'] ?? ''), ', ')) ?></td>
        <td><span class="badge <?= $view->e($c['status']) ?>"><?= $view->e(ucfirst($c['status'] ?? 'active')) ?></span></td>
        <td><?php if ((int)($c['is_no_call'] ?? 0) === 1): ?><span class="badge cancelled">No-Call</span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
        <td><div class="row-actions"><a href="/staff/customers/<?= (int) $c['id'] ?>">Open</a></div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
