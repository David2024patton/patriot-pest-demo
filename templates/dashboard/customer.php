<?php
/**
 * dashboard/customer.php - the customer portal overview.
 * Vars: $customer (row|null), $tickets, $messages, $name.
 */
$customer = $data['customer'] ?? null;
$tickets  = $data['tickets'] ?? [];
$messages = $data['messages'] ?? [];
$name     = $data['name'] ?? 'Customer';
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

    <div class="promise" style="margin-top:1.6rem"><b>Need something?</b> Call <a href="tel:+15094715767" style="color:var(--orange)">(509) 471-5767</a> or use the contact form. We're here to help. <a href="/help" style="color:var(--orange)">Help Center ▸</a></div>
  </div>
</div>
