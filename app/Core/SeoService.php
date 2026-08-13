<?php
/**
 * SeoService — automatic SEO generation for blog posts (and anything else).
 *
 * Rule-based by default (works with zero config): derives a title, meta
 * description, keywords, and og-image from the post content. When an AI
 * provider is configured it writes the same fields more naturally. Called on
 * post save when SEO fields are empty, and via the editor's "Auto SEO" button.
 */

declare(strict_types=1);

namespace PPC\Core;

final class SeoService
{
    /** Rule-based SEO from post content. Never throws. */
    public static function generate(array $post): array
    {
        $title   = trim((string) ($post['title'] ?? ''));
        $body    = strip_tags(preg_replace('/></', '> <', (string) ($post['body_html'] ?? '')) ?? '');
        $excerpt = trim((string) ($post['excerpt'] ?? ''));
        $pest    = trim((string) ($post['pest_name'] ?? $post['pest_category'] ?? ''));
        $region  = trim((string) ($post['region'] ?? 'all'));
        $season  = trim((string) ($post['season'] ?? ''));

        $regionLabel = ['all' => 'Washington, Idaho, Oregon & Arizona', 'wa' => 'Washington', 'id' => 'Idaho', 'or' => 'Oregon', 'az' => 'Arizona'][$region] ?? 'the service area';

        // meta title: Title | Pest Control | Region (<= ~64 chars)
        $metaTitle = mb_substr($title . ' | Patriot Pest Control', 0, 64);
        if ($region !== 'all') {
            $metaTitle = mb_substr($title . ' in ' . $regionLabel . ' | Patriot Pest Control', 0, 64);
        }

        // description: excerpt, else first meaningful sentence of body (<= ~158)
        $desc = $excerpt !== '' ? $excerpt : (preg_split('/(?<=[.!?])\s+/', trim($body))[0] ?? '');
        $desc = trim(preg_replace('/\s+/', ' ', $desc) ?? '');
        $metaDesc = mb_substr($desc, 0, 158);

        // keywords: pest + region + season + top frequent body words
        $keywords = array_filter([$pest, $region === 'all' ? 'pest control' : $regionLabel, $season]);
        $words = preg_split('/\W+/u', mb_strtolower($body));
        $stop = ['the','and','for','are','with','that','this','from','your','you','will','have','their','they','when','pest','pests','control','patriot','can','not','but','our','them','also','than','more','were','been','into','about','after','before','does','each','these','those','other','some','would','should','could','what','which','while','where'];
        $freq = [];
        foreach ($words as $w) {
            $w = trim($w);
            // skip too-short, stop words, and malformed joins (e.g. 'antscarpenter')
            if (strlen($w) < 4 || strlen($w) > 24 || in_array($w, $stop, true) || !preg_match('/[aeiouy]/', $w)) { continue; }
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }
        arsort($freq);
        foreach (array_slice(array_keys($freq), 0, 5) as $w) {
            if (count($keywords) < 10) { $keywords[] = $w; }
        }
        $metaKeywords = implode(', ', array_unique(array_filter($keywords)));

        return [
            'meta_title'       => $metaTitle,
            'meta_description' => $metaDesc,
            'meta_keywords'    => mb_substr($metaKeywords, 0, 250),
        ];
    }

    /** AI-enhanced SEO (falls back to rules if AI is off or fails). */
    public static function generateWithAi(array $post): array
    {
        $rule = self::generate($post);
        if (!AiService::enabled()) {
            return $rule;
        }
        $title   = trim((string) ($post['title'] ?? ''));
        $body    = strip_tags((string) ($post['body_html'] ?? '')) ?: $post['excerpt'] ?? '';
        $prompt  = "Write SEO metadata for this pest control blog article.\n\nTITLE: $title\n\n"
            . "BODY:\n" . mb_substr($body, 0, 3000) . "\n\n"
            . "Return EXACTLY three lines:\n"
            . "META_TITLE: <<=64 chars, includes 'Patriot Pest Control'>\n"
            . "META_DESCRIPTION: <<=158 chars>\n"
            . "META_KEYWORDS: <comma separated, max 10, include local regions if relevant>";
        $resp = AiService::chat([['role' => 'user', 'content' => $prompt]], 0.3);
        if ($resp === null) {
            return $rule;
        }
        $out = $rule;
        foreach (preg_split('/\r?\n/', $resp) as $line) {
            if (preg_match('/^META_TITLE:\s*(.+)$/i', $line, $m) && trim($m[1]) !== '') { $out['meta_title'] = mb_substr(trim($m[1]), 0, 64); }
            elseif (preg_match('/^META_DESCRIPTION:\s*(.+)$/i', $line, $m) && trim($m[1]) !== '') { $out['meta_description'] = mb_substr(trim($m[1]), 0, 158); }
            elseif (preg_match('/^META_KEYWORDS:\s*(.+)$/i', $line, $m) && trim($m[1]) !== '') { $out['meta_keywords'] = mb_substr(trim($m[1]), 0, 250); }
        }
        return $out;
    }
}
