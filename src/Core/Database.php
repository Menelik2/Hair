<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Production-ready PDO Database singleton.
 * Compatible with AeonFree / shared MySQL 8 hosting.
 */
final class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    private function __construct() {}
    private function __clone() {}
    public function __wakeup() { throw new \RuntimeException('Cannot unserialize singleton'); }

    public static function configure(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            if (empty(self::$config)) {
                $configFile = dirname(__DIR__, 2) . '/config/database.php';
                if (!file_exists($configFile)) {
                    throw new \RuntimeException('Database configuration not found.');
                }
                self::$config = require $configFile;
            }

            $driver   = self::$config['driver']   ?? 'mysql';
            $host     = self::$config['host']     ?? 'localhost';
            $port     = (int) (self::$config['port'] ?? 3306);
            $database = self::$config['database'] ?? self::$config['dbname'] ?? 'hair_queue';
            $charset  = self::$config['charset']  ?? 'utf8mb4';
            $username = self::$config['username'] ?? self::$config['user'] ?? 'root';
            $password = self::$config['password'] ?? self::$config['pass'] ?? '';

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
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getMessage());
                throw new \RuntimeException('Unable to connect to the database. Please try again later.');
            }
        }

        return self::$instance;
    }

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
        return self::getInstance()->rollBack();
    }

    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $result = self::query($sql, $params)->fetch();
        return $result === false ? null : $result;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): string
    {
        self::query($sql, $params);
        return self::getInstance()->lastInsertId();
    }

    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function ping(): bool
    {
        try {
            self::getInstance()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
