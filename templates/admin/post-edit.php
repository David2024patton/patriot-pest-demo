<?php
/**
 * admin/post-edit.php - create/edit a post (with pest-photo picker).
 * Vars: $post (row|null), $photos (all pest_photos), $flash.
 * New posts POST to /admin/posts; existing to /admin/posts/{id}.
 */
$post   = $data['post'] ?? null;
$photos = $data['photos'] ?? [];
$flash  = $data['flash'] ?? null;
$isNew  = $post === null;
$action = $isNew ? '/admin/posts' : '/admin/posts/' . (int)$post['id'];
$old    = fn(string $k, $d = '') => $view->e($post[$k] ?? $d);
$selPhoto = $post['pest_photo_id'] ?? null;
?>
<div class="app"><div class="wrap">
  <?= $view->raw(\PPC\Core\View::render('admin/_nav', ['active' => 'posts', 'flash' => $flash])) ?>

  <div class="app-head">
    <div><h1 style="font-size:1.4rem"><?= $isNew ? 'New Post' : 'Edit Post' ?></h1>
      <div class="sub"><?= $isNew ? 'Write a new field report.' : 'Editing “' . $view->e($post['title']) . '”' ?></div></div>
    <div class="actions"><a class="btn btn-ghost" href="/admin/posts">◂ All Posts</a></div>
  </div>

  <form method="post" action="<?= $view->e($action) ?>" class="form-stack wide" novalidate>
    <?= $view->csrf() ?>
    <?php if (!$isNew): ?><input type="hidden" name="id" value="<?= (int)$post['id'] ?>"><?php endif; ?>

    <div class="field">
      <label for="title">Title</label>
      <input type="text" id="title" name="title" required maxlength="200" value="<?= $old('title') ?>">
    </div>

    <div class="form-row">
      <div class="field">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" required maxlength="200" value="<?= $old('slug') ?>" placeholder="why-ants-invade-in-spring">
        <div class="hint">URL: /blogs/&lt;slug&gt;. Auto-uniqued on save.</div>
      </div>
      <div class="field">
        <label for="status">Status</label>
        <select id="status" name="status">
          <?php foreach (['draft', 'published', 'scheduled'] as $s): ?>
            <option value="<?= $s ?>" <?= ($post['status'] ?? 'draft') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="season">Season</label>
        <select id="season" name="season">
          <option value="">None</option>
          <?php foreach (['spring', 'summer', 'fall', 'winter'] as $s): ?>
            <option value="<?= $s ?>" <?= ($post['season'] ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label for="pest_category">Pest Category</label>
        <select id="pest_category" name="pest_category">
          <option value="">None</option>
          <?php foreach (['insect', 'rodent', 'wildlife'] as $c): ?>
            <option value="<?= $c ?>" <?= ($post['pest_category'] ?? '') === $c ? 'selected' : '' ?>><?= ucfirst($c) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="excerpt">Excerpt</label>
      <textarea id="excerpt" name="excerpt" maxlength="500" style="min-height:80px"><?= $old('excerpt') ?></textarea>
      <div class="hint">Short summary shown on the blog index and in search results.</div>
    </div>

    <div class="field">
      <label for="body_html">Body (HTML)</label>
      <textarea id="body_html" name="body_html" style="min-height:280px;font-family:var(--mono);font-size:.85rem"><?= $old('body_html') ?></textarea>
      <div class="hint">Allowed: p, br, strong, em, ul, ol, li, h2–h4, blockquote, a, img, table. Scripts &amp; handlers are stripped on save.</div>
    </div>

    <div class="field">
      <label>Featured Pest Photo</label>
      <div class="hint" style="margin-bottom:.7rem">Pick a photo from the library. It gets the site's tactical treatment automatically.</div>
      <div class="picker">
        <label class="none"><input type="radio" name="pest_photo_id" value="" <?= $selPhoto ? '' : 'checked' ?>><span>No photo</span></label>
        <?php foreach ($photos as $ph): ?>
        <label>
          <input type="radio" name="pest_photo_id" value="<?= (int)$ph['id'] ?>" <?= $selPhoto == $ph['id'] ? 'checked' : '' ?>>
          <span class="pk-thumb"><img src="<?= $view->asset('img/pests/' . $ph['filename']) ?>" alt="<?= $view->e($ph['name']) ?>" loading="lazy"></span>
          <span class="pk-name"><?= $view->e($ph['name']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Post' : 'Save Changes' ?> ▸</button>
      <a class="btn btn-ghost" href="/admin/posts">Cancel</a>
    </div>
  </form>
</div></div>
