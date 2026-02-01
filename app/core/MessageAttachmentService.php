<?php
namespace App\Core;

class MessageAttachmentService
{
    public static function hydrate(array $config, array $messages): array
    {
        if (empty($messages)) {
            return $messages;
        }

        $ids = [];
        foreach ($messages as $msg) {
            $ids[] = (int)$msg['id'];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo = Database::pdo();

        $sql = 'SELECT ma.message_id, ma.sort_order,
                mf.id, mf.type, mf.file_name, mf.original_name, mf.mime_type, mf.size_bytes, mf.duration, mf.width, mf.height, mf.thumbnail_name
            FROM ' . $config['db']['prefix'] . 'message_attachments ma
            JOIN ' . $config['db']['prefix'] . 'media_files mf ON mf.id = ma.media_id
            WHERE ma.message_id IN (' . $placeholders . ')
            ORDER BY ma.message_id ASC, ma.sort_order ASC, ma.id ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $map = [];
        foreach ($rows as $row) {
            $mid = (int)$row['message_id'];
            if (!isset($map[$mid])) {
                $map[$mid] = [];
            }
            $map[$mid][] = [
                'id' => (int)$row['id'],
                'type' => $row['type'],
                'file_name' => $row['file_name'],
                'original_name' => $row['original_name'],
                'mime_type' => $row['mime_type'],
                'size_bytes' => (int)$row['size_bytes'],
                'duration' => $row['duration'] !== null ? (int)$row['duration'] : null,
                'width' => $row['width'] !== null ? (int)$row['width'] : null,
                'height' => $row['height'] !== null ? (int)$row['height'] : null,
                'thumbnail_name' => $row['thumbnail_name'],
            ];
        }

        foreach ($messages as &$msg) {
            $mid = (int)$msg['id'];
            $attachments = $map[$mid] ?? [];

            // Backfill legacy single media into attachments if needed.
            if (empty($attachments) && !empty($msg['media'])) {
                $media = $msg['media'];
                $attachments = [[
                    'id' => (int)$media['id'],
                    'type' => $media['type'] ?? $msg['type'] ?? 'file',
                    'file_name' => $media['file_name'] ?? null,
                    'original_name' => $media['original_name'] ?? null,
                    'mime_type' => $media['mime_type'] ?? null,
                    'size_bytes' => isset($media['size_bytes']) ? (int)$media['size_bytes'] : null,
                    'duration' => isset($media['duration']) ? (int)$media['duration'] : null,
                    'width' => isset($media['width']) ? (int)$media['width'] : null,
                    'height' => isset($media['height']) ? (int)$media['height'] : null,
                    'thumbnail_name' => $media['thumbnail_name'] ?? null,
                ]];
            }

            $msg['attachments'] = $attachments;
            $msg['attachments_count'] = count($attachments);
        }
        unset($msg);

        return $messages;
    }
}
