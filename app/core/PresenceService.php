<?php
namespace App\Core;

class PresenceService
{
    private const DEFAULT_PING_INTERVAL = 15;
    private const DEFAULT_ONLINE_WINDOW = 60;

    public static function ping(array $config, int $userId): void
    {
        $interval = (int)($config['presence']['ping_interval_seconds'] ?? self::DEFAULT_PING_INTERVAL);
        $interval = max(5, min(300, $interval));

        $now = gmdate('Y-m-d H:i:s');
        $threshold = gmdate('Y-m-d H:i:s', time() - $interval);

        $pdo = Database::pdo();
        $sql = 'INSERT INTO ' . $config['db']['prefix'] . 'user_presence (user_id, last_ping_at)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE last_ping_at = IF(last_ping_at < ?, ?, last_ping_at)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $now, $threshold, $now]);
    }

    public static function onlineMap(array $config, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        $window = (int)($config['presence']['online_window_seconds'] ?? self::DEFAULT_ONLINE_WINDOW);
        $window = max(10, min(600, $window));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $window);

        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $params = $userIds;
        $params[] = $cutoff;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT user_id FROM ' . $config['db']['prefix'] . 'user_presence WHERE user_id IN (' . $placeholders . ') AND last_ping_at >= ?');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['user_id']] = true;
        }
        return $map;
    }
}
