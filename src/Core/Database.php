<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Full production PDO Database layer for Elite Cuts.
 *
 * Features:
 *  - Singleton connection with auto-reconnect (shared hosting / AeonFree)
 *  - Prepared statements only
 *  - Transaction helpers (begin / commit / rollBack / transaction callback)
 *  - fetch / fetchAll / fetchColumn / count / exists
 *  - insert (returns lastInsertId) / execute (returns rowCount)
 *  - Safe for MySQL 8 on free hosting
 */
final class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    private function __construct() {}
    private function __clone() {}

    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize Database singleton.');
    }

    // -------------------------------------------------------------------------
    // Configuration & connection
    // -------------------------------------------------------------------------

    public static function configure(array $config): void
    {
        self::$config = $config;
        // Force reconnect next time if config changes
        self::$instance = null;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::connect();
        }

        // Auto-reconnect if the connection dropped (common on free hosting)
        try {
            self::$instance->query('SELECT 1');
        } catch (Throwable) {
            self::$instance = null;
            self::connect();
        }

        return self::$instance;
    }

    private static function connect(): void
    {
        if (empty(self::$config)) {
            $configFile = dirname(__DIR__, 2) . '/config/database.php';
            if (!is_file($configFile)) {
                throw new RuntimeException('Database configuration not found: config/database.php');
            }
            self::$config = require $configFile;
        }

        $driver   = self::$config['driver']   ?? 'mysql';
        $host     = self::$config['host']     ?? 'localhost';
        $port     = (int) (self::$config['port'] ?? 3306);
        $database = self::$config['database'] ?? self::$config['dbname'] ?? 'hair_queue';
        $charset  = self::$config['charset']  ?? 'utf8mb4';
        $username = self::$config['username'] ?? self::$config['user'] ?? 'root';
        $password = (string) (self::$config['password'] ?? self::$config['pass'] ?? '');

        $dsn = sprintf(
            '%s:host=%s;port=%d;dbname=%s;charset=%s',
            $driver,
            $host,
            $port,
            $database,
            $charset
        );

        $options = self::$config['options'] ?? [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ];

        // Ensure critical options are always set
        $options[PDO::ATTR_ERRMODE]            = PDO::ERRMODE_EXCEPTION;
        $options[PDO::ATTR_DEFAULT_FETCH_MODE] = PDO::FETCH_ASSOC;

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            throw new RuntimeException(
                'Unable to connect to the database. Check your .env credentials and that MySQL is running.'
            );
        }
    }

    /** Force a fresh connection (useful after long idle / SSE). */
    public static function reconnect(): void
    {
        self::$instance = null;
        self::connect();
    }

    public static function ping(): bool
    {
        try {
            self::getInstance()->query('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------

    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    public static function rollBack(): bool
    {
        $pdo = self::getInstance();
        if ($pdo->inTransaction()) {
            return $pdo->rollBack();
        }
        return false;
    }

    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }

    /**
     * Run a callback inside a transaction.
     * Automatically commits on success, rolls back on any Throwable.
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getInstance();
        $pdo->beginTransaction();

        try {
            $result = $callback();
            $pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    // -------------------------------------------------------------------------
    // Core query helpers
    // -------------------------------------------------------------------------

    /**
     * Prepare + execute. Returns the PDOStatement.
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute(self::normalizeParams($params));
            return $stmt;
        } catch (PDOException $e) {
            // One retry on connection-lost errors (common on free hosting)
            if (self::isConnectionError($e)) {
                self::reconnect();
                $stmt = self::getInstance()->prepare($sql);
                $stmt->execute(self::normalizeParams($params));
                return $stmt;
            }
            error_log('[Database] Query failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
            throw $e;
        }
    }

    /**
     * Fetch one row as associative array, or null.
     *
     * @param  array<int|string, mixed>  $params
     * @return array<string, mixed>|null
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Fetch all rows.
     *
     * @param  array<int|string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Fetch a single column value from the first row.
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        return self::query($sql, $params)->fetchColumn($column);
    }

    /**
     * INSERT and return last insert ID as string.
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function insert(string $sql, array $params = []): string
    {
        self::query($sql, $params);
        return self::getInstance()->lastInsertId();
    }

    /**
     * UPDATE / DELETE / generic execute. Returns affected row count.
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    // -------------------------------------------------------------------------
    // Convenience helpers used across the app
    // -------------------------------------------------------------------------

    /**
     * COUNT(*) helper. Returns integer count.
     *
     * Example: Database::count('tickets', "status = ?", ['waiting']);
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function count(string $table, string $where = '1=1', array $params = []): int
    {
        $sql = "SELECT COUNT(*) FROM `{$table}` WHERE {$where}";
        return (int) self::fetchColumn($sql, $params);
    }

    /**
     * Does at least one row exist?
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function exists(string $table, string $where, array $params = []): bool
    {
        $sql = "SELECT 1 FROM `{$table}` WHERE {$where} LIMIT 1";
        return self::fetch($sql, $params) !== null;
    }

    /**
     * Fetch a single row by primary key (id).
     *
     * @return array<string, mixed>|null
     */
    public static function find(string $table, int|string $id, string $pk = 'id'): ?array
    {
        return self::fetch("SELECT * FROM `{$table}` WHERE `{$pk}` = ? LIMIT 1", [$id]);
    }

    /**
     * Simple UPDATE helper.
     *
     * Example:
     *   Database::update('stylists', ['status' => 'active', 'is_available' => 1], 'id = ?', [$id]);
     *
     * @param  array<string, mixed>      $data
     * @param  array<int|string, mixed>  $params
     */
    public static function update(string $table, array $data, string $where, array $params = []): int
    {
        if ($data === []) {
            return 0;
        }

        $sets = [];
        $values = [];
        foreach ($data as $column => $value) {
            $sets[] = "`{$column}` = ?";
            $values[] = $value;
        }

        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . " WHERE {$where}";
        return self::execute($sql, array_merge($values, $params));
    }

    /**
     * Simple DELETE helper.
     *
     * @param  array<int|string, mixed>  $params
     */
    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::execute("DELETE FROM `{$table}` WHERE {$where}", $params);
    }

    /**
     * Last insert ID (string).
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Normalize params: convert bool → int for MySQL compatibility.
     *
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    private static function normalizeParams(array $params): array
    {
        foreach ($params as $k => $v) {
            if (is_bool($v)) {
                $params[$k] = $v ? 1 : 0;
            }
        }
        return $params;
    }

    private static function isConnectionError(PDOException $e): bool
    {
        $msg = strtolower($e->getMessage());
        $codes = ['2006', '2013', 'hy000']; // MySQL server has gone away / lost connection

        foreach ($codes as $code) {
            if (str_contains($msg, $code) || (string) $e->getCode() === $code) {
                return true;
            }
        }

        return str_contains($msg, 'server has gone away')
            || str_contains($msg, 'lost connection')
            || str_contains($msg, 'no such file or directory');
    }
}
