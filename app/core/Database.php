<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $pdo = null;
    private static array $tableExistsCache = [];

    public static function init(array $config): void
    {
        if (self::$pdo) {
            return;
        }
        $db = $config['db'] ?? null;
        if (!is_array($db)) {
            return;
        }

        try {
            self::assertValidDatabaseConfig($db);
            $dsn = self::dsn($db);
        } catch (RuntimeException $e) {
            Logger::critical('database_config_invalid', ['reason' => $e->getMessage()], 'db');
            http_response_code(500);
            echo 'Database configuration is invalid.';
            exit;
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            self::$pdo = new PDO($dsn, (string)$db['user'], (string)($db['pass'] ?? ''), $options);
            self::$pdo->exec('SET NAMES utf8mb4');
        } catch (PDOException $e) {
            Logger::critical('database_connection_failed', ['sqlstate' => (string)$e->getCode()], 'db');
            http_response_code(500);
            echo 'Database connection failed.';
            exit;
        }
    }

    public static function pdo(): ?PDO
    {
        return self::$pdo;
    }

    public static function tableExists(string $tableName): bool
    {
        if ($tableName === '' || !self::isSafeIdentifier($tableName)) {
            return false;
        }
        if (array_key_exists($tableName, self::$tableExistsCache)) {
            return self::$tableExistsCache[$tableName];
        }
        $pdo = self::pdo();
        if (!$pdo) {
            return false;
        }
        $sql = 'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$tableName]);
        $exists = (bool)$stmt->fetchColumn();
        self::$tableExistsCache[$tableName] = $exists;
        return $exists;
    }

    public static function isSafePrefix(string $prefix): bool
    {
        return $prefix === '' || (bool)preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', $prefix);
    }

    public static function isSafeIdentifier(string $identifier): bool
    {
        return (bool)preg_match('/^[A-Za-z][A-Za-z0-9_]{0,63}$/', $identifier);
    }

    private static function assertValidDatabaseConfig(array $db): void
    {
        foreach (['host', 'name', 'user'] as $key) {
            if (!isset($db[$key]) || trim((string)$db[$key]) === '') {
                throw new RuntimeException('missing_' . $key);
            }
        }

        $prefix = (string)($db['prefix'] ?? '');
        if (!self::isSafePrefix($prefix)) {
            throw new RuntimeException('unsafe_table_prefix');
        }
    }

    private static function dsn(array $db): string
    {
        if (!empty($db['unix_socket'])) {
            return sprintf(
                'mysql:unix_socket=%s;dbname=%s;charset=utf8mb4',
                (string)$db['unix_socket'],
                (string)$db['name']
            );
        }

        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', (string)$db['host'], (string)$db['name']);
        if (!empty($db['port'])) {
            $dsn .= ';port=' . (int)$db['port'];
        }
        return $dsn;
    }
}
