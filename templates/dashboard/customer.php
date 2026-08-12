<?php
/**
 * dashboard/customer.php N/A the customer portal overview.
 * Vars: $customer (row|null), $tickets, $messages, $appointments, $subscriptions,
 *       $paymentMethods, $invoices, $payments, $frData, $name.

 */
$customer       = $data['customer'] ?? null;
$tickets        = $data['tickets'] ?? [];
$messages       = $data['messages'] ?? [];
$appointments   = $data['appointments'] ?? [];
$subscriptions  = $data['subscriptions'] ?? [];
$paymentMethods = $data['paymentMethods'] ?? [];
$invoices       = $data['invoices'] ?? [];
$payments       = $data['payments'] ?? [];
$frData         = $data['frData'] ?? ['configured' => false, 'linked' => false];
$name           = $data['name'] ?? 'Customer';
?>
<div class="app">
  <div class="wrap">
    <?php if (\PPC\Core\Session::pullFlash('analytics_event') === 'portal_login'): ?>
    <script>
      gtag('event', 'portal_login', {
        'event_category': 'engagement',
        'event_label': '<?= $view->e($name) ?>'
      });
    </script>
    <?php endif; ?>
    <div class="app-head">
      <div>
        <h1>Welcome back, <?= $view->e($name) ?>.</h1>
        <div class="sub">Your Patriot Pest Control account at a glance.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/contact">Request Service ▸</a>
        <a class="btn btn-ghost" href="/logout">Sign Out</a>
      </div>
    </div>

    <?php if ($customer): ?>
    <div class="panel">
      <h3>Account Details</h3>
      <dl class="kv" style="margin-top:.8rem">
        <dt>Account #</dt><dd class="mono"><?= $view->e($customer['account_number'] ?? 'N/A') ?></dd>
        <dt>Name</dt><dd><?= $view->e($customer['name'] ?? 'N/A') ?></dd>
        <dt>Email</dt><dd><?= $view->e($customer['email'] ?? 'N/A') ?></dd>
        <dt>Phone</dt><dd><?= $view->e($customer['phone'] ?? 'N/A') ?></dd>
        <dt>Service Address</dt><dd><?= $view->e(trim(($customer['address'] ?? '') . ', ' . ($customer['city'] ?? '') . ', ' . ($customer['state'] ?? '') . ' ' . ($customer['zip'] ?? ''), ', ')) ?></dd>
        <dt>Status</dt><dd><span class="badge <?= $view->e($customer['status']) ?>"><?= $view->e(ucfirst($customer['status'] ?? 'active')) ?></span></dd>
      </dl>
    </div>
    <?php endif; ?>

    <div class="grid g2">
      <div class="panel">
        <h3>Recent Tickets</h3>
        <?php if (!$tickets): ?>
          <p class="empty">No tickets yet. <a href="/contact" style="color:var(--orange)">Request service ▸</a></p>
        <?php else: ?>
          <div class="table-wrap" style="margin-top:.8rem"><table class="data">
            <thead><tr><th>Subject</th><th>Status</th><th>Opened</th></tr></thead>
            <tbody>
              <?php foreach ($tickets as $t): ?>
              <tr>
                <td><?= $view->e($t['subject']) ?></td>
                <td><span class="badge <?= $view->e($t['status']) ?>"><?= $view->e(ucfirst($t['status'])) ?></span></td>
                <td class="num"><?= $view->e(date('M j', strtotime($t['created_at']))) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Messages</h3>
        <?php if (!$messages): ?>
          <p class="empty">No messages.</p>
        <?php else: ?>
          <div style="margin-top:.8rem;display:flex;flex-direction:column;gap:.8rem">
            <?php foreach ($messages as $m): ?>
            <div style="border:1px solid var(--olive-700);padding:.8rem 1rem">
              <div class="mono" style="font-size:.72rem;color:var(--khaki)"><?= $view->e($m['from_name'] ?? 'Patriot') ?> · <?= $view->e(date('M j', strtotime($m['created_at']))) ?></div>
              <div style="color:var(--cream);margin-top:.3rem"><?= $view->e($m['subject'] ?? $m['body']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($appointments): ?>
    <div class="panel" style="margin-top:1.6rem">
      <h3>Upcoming Appointments</h3>
      <div class="table-wrap" style="margin-top:.8rem"><table class="data">
        <thead><tr><th>When</th><th>Type</th><th>Status</th><th>Notes</th></tr></thead>
        <tbody>
          <?php foreach ($appointments as $a): ?>
          <tr>
            <td><?= $view->e($a['when'] ?? $a['scheduled'] ?? 'N/A') ?></td>
            <td><?= $view->e($a['type'] ?? 'N/A') ?></td>
            <td><span class="badge <?= $view->e($a['status_kind'] ?? 'open') ?>"><?= $view->e($a['status_label'] ?? 'N/A') ?></span></td>
            <td><?= $view->e(mb_substr($a['notes'] ?? '', 0, 60)) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <?php endif; ?>

    <div class="grid g2" style="margin-top:1.6rem">
      <div class="panel">
        <h3>Active Subscriptions</h3>
        <?php if (!$subscriptions): ?>
          <p class="empty">No active subscriptions on file. <a href="/contact" style="color:var(--orange)">Contact us to set up service ▸</a></p>
        <?php else: ?>
          <div class="table-wrap" style="margin-top:.8rem"><table class="data">
            <thead><tr><th>Status</th><th>Charge</th><th>Frequency</th><th>Next Service</th></tr></thead>
            <tbody>
              <?php foreach ($subscriptions as $s): ?>
              <tr>
                <td><span class="badge <?= $view->e($s['status'] === 'active' ? 'active' : 'cancelled') ?>"><?= $view->e($s['status_label'] ?? $s['status'] ?? 'N/A') ?></span></td>
                <td><?= $view->e($s['charge'] ?? 'N/A') ?></td>
                <td><?= $view->e($s['freq_label'] ?? 'N/A') ?></td>
                <td><?= $view->e($s['next_service'] ?? 'N/A') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
        <?php endif; ?>
      </div>

      <div class="panel">
        <h3>Billing Overview</h3>
        <?php if (!$invoices && !$payments): ?>
          <p class="empty">No billing history yet. Payments are managed through FieldRoutes N/A contact us for billing questions.</p>
          <?php if ($paymentMethods): ?>
          <div style="margin-top:1rem">
            <h4 style="font-size:.85rem;color:var(--khaki)">Payment Methods on File</h4>
            <?php foreach ($paymentMethods as $pm): ?>
            <div style="color:var(--cream);font-size:.82rem;margin-top:.3rem">
              <?= $view->e(ucfirst($pm['method_type'] ?? 'Card')) ?> <?= $view->e($pm['last_four'] ? 'ending in ' . $pm['last_four'] : '') ?>
              <?php if ($pm['exp_month'] && $pm['exp_year']): ?>
                · Expires <?= $view->e($pm['exp_month'] . '/' . $pm['exp_year']) ?>
              <?php endif; ?>
              <?php if ($pm['is_default']): ?><span class="badge active">Default</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <?php if ($invoices): ?>
          <h4 style="font-size:.85rem;color:var(--khaki);margin-top:.8rem">Recent Invoices</h4>
          <div class="table-wrap" style="margin-top:.4rem"><table class="data">
            <thead><tr><th>#</th><th>Amount</th><th>Balance</th><th>Status</th><th>Due</th></tr></thead>
            <tbody>
              <?php foreach ($invoices as $inv): ?>
              <tr>
                <td><?= $view->e($inv['invoice_number'] ?? '#N/A') ?></td>
                <td><?= $view->e($inv['amount'] ?? 'N/A') ?></td>
                <td><?= $view->e($inv['balance'] ?? 'N/A') ?></td>
                <td><span class="badge <?= $view->e($inv['status']) ?>"><?= $view->e(ucfirst($inv['status'])) ?></span></td>
                <td class="num"><?= $view->e($inv['due_date'] ? date('M j', strtotime($inv['due_date'])) : 'N/A') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php endif; ?>
          <?php if ($payments): ?>
          <h4 style="font-size:.85rem;color:var(--khaki);margin-top:1rem">Recent Payments</h4>
          <div class="table-wrap" style="margin-top:.4rem"><table class="data">
            <thead><tr><th>Date</th><th>Amount</th><th>Method</th></tr></thead>
            <tbody>
              <?php foreach ($payments as $p): ?>
              <tr>
                <td><?= $view->e($p['payment_date'] ? date('M j, Y', strtotime($p['payment_date'])) : 'N/A') ?></td>
                <td><?= $view->e($p['amount'] ?? 'N/A') ?></td>
                <td><?= $view->e(ucfirst($p['payment_method'] ?? 'N/A')) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table></div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="promise" style="margin-top:1.6rem"><b>Need something?</b> Call <a href="tel:+15094715767" style="color:var(--orange)">(509) 471-5767</a> or use the contact form N/A we're here to help. <a href="/help" style="color:var(--orange)">Help Center ▸</a></div>

  </div>
</div>
