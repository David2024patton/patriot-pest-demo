<?php
/**
 * staff/customer-profile.php — a single customer ("like the original").
 * Vars: $customer, $tickets, $messages, $notes, $isAdmin.
 */
$c        = $data['customer'];
$tickets  = $data['tickets'] ?? [];
$messages = $data['messages'] ?? [];
$notes    = $data['notes'] ?? [];
$fr       = $data['fr'] ?? ['configured' => false, 'linked' => false, 'appointments' => [], 'subscriptions' => [], 'error' => null];
$addr     = trim(($c['address'] ?? '') . ', ' . ($c['city'] ?? '') . ', ' . ($c['state'] ?? '') . ' ' . ($c['zip'] ?? ''), ', ');
$noCall   = (int) ($c['is_no_call'] ?? 0) === 1;
?>
<div class="app"><div class="wrap">
  <div class="app-head">
    <div>
      <h1><?= $view->e($c['name'] ?? 'Customer') ?></h1>
      <div class="sub">
        Account #<?= $view->e($c['account_number'] ?? '—') ?>
        · <span class="badge <?= $view->e($c['status']) ?>"><?= $view->e(ucfirst($c['status'] ?? 'active')) ?></span>
        <?php if ($noCall): ?> · <span class="badge cancelled">No-Call</span><?php endif; ?>
      </div>
    </div>
    <div class="actions">
      <a class="btn btn-ghost" href="/staff/customers">◂ All Customers</a>
      <?php if (!empty($c['phone'])): ?><a class="btn btn-primary" href="tel:<?= $view->e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>">☎ Call</a><?php endif; ?>
    </div>
  </div>

  <?php if ($noCall): ?>
    <div class="notice error"><strong>Do not contact.</strong> <?= $view->e($c['dnc_reason'] ?: 'This customer has opted out of outreach. Respect this flag on every campaign.') ?></div>
  <?php endif; ?>

  <div class="grid g2">
    <div class="panel">
      <h3>Account Details</h3>
      <dl class="kv" style="margin-top:.8rem">
        <dt>Name</dt><dd><?= $view->e($c['name'] ?? '—') ?></dd>
        <dt>Email</dt><dd><?= $view->e($c['email'] ?? '—') ?></dd>
        <dt>Phone</dt><dd><?= $view->e($c['phone'] ?? '—') ?></dd>
        <dt>Address</dt><dd><?= $view->e($addr ?: '—') ?></dd>
        <dt>District</dt><dd class="mono"><?= $view->e(strtoupper($c['district'] ?? '—')) ?></dd>
        <dt>FieldRoutes ID</dt><dd class="mono"><?= $view->e($c['fr_id'] ?? 'not linked') ?></dd>
        <dt>Last Service</dt><dd><?= $view->e($c['last_service'] ? date('M j, Y', strtotime($c['last_service'])) : '—') ?></dd>
        <dt>Customer Since</dt><dd><?= $view->e(isset($c['created_at']) && $c['created_at'] ? date('M j, Y', strtotime($c['created_at'])) : '—') ?></dd>
      </dl>
    </div>

    <div class="panel">
      <h3>FieldRoutes — Live</h3>
      <?php if (!$fr['configured']): ?>
        <p class="muted" style="margin-top:.6rem;line-height:1.7">Appointments, subscriptions &amp; billing stream straight from FieldRoutes so staff never leave this console.</p>
        <p class="muted" style="margin-top:.8rem;line-height:1.7"><span class="badge draft">Pending</span> &nbsp;Waiting on FieldRoutes API credentials in <code>.env</code> (WA + AZ) to connect this customer's live record.</p>
      <?php elseif (!$fr['linked']): ?>
        <p class="muted" style="margin-top:.6rem;line-height:1.7">FieldRoutes is connected, but this local record isn't linked to a FieldRoutes customer ID yet.</p>
        <p class="muted" style="margin-top:.8rem;line-height:1.7">Run <strong>⟳ Sync FieldRoutes</strong> on the Customers page — it matches customers by email and links them automatically.</p>
      <?php else: ?>
        <?php if (!empty($fr['error'])): ?><div class="notice error">Couldn't reach FieldRoutes right now: <?= $view->e($fr['error']) ?></div><?php endif; ?>

        <h4 style="font-family:var(--display);font-size:.95rem;margin:.2rem 0 .6rem">Appointments (<?= count($fr['appointments']) ?>)</h4>
        <?php if (!$fr['appointments']): ?>
          <p class="empty">No appointments on record.</p>
        <?php else: ?>
          <div class="table-wrap"><table class="data">
            <thead><tr><th>When</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($fr['appointments'] as $a): ?>
              <tr>
                <td class="num"><?= $view->e($a['when']) ?></td>
                <td class="muted"><?= $view->e($a['type']) ?></td>
                <td><span class="badge <?= $view->e($a['status_kind']) ?>"><?= $view->e($a['status_label']) ?></span></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>

        <h4 style="font-family:var(--display);font-size:.95rem;margin:1.2rem 0 .6rem">Subscriptions (<?= count($fr['subscriptions']) ?>)</h4>
        <?php if (!$fr['subscriptions']): ?>
          <p class="empty">No active or past subscriptions.</p>
        <?php else: ?>
          <div class="table-wrap"><table class="data">
            <thead><tr><th>Status</th><th>Charge</th><th>Frequency</th><th>Next Service</th><th>Last Completed</th></tr></thead>
            <tbody>
              <?php foreach ($fr['subscriptions'] as $s): ?>
              <tr>
                <td><span class="badge <?= $view->e($s['status_kind']) ?>"><?= $view->e($s['status_label']) ?></span></td>
                <td class="num"><?= $view->e($s['charge']) ?></td>
                <td class="muted"><?= $view->e($s['freq_label']) ?></td>
                <td class="num"><?= $view->e($s['next']) ?></td>
                <td class="num"><?= $view->e($s['last']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid g2">
    <div class="panel">
      <h3>Tickets (<?= count($tickets) ?>)</h3>
      <?php if (!$tickets): ?>
        <p class="empty">No tickets on file.</p>
      <?php else: ?>
        <div class="table-wrap" style="margin-top:.8rem"><table class="data">
          <thead><tr><th>Subject</th><th>Status</th><th>Opened</th></tr></thead>
          <tbody>
            <?php foreach ($tickets as $t): ?>
            <tr>
              <td><?= $view->e($t['subject']) ?></td>
              <td><span class="badge <?= $view->e($t['status']) ?>"><?= $view->e(ucfirst($t['status'])) ?></span></td>
              <td class="num"><?= $view->e(date('M j, Y', strtotime($t['created_at']))) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table></div>
      <?php endif; ?>
    </div>

    <div class="panel">
      <h3>Messages (<?= count($messages) ?>)</h3>
      <?php if (!$messages): ?>
        <p class="empty">No messages.</p>
      <?php else: ?>
        <div style="margin-top:.8rem;display:flex;flex-direction:column;gap:.7rem">
          <?php foreach ($messages as $m): ?>
          <div style="border:1px solid var(--olive-700);padding:.75rem .9rem;border-radius:6px">
            <div class="mono" style="font-size:.7rem;color:var(--khaki)"><?= $view->e($m['from_name'] ?? 'Patriot') ?> → <?= $view->e($m['to_name'] ?? 'customer') ?> · <?= $view->e(date('M j, Y', strtotime($m['created_at']))) ?></div>
            <div style="margin-top:.3rem"><?= $view->e($m['subject'] ?? $m['body']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel">
    <h3>Internal Notes (<?= count($notes) ?>)</h3>
    <?php if (!$notes): ?>
      <p class="empty">No internal notes yet.</p>
    <?php else: ?>
      <div style="margin-top:.8rem;display:flex;flex-direction:column;gap:.6rem">
        <?php foreach ($notes as $n): ?>
        <div style="border-left:3px solid var(--orange);padding:.5rem .9rem;background:var(--olive-900)">
          <div><?= $view->e($n['note']) ?></div>
          <div class="mono" style="font-size:.68rem;color:var(--khaki);margin-top:.3rem"><?= $view->e($n['updated_by'] ?? 'staff') ?> · <?= $view->e(date('M j, Y H:i', strtotime($n['updated_at']))) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div></div>
