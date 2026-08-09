<?php
/**
 * Database — SQLite (PDO) access layer.
 *
 * Replaces the old dual-layer setup (a PPDatabase singleton plus a dead MySQL
 * helper that silently returned null). This is the single source of DB access:
 *
 *   - SQLite in WAL mode (fast reads, safe concurrent access),
 *   - foreign keys enforced,
 *   - busy-timeout so requests don't fail under brief locks,
 *   - EVERY query goes through prepared statements (no string-built SQL),
 *   - schema auto-applied from database/schema.sql on first run,
 *   - small typed helpers so controllers never touch PDO directly.
 *
 * Usage:
 *   $db   = Database::instance();
 *   $row  = $db->fetch('SELECT * FROM staff WHERE id = ?', [$id]);
 *   $rows = $db->fetchAll('SELECT * FROM posts WHERE status = ?', ['published']);
 *   $id   = $db->insert('posts', ['title' => 'x', 'status' => 'draft']);
 *   $db->execute('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
 */

declare(strict_types=1);

namespace PPC\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;

    private function __construct(string $path)
    {
        // Ensure the directory exists so SQLite can create the file.
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw, never silent
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
            ]);
        } catch (PDOException $e) {
            Logger::critical('Database connection failed', ['path' => $path]);
            throw new RuntimeException('Database connection failed.');
        }

        // Pragmas: WAL for concurrency, FK enforcement, 5s busy timeout.
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
    }

    /** Get the shared instance (created on first call). */
    public static function instance(): self
    {
        if (self::$instance === null) {
            // DB_PATH is relative to the project root.
            $rel  = Config::get('DB_PATH', 'database/patriot.db') ?? 'database/patriot.db';
            $path = str_starts_with($rel, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $rel)
                ? $rel
                : BASE_PATH . DIRECTORY_SEPARATOR . $rel;

            self::$instance = new self($path);
            self::$instance->migrate();
        }
        return self::$instance;
    }

    /**
     * Apply database/schema.sql if the schema_version marker is missing/stale.
     * The schema file is idempotent (CREATE TABLE IF NOT EXISTS), so re-running
     * it is safe. Add ALTER-based upgrades in upgrade() as the schema evolves.
     */
    private function migrate(): void
    {
        $schemaFile = BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (!is_readable($schemaFile)) {
            return;
        }

        // Track applied schema version so we only run the full script once.
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)');
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = ?');
        $stmt->execute(['schema_version']);
        $current = (int) ($stmt->fetchColumn() ?: 0);

        $target = 2; // bump when schema.sql changes structurally
        if ($current < $target) {
            $sql = file_get_contents($schemaFile);
            if ($sql !== false && trim($sql) !== '') {
                $this->pdo->exec($sql);
            }
            $up = $this->pdo->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)');
            $up->execute(['schema_version', (string) $target]);
            Logger::info('Database schema applied', ['version' => $target]);
        }
    }

    /** Expose raw PDO only where genuinely needed (e.g. transactions). */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Run a statement; returns affected row count. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Fetch a single row (associative array) or null. */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows as a list of associative arrays. */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Fetch a single scalar value (e.g. COUNT(*)). */
    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Insert an associative array as a row; returns the new row id.
     * Column names come from code (never user input) — values are bound.
     */
    public function insert(string $table, array $data): int
    {
        $cols   = array_keys($data);
        $places = implode(', ', array_fill(0, count($cols), '?'));
        $sql    = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . $places . ')';
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching $where (associative column => value); returns count.
     */
    public function update(string $table, array $data, array $where): int
    {
        $set    = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $cond   = implode(' AND ', array_map(fn($c) => "$c = ?", array_keys($where)));
        $sql    = "UPDATE $table SET $set WHERE $cond";
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute([...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    /** Begin/commit/rollback helpers for multi-step operations. */
    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
