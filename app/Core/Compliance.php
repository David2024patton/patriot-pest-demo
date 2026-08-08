<?php
/**
 * Compliance - DNC (do-not-contact) enforcement for outbound messaging.
 *
 * Central gate for every outbound SMS, voice call, or email campaign. Before
 * any send the caller asks Compliance::isBlocked(); if it returns a block the
 * send MUST be refused and the refusal audited. This is the C1 send gate.
 *
 * Block sources (either is a hard refusal):
 *   1. customers.is_no_call = 1 (customer-level no-contact flag).
 *   2. A row in unsubscribes whose channel matches the outbound channel
 *      ('email' or 'sms') or is 'all' (global opt-out).
 *
 * Phone matching is defensive against legacy dirty data: the local cache
 * historically stored numbers without the +1 prefix, so lookups compare the
 * raw input, the digits-only form, the +1 canonical form, and a trailing
 * LIKE match. As FieldRoutes sync normalizes to E.164 the extra forms become
 * no-ops but remain harmless.
 *
 * The class also owns:
 *   - recordUnsubscribe(): durable opt-out (unsubscribes row + optional
 *     customers.is_no_call) with an audit trail.
 *   - signed unsubscribe tokens: public, CSRF-exempt unsubscribe links are
 *     HMAC-signed with APP_KEY so a third party cannot opt someone else out.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Compliance
{
    /** Valid outbound channels the gate understands. */
    private const CHANNELS = ['email', 'sms'];

    /**
     * Hard refusal check for one outbound message.
     *
     * @param string      $channel 'email' | 'sms' (the channel being used).
     * @param string|null $email   Recipient email, when known.
     * @param string|null $phone   Recipient phone, when known.
     * @return array|null Block detail array, or null when the send is allowed.
     */
    public static function isBlocked(string $channel, ?string $email, ?string $phone): ?array
    {
        if (!in_array($channel, self::CHANNELS, true)) {
            $channel = 'sms';
        }

        $db      = Database::instance();
        $digits  = self::digits((string) $phone);
        $phones  = self::phoneCandidates($phone, $digits);
        $emails  = self::emailCandidates($email);

        // 1) Customer-level no-call flag.
        if ($phones !== []) {
            $ph   = implode(',', array_fill(0, count($phones), '?'));
            $row  = $db->fetch(
                "SELECT id, name, dnc_reason FROM customers
                 WHERE is_no_call = 1 AND (phone IN ($ph) OR (phone IS NOT NULL AND phone LIKE ?))
                 LIMIT 1",
                [...$phones, self::likeSuffix($digits)]
            );
            if ($row !== null) {
                return [
                    'blocked'     => true,
                    'reason'      => 'customer_is_no_call',
                    'detail'      => (string) ($row['dnc_reason'] ?? ''),
                    'customer_id' => (int) $row['id'],
                ];
            }
        }
        if ($emails !== []) {
            $row = $db->fetch(
                'SELECT id, name, dnc_reason FROM customers
                 WHERE is_no_call = 1 AND email = ? COLLATE NOCASE LIMIT 1',
                [$emails[0]]
            );
            if ($row !== null) {
                return [
                    'blocked'     => true,
                    'reason'      => 'customer_is_no_call',
                    'detail'      => (string) ($row['dnc_reason'] ?? ''),
                    'customer_id' => (int) $row['id'],
                ];
            }
        }

        // 2) Global unsubscribe list (channel-specific + 'all').
        $conds  = [];
        $params = [];
        if ($emails !== []) {
            $conds[]  = 'email = ? COLLATE NOCASE';
            $params[] = $emails[0];
        }
        if ($phones !== []) {
            $ph      = implode(',', array_fill(0, count($phones), '?'));
            $conds[] = '(phone IN (' . $ph . ') OR (phone IS NOT NULL AND phone LIKE ?))';
            $params  = array_merge($params, $phones, [self::likeSuffix($digits)]);
        }
        if ($conds !== []) {
            $row = $db->fetch(
                "SELECT id, customer_id, email, phone, channel, reason FROM unsubscribes
                 WHERE channel IN ('" . $channel . "', 'all') AND (" . implode(' OR ', $conds) . ")
                 ORDER BY id DESC LIMIT 1",
                $params
            );
            if ($row !== null) {
                return [
                    'blocked'        => true,
                    'reason'         => 'unsubscribed',
                    'detail'         => (string) ($row['reason'] ?? ''),
                    'channel'        => $row['channel'],
                    'unsubscribe_id' => (int) $row['id'],
                    'customer_id'    => $row['customer_id'] !== null ? (int) $row['customer_id'] : null,
                ];
            }
        }

        return null;
    }

    /**
     * Record a durable opt-out.
     *
     * @param string|null $email      Unsubscribing email (optional).
     * @param string|null $phone      Unsubscribing phone (optional).
     * @param string      $channel    'email' | 'sms' | 'all'.
     * @param string|null $reason     Free-text reason for the audit trail.
     * @param bool        $setNoCall  Also flip customers.is_no_call = 1.
     */
    public static function recordUnsubscribe(?string $email, ?string $phone, string $channel, ?string $reason = null, bool $setNoCall = false): void
    {
        if (!in_array($channel, ['email', 'sms', 'all'], true)) {
            $channel = 'sms';
        }

        $db     = Database::instance();
        $digits = self::digits((string) $phone);

        $custId = null;
        $phoneCands = self::phoneCandidates($phone, $digits);
        if ($phoneCands !== []) {
            $ph = implode(',', array_fill(0, count($phoneCands), '?'));
            $c  = $db->fetch(
                "SELECT id FROM customers WHERE phone IN ($ph) OR (phone IS NOT NULL AND phone LIKE ?) LIMIT 1",
                [...$phoneCands, '%' . $digits]
            );
            if ($c !== null) {
                $custId = (int) $c['id'];
            }
        }
        $emailCands = self::emailCandidates($email);
        if ($custId === null && $emailCands !== []) {
            $c = $db->fetch('SELECT id FROM customers WHERE email = ? COLLATE NOCASE LIMIT 1', [$emailCands[0]]);
            if ($c !== null) {
                $custId = (int) $c['id'];
            }
        }

        $db->insert('unsubscribes', [
            'customer_id' => $custId,
            'email'       => $emailCands[0] ?? null,
            'phone'       => $phone !== null && $phone !== '' ? $phone : null,
            'channel'     => $channel,
            'reason'      => $reason,
            'synced_to_fr' => 0,
            'created_at'  => gmdate('Y-m-d H:i:s'),
        ]);

        if ($setNoCall && $custId !== null) {
            $db->update('customers', [
                'is_no_call' => 1,
                'dnc_reason' => $reason ?? 'unsubscribed via link',
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $custId]);
        }

        try {
            $db->insert('audit_log', [
                'user_id'    => $custId !== null ? (string) $custId : null,
                'user_type'  => $custId !== null ? 'customer' : 'guest',
                'action'     => 'unsubscribe',
                'entity'     => 'customer',
                'entity_id'  => $custId !== null ? (string) $custId : null,
                'meta_json'  => json_encode([
                    'email'       => $email,
                    'phone'       => $phone,
                    'channel'     => $channel,
                    'reason'      => $reason,
                    'set_no_call' => $setNoCall,
                ]),
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Unsubscribe audit write failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Build a signed public unsubscribe URL (CSRF-exempt by design: the token
     * is the proof of consent, HMAC-signed with APP_KEY so it cannot be forged).
     * The reactivation engine must populate {{unsubscribe_url}} with this.
     */
    public static function unsubscribeUrl(string $email, ?string $phone, string $channel): string
    {
        $token = self::signToken(['e' => $email, 'p' => $phone ?? '', 'c' => $channel]);
        return '/unsubscribe?token=' . urlencode($token);
    }

    /** Sign an unsubscribe payload; valid for 90 days by default. */
    public static function signToken(array $payload, int $ttl = 7776000): string
    {
        $payload['x'] = time() + $ttl;
        $data = base64_encode((string) json_encode($payload));
        return $data . '.' . hash_hmac('sha256', $data, self::hmacKey());
    }

    /** Verify and decode a signed token; null when invalid or expired. */
    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$data, $sig] = $parts;
        $expected = hash_hmac('sha256', $data, self::hmacKey());
        if (!hash_equals($expected, (string) $sig)) {
            return null;
        }
        $payload = json_decode((string) base64_decode($data, true), true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($payload['x']) && (int) $payload['x'] < time()) {
            return null;
        }
        return $payload;
    }

    /** HMAC key: APP_KEY, with a logged fallback so a broken key never bricks sends. */
    private static function hmacKey(): string
    {
        $key = (string) Config::get('APP_KEY', '');
        if ($key === '' || $key === 'replace_with_64_char_random_hex_string') {
            Logger::warning('Compliance: APP_KEY missing, falling back to TWILIO_AUTH_TOKEN for token signing');
            return (string) Config::get('TWILIO_AUTH_TOKEN', '');
        }
        return $key;
    }

    /** Digits-only form of a phone (lookup normalization). */
    public static function digits(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    /** Candidate forms of a phone for matching: raw, digits, canonical +1, national. */
    private static function phoneCandidates(?string $phone, string $digits): array
    {
        $cands = [];
        if ($phone !== null && $phone !== '') {
            $cands[] = $phone;
        }
        if ($digits !== '') {
            $cands[] = $digits;
            if (strlen($digits) === 10) {
                $cands[] = '+1' . $digits;
            } elseif (strlen($digits) === 11 && $digits[0] === '1') {
                // National form of a +1 number, so legacy rows stored without
                // the country code (e.g. '5559876543') still match.
                $cands[] = substr($digits, 1);
            }
        }
        return array_values(array_unique($cands));
    }

    /**
     * Trailing-LIKE suffix for a phone lookup. Uses the national form when the
     * input is a +1 E.164 number so legacy prefixed rows still match.
     */
    private static function likeSuffix(string $digits): string
    {
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return '%' . substr($digits, 1);
        }
        return '%' . $digits;
    }

    /** Lowercased email candidates (single; presence-checked). */
    private static function emailCandidates(?string $email): array
    {
        if ($email === null || $email === '' || !str_contains($email, '@')) {
            return [];
        }
        return [strtolower(trim($email))];
    }
}
