<?php
/**
 * MarketingAds — targeted, rotating ads injected into customer emails
 * (login codes, and anywhere else Mailer sends to customers).
 *
 * Targeting buckets:
 *   - new_plan    no active subscription
 *   - upgrade     has an active subscription (cross-sell add-ons)
 *   - reactivate  had a subscription but it's inactive/cancelled
 *   - referral    anyone (rotates, always eligible as secondary)
 *   - review      anyone (rotates, always eligible as secondary)
 *
 * Ads are weighted-random so customers never see the same one twice in a row.
 */

declare(strict_types=1);

namespace PPC\Core;

final class MarketingAds
{
    /** Return a rendered ad block (HTML) for this customer, or '' when none. */
    public static function renderFor(array $customer, ?string $purpose = 'otp'): string
    {
        $db = Database::instance();
        try {
            $subs = $db->fetchAll('SELECT status, charge, freq_label, district FROM subscriptions WHERE customer_id = ?', [(int) ($customer['id'] ?? 0)]);
        } catch (\Throwable) {
            $subs = [];
        }

        $hasActive = false;
        $hasLapsed = false;
        foreach ($subs as $s) {
            $st = strtolower((string) ($s['status'] ?? ''));
            if ($st === 'active' || $st === 'active ' ) { $hasActive = true; }
            if (in_array($st, ['inactive', 'cancelled', 'canceled'], true)) { $hasLapsed = true; }
        }
        $bucket = $hasActive ? 'upgrade' : ($hasLapsed ? 'reactivate' : 'new_plan');

        // Primary targeted ad + a rotating secondary (referral/review).
        $primary = self::pick($bucket, $customer);
        $secondary = self::pick(self::randomBool() ? 'referral' : 'review', $customer);

        $blocks = [];
        if ($primary !== null) {
            $blocks[] = self::renderAd($primary, $purpose, 'primary');
            self::impression((int) $primary['id'], $customer);
        }
        if ($secondary !== null && ($secondary['id'] ?? 0) !== ($primary['id'] ?? 0)) {
            $blocks[] = self::renderAd($secondary, $purpose, 'secondary');
            self::impression((int) $secondary['id'], $customer);
        }
        return implode("\n", $blocks);
    }

    /** Weighted-random pick among ads matching bucket + customer region + current season. */
    public static function pick(string $bucket, array $customer = [], bool $any = false): ?array
    {
        $db = Database::instance();
        $month = (int) date('n');
        $season = match (true) {
            $month >= 3 && $month <= 5  => 'spring',
            $month >= 6 && $month <= 8  => 'summer',
            $month >= 9 && $month <= 11 => 'fall',
            default                     => 'winter',
        };
        $region = strtolower(trim((string) ($customer['state'] ?? $customer['district'] ?? 'all')));
        $region = in_array($region, ['wa', 'id', 'or', 'az'], true) ? $region : 'all';

        $rows = $db->fetchAll(
            "SELECT * FROM marketing_ads WHERE active = 1 AND bucket = ? AND (region = ? OR region = 'all') AND (season = ? OR season = 'all')",
            [$bucket, $region, $season]
        );
        if (!$rows && !$any) {
            // fall back to region/season-agnostic
            $rows = $db->fetchAll("SELECT * FROM marketing_ads WHERE active = 1 AND bucket = ?", [$bucket]);
        }
        if (!$rows) { return null; }
        return self::weightedPick($rows);
    }

    private static function weightedPick(array $rows): array
    {
        $total = 0;
        foreach ($rows as $r) { $total += max(1, (int) ($r['weight'] ?? 1)); }
        $roll = random_int(1, $total);
        foreach ($rows as $r) {
            $roll -= max(1, (int) ($r['weight'] ?? 1));
            if ($roll <= 0) { return $r; }
        }
        return $rows[0];
    }

    private static function randomBool(): bool
    {
        return random_int(0, 1) === 1;
    }

    /** HTML block for one ad (branded, tracked CTA). */
    private static function renderAd(array $ad, string $purpose, string $slot): string
    {
        $url = (string) ($ad['cta_url'] ?? '#');
        $sep = str_contains($url, '?') ? '&' : '?';
        $tracked = $url . $sep . 'utm_source=email&utm_medium=' . rawurlencode($purpose) . '&utm_campaign=' . rawurlencode((string) ($ad['bucket'] ?? ''));
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:24px;border:1px solid #3a3f2c;border-radius:6px;overflow:hidden">'
            . '<tr><td style="background:#14180f;padding:18px 22px">'
            . '<div style="font-size:11px;letter-spacing:2px;color:#c8a24a;text-transform:uppercase;font-weight:bold;margin-bottom:6px">★ Patriot Pest — ' . ($slot === 'primary' ? 'Recommended for you' : 'Perk') . '</div>'
            . '<div style="font-size:17px;color:#e8e6da;font-weight:bold;margin-bottom:6px">' . $ad['title'] . '</div>'
            . '<div style="font-size:13px;color:#a9b09a;line-height:1.5;margin-bottom:12px">' . $ad['body'] . '</div>'
            . '<a href="' . htmlspecialchars($tracked, ENT_QUOTES) . '" style="display:inline-block;background:#e8762d;color:#12140d;font-weight:bold;text-decoration:none;padding:9px 16px;border-radius:3px;font-size:13px">' . htmlspecialchars((string) $ad['cta_label'], ENT_QUOTES) . ' →</a>'
            . '</td></tr></table>';
    }

    /** Record an impression (fire-and-forget). */
    private static function impression(int $adId, array $customer): void
    {
        try {
            Database::instance()->insert('ad_impressions', [
                'ad_id'       => $adId,
                'customer_id' => (int) ($customer['id'] ?? 0),
                'purpose'     => 'otp',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // non-fatal
        }
    }
}
