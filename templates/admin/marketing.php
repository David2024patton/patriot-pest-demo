<?php
/**
 * admin/marketing.php: Marketing Command Center.
 * Vars: $campaigns, $templates, $sendStats, $copy, $smsEnabled, $platforms,
 * $ga4Id, $flash.
 */
$campaigns = $data['campaigns'] ?? [];
$templates = $data['templates'] ?? [];
$stats     = $data['sendStats'] ?? [];
$copy      = $data['copy'] ?? [];
$smsOn     = !empty($data['smsEnabled']);
$platforms = $data['platforms'] ?? [];
$ga4Id     = $data['ga4Id'] ?? '';
$flash     = $data['flash'] ?? null;

$mc   = $copy['microcopy'] ?? [];
$cards = $mc['cards'] ?? [];
$btns  = $mc['buttons'] ?? [];
$empty = $mc['empty_states'] ?? [];
$notices = $mc['notices'] ?? [];
$plat  = $mc['platform_connect'] ?? [];
$social = $copy['social_posts'] ?? [];
?>
<div class="app"><div class="wrap">

  <div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
    <div>
      <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem"><?= $view->e($mc['page_title'] ?? 'Marketing Command Center') ?></h1>
      <div class="sub"><?= $view->e($mc['page_sub'] ?? 'Campaigns, social, and analytics in one console.') ?></div>
    </div>
    <div class="actions">
      <a class="btn btn-ghost" href="/staff-dashboard">◂ Dashboard</a>
      <a class="btn btn-primary" href="/admin/marketing/campaigns">▸ <?= $view->e($btns['launch'] ?? 'Launch Campaign') ?></a>
    </div>
  </div>

  <?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
  <?php if (!empty($flash['errors'])): ?>
    <div class="notice error"><strong>Please fix the following:</strong>
      <ul><?php foreach ($flash['errors'] as $e): ?><li><?= $view->e(is_array($e) ? implode(', ', $e) : $e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <?php if (!$smsOn): ?><div class="notice info"><?= $view->e($notices['sms_disabled'] ?? 'SMS is disabled in this environment. Test sends are limited to email.') ?></div><?php endif; ?>

  <div class="stat-cards">
    <div class="stat-card"><div class="v"><?= count($templates) ?></div><div class="k">Active Templates</div></div>
    <div class="stat-card"><div class="v"><?= count($campaigns) ?></div><div class="k">Campaign Drafts</div></div>
    <div class="stat-card"><div class="v"><?= (int)($stats['sent'] ?? 0) ?></div><div class="k">Test Sends</div></div>
    <div class="stat-card"><div class="v"><?= (int)($stats['opened'] ?? 0) ?></div><div class="k">Opens</div></div>
  </div>

  <div class="mkt-grid">

    <!-- ===== Campaign overview ===== -->
    <section class="mkt-card">
      <h3><?= $view->e($cards['campaigns']['title'] ?? 'Campaign Overview') ?></h3>
      <div class="mkt-sub"><?= $view->e($cards['campaigns']['sub'] ?? 'Seasonal reactivation waves') ?></div>
      <?php if (!$campaigns): ?>
        <p class="mkt-empty"><?= $view->e($empty['campaigns'] ?? 'No campaigns yet. Pick a template and launch your first reactivation wave.') ?></p>
      <?php else: ?>
        <div class="table-wrap"><table class="data">
          <tr><th>Campaign</th><th>Template</th><th>Status</th><th>Created</th></tr>
          <?php foreach ($campaigns as $c): ?>
          <tr>
            <td><?= $view->e($c['name']) ?></td>
            <td><?= $view->e($c['template_name'] ?? '') ?></td>
            <td><span class="badge <?= $view->e($c['status']) ?>"><?= $view->e($c['status']) ?></span></td>
            <td class="num"><?= $view->e($c['created_at']) ?></td>
          </tr>
          <?php endforeach; ?>
        </table></div>
      <?php endif; ?>
      <h3 style="margin-top:1.6rem">Template Library</h3>
      <div class="table-wrap"><table class="data">
        <tr><th>Template</th><th>Season</th><th>Channel</th><th>Angle</th></tr>
        <?php foreach ($templates as $t): ?>
        <tr>
          <td><?= $view->e($t['name']) ?></td>
          <td><span class="badge scheduled"><?= $view->e($t['season']) ?></span></td>
          <td class="num"><?= $view->e($t['channel']) ?></td>
          <td><?= $view->e($t['angle'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </table></div>
    </section>

    <div class="mkt-col">

      <!-- ===== Social quick-post ===== -->
      <section class="mkt-card">
        <h3><?= $view->e($cards['social']['title'] ?? 'Social Quick-Post') ?></h3>
        <div class="mkt-sub"><?= $view->e($cards['social']['sub'] ?? 'Post to connected platforms') ?></div>

        <div class="platform-row">
          <?php foreach (['facebook', 'x', 'instagram', 'linkedin'] as $pk): $pc = $plat[$pk] ?? []; ?>
          <div class="platform-card <?= $platforms[$pk] ? 'on' : '' ?>">
            <div class="p-head"><b><?= $view->e($pc['title'] ?? ucfirst($pk)) ?></b>
              <span class="badge <?= $platforms[$pk] ? 'active' : 'draft' ?>"><?= $platforms[$pk] ? ($btns['connected'] ?? 'Connected') : ($btns['not_connected'] ?? 'Not connected') ?></span>
            </div>
            <div class="p-status"><?= $view->e($pc['status'] ?? '') ?></div>
            <div class="p-unlock"><?= $view->e($pc['unlocks'] ?? '') ?></div>
          </div>
          <?php endforeach; ?>
        </div>

        <label class="mkt-label" for="social-post-body">Draft a post</label>
        <select id="social-starter" class="mkt-select" onchange="var o=JSON.parse(this.value);document.getElementById('social-post-body').value=o.body;">
          <option value='{"body":""}'>Start from a starter post...</option>
          <?php foreach ($social as $platform => $posts): foreach ($posts as $p): ?>
            <option value='<?= $view->e(json_encode(["body" => trim(($p["body"] ?? "") . " " . implode(" ", $p["hashtags"] ?? []))])) ?>'><?= $view->e(ucfirst($platform)) ?>: <?= $view->e($p['key']) ?></option>
          <?php endforeach; endforeach; ?>
        </select>
        <textarea id="social-post-body" class="mkt-textarea" rows="5" placeholder="Write or pick a starter post. Publishing unlocks when a platform is connected."></textarea>
        <button class="btn btn-ghost" disabled title="Publishing unlocks when a platform is connected"><?= $view->e($btns['connect'] ?? 'Connect') ?> a platform to publish</button>
        <p class="mkt-empty" style="margin-top:.8rem"><?= $view->e($empty['social'] ?? 'No platforms connected. Connect Facebook, X, Instagram, or LinkedIn to post from here.') ?></p>
      </section>

      <!-- ===== Analytics snapshot ===== -->
      <section class="mkt-card">
        <h3><?= $view->e($cards['analytics']['title'] ?? 'Analytics Snapshot') ?></h3>
        <div class="mkt-sub"><?= $view->e($cards['analytics']['sub'] ?? 'GA4 traffic and lead flow') ?></div>
        <div class="stat-cards" style="margin:1rem 0">
          <div class="stat-card"><div class="v">GA4</div><div class="k"><?= $view->e($ga4Id) ?></div></div>
          <div class="stat-card"><div class="v">2</div><div class="k">Events Wired</div></div>
        </div>
        <p class="mkt-empty"><?= $view->e($empty['analytics'] ?? 'GA4 data appears here once the property is connected and traffic flows.') ?></p>
        <div class="table-wrap"><table class="data">
          <tr><th>Event</th><th>Trigger</th><th>Status</th></tr>
          <tr><td class="num">generate_lead</td><td>Contact form success</td><td><span class="badge active">live</span></td></tr>
          <tr><td class="num">phone_call</td><td>Call button click</td><td><span class="badge active">live</span></td></tr>
          <tr><td class="num">purchase</td><td>Payment portal</td><td><span class="badge draft">blocked on portal</span></td></tr>
        </table></div>
      </section>

    </div>
  </div>

</div></div>
