<?php
/**
 * staff/messages.php — internal message center.
 * Vars: $messages, $isAdmin.
 */
$messages = $data['messages'] ?? [];
?>
<div class="app"><div class="wrap">
  <div class="app-head">
    <div>
      <h1>Messages</h1>
      <div class="sub">Customer &amp; staff conversations · Twilio SMS threads plug in here</div>
    </div>
    <div class="actions"><a class="btn btn-ghost" href="/staff-dashboard">◂ Dashboard</a></div>
  </div>

  <?php if (!$messages): ?>
    <div class="panel"><p class="empty">No messages yet. Inbound contact requests and Twilio SMS threads will appear here once connected.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>From</th><th>To</th><th>Subject</th><th>Received</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($messages as $m): ?>
      <tr>
        <td><?= $view->e($m['from_name'] ?? $m['from_user']) ?><div class="mono" style="font-size:.68rem;color:var(--olive-300)"><?= $view->e($m['from_type']) ?></div></td>
        <td><?= $view->e($m['to_name'] ?? $m['to_user']) ?></td>
        <td><?= $view->e($m['subject'] ?? mb_strimwidth($m['body'], 0, 60, '…')) ?></td>
        <td class="num"><?= $view->e(date('M j, Y H:i', strtotime($m['created_at']))) ?></td>
        <td><?php if ((int)($m['is_read'] ?? 0) === 0): ?><span class="badge open">New</span><?php else: ?><span class="muted">read</span><?php endif; ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
