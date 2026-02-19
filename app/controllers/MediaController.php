<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\UploadPaths;

class MediaController
{
    public static function serve(array $config, int $mediaId): void
    {
        if ($mediaId <= 0) {
            http_response_code(404);
            exit;
        }
        $user = Auth::user($config);
        if (!$user && isset($_COOKIE['selo_token']) && is_string($_COOKIE['selo_token'])) {
            $user = Auth::userFromToken($config, $_COOKIE['selo_token']);
        }
        if (!$user) {
            \App\Core\Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }
        $download = isset($_GET['download']) && $_GET['download'] === '1';

        $pdo = Database::pdo();
        $sql = 'SELECT mf.id, mf.file_name, mf.original_name, mf.mime_type, mf.type, mf.thumbnail_name
                FROM ' . $config['db']['prefix'] . 'media_files mf
                WHERE mf.id = ?
                  AND (
                    EXISTS (
                      SELECT 1
                      FROM ' . $config['db']['prefix'] . 'messages m
                      JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
                      WHERE m.media_id = mf.id
                        AND m.conversation_id IS NOT NULL
                        AND m.is_deleted_for_all = 0
                        AND (c.user_one_id = ? OR c.user_two_id = ?)
                        AND NOT EXISTS (
                          SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                          WHERE md.message_id = m.id AND md.user_id = ?
                        )
                    )
                    OR EXISTS (
                      SELECT 1
                      FROM ' . $config['db']['prefix'] . 'messages m
                      JOIN ' . $config['db']['prefix'] . 'group_members gm
                        ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
                      WHERE m.media_id = mf.id
                        AND m.group_id IS NOT NULL
                        AND m.is_deleted_for_all = 0
                        AND NOT EXISTS (
                          SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                          WHERE md.message_id = m.id AND md.user_id = ?
                        )
                    )
                    OR EXISTS (
                      SELECT 1
                      FROM ' . $config['db']['prefix'] . 'message_attachments ma
                      JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = ma.message_id
                      JOIN ' . $config['db']['prefix'] . 'conversations c ON c.id = m.conversation_id
                      WHERE ma.media_id = mf.id
                        AND m.conversation_id IS NOT NULL
                        AND m.is_deleted_for_all = 0
                        AND (c.user_one_id = ? OR c.user_two_id = ?)
                        AND NOT EXISTS (
                          SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                          WHERE md.message_id = m.id AND md.user_id = ?
                        )
                    )
                    OR EXISTS (
                      SELECT 1
                      FROM ' . $config['db']['prefix'] . 'message_attachments ma
                      JOIN ' . $config['db']['prefix'] . 'messages m ON m.id = ma.message_id
                      JOIN ' . $config['db']['prefix'] . 'group_members gm
                        ON gm.group_id = m.group_id AND gm.user_id = ? AND gm.status = ?
                      WHERE ma.media_id = mf.id
                        AND m.group_id IS NOT NULL
                        AND m.is_deleted_for_all = 0
                        AND NOT EXISTS (
                          SELECT 1 FROM ' . $config['db']['prefix'] . 'message_deletions md
                          WHERE md.message_id = m.id AND md.user_id = ?
                        )
                    )
                  )
                LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $mediaId,
            $user['id'], $user['id'], $user['id'],
            $user['id'], 'active', $user['id'],
            $user['id'], $user['id'], $user['id'],
            $user['id'], 'active', $user['id'],
        ]);
        $media = $stmt->fetch();
        if (!$media) {
            $existsStmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'media_files WHERE id = ? LIMIT 1');
            $existsStmt->execute([$mediaId]);
            if ($existsStmt->fetch()) {
                \App\Core\Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
            }
            http_response_code(404);
            exit;
        }

        $mediaDir = UploadPaths::mediaDir($config);
        $thumb = isset($_GET['thumb']) && $_GET['thumb'] === '1';
        $fileName = $media['file_name'];
        $path = rtrim($mediaDir, '/') . '/' . $fileName;
        $isThumb = false;
        if ($thumb && !empty($media['thumbnail_name'])) {
            $thumbPath = rtrim($mediaDir, '/') . '/' . $media['thumbnail_name'];
            if (is_file($thumbPath)) {
                $path = $thumbPath;
                $fileName = $media['thumbnail_name'];
                $isThumb = true;
            }
        }
        if (!is_file($path)) {
            http_response_code(404);
            exit;
        }

        $mime = $media['mime_type'] ?: 'application/octet-stream';
        if ($media['type'] === 'voice' && $mime === 'video/webm') {
            $mime = 'audio/webm';
        }
        if ($thumb || $mime === '' || $mime === 'application/octet-stream') {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $thumbMime = $finfo->file($path);
            if ($thumbMime) {
                $mime = $thumbMime;
            }
        }
        $size = filesize($path);
        $disposition = ($download || $media['type'] === 'file') ? 'attachment' : 'inline';
        if ($thumb) {
            $disposition = 'inline';
        }

        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Accept-Ranges: bytes');
        header('Cache-Control: private, max-age=604800');
        header('Content-Disposition: ' . self::buildContentDisposition($disposition, (string)($media['original_name'] ?? ''), (string)($media['file_name'] ?? ''), $mime));

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

    private static function buildContentDisposition(string $disposition, string $originalName, string $storedName, string $mime): string
    {
        $name = $originalName !== '' ? $originalName : 'file';
        $name = self::ensureExtension($name, $storedName, $mime);
        $ascii = self::safeFilename($name);
        $header = $disposition . '; filename="' . $ascii . '"';
        if ($name !== $ascii) {
            $header .= "; filename*=UTF-8''" . rawurlencode($name);
        }
        return $header;
    }

    private static function ensureExtension(string $name, string $storedName, string $mime): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if ($ext !== '') {
            return $name;
        }
        $ext = pathinfo($storedName, PATHINFO_EXTENSION);
        if ($ext === '') {
            $ext = self::extensionFromMime($mime);
        }
        if ($ext !== '') {
            $name .= '.' . $ext;
        }
        return $name;
    }

    private static function extensionFromMime(string $mime): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/mp4' => 'm4a',
            'audio/aac' => 'aac',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogv',
            'video/quicktime' => 'mov',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ];
        return $map[$mime] ?? '';
    }
}
