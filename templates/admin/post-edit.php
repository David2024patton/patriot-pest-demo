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
      <div class="field">
        <label for="region">Target Region</label>
        <select id="region" name="region">
          <?php foreach (['all' => 'All States', 'wa' => 'Washington', 'id' => 'Idaho', 'or' => 'Oregon', 'az' => 'Arizona'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($post['region'] ?? 'all') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint">Used for region-specific pest calendars and local SEO.</div>
      </div>
    </div>

    <div class="form-row" id="sched-row" style="display:none">
      <div class="field">
        <label for="scheduled_at">Publish At (local time)</label>
        <input type="datetime-local" id="scheduled_at" name="scheduled_at"
               value="<?= $view->e(str_replace(' ', 'T', (string)($post['scheduled_at'] ?? ''))) ?>"
               step="60">
        <div class="hint">Post auto-publishes when this time passes.</div>
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
      <div style="display:flex;gap:.6rem;align-items:center;margin-top:.6rem">
        <button type="button" id="ai-draft-btn" style="background:var(--olive-700);color:var(--cream);border:1px solid var(--olive-500);padding:.5rem .9rem;cursor:pointer;font-family:var(--mono);font-size:.75rem">✦ Draft with AI</button>
        <input type="text" id="ai-outline" placeholder="Optional outline: sections separated by | (AI prompt)" style="flex:1;background:var(--olive-950);border:1px solid var(--olive-700);color:var(--cream);padding:.5rem .8rem">
        <span id="ai-draft-status" style="font-family:var(--mono);font-size:.7rem;color:var(--khaki)"></span>
      </div>
    </div>
    <script>
    (function(){
      var btn = document.getElementById('ai-draft-btn');
      var out = document.getElementById('ai-outline');
      var st  = document.getElementById('ai-draft-status');
      var csrf = document.querySelector('input[name="_csrf"]');
      if (btn) btn.addEventListener('click', function(){
        var title = document.getElementById('title').value.trim();
        if (!title) { st.textContent = 'Add a title first.'; return; }
        st.textContent = 'Drafting…';
        var fd = new FormData();
        fd.append('title', title);
        fd.append('pest_category', document.getElementById('pest_category').value);
        fd.append('region', document.getElementById('region').value);
        fd.append('season', document.getElementById('season').value);
        fd.append('outline', out ? out.value : '');
        if (csrf) fd.append('_csrf', csrf.value);
        fetch('/admin/posts/draft', { method: 'POST', body: fd })
          .then(function(r){ return r.json(); })
          .then(function(j){
            if (j.ok) { document.getElementById('body_html').value = j.html; st.textContent = 'Draft inserted ✓'; }
            else { st.textContent = j.error || 'Draft failed'; }
          })
          .catch(function(){ st.textContent = 'Draft failed (network)'; });
      });
    })();
    </script>

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

    <fieldset class="seo-box">
      <legend>SEO &amp; Social <button type="button" id="seo-auto-btn" style="margin-left:1rem;background:var(--olive-700);color:var(--cream);border:1px solid var(--olive-500);padding:.3rem .7rem;cursor:pointer;font-family:var(--mono);font-size:.7rem">⚡ Auto-SEO</button> <span id="seo-status" style="font-family:var(--mono);font-size:.7rem;color:var(--khaki)"></span></legend>
      <div class="hint" style="margin-bottom:.8rem">Leave blank to auto-generate from the article on save (rule-based, AI-polished when configured).</div>
      <div class="form-row">
        <div class="field">
          <label for="meta_title">SEO Title</label>
          <input type="text" id="meta_title" name="meta_title" maxlength="70" value="<?= $old('meta_title') ?: $view->e($post['meta_title'] ?? '') ?>">
          <div class="hint">Default: post title. Keep under ~60 chars for Google.</div>
        </div>
        <div class="field">
          <label for="meta_keywords">Keywords (comma separated)</label>
          <input type="text" id="meta_keywords" name="meta_keywords" maxlength="300" value="<?= $old('meta_keywords') ?: $view->e($post['meta_keywords'] ?? '') ?>">
        </div>
      </div>
      <div class="field">
        <label for="meta_description">Meta Description</label>
        <textarea id="meta_description" name="meta_description" maxlength="300" style="min-height:70px"><?= $old('meta_description') ?: $view->e($post['meta_description'] ?? '') ?></textarea>
        <div class="hint">Default: excerpt. 150–160 chars shows best in search results.</div>
      </div>
      <div class="field">
        <label for="og_image">Social Share Image URL</label>
        <input type="text" id="og_image" name="og_image" maxlength="300" placeholder="https://... (defaults to the pest photo / site OG image)" value="<?= $old('og_image') ?: $view->e($post['og_image'] ?? '') ?>">
      </div>
      <script>
      (function(){
        var btn = document.getElementById('seo-auto-btn');
        var st  = document.getElementById('seo-status');
        var csrf = document.querySelector('input[name="_csrf"]');
        if (btn) btn.addEventListener('click', function(){
          var title = document.getElementById('title').value.trim();
          if (!title) { st.textContent = 'Add a title first.'; return; }
          st.textContent = 'Generating…';
          var fd = new FormData();
          fd.append('title', title);
          fd.append('body_html', document.getElementById('body_html').value);
          fd.append('excerpt', document.getElementById('excerpt').value);
          fd.append('pest_category', document.getElementById('pest_category').value);
          fd.append('region', document.getElementById('region').value);
          fd.append('season', document.getElementById('season').value);
          if (csrf) fd.append('_csrf', csrf.value);
          fetch('/admin/posts/seo', { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(j){
              if (j.ok) {
                if (document.getElementById('meta_title').value === '') document.getElementById('meta_title').value = j.meta_title || '';
                if (document.getElementById('meta_description').value === '') document.getElementById('meta_description').value = j.meta_description || '';
                if (document.getElementById('meta_keywords').value === '') document.getElementById('meta_keywords').value = j.meta_keywords || '';
                st.textContent = 'SEO generated ✓';
              } else { st.textContent = j.error || 'Failed'; }
            })
            .catch(function(){ st.textContent = 'Failed (network)'; });
        });
      })();
      </script>
    </fieldset>

    <script>
    (function(){
      var sel = document.getElementById('status');
      var sched = document.getElementById('sched-row');
      var toggle = function(){ sched.style.display = sel.value === 'scheduled' ? 'flex' : 'none'; };
      if (sel && sched) { sel.addEventListener('change', toggle); toggle(); }
    })();
    </script>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary"><?= $isNew ? 'Create Post' : 'Save Changes' ?> ▸</button>
      <a class="btn btn-ghost" href="/admin/posts">Cancel</a>
    </div>
  </form>
</div></div>
