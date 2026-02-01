<?php
namespace App\Core;

class MessageMediaService
{
    public static function normalizeMediaIds($mediaIds, ?int $singleId = null): array
    {
        $ids = [];
        if (is_array($mediaIds)) {
            foreach ($mediaIds as $id) {
                $intId = (int)$id;
                if ($intId > 0) {
                    $ids[] = $intId;
                }
            }
        }
        if ($singleId !== null && $singleId > 0) {
            $ids[] = $singleId;
        }
        $ids = array_values(array_unique($ids));
        return $ids;
    }

    public static function loadUserMedia(array $config, int $userId, array $mediaIds): array
    {
        if (empty($mediaIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
        $params = $mediaIds;
        $params[] = $userId;

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, type FROM ' . $config['db']['prefix'] . 'media_files WHERE id IN (' . $placeholders . ') AND user_id = ?');
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = $row['type'];
        }
        return $map;
    }
}
