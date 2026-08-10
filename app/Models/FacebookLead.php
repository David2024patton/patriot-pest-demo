<?php
/**
 * FacebookLead — model for the facebook_leads table.
 *
 * Read-only accessor: the webhook writes, the admin dashboard reads.
 * Every method returns null on missing data so callers never crash on
 * corrupted or partially-fetched Graph API payloads.
 */

declare(strict_types=1);

namespace PPC\Models;

use PPC\Core\Database;

final class FacebookLead
{
    /**
     * Find a lead by its Facebook leadgen_id.
     */
    public static function findByLeadgenId(string $leadgenId): ?array
    {
        return Database::instance()->fetch(
            'SELECT * FROM facebook_leads WHERE leadgen_id = ?',
            [$leadgenId]
        );
    }

    /**
     * Check if a fingerprint (SHA256 of name|email|phone) exists within
     * the last $windowHours hours. Prevents duplicate notifications when
     * Facebook re-sends the same lead event.
     */
    public static function fingerprintExists(string $fingerprint, int $windowHours = 24): bool
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($windowHours * 3600));
        $row = Database::instance()->fetch(
            'SELECT id FROM facebook_leads WHERE fingerprint = ? AND created_at >= ? LIMIT 1',
            [$fingerprint, $cutoff]
        );
        return $row !== null;
    }

    /**
     * Insert a new lead row. Returns the new row id.
     */
    public static function insert(array $data): int
    {
        return Database::instance()->insert('facebook_leads', $data);
    }

    /**
     * Update notification status after dispatch attempt.
     */
    public static function updateNotificationStatus(int $id, bool $smsOk, ?string $smsError, bool $emailOk): void
    {
        $now = gmdate('Y-m-d H:i:s');
        $update = [
            'sms_sent'              => $smsOk ? 1 : 0,
            'sms_error'             => $smsError,
            'email_fallback_sent'   => $emailOk ? 1 : 0,
            'processed'             => 1,
            'processed_at'          => $now,
        ];
        if ($smsOk) {
            $update['sms_sent_at'] = $now;
        }
        if ($emailOk) {
            $update['email_fallback_sent_at'] = $now;
        }
        Database::instance()->update('facebook_leads', $update, ['id' => $id]);
    }
}
