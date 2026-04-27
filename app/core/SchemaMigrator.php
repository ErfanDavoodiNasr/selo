<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use RuntimeException;

class SchemaMigrator
{
    private const PERFORMANCE_INDEXES = [
        'media_files' => [
            'idx_user_created' => '(`user_id`, `created_at`)',
        ],
        'conversations' => [
            'idx_user_one_last' => '(`user_one_id`, `last_message_at`, `id`)',
            'idx_user_two_last' => '(`user_two_id`, `last_message_at`, `id`)',
        ],
        'messages' => [
            'idx_recipient_unread' => '(`recipient_id`, `is_deleted_for_all`, `conversation_id`, `id`)',
            'idx_sender_conv_id' => '(`sender_id`, `conversation_id`, `id`)',
        ],
        'message_deletions' => [
            'idx_user_message' => '(`user_id`, `message_id`)',
        ],
    ];

    public static function applyFile(PDO $pdo, string $path, string $prefix): int
    {
        if (!Database::isSafePrefix($prefix)) {
            throw new RuntimeException('Unsafe table prefix.');
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Schema file is not readable.');
        }

        $sql = file_get_contents($path);
        if (!is_string($sql)) {
            throw new RuntimeException('Schema file could not be loaded.');
        }

        $sql = str_replace('{{prefix}}', $prefix, $sql);
        return self::applySql($pdo, $sql);
    }

    public static function applyPerformanceIndexes(PDO $pdo, string $prefix): int
    {
        if (!Database::isSafePrefix($prefix)) {
            throw new RuntimeException('Unsafe table prefix.');
        }

        $added = 0;
        foreach (self::PERFORMANCE_INDEXES as $tableSuffix => $indexes) {
            $table = $prefix . $tableSuffix;
            if (!Database::isSafeIdentifier($table)) {
                throw new RuntimeException('Unsafe table name.');
            }
            if (!self::tableExists($pdo, $table)) {
                continue;
            }
            foreach ($indexes as $indexName => $definition) {
                if (self::indexExists($pdo, $table, $indexName)) {
                    continue;
                }
                $pdo->exec('ALTER TABLE `' . $table . '` ADD INDEX `' . $indexName . '` ' . $definition);
                $added++;
            }
        }

        return $added;
    }

    public static function applySql(PDO $pdo, string $sql): int
    {
        $count = 0;
        foreach (self::splitStatements($sql) as $statement) {
            $pdo->exec($statement);
            $count++;
        }
        return $count;
    }

    /**
     * Split SQL files without breaking on semicolons inside quoted strings.
     */
    public static function splitStatements(string $sql): array
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;
        $statements = [];
        $buffer = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($quote === null && $char === '-' && $next === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($quote === null && $char === '#') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }
                $buffer .= "\n";
                continue;
            }

            if ($quote === null && $char === '/' && $next === '*') {
                $i += 2;
                while ($i < $length - 1 && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i++;
                continue;
            }

            if (($char === "'" || $char === '"' || $char === '`')) {
                if ($quote === null) {
                    $quote = $char;
                } elseif ($quote === $char && !self::isEscaped($sql, $i)) {
                    $quote = null;
                }
            }

            if ($char === ';' && $quote === null) {
                $statement = trim($buffer);
                if ($statement !== '') {
                    $statements[] = $statement;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $statement = trim($buffer);
        if ($statement !== '') {
            $statements[] = $statement;
        }

        return $statements;
    }

    private static function isEscaped(string $sql, int $offset): bool
    {
        $slashes = 0;
        for ($i = $offset - 1; $i >= 0 && $sql[$i] === '\\'; $i--) {
            $slashes++;
        }
        return ($slashes % 2) === 1;
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    }

    private static function indexExists(PDO $pdo, string $table, string $index): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1');
        $stmt->execute([$table, $index]);
        return (bool)$stmt->fetchColumn();
    }
}
