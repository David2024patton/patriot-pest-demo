<?php
/** admin/ads.php - targeted email ad catalog manager. */
$ads = $data['ads'] ?? [];
$msg = $data['msg'] ?? '';
$bucketLabel = ['new_plan' => 'New Plans', 'upgrade' => 'Upsell / Add-ons', 'reactivate' => 'Reactivate', 'referral' => 'Referral', 'review' => 'Reviews'];
?>
<div class="wrap">
  <div class="eyebrow">ADMIN // MARKETING ADS</div>
  <h1 style="font-family:var(--display);color:var(--cream);font-size:1.8rem;margin:.4rem 0 1rem">Marketing Ads</h1>
  <p style="color:var(--khaki);font-size:.85rem;margin-bottom:1.2rem">These rotate inside customer login-code emails, targeted by account state (new / active / lapsed), region, and season. Weight = how often it shows. Every CTA is UTM-tracked; impressions are counted.</p>
  <?php if ($msg): ?><p style="background:var(--olive-800);border:1px solid var(--olive-500);color:var(--cream);padding:.7rem 1rem;margin-bottom:1rem"><?= $view->e(is_string($msg) ? $msg : '') ?></p><?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:1.6rem">
    <form method="post" action="/admin/ads" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.4rem;align-self:start">
      <?= $view->csrf() ?>
      <h2 style="font-family:var(--display);color:var(--cream);font-size:1.05rem;margin-bottom:.9rem">Add Ad</h2>
      <div class="field"><label for="bucket">Bucket</label>
        <select id="bucket" name="bucket"><?php foreach ($bucketLabel as $v => $l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select>
      </div>
      <div class="field"><label for="title">Headline</label><input type="text" id="title" name="title" required maxlength="120" placeholder="Add Flea &amp; Tick protection"></div>
      <div class="field"><label for="body">Body</label><textarea id="body" name="body" style="min-height:70px" maxlength="300" placeholder="One or two sentences…"></textarea></div>
      <div class="field"><label for="cta_label">Button text</label><input type="text" id="cta_label" name="cta_label" value="Learn More" maxlength="40"></div>
      <div class="field"><label for="cta_url">Button URL</label><input type="text" id="cta_url" name="cta_url" value="/prices" maxlength="200" placeholder="/prices"></div>
      <div class="form-row">
        <div class="field"><label for="region">Region</label><select id="region" name="region"><?php foreach (['all'=>'All','wa'=>'WA','id'=>'ID','or'=>'OR','az'=>'AZ'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="season">Season</label><select id="season" name="season"><?php foreach (['all'=>'All','spring'=>'Spring','summer'=>'Summer','fall'=>'Fall','winter'=>'Winter'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></div>
        <div class="field"><label for="weight">Weight</label><input type="number" id="weight" name="weight" value="1" min="1" max="10" style="width:70px"></div>
      </div>
      <button class="btn btn-primary" type="submit">Add Ad</button>
    </form>

    <div style="display:flex;flex-direction:column;gap:.7rem">
      <?php foreach ($ads as $a): ?>
      <div style="background:var(--olive-800);border:1px solid <?= $a['active'] ? 'var(--olive-500)' : 'var(--olive-700)' ?>;padding:1rem">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:.8rem;flex-wrap:wrap">
          <div style="flex:1">
            <span style="font-size:.68rem;text-transform:uppercase;letter-spacing:.12em;color:var(--orange);font-family:var(--mono)"><?= $view->e($bucketLabel[$a['bucket']] ?? $a['bucket']) ?></span>
            <span style="font-size:.68rem;color:var(--khaki);font-family:var(--mono)"> · <?= strtoupper($view->e($a['region'])) ?> · <?= ucfirst($view->e($a['season'])) ?> · w<?= (int)$a['weight'] ?> · <?= (int)$a['impressions'] ?> shown</span>
            <div style="color:var(--cream);font-weight:600;margin-top:.2rem"><?= $view->e($a['title']) ?></div>
            <div style="color:var(--khaki);font-size:.82rem;line-height:1.45"><?= $view->e($a['body']) ?></div>
            <div style="font-size:.72rem;color:var(--olive-300);font-family:var(--mono);margin-top:.3rem">[ <?= $view->e($a['cta_label']) ?> → <?= $view->e($a['cta_url']) ?> ]</div>
          </div>
          <form method="post" action="/admin/ads/toggle">
            <?= $view->csrf() ?>
            <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
            <button type="submit" style="background:none;border:1px solid <?= $a['active'] ? 'var(--red)' : 'var(--olive-500)' ?>;color:<?= $a['active'] ? 'var(--red)' : 'var(--cream)' ?>;padding:.35rem .7rem;cursor:pointer;font-size:.72rem"><?= $a['active'] ? 'Pause' : 'Enable' ?></button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (!$ads): ?><p style="color:var(--khaki)">No ads yet.</p><?php endif; ?>
    </div>
  </div>
</div>
