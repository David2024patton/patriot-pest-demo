<?php
/**
 * admin/_nav.php - shared CMS page head + flash messages.
 * Rendered via View::render('admin/_nav', ['active' => '...', 'flash' => ...]).
 *
 * The CMS section nav (Overview / Posts / Media / Content) now lives in the app
 * shell sidebar (layouts/app.php), so this partial only emits the page head and
 * any flash messages. $active is kept for callers but no longer renders tabs.
 */
$active = $data['active'] ?? '';
$flash  = $data['flash'] ?? null;
?>
<div class="app-head" style="border-bottom:1px solid var(--olive-700);padding-bottom:1rem;margin-bottom:1.4rem">
  <div>
    <h1 style="font-family:var(--display);color:var(--cream);font-size:1.6rem">Content Manager</h1>
    <div class="sub">Edit the site without touching code.</div>
  </div>
  <div class="actions">
    <a class="btn btn-ghost" href="/staff-dashboard">◂ Dashboard</a>
    <a class="btn btn-ghost" href="/" target="_blank">View Site ↗</a>
  </div>
</div>

<?php if (!empty($flash['success'])): ?><div class="notice success"><?= $view->e($flash['success']) ?></div><?php endif; ?>
<?php if (!empty($flash['errors'])): ?>
  <div class="notice error"><strong>Please fix the following:</strong>
    <ul><?php foreach ($flash['errors'] as $e): ?><li><?= $view->e(is_array($e) ? implode(', ', $e) : $e) ?></li><?php endforeach; ?></ul>
  </div>
<?php endif; ?>
