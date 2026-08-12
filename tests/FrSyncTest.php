<?php
declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PPC\Core\Database;
use PPC\Integrations\FieldRoutes;

/**
 * FieldRoutes sync primitives: FR -> local upserts with change detection.
 *
 * FieldRoutes is the source of truth for customers AND staff; the local tables
 * are a cache. These tests lock the two rules that keep the cache safe:
 *   1. repeated syncs with no book movement write nothing ('skipped'),
 *   2. locally-managed fields are never clobbered by a sync
 *      (customers: is_no_call / dnc_reason; staff: role / department).
 *
 * Network calls are not exercised here (FieldRoutes HTTP is integration-level,
 * verified on staging with real credentials). Only the validation short-circuit
 * of updateCustomerEmail() is reachable without HTTP and is tested.
 */
final class FrSyncTest extends TestCase
{
    private string $dbPath;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/test_fr_sync_' . uniqid() . '.db';
        putenv('DB_PATH=' . $this->dbPath);
        $this->resetDatabaseSingleton();
        Database::instance(); // applies database/schema.sql to the fresh file
    }

    protected function tearDown(): void
    {
        putenv('DB_PATH');
        $this->resetDatabaseSingleton();
        @unlink($this->dbPath);
        @unlink($this->dbPath . '-wal');
        @unlink($this->dbPath . '-shm');
    }

    private function resetDatabaseSingleton(): void
    {
        $ref = new \ReflectionClass(Database::class);
        $prop = $ref->getProperty('instance');
        $prop->setAccessible(true);
        $prop->setValue(null, null);
    }

    /* ---------------- employees ---------------- */

    #[Test] public function upsert_employee_inserts_fr_staff_row(): void
    {
        $result = FieldRoutes::upsertEmployee([
            'fr_employee_id' => 'EMP-1001',
            'district'       => 'wa',
            'name'           => 'Jane Tech',
            'email'          => 'jane@patriotpest.pro',
            'phone'          => '5095551234',
            'active'         => 1,
        ]);
        $this->assertSame('inserted', $result);

        $row = Database::instance()->fetch("SELECT * FROM staff WHERE fr_employee_id = 'EMP-1001'");
        $this->assertNotNull($row, 'FR employee must land in the staff table');
        $this->assertSame('jane@patriotpest.pro', $row['email']);
        $this->assertSame('Jane Tech', $row['name']);
        $this->assertSame('staff', $row['role'], 'FR employees default to the staff role');
        $this->assertSame(1, (int) $row['active']);
    }

    #[Test] public function upsert_employee_unchanged_is_skipped(): void
    {
        $row = [
            'fr_employee_id' => 'EMP-1002',
            'district'       => 'az',
            'name'           => 'Sam Field',
            'email'          => 'sam@patriotpest.pro',
            'phone'          => '6025550000',
            'active'         => 1,
        ];
        $this->assertSame('inserted', FieldRoutes::upsertEmployee($row));
        $this->assertSame('skipped', FieldRoutes::upsertEmployee($row), 'Idempotent re-sync must not write');
        $this->assertSame(1, (int) Database::instance()->scalar('SELECT COUNT(*) FROM staff'));
    }

    #[Test] public function upsert_employee_update_preserves_local_role(): void
    {
        $row = [
            'fr_employee_id' => 'EMP-1003',
            'district'       => 'wa',
            'name'           => 'Old Name',
            'email'          => 'ops@patriotpest.pro',
            'phone'          => null,
            'active'         => 1,
        ];
        $this->assertSame('inserted', FieldRoutes::upsertEmployee($row));

        // Local promotion: someone made this employee an admin locally.
        $db = Database::instance();
        $db->update('staff', ['role' => 'admin'], ['fr_employee_id' => 'EMP-1003']);

        // FR changes the name; the sync must update the name but keep the role.
        $row['name'] = 'New Name';
        $this->assertSame('updated', FieldRoutes::upsertEmployee($row));
        $after = $db->fetch("SELECT name, role FROM staff WHERE fr_employee_id = 'EMP-1003'");
        $this->assertSame('New Name', $after['name']);
        $this->assertSame('admin', $after['role'], 'Local role must survive an FR sync');
    }

    #[Test] public function upsert_employee_deactivation_flips_active(): void
    {
        $row = [
            'fr_employee_id' => 'EMP-1004',
            'district'       => 'wa',
            'name'           => 'Leaving Soon',
            'email'          => 'bye@patriotpest.pro',
            'phone'          => null,
            'active'         => 1,
        ];
        $this->assertSame('inserted', FieldRoutes::upsertEmployee($row));
        $row['active'] = 0;
        $this->assertSame('updated', FieldRoutes::upsertEmployee($row));
        $this->assertSame(0, (int) Database::instance()->scalar("SELECT active FROM staff WHERE fr_employee_id = 'EMP-1004'"));
    }

    #[Test] public function upsert_employee_without_key_or_email_is_skipped(): void
    {
        $this->assertSame('skipped', FieldRoutes::upsertEmployee([
            'fr_employee_id' => '',
            'district'       => 'wa',
            'name'           => 'No Id',
            'email'          => 'noid@patriotpest.pro',
            'phone'          => null,
            'active'         => 1,
        ]), 'Missing fr_employee_id must not insert');
        $this->assertSame('skipped', FieldRoutes::upsertEmployee([
            'fr_employee_id' => 'EMP-1005',
            'district'       => 'wa',
            'name'           => 'No Email',
            'email'          => '',
            'phone'          => null,
            'active'         => 1,
        ]), 'Missing email must not insert (no way to reach the employee)');
        $this->assertSame(0, (int) Database::instance()->scalar('SELECT COUNT(*) FROM staff'));
    }

    /* ---------------- customers ---------------- */

    #[Test] public function upsert_customer_unchanged_is_skipped(): void
    {
        $row = [
            'fr_id'          => 'C-9001',
            'district'       => 'wa',
            'name'           => 'Ada Homeowner',
            'email'          => 'ada@example.com',
            'phone'          => '5095551111',
            'account_number' => 'C-9001',
            'address'        => '1 Main St',
            'city'           => 'Spokane',
            'state'          => 'WA',
            'zip'            => '99201',
            'status'         => 'active',
            'last_service'   => '2026-07-01 10:00:00',
        ];
        $this->assertSame('inserted', FieldRoutes::upsertCustomer($row));
        $this->assertSame('skipped', FieldRoutes::upsertCustomer($row), 'Idempotent re-sync must not write');
        $this->assertSame(1, (int) Database::instance()->scalar('SELECT COUNT(*) FROM customers'));
    }

    #[Test] public function upsert_customer_update_preserves_local_flags(): void
    {
        $row = [
            'fr_id'          => 'C-9002',
            'district'       => 'az',
            'name'           => 'Bob Renovates',
            'email'          => 'bob@example.com',
            'phone'          => '6025552222',
            'account_number' => 'C-9002',
            'address'        => '2 Oak Ave',
            'city'           => 'Phoenix',
            'state'          => 'AZ',
            'zip'            => '85001',
            'status'         => 'active',
            'last_service'   => null,
        ];
        $this->assertSame('inserted', FieldRoutes::upsertCustomer($row));

        // Local opt-out: customer asked not to be contacted. Sync must respect it.
        $db = Database::instance();
        $db->update('customers', ['is_no_call' => 1, 'dnc_reason' => 'requested no contact'], ['fr_id' => 'C-9002']);

        // FR updates the phone number; the sync must apply it but keep the DNC flags.
        $row['phone'] = '6025553333';
        $this->assertSame('updated', FieldRoutes::upsertCustomer($row));
        $after = $db->fetch("SELECT phone, is_no_call, dnc_reason FROM customers WHERE fr_id = 'C-9002'");
        $this->assertSame('6025553333', $after['phone'], 'FR identity change must be applied');
        $this->assertSame(1, (int) $after['is_no_call'], 'is_no_call must survive the sync');
        $this->assertSame('requested no contact', $after['dnc_reason'], 'dnc_reason must survive the sync');
    }

    #[Test] public function upsert_customer_without_fr_id_is_skipped(): void
    {
        $this->assertSame('skipped', FieldRoutes::upsertCustomer([
            'fr_id'    => '',
            'district' => 'wa',
            'name'     => 'Local Only',
            'email'    => 'local@example.com',
        ]));
        $this->assertSame(0, (int) Database::instance()->scalar('SELECT COUNT(*) FROM customers'));
    }

    /* ---------------- email push (validation short-circuit, no HTTP) ---------------- */

    #[Test] public function update_customer_email_validates_before_network(): void
    {
        $district = ['code' => 'wa', 'base' => 'https://example.invalid', 'key' => 'k', 'token' => 't'];
        $this->assertSame(
            ['success' => false, 'error' => 'Missing email or customer id'],
            FieldRoutes::updateCustomerEmail($district, 'C-1', ''),
            'Empty email must fail fast without hitting the API'
        );
        $this->assertSame(
            ['success' => false, 'error' => 'Missing email or customer id'],
            FieldRoutes::updateCustomerEmail($district, '', 'x@example.com'),
            'Empty customer id must fail fast without hitting the API'
        );
    }
}
