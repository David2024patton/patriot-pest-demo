<?php
/**
 * FieldRoutes — read/write client for the FieldRoutes CRM API.
 *
 * FieldRoutes is the SOURCE OF TRUTH for customers; this app keeps a local
 * cache (the `customers` table) plus local-only flags (is_no_call / dnc_reason)
 * so reactivation respects opt-outs even offline. This client pulls customers
 * from EVERY configured district (WA covers WA-ID-OR; AZ is separate) and
 * upserts them into the cache WITHOUT clobbering those local flags.
 *
 * Two districts share one base URL but each has its own API key + token, read
 * from .env (FIELDROUTES_BASE_URL, FIELDROUTES_{WA,AZ}_{KEY,TOKEN}).
 *
 * API contract (see fieldroutes skill): GET {base}/api/{entity}/{action} with
 * authenticationKey + authenticationToken as query params; envelope
 * {success, result/customers/...IDs, errorMessage, count}. customer/search
 * returns IDs; customer/get returns up to 1000 records per call (we batch 200).
 *
 * Every network call is wrapped so a missing cURL extension or a dead endpoint
 * never throws into the caller — it returns [] / false and logs instead.
 */

declare(strict_types=1);

namespace PPC\Integrations;

use PPC\Core\Config;
use PPC\Core\Database;
use PPC\Core\Logger;

final class FieldRoutes
{
    private const BATCH = 200;          // customer/get chunk size (<=1000)
    private const TIMEOUT = 30;

    /**
     * The two districts, read from config. Only districts with BOTH a key and a
     * token are returned, so callers can simply iterate what's configured.
     *
     * @return array<int, array{code:string, base:string, key:string, token:string}>
     */
    public static function districts(): array
    {
        $base = rtrim((string) Config::get('FIELDROUTES_BASE_URL', ''), '/');
        $defs = [
            ['code' => 'wa', 'key' => 'FIELDROUTES_WA_KEY', 'token' => 'FIELDROUTES_WA_TOKEN'],
            ['code' => 'az', 'key' => 'FIELDROUTES_AZ_KEY', 'token' => 'FIELDROUTES_AZ_TOKEN'],
        ];
        $out = [];
        foreach ($defs as $d) {
            $k = trim((string) Config::get($d['key'], ''));
            $t = trim((string) Config::get($d['token'], ''));
            if ($k !== '' && $t !== '' && $base !== '') {
                $out[] = ['code' => $d['code'], 'base' => $base, 'key' => $k, 'token' => $t];
            }
        }
        return $out;
    }

    /** True when at least one district is fully configured. */
    public static function isConfigured(): bool
    {
        return self::districts() !== [];
    }

    /**
     * Which districts are missing credentials (for a friendly "what's needed"
     * message). Returns codes like ['wa','az'] or ['az'] etc.
     *
     * @return string[]
     */
    public static function missingDistricts(): array
    {
        $base = trim((string) Config::get('FIELDROUTES_BASE_URL', ''));
        $missing = [];
        foreach (['wa' => ['FIELDROUTES_WA_KEY', 'FIELDROUTES_WA_TOKEN'], 'az' => ['FIELDROUTES_AZ_KEY', 'FIELDROUTES_AZ_TOKEN']] as $code => $keys) {
            if ($base === '' || trim((string) Config::get($keys[0], '')) === '' || trim((string) Config::get($keys[1], '')) === '') {
                $missing[] = $code;
            }
        }
        return $missing;
    }

    /**
     * Pull every customer from one district as normalized cache rows.
     *
     * @param array{code:string, base:string, key:string, token:string} $district
     * @return array<int, array<string, mixed>> normalized rows (see normalize())
     */
    public static function pullCustomersForDistrict(array $district): array
    {
        $ids = self::searchCustomerIds($district);
        if (!$ids) {
            return [];
        }
        $rows = [];
        foreach (array_chunk($ids, self::BATCH) as $chunk) {
            $data = self::request($district, 'customer/get', ['customerIDs' => implode(',', $chunk)]);
            foreach (($data['customers'] ?? []) as $c) {
                $rows[] = self::normalize($c, $district['code']);
            }
        }
        return $rows;
    }

    /** customer/search → list of customer IDs for a district. */
    private static function searchCustomerIds(array $district): array
    {
        $data = self::request($district, 'customer/search');
        $ids  = $data['customerIDs'] ?? $data['result'] ?? [];
        return is_array($ids) ? array_values(array_filter(array_map('strval', $ids), 'strlen')) : [];
    }

    /**
     * Map a FieldRoutes customer record onto the local cache row shape. We keep
     * only identity fields here; local-only flags (is_no_call/dnc_reason) are
     * managed by upsertCustomer() and never overwritten from FR.
     * last_service is populated from FR's lastCompleted date when available.
     */
    private static function normalize(array $c, string $district): array
    {
        $name = trim(($c['fname'] ?? '') . ' ' . ($c['lname'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($c['companyName'] ?? ''));
        }
        $id = (string) ($c['customerID'] ?? '');

        // FR status → local status enum (active|cancelled|inactive).
        $text = strtolower((string) ($c['statusText'] ?? ''));
        $status = 'active';
        if ($text !== '' && str_contains($text, 'cancel')) {
            $status = 'cancelled';
        } elseif ($text !== '' && str_contains($text, 'inactive')) {
            $status = 'inactive';
        }
        if (!empty($c['dateCancelled'])) {
            $status = 'cancelled';
        }

        // Last service date from FR — the recency signal for reactivation.
        $lastService = null;
        $rawLast = $c['lastCompleted'] ?? $c['lastService'] ?? null;
        if ($rawLast !== null && trim((string) $rawLast) !== '') {
            $ts = strtotime((string) $rawLast);
            $lastService = $ts ? date('Y-m-d H:i:s', $ts) : null;
        }

        return [
            'fr_id'          => $id,
            'district'       => $district,
            'name'           => $name !== '' ? $name : ('Customer ' . $id),
            'email'          => $c['email'] ?? null,
            'phone'          => self::normalizePhone($c['phone1'] ?? null),
            'account_number' => $id,                 // FR customerID is the account id
            'address'        => $c['address'] ?? null,
            'city'           => $c['city'] ?? null,
            'state'          => $c['state'] ?? null,
            'zip'            => $c['zip'] ?? null,
            'status'         => $status,
            'last_service'   => $lastService,
        ];
    }

    /**
     * Upsert one normalized row into the local cache. Matches an existing row by
     * (fr_id + district) first, then by email against any UNLINKED row (so a
     * seeded fixture gets claimed by its real FR record), otherwise inserts.
     * Local opt-out flags (is_no_call / dnc_reason) are NEVER touched on update
     * — FieldRoutes does not own them. last_service is updated from FR data.
     *
     * @return string 'inserted' | 'updated' | 'skipped'
     */
    public static function upsertCustomer(array $row): string
    {
        if (($row['fr_id'] ?? '') === '') {
            return 'skipped';
        }
        $db = Database::instance();

        $existing = $db->fetch(
            'SELECT id, is_no_call, dnc_reason, last_service FROM customers WHERE fr_id = ? AND district = ?',
            [$row['fr_id'], $row['district']]
        );
        if (!$existing && !empty($row['email'])) {
            $existing = $db->fetch(
                "SELECT id, is_no_call, dnc_reason, last_service FROM customers
                 WHERE email = ? COLLATE NOCASE AND (fr_id IS NULL OR fr_id = '')",
                [$row['email']]
            );
        }

        $identity = [
            'fr_id'          => $row['fr_id'],
            'district'       => $row['district'],
            'name'           => $row['name'],
            'email'          => $row['email'],
            'phone'          => $row['phone'],
            'account_number' => $row['account_number'],
            'address'        => $row['address'],
            'city'           => $row['city'],
            'state'          => $row['state'],
            'zip'            => $row['zip'],
            'status'         => $row['status'],
            'source'         => 'fieldroutes',  // anything synced from FR is real book data, never seed
            'last_service'   => $row['last_service'] ?? $existing['last_service'] ?? null,
            'updated_at'     => gmdate('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $db->update('customers', $identity, ['id' => $existing['id']]);
            return 'updated';
        }
        $db->insert('customers', $identity);
        return 'inserted';
    }

    /** Look up a configured district by its code ('wa'/'az'); null if not set. */
    public static function districtByCode(string $code): ?array
    {
        $code = strtolower(trim($code));
        foreach (self::districts() as $d) {
            if ($d['code'] === $code) {
                return $d;
            }
        }
        return null;
    }

    /**
     * Pull one customer's LIVE appointments + subscriptions straight from FR for
     * the profile page. Returns display-ready rows (status/freq/dates pre-mapped
     * so the template stays dumb). Empty arrays when the customer has none.
     * Network failures degrade to empty arrays (logged), never throw.
     *
     * @return array{appointments: array<int,array<string,mixed>>, subscriptions: array<int,array<string,mixed>>}
     */
    public static function pullCustomerLive(array $district, string $frId): array
    {
        $frId = trim($frId);
        if ($frId === '') {
            return ['appointments' => [], 'subscriptions' => []];
        }

        // Appointments: includeData=1 makes /search return the records inline.
        $appts = [];
        $aSearch = self::request($district, 'appointment/search', ['customerID' => $frId, 'includeData' => '1']);
        $appts = $aSearch['appointments'] ?? [];
        if (!$appts && !empty($aSearch['appointmentIDs'])) {
            $aGet = self::request($district, 'appointment/get', ['appointmentIDs' => implode(',', $aSearch['appointmentIDs'])]);
            $appts = $aGet['appointments'] ?? [];
        }
        usort($appts, fn($x, $y) => strcmp((string) ($y['date'] ?? ''), (string) ($x['date'] ?? '')));
        $appts = array_slice($appts, 0, 25);
        $appts = array_map(fn($a) => [
            'when'         => trim(self::fmtDate($a['date'] ?? '') . ' ' . (string) ($a['start'] ?? '')),
            'type'         => $a['type'] ?? '—',
            'status_label' => self::apptStatusLabel($a),
            'status_kind'  => self::apptStatusKind($a),
            'notes'        => $a['notes'] ?? '',
        ], $appts);

        // Subscriptions: /search returns IDs only, then /get the records.
        $subs = [];
        $sSearch = self::request($district, 'subscription/search', ['customerID' => $frId]);
        $sIds = $sSearch['subscriptionIDs'] ?? [];
        if ($sIds) {
            $sGet = self::request($district, 'subscription/get', ['subscriptionIDs' => implode(',', $sIds)]);
            $subs = $sGet['subscriptions'] ?? [];
        }
        $subs = array_map(fn($s) => [
            'status_label' => !empty($s['activeText']) ? $s['activeText'] : ((int) ($s['active'] ?? 0) === 1 ? 'Active' : 'Inactive'),
            'status_kind'  => (int) ($s['active'] ?? 0) === 1 ? 'active' : 'cancelled',
            'charge'       => isset($s['recurringCharge']) && $s['recurringCharge'] !== '' ? '$' . number_format((float) $s['recurringCharge'], 2) : '—',
            'freq_label'   => self::freqLabel($s['billingFrequency'] ?? null),
            'next'         => self::fmtDate($s['nextService'] ?? ''),
            'last'         => self::fmtDate($s['lastCompleted'] ?? ''),
            'added'        => self::fmtDate($s['dateAdded'] ?? ''),
        ], $subs);

        return ['appointments' => $appts, 'subscriptions' => $subs];
    }

    /** billingFrequency code → human label (per FR spec). */
    public static function freqLabel(mixed $f): string
    {
        return match ((int) $f) {
            30  => 'Monthly',
            60  => 'Bi-monthly',
            90  => 'Quarterly',
            180 => 'Semi-annual',
            365 => 'Annual',
            default => $f === null || $f === '' ? '—' : 'Custom',
        };
    }

    /** Appointment status → label (prefer FR's own statusText). */
    public static function apptStatusLabel(array $a): string
    {
        if (!empty($a['statusText'])) {
            return (string) $a['statusText'];
        }
        return match ((int) ($a['status'] ?? -1)) {
            0 => 'Pending', 1 => 'Completed', 2 => 'Scheduled', 3 => 'Cancelled', default => '—',
        };
    }

    /** Appointment status → badge kind for the light theme. */
    public static function apptStatusKind(array $a): string
    {
        return match ((int) ($a['status'] ?? -1)) {
            1 => 'closed', 3 => 'cancelled', 2 => 'scheduled', default => 'open',
        };
    }

    /** Safe date formatter: 'Y-m-d'/'Y-m-d H:i' → 'M j, Y'; passthrough otherwise. */
    private static function fmtDate(mixed $v): string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return '—';
        }
        $ts = strtotime($v);
        return $ts ? date('M j, Y', $ts) : $v;
    }

    /**
     * Normalize a phone number to E.164 format (+1XXXXXXXXXX for US/Canada).
     *
     * Rules:
     *   - Strips all non-digit characters.
     *   - 10 digits (e.g. 5095551234) → +15095551234 (assumes US/CA +1 prefix).
     *   - 11 digits starting with 1  → +15095551234.
     *   - Already E.164 (+1...)       → returned as-is.
     *   - Null / empty / garbage      → returns null (can't normalize what we
     *     don't recognize; callers should handle null gracefully).
     *
     * @param string|null $raw Raw phone from FR or user input.
     * @return string|null E.164 number or null if unparseable.
     */
    public static function normalizePhone(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $raw);
        $len = strlen($digits);

        // Already E.164: "+1..." — strip the + and validate.
        if (str_starts_with(trim($raw), '+')) {
            $stripped = substr($digits, 0); // digits only, already extracted
            if ($len >= 11 && $len <= 15) {
                return '+' . $digits;
            }
            return null; // too short/long for E.164
        }

        // 11 digits starting with 1 → US/CA national format.
        if ($len === 11 && $digits[0] === '1') {
            return '+' . $digits;
        }

        // 10 digits → assume US/CA, prepend +1.
        if ($len === 10) {
            return '+1' . $digits;
        }

        // Anything else is not a recognizable US number — return as-is with +
        // prefix if it looks like a country-coded number, otherwise null.
        if ($len >= 7 && $len <= 15) {
            return '+' . $digits;
        }

        return null;
    }

    /**
     * One GET against the FieldRoutes API. Returns the decoded envelope, or []
     * on any failure (logged). Auth is passed as query params per the API spec.
     */
    private static function request(array $district, string $endpoint, array $params = []): array
    {
        $params = array_merge([
            'authenticationKey'   => $district['key'],
            'authenticationToken' => $district['token'],
        ], $params);
        $url = $district['base'] . '/api/' . ltrim($endpoint, '/') . '?' . http_build_query($params);

        $body = self::httpGet($url);
        if ($body === false) {
            Logger::error('FieldRoutes request failed', ['district' => $district['code'], 'endpoint' => $endpoint]);
            return [];
        }
        $data = json_decode($body, true);
        if (!is_array($data)) {
            Logger::error('FieldRoutes bad JSON', ['district' => $district['code'], 'endpoint' => $endpoint]);
            return [];
        }
        if (isset($data['success']) && $data['success'] === false) {
            Logger::warning('FieldRoutes API error', [
                'district' => $district['code'], 'endpoint' => $endpoint,
                'error'    => $data['errorMessage'] ?? 'unknown',
            ]);
        }
        return $data;
    }

    /** cURL if available, else stream context. Returns body string or false. */
    private static function httpGet(string $url): string|false
    {
        $verifySsl = Config::bool('FIELDROUTES_SSL_VERIFY', true);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $opts = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_USERAGENT      => 'PatriotPest/1.0 (+fieldroutes-sync)',
            ];
            if (!$verifySsl) {
                $opts[CURLOPT_SSL_VERIFYPEER] = false;
                $opts[CURLOPT_SSL_VERIFYHOST] = 0;
            }
            curl_setopt_array($ch, $opts);
            $resp = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err  = curl_error($ch);
            curl_close($ch);
            if ($resp === false || $code < 200 || $code >= 300) {
                Logger::warning('FieldRoutes HTTP', ['code' => $code, 'err' => $err]);
                return false;
            }
            return (string) $resp;
        }
        $ctxOpts = [
            'http' => ['method' => 'GET', 'timeout' => self::TIMEOUT, 'header' => "User-Agent: PatriotPest/1.0\r\n"],
        ];
        if (!$verifySsl) {
            $ctxOpts['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false];
        }
        $ctx  = stream_context_create($ctxOpts);
        $resp = @file_get_contents($url, false, $ctx);
        return $resp === false ? false : $resp;
    }
}
