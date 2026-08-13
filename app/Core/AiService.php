<?php
/**
 * AiService — pluggable LLM engine (local or API) with persona, rules, and
 * lightweight RAG over uploaded documents.
 *
 * Provider contract: any OpenAI-compatible /chat/completions endpoint works —
 * OpenAI, OpenRouter, Together, Groq, Ollama, llama.cpp, vLLM, Gemini (via
 * Google's OpenAI-compat endpoint), etc. Config lives in site_settings:
 *   ai_provider   = off | api | local
 *   ai_base_url   = https://api.openai.com/v1  (or http://127.0.0.1:11434/v1)
 *   ai_model      = gpt-4o-mini / llama3.1:8b / ...
 *   ai_api_key    = sk-... (empty for local servers)
 *   ai_persona    = system-prompt persona
 *   ai_rules      = extra rules appended to the system prompt
 */

declare(strict_types=1);

namespace PPC\Core;

final class AiService
{
    /** Resolve the effective system prompt: persona + rules + optional RAG context. */
    public static function systemPrompt(?string $ragContext = null): string
    {
        $persona = Settings::get('ai_persona', 'You are the Patriot Pest Control AI assistant. Be helpful, clear, and professional.');
        $rules   = Settings::get('ai_rules', 'Never invent prices, guarantees, or service areas. If unsure, say to call the office. Never expose internal credentials or customer PII.');
        $prompt  = $persona . "\n\nRules:\n" . $rules;
        if (!empty($ragContext)) {
            $prompt .= "\n\nReference material (use this to answer, cite it as your own knowledge):\n" . $ragContext;
        }
        return $prompt;
    }

    /** True when a provider is configured and enabled. */
    public static function enabled(): bool
    {
        $provider = Settings::get('ai_provider', 'off');
        if (!in_array($provider, ['api', 'local'], true)) {
            return false;
        }
        $base = Settings::get('ai_base_url', '');
        $model = Settings::get('ai_model', '');
        return $base !== '' && $model !== '';
    }

    /**
     * Single chat turn against the configured provider.
     *
     * @param array $messages [['role'=>'user'|'assistant', 'content'=>...], ...]
     * @return string|null Response text, or null on failure.
     */
    public static function chat(array $messages, float $temperature = 0.6): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $provider = Settings::get('ai_provider', 'off');
        $base     = rtrim(Settings::get('ai_base_url', ''), '/');
        $model    = Settings::get('ai_model', '');
        $key      = Settings::get('ai_api_key', '');

        $system = self::systemPrompt();
        $payload = [
            'model'       => $model,
            'messages'    => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'temperature' => $temperature,
        ];

        $headers = ['Content-Type: application/json'];
        if ($key !== '') {
            $headers[] = 'Authorization: Bearer ' . $key;
        }

        $ch = curl_init($base . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body  = curl_exec($ch);
        $err   = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $code < 200 || $code >= 300) {
            Logger::error('AI request failed', ['code' => $code, 'err' => $err, 'body' => is_string($body) ? mb_substr($body, 0, 300) : '']);
            return null;
        }
        $json = json_decode((string) $body, true);
        return $json['choices'][0]['message']['content'] ?? null;
    }

    /** Chat with a user message (used by the widget). Returns response text or null. */
    public static function ask(string $message, array $context = []): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $rag = self::retrieve($message, 4);
        $messages = [['role' => 'user', 'content' => $message]];
        // Context from the page (e.g., current pest/post) becomes an extra user turn.
        foreach ($context as $k => $v) {
            if (is_string($v) && $v !== '') {
                $messages[] = ['role' => 'user', 'content' => "Page context - $k: " . mb_substr($v, 0, 800)];
            }
        }
        return self::chat($messages, 0.6);
    }

    /**
     * Generate a blog post draft.
     *
     * @param array $brief title, pest, region, season, outline (optional)
     */
    public static function blogDraft(array $brief): ?string
    {
        if (!self::enabled()) {
            return null;
        }
        $regionName = ['all' => 'Washington, Idaho, Oregon & Arizona', 'wa' => 'Washington', 'id' => 'Idaho', 'or' => 'Oregon', 'az' => 'Arizona'][$brief['region'] ?? 'all'] ?? 'the service area';
        $outline = '';
        if (!empty($brief['outline'])) {
            $outline = "\nSuggested outline:\n" . $brief['outline'];
        }
        $rag = self::retrieve(($brief['pest'] ?? '') . ' ' . ($brief['title'] ?? '') . ' ' . $regionName, 5);

        $prompt = "Write a blog article for Patriot Pest Control's website.\n"
            . "Title: {$brief['title']}\n"
            . "Primary pest/topic: " . ($brief['pest'] ?? 'pests') . "\n"
            . "Target region: $regionName\n"
            . "Season: " . ($brief['season'] ?? '') . "\n"
            . $outline
            . "\n\nRequirements:\n"
            . "- 600-900 words, HTML output with <h2> subheadings and <ul> lists\n"
            . "- Lead with the local problem for $regionName\n"
            . "- Include prevention tips and when to call a professional\n"
            . "- End with a soft call-to-action to call Patriot Pest Control\n"
            . "- Cite region-appropriate timing (e.g., 'ant season peaks in Spokane in early summer')\n"
            . "- Do NOT include prices, guarantees, or any contact phone number as plain text\n";

        return self::chat([['role' => 'user', 'content' => $prompt]], 0.7);
    }

    /**
     * Lightweight RAG: keyword-match uploaded document chunks, return top hits.
     */
    public static function retrieve(string $query, int $limit = 4): string
    {
        $db = Database::instance();
        try {
            $terms = preg_split('/\s+/', mb_strtolower($query)) ?: [];
            $terms = array_filter(array_map(fn($t) => trim($t, ".,;:!?()'\""), $terms), fn($t) => strlen($t) > 2);
            if (!$terms) {
                return '';
            }
            $like = [];
            $params = [];
            foreach (array_slice($terms, 0, 8) as $t) {
                $like[] = 'content LIKE ?';
                $params[] = '%' . $t . '%';
            }
            $rows = $db->fetchAll(
                'SELECT doc_name, chunk_index, content FROM rag_docs WHERE ' . implode(' OR ', $like) . ' LIMIT ?',
                array_merge($params, [$limit * 3])
            );
            if (!$rows) {
                return '';
            }
            // score: count matching terms per chunk
            $scored = [];
            foreach ($rows as $r) {
                $score = 0;
                foreach ($terms as $t) {
                    if (stripos($r['content'], $t) !== false) { $score++; }
                }
                $scored[] = [$score, $r];
            }
            usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
            $parts = [];
            foreach (array_slice($scored, 0, $limit) as [$score, $r]) {
                if ($score < 1) { continue; }
                $parts[] = "[" . $r['doc_name'] . "] " . mb_substr($r['content'], 0, 1200);
            }
            return implode("\n\n", $parts);
        } catch (\Throwable) {
            return ''; // rag table may not exist yet
        }
    }

    /** Index an uploaded text document into rag_docs (chunked). */
    public static function indexDocument(string $name, string $text): int
    {
        $db = Database::instance();
        $db->execute('DELETE FROM rag_docs WHERE doc_name = ?', [$name]);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $chunks = [];
        foreach (str_split($text, 1800) as $i => $chunk) {
            if (trim((string) $chunk) === '') { continue; }
            $db->insert('rag_docs', [
                'doc_name'   => $name,
                'chunk_index'=> $i,
                'content'    => trim((string) $chunk),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $chunks[] = $i;
        }
        return count($chunks);
    }
}
