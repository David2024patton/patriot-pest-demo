<?php
/**
 * Migration script for RBAC UI enhancements
 * Adds departments table and department_id/manager_id columns to staff table
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use PPC\Core\Database;
use PPC\Core\Logger;

echo "Running RBAC UI migration...\n";

try {
    $db = Database::instance();

    // Create departments table
    echo "Creating departments table...\n";
    $db->execute("
        CREATE TABLE IF NOT EXISTS departments (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            name       TEXT NOT NULL,
            parent_id  INTEGER REFERENCES departments(id),
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        )
    ");

    // Add department_id column to staff table if it doesn't exist
    echo "Adding department_id column to staff table...\n";
    try {
        $db->execute("ALTER TABLE staff ADD COLUMN department_id INTEGER REFERENCES departments(id)");
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), 'duplicate column name')) {
            echo "Column department_id already exists, skipping...\n";
        } else {
            throw $e;
        }
    }

    // Add manager_id column to staff table if it doesn't exist
    echo "Adding manager_id column to staff table...\n";
    try {
        $db->execute("ALTER TABLE staff ADD COLUMN manager_id INTEGER REFERENCES staff(id)");
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), 'duplicate column name')) {
            echo "Column manager_id already exists, skipping...\n";
        } else {
            throw $e;
        }
    }

    // Seed some default departments
    echo "Seeding default departments...\n";
    $db->execute("INSERT OR IGNORE INTO departments (name, parent_id) VALUES ('Operations', NULL)");
    $db->execute("INSERT OR IGNORE INTO departments (name, parent_id) VALUES ('Sales', NULL)");
    $db->execute("INSERT OR IGNORE INTO departments (name, parent_id) VALUES ('Marketing', NULL)");
    $db->execute("INSERT OR IGNORE INTO departments (name, parent_id) VALUES ('Customer Service', NULL)");
    $db->execute("INSERT OR IGNORE INTO departments (name, parent_id) VALUES ('Inside Sales', (SELECT id FROM departments WHERE name = 'Sales'))");
    $db->execute("INSERT OR IGNORE INTO departments (name, parent_id) VALUES ('Field Sales', (SELECT id FROM departments WHERE name = 'Sales'))");

    echo "✅ RBAC UI migration completed successfully!\n";
    echo "\nNext steps:\n";
    echo "1. Visit /admin/roles to manage role permissions\n";
    echo "2. Visit /admin/departments to manage organizational structure\n";
    echo "3. Edit staff members to assign them to departments and managers\n";

} catch (\Throwable $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    Logger::error('RBAC UI migration failed', ['error' => $e->getMessage()]);
    exit(1);
}