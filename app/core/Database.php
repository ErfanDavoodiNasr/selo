<?php
namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static $pdo;

    public static function init(array $config): void
    {
        if (self::$pdo) {
            return;
        }
        $db = $config['db'] ?? null;
        if (!$db) {
            return;
        }
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['name']);
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], $options);
            self::$pdo->exec("SET NAMES utf8mb4");
        } catch (PDOException $e) {
            http_response_code(500);
            echo 'Database connection failed.';
            exit;
        }
    }

    public static function pdo(): ?PDO
    {
        return self::$pdo;
    }
}
