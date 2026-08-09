<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>API Key Audit Trail</h1>
        <div class="sub">Issue, rotate, revoke, and scope changes on API keys. Newest first, last 200.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin/api-keys">API Keys</a>
        <a class="btn btn-ghost" href="/admin">Admin Home</a>
      </div>
    </div>

    <div class="panel" style="margin-bottom:1.6rem">
      <form method="get" action="/admin/api-keys/audit" style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
        <div>
          <label style="color:var(--cream);font-size:.82rem">Filter by key</label>
          <select name="key" style="min-width:260px;padding:.4rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.2rem">
            <option value="">All keys</option>
<?php foreach ($data['keys'] as $k): ?>
            <option value="<?= $view->e($k['key_prefix']) ?>" <?= ($data['keyFilter'] ?? '') === $k['key_prefix'] ? 'selected' : '' ?>><?= $view->e($k['name']) ?> (ppc_live_<?= $view->e($k['key_prefix']) ?>...)</option>
<?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.4rem 1rem;border:none">Filter</button>
        <?php if (($data['keyFilter'] ?? '') !== ''): ?>
        <a class="btn btn-ghost" href="/admin/api-keys/audit">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="panel">
      <h3>Lifecycle Events</h3>
<?php $rows = $data['rows'] ?? []; if (!$rows): ?>
      <p class="empty">No key lifecycle events yet.</p>
<?php else: ?>
      <div class="table-wrap" style="margin-top:.8rem"><table class="data">
        <thead><tr><th>Time</th><th>Action</th><th>Key</th><th>Actor</th><th>Scopes</th><th>IP</th></tr></thead>
        <tbody>
<?php foreach ($rows as $r): $m = $r['meta']; $action = str_replace('api_key.', '', $r['action']); ?>
          <tr>
            <td class="num"><?= $view->e(date("M j, Y H:i", strtotime($r['created_at']))) ?></td>
            <td><span class="badge <?= $action === 'revoke' ? 'cancelled' : 'active' ?>"><?= $view->e($action) ?></span></td>
            <td><?= $view->e($r['key_label']) ?></td>
            <td class="mono" style="font-size:.75rem"><?= $view->e($r['user_id']) ?></td>
            <td style="font-size:.75rem">
<?php if (isset($m['scopes_before']) || isset($m['scopes_after'])): ?>
              <?= $view->e(implode(", ", (array)($m['scopes_before'] ?? $m['scopes'] ?? []))) ?>
<?php if (isset($m['scopes_after'])): ?> &rarr; <?= $view->e(implode(", ", (array)$m['scopes_after'])) ?><?php endif; ?>
<?php if (isset($m['new_key_prefix'])): ?><br><span style="color:var(--khaki)">new key: ppc_live_<?= $view->e($m['new_key_prefix']) ?>...</span><?php endif; ?>
<?php elseif (isset($m['scopes'])): ?>
              <?= $view->e(implode(", ", (array)$m['scopes'])) ?>
<?php endif; ?>
            </td>
            <td class="mono" style="font-size:.75rem"><?= $view->e($r['ip']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
  </div>
</div>
