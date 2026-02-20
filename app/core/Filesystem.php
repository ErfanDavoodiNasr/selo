<?php
namespace App\Core;

class Filesystem
{
    private static $config = [
        'dir_mode' => 0775,
        'file_mode' => 0664,
        'umask' => 0002,
    ];
    private static $initialized = false;

    public static function init(?array $config): void
    {
        if (self::$initialized) {
            return;
        }
        $fs = $config['filesystem'] ?? [];
        self::$config['dir_mode'] = self::normalizeMode($fs['dir_mode'] ?? 0775, 0775);
        self::$config['file_mode'] = self::normalizeMode($fs['file_mode'] ?? 0664, 0664);
        self::$config['umask'] = self::normalizeMode($fs['umask'] ?? 0002, 0002);
        @umask(self::$config['umask']);
        self::$initialized = true;
    }

    public static function ensureDir(string $path): bool
    {
        if ($path === '') {
            return false;
        }
        if (!is_dir($path) && !@mkdir($path, self::$config['dir_mode'], true) && !is_dir($path)) {
            return false;
        }
        @chmod($path, self::$config['dir_mode']);
        return is_dir($path) && is_writable($path);
    }

    public static function ensureWritableFile(string $path): void
    {
        if ($path === '' || !file_exists($path)) {
            return;
        }
        @chmod($path, self::$config['file_mode']);
    }

    private static function normalizeMode($value, int $fallback): int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $fallback;
            }
            if (preg_match('/^[0-7]{3,4}$/', $trimmed)) {
                return intval($trimmed, 8);
            }
            if (is_numeric($trimmed)) {
                return (int)$trimmed;
            }
        }
        return $fallback;
    }
}
