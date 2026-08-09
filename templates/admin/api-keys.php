<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>API Keys</h1>
        <div class="sub">Manage keys for AI and agent access to the API.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin">Admin Home</a>
      </div>
    </div>

<?php $flash = $data['flash'] ?? null; if ($flash): ?>
    <div class="flash">
<?php if (!empty($flash['success'])): ?>
      <?= $view->e($flash['success']) ?>
<?php elseif (!empty($flash['errors'])): foreach ($flash['errors'] as $msgs): ?>
      <div style="color:var(--orange)"><?= $view->e(implode(" ", (array)$msgs)) ?></div>
<?php endforeach; endif; ?>
    </div>
<?php endif; ?>

    <div class="panel" style="margin-bottom:1.6rem">
      <h3>Create New Key</h3>
      <form method="post" action="/admin/api-keys" style="margin-top:.8rem">
        <?= $view->csrf() ?>
        <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
          <div>
            <label style="color:var(--cream);font-size:.82rem">Key Name</label>
            <input type="text" name="name" placeholder="e.g. QA Testing" required style="width:200px;padding:.4rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.2rem">
          </div>
          <div>
            <label style="color:var(--cream);font-size:.82rem">Scopes (comma-separated)</label>
            <input type="text" name="scopes" value="customer:read,ticket:read" required style="width:300px;padding:.4rem;background:var(--olive-900);border:1px solid var(--olive-700);color:var(--cream);margin-top:.2rem">
          </div>
          <button type="submit" class="btn" style="background:var(--orange);color:var(--olive-950);padding:.4rem 1rem;border:none">Generate Key</button>
        </div>
        <div style="font-size:.7rem;color:var(--khaki);margin-top:.5rem">
          Available scopes: customer:read, customer:read-full, ticket:read, message:read, twilio:read, staff:read, all
        </div>
      </form>
    </div>

    <div class="panel">
      <h3>Existing Keys</h3>
<?php $keys = $data['keys'] ?? []; if (!$keys): ?>
      <p class="empty">No API keys yet. Create one above.</p>
<?php else: ?>
      <div class="table-wrap" style="margin-top:.8rem"><table class="data">
        <thead><tr><th>Name</th><th>Prefix</th><th>Scopes</th><th>Last Used</th><th>Status</th><th></th></tr></thead>
        <tbody>
<?php foreach ($keys as $k): $revoked = $k['revoked_at'] !== null; $expired = $k['expires_at'] && strtotime($k['expires_at']) < time(); ?>
          <tr>
            <td><?= $view->e($k['name']) ?></td>
            <td class="mono">ppc_live_<?= $view->e($k['key_prefix']) ?>...</td>
            <td style="font-size:.75rem"><?= $view->e(implode(", ", json_decode($k['scopes'] ?? '[]', true) ?: [])) ?></td>
            <td class="num"><?= $view->e($k['last_used_at'] ? date("M j, Y", strtotime($k['last_used_at'])) : "Never") ?></td>
            <td><span class="badge <?= $revoked ? 'cancelled' : ($expired ? 'cancelled' : 'active') ?>"><?= $revoked ? "Revoked" : ($expired ? "Expired" : "Active") ?></span></td>
            <td style="display:flex;gap:.3rem;flex-wrap:wrap">
<?php if (!$revoked && !$expired): ?>
              <form method="post" action="/admin/api-keys/<?= (int)$k['id'] ?>/scopes" style="display:inline">
                <?= $view->csrf() ?>
                <input type="hidden" name="scopes" value="<?= $view->e(implode(",", json_decode($k['scopes'] ?? '[]', true) ?: [])) ?>">
              </form>
              <form method="post" action="/admin/api-keys/<?= (int)$k['id'] ?>/rotate" style="display:inline">
                <?= $view->csrf() ?>
                <button class="btn btn-ghost" style="font-size:.7rem;padding:.2rem .5rem" type="submit">Rotate</button>
              </form>
              <form method="post" action="/admin/api-keys/<?= (int)$k['id'] ?>/revoke" style="display:inline">
                <?= $view->csrf() ?>
                <button class="btn btn-ghost" style="font-size:.7rem;padding:.2rem .5rem;color:var(--orange)" type="submit">Revoke</button>
              </form>
<?php endif; ?>
            </td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </div>
  </div>
</div>
