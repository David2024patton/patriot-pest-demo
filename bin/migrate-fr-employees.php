<?php
/**
 * bin/migrate-fr-employees.php — add staff.fr_employee_id (FieldRoutes employee
 * id) so FR employees can sync into the staff table and log in passwordless.
 *
 * Safe to re-run: checks the column exists before altering.
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;
use PPC\Core\Logger;

echo "Running FR-employee migration...\n";

try {
    $db = Database::instance();

    $cols = $db->fetchAll("PRAGMA table_info(staff)");
    $has = array_filter($cols, fn (array $c) => $c['name'] === 'fr_employee_id');
    if (!$has) {
        $db->execute("ALTER TABLE staff ADD COLUMN fr_employee_id TEXT");
        echo "✅ Added staff.fr_employee_id\n";
    } else {
        echo "staff.fr_employee_id already exists, skipping\n";
    }

    $idx = $db->fetchAll("PRAGMA index_list(staff)");
    $hasIdx = array_filter($idx, fn (array $i) => $i['name'] === 'idx_staff_fr_employee');
    if (!$hasIdx) {
        $db->execute("CREATE INDEX IF NOT EXISTS idx_staff_fr_employee ON staff(fr_employee_id)");
        echo "✅ Created idx_staff_fr_employee\n";
    } else {
        echo "idx_staff_fr_employee already exists, skipping\n";
    }

    echo "✅ FR-employee migration completed successfully!\n";
} catch (\Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    Logger::error('FR-employee migration failed', ['error' => $e->getMessage()]);
    exit(1);
}
