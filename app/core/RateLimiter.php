<?php
namespace App\Core;

class RateLimiter
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_MINUTES = 15;
    private const LOCK_MINUTES = 15;

    public static function tooManyAttempts(string $ip, string $identifier, array $config): bool
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT attempts, last_attempt_at, lock_until FROM ' . $config['db']['prefix'] . 'login_attempts WHERE ip = ? AND identifier = ? LIMIT 1');
        $stmt->execute([$ip, $identifier]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        if ($row['lock_until'] && strtotime($row['lock_until']) > time()) {
            return true;
        }
        return false;
    }

    public static function hit(string $ip, string $identifier, array $config): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, attempts, last_attempt_at FROM ' . $config['db']['prefix'] . 'login_attempts WHERE ip = ? AND identifier = ? LIMIT 1');
        $stmt->execute([$ip, $identifier]);
        $row = $stmt->fetch();
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);
        if (!$row) {
            $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'login_attempts (ip, identifier, attempts, last_attempt_at, lock_until) VALUES (?, ?, ?, ?, NULL)');
            $insert->execute([$ip, $identifier, 1, $nowStr]);
            return;
        }
        $last = strtotime($row['last_attempt_at']);
        $attempts = $row['attempts'];
        if ($last < ($now - self::WINDOW_MINUTES * 60)) {
            $attempts = 1;
        } else {
            $attempts++;
        }
        $lockUntil = null;
        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', $now + self::LOCK_MINUTES * 60);
        }
        $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'login_attempts SET attempts = ?, last_attempt_at = ?, lock_until = ? WHERE id = ?');
        $update->execute([$attempts, $nowStr, $lockUntil, $row['id']]);
    }

    public static function clear(string $ip, string $identifier, array $config): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('DELETE FROM ' . $config['db']['prefix'] . 'login_attempts WHERE ip = ? AND identifier = ?');
        $stmt->execute([$ip, $identifier]);
    }
}
