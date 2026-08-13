<?php
/**
 * admin/ai.php - AI & Agents: LLM provider config, RAG knowledge base,
 * and regional blog generation (NPMA/USDA pest calendar).
 */
$vals   = $data['vals'] ?? [];
$docs   = $data['docs'] ?? [];
$msg    = $data['msg'] ?? '';
$enabled= $data['enabled'] ?? false;
?>
<div class="wrap">
  <div class="eyebrow">ADMIN // AI &amp; AGENTS</div>
  <h1 style="font-family:var(--display);color:var(--cream);font-size:1.8rem;margin:.4rem 0 1.2rem">AI &amp; Agents</h1>

  <?php if ($msg): ?><p class="ok-banner" style="background:var(--olive-800);border:1px solid var(--olive-500);color:var(--cream);padding:.8rem 1rem;margin-bottom:1.2rem"><?= $view->e($msg) ?></p><?php endif; ?>

  <form method="post" action="/admin/ai" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.6rem;margin-bottom:1.6rem">
    <?= $view->csrf() ?>
    <h2 style="font-family:var(--display);color:var(--cream);font-size:1.15rem;margin-bottom:1rem">1 · LLM Provider <span style="color:var(--orange)"><?= $enabled ? '● CONNECTED' : '○ OFF' ?></span></h2>
    <div class="form-row">
      <div class="field">
        <label for="ai_provider">Provider</label>
        <select id="ai_provider" name="ai_provider">
          <option value="off" <?= ($vals['ai_provider'] ?? 'off') === 'off' ? 'selected' : '' ?>>Off</option>
          <option value="openai" <?= ($vals['ai_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI-compatible (OpenAI / OpenRouter / Together / Groq…)</option>
          <option value="anthropic" <?= ($vals['ai_provider'] ?? '') === 'anthropic' ? 'selected' : '' ?>>Anthropic (Claude)</option>
          <option value="local" <?= ($vals['ai_provider'] ?? '') === 'local' ? 'selected' : '' ?>>Local (Ollama / llama.cpp / vLLM)</option>
        </select>
      </div>
      <div class="field">
        <label for="ai_model">Model</label>
        <input type="text" id="ai_model" name="ai_model" value="<?= $view->e($vals['ai_model'] ?? '') ?>" placeholder="gpt-4o-mini | claude-sonnet-4-5 | llama3.1:8b">
      </div>
    </div>
    <div class="field">
      <label for="ai_base_url">Base URL</label>
      <input type="text" id="ai_base_url" name="ai_base_url" value="<?= $view->e($vals['ai_base_url'] ?? '') ?>" placeholder="https://api.openai.com/v1 | https://api.anthropic.com/v1 | http://127.0.0.1:11434/v1">
      <div class="hint">Any OpenAI-compatible /chat/completions endpoint, or Anthropic's /v1/messages.</div>
    </div>
    <div class="field">
      <label for="ai_api_key">API Key</label>
      <input type="password" id="ai_api_key" name="ai_api_key" value="<?= $view->e($vals['ai_api_key'] ?? '') ?>" placeholder="sk-… (leave blank for local servers)">
    </div>
    <div class="field">
      <label for="ai_max_tokens">Max tokens</label>
      <input type="number" id="ai_max_tokens" name="ai_max_tokens" value="<?= $view->e($vals['ai_max_tokens'] ?? '4096') ?>" min="256" max="32000" step="256">
    </div>
    <h2 style="font-family:var(--display);color:var(--cream);font-size:1.15rem;margin:1.4rem 0 .6rem">2 · Persona &amp; Rules</h2>
    <div class="field">
      <label for="ai_persona">Persona (system prompt)</label>
      <textarea id="ai_persona" name="ai_persona" style="min-height:70px"><?= $view->e($vals['ai_persona'] ?? '') ?></textarea>
    </div>
    <div class="field">
      <label for="ai_rules">Rules</label>
      <textarea id="ai_rules" name="ai_rules" style="min-height:70px"><?= $view->e($vals['ai_rules'] ?? '') ?></textarea>
      <div class="hint">e.g. never invent prices, never reveal credentials, answer like a licensed pest pro.</div>
    </div>
    <button class="btn btn-primary" type="submit">Save AI Settings</button>
  </form>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.6rem">
    <form method="post" action="/admin/ai/docs" enctype="multipart/form-data" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.6rem">
      <?= $view->csrf() ?>
      <h2 style="font-family:var(--display);color:var(--cream);font-size:1.15rem;margin-bottom:1rem">3 · Knowledge Base (RAG)</h2>
      <div class="field">
        <label for="doc_name">Document name</label>
        <input type="text" id="doc_name" name="doc_name" placeholder="ppc-service-guide">
      </div>
      <div class="field">
        <label for="doc_file">Upload .txt / .md</label>
        <input type="file" id="doc_file" name="doc_file" accept=".txt,.md">
      </div>
      <div class="field">
        <label for="doc_text">…or paste text</label>
        <textarea id="doc_text" name="doc_text" style="min-height:90px" placeholder="Paste content here. The AI uses it to answer chat + blog questions."></textarea>
      </div>
      <button class="btn btn-primary" type="submit">Index Document</button>
      <?php if ($docs): ?>
      <p class="hint" style="margin-top:1rem"><?= $view->e($data['totalChunks'] ?? 0) ?> chunks indexed:</p>
      <?php foreach ($docs as $d): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:.4rem 0;border-bottom:1px solid var(--olive-700)">
        <span style="color:var(--cream);font-family:var(--mono);font-size:.8rem"><?= $view->e($d['doc_name']) ?> <small style="color:var(--khaki)">(<?= (int)$d['chunks'] ?> chunks)</small></span>
        <form method="post" action="/admin/ai/docs/delete" style="display:inline">
          <?= $view->csrf() ?>
          <input type="hidden" name="doc_name" value="<?= $view->e($d['doc_name']) ?>">
          <button type="submit" style="background:none;border:none;color:var(--red);cursor:pointer;font-size:.75rem">✕</button>
        </form>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </form>

    <form method="post" action="/admin/ai/generate" style="background:var(--olive-800);border:1px solid var(--olive-700);padding:1.6rem">
      <?= $view->csrf() ?>
      <h2 style="font-family:var(--display);color:var(--cream);font-size:1.15rem;margin-bottom:1rem">4 · Regional Blog Generator</h2>
      <p class="hint" style="margin-bottom:1rem">Creates one <b>draft</b> post per pest for a region, using the NPMA/USDA-style pest-pressure calendar. Review + schedule them in <a href="/admin/posts" style="color:var(--orange)">Posts</a>.</p>
      <div class="field">
        <label for="region">Region</label>
        <select id="region" name="region">
          <?php foreach (['all'=>'All States','wa'=>'Washington','id'=>'Idaho','or'=>'Oregon','az'=>'Arizona'] as $v=>$l): ?>
          <option value="<?= $v ?>"><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="btn btn-primary" type="submit">Generate Regional Drafts</button>
    </form>
  </div>
</div>
