<?php
/**
 * admin/posts.php - list every post (draft + published). Vars: $posts, $flash.
 */
$posts = $data['posts'] ?? [];
$flash = $data['flash'] ?? null;
?>
<div class="app"><div class="wrap">
  <?= $view->raw(\PPC\Core\View::render('admin/_nav', ['active' => 'posts', 'flash' => $flash])) ?>

  <div class="app-head">
    <div><h1 style="font-size:1.4rem">Blog Posts</h1><div class="sub"><?= count($posts) ?> post<?= count($posts) !== 1 ? 's' : '' ?></div></div>
    <div class="actions"><a class="btn btn-primary" href="/admin/posts/new">＋ New Post</a></div>
  </div>

  <?php if (!$posts): ?>
    <p class="empty">No posts yet. <a href="/admin/posts/new" style="color:var(--orange)">Create your first ▸</a></p>
  <?php else: ?>
  <div class="table-wrap"><table class="data">
    <thead><tr><th>Title</th><th>Pest</th><th>Status</th><th>Views</th><th>Updated</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($posts as $p): ?>
      <tr>
        <td><a href="/admin/posts/<?= (int)$p['id'] ?>"><?= $view->e($p['title']) ?></a><div class="mono" style="font-size:.7rem;color:var(--olive-300)">/blogs/<?= $view->e($p['slug']) ?></div></td>
        <td class="muted"><?= $view->e($p['pest_name'] ?? 'N/A') ?></td>
        <td><span class="badge <?= $view->e($p['status']) ?>"><?= $view->e(ucfirst($p['status'])) ?></span></td>
        <td class="num"><?= (int)$p['views'] ?></td>
        <td class="num"><?= $view->e($p['updated_at'] ? date('M j, Y', strtotime($p['updated_at'])) : 'N/A') ?></td>
        <td><div class="row-actions">
          <a href="/admin/posts/<?= (int)$p['id'] ?>">Edit</a>
          <?php if ($p['status'] === 'published'): ?><a href="/blogs/<?= $view->e($p['slug']) ?>" target="_blank">View ↗</a><?php endif; ?>
        </div></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div></div>
