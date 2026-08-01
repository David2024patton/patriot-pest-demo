<?php
/**
 * admin/content.php — per-page editable content blocks. Vars: $blocks, $flash.
 * Phase 1 lists the registered blocks; inline editing lands in the CMS phase.
 */
$blocks = $data['blocks'] ?? [];
$flash  = $data['flash'] ?? null;
?>
<div class="app"><div class="wrap">
  <?= $view->raw(\PPC\Core\View::render('admin/_nav', ['active' => 'content', 'flash' => $flash])) ?>

  <div class="app-head">
    <div><h1 style="font-size:1.4rem">Content Blocks</h1><div class="sub">Per-page editable sections (hero, intros, guarantee, FAQs…)</div></div>
  </div>

  <?php if (!$blocks): ?>
    <div class="panel"><p class="muted">No content blocks are registered yet. Blocks let admins edit page sections (hero copy, intros, guarantees) without touching code — they'll appear here as the CMS phase registers them per page.</p></div>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Page</th><th>Block</th><th>Type</th><th>Order</th><th>Updated</th></tr></thead>
    <tbody>
      <?php foreach ($blocks as $b): ?>
      <tr>
        <td class="mono"><?= $view->e($b['page']) ?></td>
        <td><?= $view->e($b['block_key']) ?></td>
        <td><span class="badge draft"><?= $view->e($b['block_type']) ?></span></td>
        <td class="num"><?= (int)$b['sort_order'] ?></td>
        <td class="num"><?= $view->e($b['updated_at'] ? date('M j, Y', strtotime($b['updated_at'])) : '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
