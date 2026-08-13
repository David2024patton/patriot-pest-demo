<?php
/** admin/audit-log.php - every user action, filterable by role/action/user. */
$rows  = $data['rows'] ?? [];
$role  = $data['role'] ?? 'all';
$action= $data['action'] ?? '';
$user  = $data['user'] ?? '';
$roles = $data['roles'] ?? [];
$page  = $data['page'] ?? 1;
$pages = $data['pages'] ?? 1;
$total = $data['total'] ?? 0;
$qs = fn($extra) => http_build_query(array_merge(['role'=>$role,'action'=>$action,'user'=>$user], $extra));
$badge = function ($t) {
    return match ($t) {
        'super-user' => 'background:var(--red);color:#fff',
        'admin' => 'background:var(--orange);color:var(--olive-950)',
        'staff' => 'background:var(--olive-500);color:#fff',
        'customer' => 'background:var(--olive-700);color:var(--cream)',
        'system' => 'background:var(--olive-950);color:var(--khaki);border:1px solid var(--olive-500)',
        default => 'background:var(--olive-800);color:var(--khaki)',
    };
};
?>
<div class="wrap">
  <div class="eyebrow">ADMIN // AUDIT LOG</div>
  <h1 style="font-family:var(--display);color:var(--cream);font-size:1.8rem;margin:.4rem 0 1rem">Audit Log <span style="color:var(--khaki);font-size:.9rem">(<?= number_format($total) ?> events)</span></h1>

  <form method="get" action="/admin/audit-log" style="display:flex;gap:.6rem;flex-wrap:wrap;background:var(--olive-800);border:1px solid var(--olive-700);padding:1rem;margin-bottom:1rem">
    <select name="role" style="background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem">
      <?php foreach ($roles as $v => $l): ?><option value="<?= $v ?>" <?= $role === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
    </select>
    <input type="text" name="action" value="<?= $view->e($action) ?>" placeholder="Action (e.g. post.create, login)" style="background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem;width:220px">
    <input type="text" name="user" value="<?= $view->e($user) ?>" placeholder="Who (name/email)" style="background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem;width:200px">
    <button type="submit" style="background:var(--orange);color:var(--olive-950);border:0;padding:.5rem 1.2rem;cursor:pointer">Filter</button>
    <a href="/admin/audit-log" style="color:var(--khaki);align-self:center;font-size:.8rem">clear</a>
  </form>

  <div class="table-wrap" style="background:var(--olive-800);border:1px solid var(--olive-700)"><table class="data" style="width:100%;font-size:.8rem">
    <thead><tr style="color:var(--khaki)"><th>#</th><th>When</th><th>Who</th><th>Role</th><th>Action</th><th>Entity</th><th>Meta</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $who = $r['staff_name'] ?: $r['cust_name'] ?: ($r['user_type'] === 'system' ? 'System' : ($r['user_type'] === 'guest' ? 'Guest' : '#' . $r['user_id']));
        $email = $r['staff_email'] ?: $r['cust_email'];
        $rle = $r['staff_role'] ?: $r['user_type'];
    ?>
      <tr>
        <td class="num"><?= (int)$r['id'] ?></td>
        <td class="mono" style="white-space:nowrap"><?= $view->e(date('M j H:i:s', strtotime($r['created_at']))) ?></td>
        <td><?= $view->e($who) ?><?php if ($email): ?><br><small style="color:var(--khaki)"><?= $view->e($email) ?></small><?php endif; ?></td>
        <td><span style="display:inline-block;padding:.15rem .5rem;border-radius:3px;font-size:.68rem;text-transform:uppercase;<?= $badge($rle) ?>"><?= $view->e($rle) ?></span></td>
        <td class="mono"><?= $view->e($r['action']) ?></td>
        <td><?= $view->e($r['entity'] ?: '') ?><?php if ($r['entity_id'] !== null): ?> <span class="mono" style="color:var(--khaki)">#<?= $view->e($r['entity_id']) ?></span><?php endif; ?></td>
        <td style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--khaki)"><?= $view->e($r['meta_json'] ?? '') ?></td>
        <td class="mono" style="color:var(--khaki)"><?= $view->e($r['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="8" style="text-align:center;color:var(--khaki);padding:2rem">No events match the filter.</td></tr><?php endif; ?>
    </tbody>
  </table></div>

  <?php if ($pages > 1): ?>
  <div style="display:flex;gap:.5rem;margin-top:1rem">
    <?php if ($page > 1): ?><a class="btn btn-ghost" href="/admin/audit-log?<?= $qs(['page' => $page - 1]) ?>">‹ Prev</a><?php endif; ?>
    <span style="color:var(--khaki);align-self:center;font-size:.8rem">Page <?= $page ?> / <?= $pages ?></span>
    <?php if ($page < $pages): ?><a class="btn btn-ghost" href="/admin/audit-log?<?= $qs(['page' => $page + 1]) ?>">Next ›</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
