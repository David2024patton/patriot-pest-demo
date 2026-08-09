<div class="app">
  <div class="wrap">
    <div class="app-head">
      <div>
        <h1>New API Key</h1>
        <div class="sub">Copy this key now. It will not be shown again.</div>
      </div>
      <div class="actions">
        <a class="btn btn-ghost" href="/admin/api-keys">Back to API Keys</a>
      </div>
    </div>

<?php $flash = $data['flash'] ?? null; if ($flash && !empty($flash['success'])): ?>
    <div class="flash"><?= $view->e($flash['success']) ?></div>
<?php endif; ?>

    <div class="panel" style="max-width:700px">
      <h3><?= $view->e($data['name'] ?? 'API Key') ?></h3>
      <div style="margin-top:1rem;background:var(--olive-950);border:2px solid var(--orange);padding:1rem;border-radius:4px">
        <div style="font-size:.75rem;color:var(--khaki);margin-bottom:.5rem">YOUR API KEY (copy now — shown once only)</div>
        <div class="mono" style="font-size:1rem;word-break:break-all;color:var(--cream);background:var(--olive-900);padding:.5rem"><?= $view->e($data['rawKey'] ?? '—') ?></div>
      </div>
      <div style="margin-top:1rem;font-size:.82rem;color:var(--khaki)">
        <p>Use this key in the <code>Authorization</code> header:</p>
        <pre style="background:var(--olive-950);padding:.5rem;color:var(--cream)">curl -H "Authorization: Bearer <?= $view->e($data['rawKey'] ?? 'YOUR_KEY') ?>" https://test.patriotpest.pro/api/v1/health</pre>
        <p style="margin-top:.8rem"><b>Security: Store this key securely.</b> If compromised, revoke it immediately from the API Keys page. The raw key is never stored on the server; only its SHA-256 hash is kept.</p>
      </div>
    </div>
  </div>
</div>
