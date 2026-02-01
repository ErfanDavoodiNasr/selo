<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;

class MediaController
{
    public static function serve(array $config, int $mediaId): void
    {
        $user = Auth::user($config);
        if (!$user && isset($_COOKIE['selo_token']) && is_string($_COOKIE['selo_token'])) {
            $user = Auth::userFromToken($config, $_COOKIE['selo_token']);
        }
        if (!$user) {
            \App\Core\Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }
        $download = isset($_GET['download']) && $_GET['download'] === '1';

        $pdo = Database::pdo();
        $sql = 'SELECT DISTINCT mf.id, mf.file_name, mf.original_name, mf.mime_type, mf.type, mf.thumbnail_name
                FROM ' . $config['db']['prefix'] . 'media_files mf
                LEFT JOIN ' . $config['db']['prefix'] . 'message_attachments ma ON ma.media_id = mf.id
                LEFT JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = ma.message_id OR m.media_id = mf.id
                LEFT JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
                LEFT JOIN ' . $config['db']['prefix'] . 'group_members gm ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
                WHERE mf.id = ?
                  AND m.id IS NOT NULL
                  AND (
                    (m.conversation_id IS NOT NULL AND (c.user_one_id = ? OR c.user_two_id = ?))
                    OR (m.group_id IS NOT NULL AND gm.user_id IS NOT NULL)
                  )
                  AND m.is_deleted_for_all = 0
                  AND NOT EXISTS (
                      SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                      WHERE md.message_id = m.id AND md.user_id = ?
                  )
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user['id'], 'active', $mediaId, $user['id'], $user['id'], $user['id']]);
        $media = $stmt->fetch();
        if (!$media) {
            http_response_code(404);
            exit;
        }

        $mediaDir = $config['uploads']['media_dir'] ?? null;
        if (!$mediaDir) {
            $baseDir = $config['uploads']['dir'] ?? (dirname(__DIR__, 2) . '/storage/uploads');
            $mediaDir = rtrim($baseDir, '/') . '/media';
        }
        $thumb = isset($_GET['thumb']) && $_GET['thumb'] === '1';
        $fileName = $media['file_name'];
        if ($thumb && !empty($media['thumbnail_name'])) {
            $fileName = $media['thumbnail_name'];
        }
        $path = rtrim($mediaDir, '/') . '/' . $fileName;
        if (!file_exists($path)) {
            http_response_code(404);
            exit;
        }

        $mime = $media['mime_type'] ?: 'application/octet-stream';
        if ($thumb) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $thumbMime = $finfo->file($path);
            if ($thumbMime) {
                $mime = $thumbMime;
            }
        }
        $size = filesize($path);
        $safeName = self::safeFilename($media['original_name']);
        $disposition = ($download || $media['type'] === 'file') ? 'attachment' : 'inline';
        if ($thumb) {
            $disposition = 'inline';
        }

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=604800');
        header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');

        $start = 0;
        $end = $size - 1;
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $matches)) {
            if ($matches[1] !== '') {
                $start = (int)$matches[1];
            }
            if ($matches[2] !== '') {
                $end = (int)$matches[2];
            }
            if ($start > $end || $start >= $size) {
                http_response_code(416);
                exit;
            }
            if ($end >= $size) {
                $end = $size - 1;
            }
            http_response_code(206);
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }

        $length = $end - $start + 1;
        header('Content-Length: ' . $length);

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            http_response_code(500);
            exit;
        }
        fseek($fp, $start);
        $chunkSize = 8192;
        $bytesSent = 0;
        while (!feof($fp) && $bytesSent < $length) {
            $read = $chunkSize;
            if ($bytesSent + $read > $length) {
                $read = $length - $bytesSent;
            }
            $buffer = fread($fp, $read);
            if ($buffer === false) {
                break;
            }
            echo $buffer;
            $bytesSent += strlen($buffer);
        }
        fclose($fp);
        exit;
    }

    private static function safeFilename(string $name): string
    {
        $name = str_replace(["\0", "\r", "\n"], '', $name);
        $name = trim($name);
        if ($name === '') {
            return 'file';
        }
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
        return $safe !== '' ? $safe : 'file';
    }
}
