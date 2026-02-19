<?php
namespace App\Core;

class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const LOCK_MINUTES = 15;
    private const LOGIN_TTL_HOURS = 48;
    private const REACTION_TTL_HOURS = 6;
    private const ENDPOINT_TTL_HOURS = 24;

    private static $lastCleanupAt = [
        'login' => 0,
        'reaction' => 0,
    ];
    private static $loginReady = false;
    private static $endpointReady = false;

    public static function tooManyAttempts(string $ip, string $identifier, array $config): bool
    {
        self::ensureLoginTable($config);
        self::cleanupLogin($config);
        return self::tooManyAttemptsInTable($config, self::loginTable($config), $ip, $identifier, self::MAX_ATTEMPTS, self::WINDOW_MINUTES);
    }

    public static function hit(string $ip, string $identifier, array $config): void
    {
        self::ensureLoginTable($config);
        self::cleanupLogin($config);
        self::hitInTable($config, self::loginTable($config), $ip, $identifier, self::MAX_ATTEMPTS, self::WINDOW_MINUTES, self::LOCK_MINUTES);
    }

    public static function clear(string $ip, string $identifier, array $config): void
    {
        self::ensureLoginTable($config);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM ' . self::loginTable($config) . ' WHERE ip = ? AND identifier = ?');
        $stmt->execute([$ip, $identifier]);
    }

    public static function tooManyAttemptsCustom(string $ip, string $identifier, array $config, int $maxAttempts, int $windowMinutes, int $lockMinutes): bool
    {
        self::ensureLoginTable($config);
        self::cleanupLogin($config);
        return self::tooManyAttemptsInTable($config, self::loginTable($config), $ip, $identifier, $maxAttempts, $windowMinutes);
    }

    public static function hitCustom(string $ip, string $identifier, array $config, int $maxAttempts, int $windowMinutes, int $lockMinutes): void
    {
        self::ensureLoginTable($config);
        self::cleanupLogin($config);
        self::hitInTable($config, self::loginTable($config), $ip, $identifier, $maxAttempts, $windowMinutes, $lockMinutes);
    }

    public static function tooManyReactionAttempts(string $ip, string $identifier, array $config, int $maxAttempts, int $windowMinutes, int $lockMinutes): bool
    {
        self::ensureReactionTable($config);
        self::cleanupReaction($config);
        return self::tooManyAttemptsInTable($config, self::reactionTable($config), $ip, $identifier, $maxAttempts, $windowMinutes);
    }

    public static function hitReactionAttempt(string $ip, string $identifier, array $config, int $maxAttempts, int $windowMinutes, int $lockMinutes): void
    {
        self::ensureReactionTable($config);
        self::cleanupReaction($config);
        self::hitInTable($config, self::reactionTable($config), $ip, $identifier, $maxAttempts, $windowMinutes, $lockMinutes);
    }

    public static function endpointIsLimited(array $config, string $endpoint, ?int $userId = null): bool
    {
        self::ensureEndpointTable($config);
        self::cleanupEndpoint($config);

        $policy = self::endpointPolicy($config, $endpoint);
        $ip = LogContext::getIp() ?: 'unknown';
        if (self::tooManyEndpointKey($config, self::endpointIpIdentifier($endpoint, $ip), $policy['ip_burst'], $policy['window_seconds'])) {
            return true;
        }
        if ($userId !== null && $userId > 0 && self::tooManyEndpointKey($config, self::endpointUserIdentifier($endpoint, $userId), $policy['user_burst'], $policy['window_seconds'])) {
            return true;
        }
        return false;
    }

    public static function hitEndpoint(array $config, string $endpoint, ?int $userId = null): void
    {
        self::ensureEndpointTable($config);
        self::cleanupEndpoint($config);

        $policy = self::endpointPolicy($config, $endpoint);
        $ip = LogContext::getIp() ?: 'unknown';
        self::hitEndpointKey($config, self::endpointIpIdentifier($endpoint, $ip), $policy, $policy['ip_burst']);
        if ($userId !== null && $userId > 0) {
            self::hitEndpointKey($config, self::endpointUserIdentifier($endpoint, $userId), $policy, $policy['user_burst']);
        }
    }

    private static function tooManyAttemptsInTable(array $config, string $table, string $ip, string $identifier, int $maxAttempts, int $windowMinutes): bool
    {
        $maxAttempts = max(1, $maxAttempts);
        $windowMinutes = max(1, $windowMinutes);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT attempts, last_attempt_at, lock_until FROM ' . $table . ' WHERE ip = ? AND identifier = ? LIMIT 1');
        $stmt->execute([$ip, $identifier]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $now = time();
        if (!empty($row['lock_until']) && strtotime($row['lock_until']) > $now) {
            return true;
        }
        $last = strtotime((string)$row['last_attempt_at']);
        if (!$last || $last < ($now - ($windowMinutes * 60))) {
            return false;
        }
        return (int)$row['attempts'] >= $maxAttempts;
    }

    private static function hitInTable(array $config, string $table, string $ip, string $identifier, int $maxAttempts, int $windowMinutes, int $lockMinutes): void
    {
        $maxAttempts = max(1, $maxAttempts);
        $windowMinutes = max(1, $windowMinutes);
        $lockMinutes = max(1, $lockMinutes);

        $pdo = Database::pdo();
        $sql = 'INSERT INTO ' . $table . ' (ip, identifier, attempts, last_attempt_at, lock_until)
            VALUES (?, ?, 1, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                attempts = IF(last_attempt_at < (NOW() - INTERVAL ? MINUTE), 1, attempts + 1),
                lock_until = IF(
                    IF(last_attempt_at < (NOW() - INTERVAL ? MINUTE), 1, attempts + 1) >= ?,
                    DATE_ADD(NOW(), INTERVAL ? MINUTE),
                    NULL
                ),
                last_attempt_at = NOW()';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ip, $identifier, $windowMinutes, $windowMinutes, $maxAttempts, $lockMinutes]);
    }

    private static function cleanupLogin(array $config): void
    {
        self::cleanupTable($config, self::loginTable($config), 'login', self::LOGIN_TTL_HOURS);
    }

    private static function cleanupReaction(array $config): void
    {
        $reactionCfg = $config['reactions']['rate_limit'] ?? [];
        $ttl = (int)($reactionCfg['ttl_hours'] ?? self::REACTION_TTL_HOURS);
        $ttl = max(1, min(168, $ttl));
        self::cleanupTable($config, self::reactionTable($config), 'reaction', $ttl);
    }

    private static function cleanupEndpoint(array $config): void
    {
        $cfg = $config['rate_limits']['endpoints'] ?? [];
        $ttl = (int)($cfg['ttl_hours'] ?? self::ENDPOINT_TTL_HOURS);
        $ttl = max(1, min(168, $ttl));
        self::cleanupTable($config, self::endpointTable($config), 'endpoint', $ttl);
    }

    private static function cleanupTable(array $config, string $table, string $scope, int $ttlHours): void
    {
        $now = time();
        $last = self::$lastCleanupAt[$scope] ?? 0;
        if (($now - $last) < 30) {
            return;
        }
        self::$lastCleanupAt[$scope] = $now;

        $ttlHours = max(1, $ttlHours);
        $pdo = Database::pdo();
        $sql = 'DELETE FROM ' . $table . '
            WHERE (
                lock_until IS NULL
                AND last_attempt_at < (NOW() - INTERVAL ? HOUR)
            ) OR (
                lock_until IS NOT NULL
                AND lock_until < (NOW() - INTERVAL ? HOUR)
            )
            LIMIT 500';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ttlHours, $ttlHours]);
    }

    private static function ensureReactionTable(array $config): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $pdo = Database::pdo();
        $table = self::reactionTable($config);
        $sql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip` VARCHAR(45) NOT NULL,
            `identifier` VARCHAR(190) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_attempt_at` DATETIME NOT NULL,
            `lock_until` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ip_identifier` (`ip`, `identifier`),
            KEY `idx_last_attempt` (`last_attempt_at`),
            KEY `idx_lock_until` (`lock_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $pdo->exec($sql);
        $ready = true;
    }

    private static function ensureLoginTable(array $config): void
    {
        if (self::$loginReady) {
            return;
        }
        $pdo = Database::pdo();
        $table = self::loginTable($config);
        $createSql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip` VARCHAR(45) NOT NULL,
            `identifier` VARCHAR(190) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_attempt_at` DATETIME NOT NULL,
            `lock_until` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_ip_identifier` (`ip`, `identifier`),
            KEY `idx_last_attempt` (`last_attempt_at`),
            KEY `idx_lock_until` (`lock_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $pdo->exec($createSql);

        try {
            $pdo->exec('DELETE t1 FROM `' . $table . '` t1
                INNER JOIN `' . $table . '` t2
                ON t1.ip = t2.ip AND t1.identifier = t2.identifier AND t1.id < t2.id');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD UNIQUE KEY `uniq_ip_identifier` (`ip`, `identifier`)');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD KEY `idx_last_attempt` (`last_attempt_at`)');
        } catch (\Throwable $e) {
            // ignore
        }
        try {
            $pdo->exec('ALTER TABLE `' . $table . '` ADD KEY `idx_lock_until` (`lock_until`)');
        } catch (\Throwable $e) {
            // ignore
        }
        self::$loginReady = true;
    }

    private static function ensureEndpointTable(array $config): void
    {
        if (self::$endpointReady) {
            return;
        }
        $pdo = Database::pdo();
        $table = self::endpointTable($config);
        $createSql = 'CREATE TABLE IF NOT EXISTS `' . $table . '` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `identifier` VARCHAR(255) NOT NULL,
            `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
            `penalty_level` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_attempt_at` DATETIME NOT NULL,
            `lock_until` DATETIME NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uniq_identifier` (`identifier`),
            KEY `idx_last_attempt` (`last_attempt_at`),
            KEY `idx_lock_until` (`lock_until`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $pdo->exec($createSql);
        self::$endpointReady = true;
    }

    private static function endpointPolicy(array $config, string $endpoint): array
    {
        $base = $config['rate_limits']['endpoints']['default'] ?? [];
        $custom = $config['rate_limits']['endpoints'][$endpoint] ?? [];
        $merged = array_merge($base, $custom);
        return [
            'ip_burst' => max(1, (int)($merged['ip_burst'] ?? 30)),
            'user_burst' => max(1, (int)($merged['user_burst'] ?? 20)),
            'window_seconds' => max(1, (int)($merged['window_seconds'] ?? 60)),
            'base_ban_seconds' => max(1, (int)($merged['base_ban_seconds'] ?? 30)),
            'max_ban_seconds' => max(5, (int)($merged['max_ban_seconds'] ?? 1800)),
            'max_penalty_level' => max(1, min(12, (int)($merged['max_penalty_level'] ?? 8))),
        ];
    }

    private static function tooManyEndpointKey(array $config, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $pdo = Database::pdo();
        $table = self::endpointTable($config);
        $stmt = $pdo->prepare('SELECT attempts, last_attempt_at, lock_until FROM ' . $table . ' WHERE identifier = ? LIMIT 1');
        $stmt->execute([$identifier]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $now = time();
        if (!empty($row['lock_until']) && strtotime($row['lock_until']) > $now) {
            return true;
        }
        $last = strtotime((string)$row['last_attempt_at']);
        if (!$last || $last < ($now - $windowSeconds)) {
            return false;
        }
        return (int)$row['attempts'] >= $maxAttempts;
    }

    private static function hitEndpointKey(array $config, string $identifier, array $policy, int $maxAttempts): void
    {
        $maxAttempts = max(1, $maxAttempts);
        $table = self::endpointTable($config);
        $pdo = Database::pdo();
        $sql = 'INSERT INTO ' . $table . ' (identifier, attempts, penalty_level, last_attempt_at, lock_until)
            VALUES (?, 1, 0, NOW(), NULL)
            ON DUPLICATE KEY UPDATE
                attempts = IF(last_attempt_at < (NOW() - INTERVAL ? SECOND), 1, attempts + 1),
                penalty_level = IF(
                    IF(last_attempt_at < (NOW() - INTERVAL ? SECOND), 1, attempts + 1) >= ?,
                    LEAST(penalty_level + 1, ?),
                    GREATEST(penalty_level - 1, 0)
                ),
                lock_until = IF(
                    IF(last_attempt_at < (NOW() - INTERVAL ? SECOND), 1, attempts + 1) >= ?,
                    DATE_ADD(
                        NOW(),
                        INTERVAL LEAST(
                            ?,
                            (? * POW(2, GREATEST(LEAST(penalty_level + 1, ?), 1) - 1))
                        ) SECOND
                    ),
                    NULL
                ),
                last_attempt_at = NOW()';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $identifier,
            $policy['window_seconds'],
            $policy['window_seconds'],
            $maxAttempts,
            $policy['max_penalty_level'],
            $policy['window_seconds'],
            $maxAttempts,
            $policy['max_ban_seconds'],
            $policy['base_ban_seconds'],
            $policy['max_penalty_level'],
        ]);
    }

    private static function endpointIpIdentifier(string $endpoint, string $ip): string
    {
        return 'endpoint:ip:' . $endpoint . ':' . $ip;
    }

    private static function endpointUserIdentifier(string $endpoint, int $userId): string
    {
        return 'endpoint:user:' . $endpoint . ':' . $userId;
    }

    private static function loginTable(array $config): string
    {
        return $config['db']['prefix'] . 'login_attempts';
    }

    private static function reactionTable(array $config): string
    {
        return $config['db']['prefix'] . 'reaction_rate_limits';
    }

    private static function endpointTable(array $config): string
    {
        return $config['db']['prefix'] . 'endpoint_rate_limits';
    }
}
