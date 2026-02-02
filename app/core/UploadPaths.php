<?php
namespace App\Core;

class UploadPaths
{
    public static function baseDir(array $config): string
    {
        $dir = $config['uploads']['dir'] ?? '';
        $fallback = self::joinBase('storage/uploads');
        return self::normalize($dir, $fallback);
    }

    public static function mediaDir(array $config): string
    {
        $dir = $config['uploads']['media_dir'] ?? '';
        if ($dir === '' || $dir === null) {
            $dir = rtrim(self::baseDir($config), '/') . '/media';
        }
        $fallback = self::joinBase('storage/uploads/media');
        return self::normalize($dir, $fallback);
    }

    private static function normalize(string $path, string $fallback): string
    {
        $path = trim($path);
        if ($path === '') {
            $path = $fallback;
        }
        if (!self::isAbsolute($path)) {
            $path = rtrim(self::basePath(), '/') . '/' . ltrim($path, '/');
        }
        return $path;
    }

    private static function basePath(): string
    {
        if (defined('BASE_PATH')) {
            return BASE_PATH;
        }
        return dirname(__DIR__, 2);
    }

    private static function joinBase(string $relative): string
    {
        return rtrim(self::basePath(), '/') . '/' . ltrim($relative, '/');
    }

    private static function isAbsolute(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if ($path[0] === '/' || $path[0] === '\\') {
            return true;
        }
        return (bool)preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
