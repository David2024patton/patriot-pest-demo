<?php
/**
 * admin/marketing-campaigns.php: reactivation campaign launcher.
 * Vars: $templates, $districts, $statuses, $previews, $seeds, $copy,
 * $smsEnabled, $flash.
 */
$templates = $data['templates'] ?? [];
$districts = $data['districts'] ?? [];
$statuses  = $data['statuses'] ?? [];
$previews  = $data['previews'] ?? [];
$seeds     = $data['seeds'] ?? [];
$copy      = $data['copy'] ?? [];
$smsOn     = !empty($data['smsEnabled']);
$flash     = $data['flash'] ?? null;

$mc      = $copy['microcopy'] ?? [];
$btns    = $mc['buttons'] ?? [];
$notices = $mc['notices'] ?? [];
$segs    = $copy['segments'] ?? [];

$dCounts = []; foreach ($districts as $d) { $dCounts[$d['district']] = (int) $d['n']; }
$sCounts = []; foreach ($statuses as $s) { $sCounts[$s['status']] = (int) $s['n']; }
$dLabels = []; foreach ($segs['by_district'] ?? [] as $d) { $dLabels[$d['key']] = $d['label']; }
$sLabels = []; foreach ($segs['by_status'] ?? [] as $s) { $sLabels[$s['key']] = $s['label']; }
?>
<div class="app"><div class="wrap">

  <div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
    <div>
      <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Reactivation Launcher</h1>
      <div class="sub">Pick a segment, preview the message, test it, save the draft.</div>
    </div>
    <div class="actions">
      <a class="btn btn-ghost" href="/admin/marketing">◂ Command Center</a>
    </div>
  </div>

  <?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
  <?php if (!empty($flash['errors'])): ?>
    <div class="notice error"><strong>Please fix the following:</strong>
      <ul><?php foreach ($flash['errors'] as $e): ?><li><?= $view->e(is_array($e) ? implode(', ', $e) : $e) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <?php if (!$smsOn): ?><div class="notice info"><?= $view->e($notices['sms_disabled'] ?? 'SMS is disabled in this environment. Test sends are limited to email.') ?></div><?php endif; ?>
  <div class="notice info"><?= $view->e($notices['test_only'] ?? 'Test sends go to approved test contacts only. No production numbers.') ?></div>

  <div class="mkt-grid">
    <section class="mkt-card">
      <h3>1. Segment</h3>
      <form method="post" action="/admin/marketing/campaigns/store" class="form-stack wide" id="campaign-form">
        <?= $view->csrf() ?>
        <div class="form-row">
          <div>
            <label class="mkt-label" for="f-district">District</label>
            <select id="f-district" name="district" class="mkt-select">
              <option value="all">All districts</option>
              <option value="wa"><?= $view->e($dLabels['wa'] ?? 'WA') ?> (<?= (int)($dCounts['wa'] ?? 0) ?> test)</option>
              <option value="az"><?= $view->e($dLabels['az'] ?? 'AZ') ?> (<?= (int)($dCounts['az'] ?? 0) ?> test)</option>
            </select>
          </div>
          <div>
            <label class="mkt-label" for="f-status">Status</label>
            <select id="f-status" name="status" class="mkt-select">
              <option value="all">All statuses</option>
              <option value="cancelled"><?= $view->e($sLabels['cancelled'] ?? 'Cancelled') ?> (<?= (int)($sCounts['cancelled'] ?? 0) ?> test)</option>
              <option value="inactive"><?= $view->e($sLabels['inactive'] ?? 'Inactive') ?> (<?= (int)($sCounts['inactive'] ?? 0) ?> test)</option>
              <option value="active"><?= $view->e($sLabels['active'] ?? 'Active') ?> (<?= (int)($sCounts['active'] ?? 0) ?> test)</option>
            </select>
          </div>
        </div>

        <h3 style="margin-top:1.4rem">2. Template</h3>
        <select id="f-template" name="template_id" class="mkt-select" onchange="mktPreview(this.value)">
          <?php foreach ($templates as $t): ?>
          <option value="<?= (int) $t['id'] ?>"><?= $view->e($t['name']) ?> (<?= $view->e($t['season']) ?>)</option>
          <?php endforeach; ?>
        </select>

        <h3 style="margin-top:1.4rem">3. Preview</h3>
        <div class="mkt-preview" id="mkt-preview">
          <?php foreach ($templates as $i => $t): $pv = $previews[$t['id']] ?? []; ?>
          <div class="mkt-pv" data-tid="<?= (int) $t['id'] ?>" style="<?= $i ? 'display:none' : '' ?>">
            <div class="mkt-label">Email subject</div>
            <div class="mkt-pv-subject"><?= $view->e($pv['subject'] ?? '') ?></div>
            <div class="mkt-label">Email body</div>
            <div class="mkt-pv-html"><?= $view->raw($pv['html'] ?? '') ?></div>
            <div class="mkt-label">SMS (<?= mb_strlen($pv['sms'] ?? '') ?> chars)</div>
            <div class="mkt-pv-sms"><?= $view->e($pv['sms'] ?? '') ?></div>
            <?php if (!empty($t['cta'])): ?><div class="mkt-cta">CTA: <?= $view->e($t['cta']) ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <h3 style="margin-top:1.4rem">4. Test send</h3>
        <div class="form-row">
          <div>
            <label class="mkt-label" for="f-customer">Test customer (seed book only)</label>
            <select id="f-customer" name="customer_id" class="mkt-select">
              <?php foreach ($seeds as $s): ?>
              <option value="<?= (int) $s['id'] ?>"><?= $view->e($s['name']) ?> · <?= $view->e($s['district']) ?> · <?= $view->e($s['status']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="mkt-label">Channel</label>
            <div class="mkt-channel-row">
              <label class="mkt-radio"><input type="radio" name="channel" value="email" checked> Email</label>
              <label class="mkt-radio <?= $smsOn ? '' : 'off' ?>"><input type="radio" name="channel" value="sms" <?= $smsOn ? '' : 'disabled' ?>> SMS <?= $smsOn ? '' : '(disabled)' ?></label>
            </div>
          </div>
        </div>
        <div class="mkt-actions">
          <button type="submit" class="btn btn-ghost" formaction="/admin/marketing/campaigns/test" name="mkt_action" value="test"><?= $view->e($btns['send_test'] ?? 'Send Test') ?></button>
          <button type="submit" formaction="/admin/marketing/campaigns/store" class="btn btn-primary" name="mkt_action" value="draft"><?= $view->e($btns['save_draft'] ?? 'Save Draft') ?></button>
          <button type="button" class="btn btn-ghost" disabled><?= $view->e($btns['schedule'] ?? 'Schedule (coming soon)') ?></button>
        </div>
      </form>
    </section>

    <div class="mkt-col">
      <section class="mkt-card">
        <h3>Segment guide</h3>
        <div class="mkt-sub">Voice and ordering from the Marketing copy pack.</div>
        <div class="table-wrap"><table class="data">
          <tr><th>Segment</th><th>Why</th></tr>
          <?php foreach ($segs['by_status'] ?? [] as $s): ?>
          <tr><td><?= $view->e($s['label']) ?></td><td><?= $view->e($s['description']) ?></td></tr>
          <?php endforeach; ?>
          <?php foreach ($segs['by_district'] ?? [] as $d): ?>
          <tr><td><?= $view->e($d['label']) ?></td><td><?= $view->e($d['description']) ?></td></tr>
          <?php endforeach; ?>
        </table></div>
        <div class="mkt-label" style="margin-top:1rem">Combined examples</div>
        <ul class="mkt-list">
          <?php foreach ($segs['combined_examples'] ?? [] as $ex): ?><li><?= $view->e($ex) ?></li><?php endforeach; ?>
        </ul>
        <p class="mkt-empty" style="margin-top:1rem">Pest-history targeting ships when service history lands in the data model. Until then, match pest by template season.</p>
      </section>
    </div>
  </div>

</div></div>

<script>
function mktPreview(tid) {
  document.querySelectorAll('.mkt-pv').forEach(function (el) {
    el.style.display = el.getAttribute('data-tid') === String(tid) ? '' : 'none';
  });
}
</script>
