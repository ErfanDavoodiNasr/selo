<?php
namespace App\Core;

class LogRotator
{
    public static function append(string $path, string $line, int $maxBytes, int $maxFiles): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $lockPath = $path . '.lock';
        $lock = @fopen($lockPath, 'c');
        if (!$lock) {
            return;
        }

        if (!flock($lock, LOCK_EX)) {
            fclose($lock);
            return;
        }

        self::rotateIfNeeded($path, $maxBytes, $maxFiles);

        $fh = @fopen($path, 'a');
        if ($fh) {
            if (flock($fh, LOCK_EX)) {
                fwrite($fh, $line . PHP_EOL);
                fflush($fh);
                flock($fh, LOCK_UN);
            }
            fclose($fh);
        }

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    private static function rotateIfNeeded(string $path, int $maxBytes, int $maxFiles): void
    {
        if ($maxBytes <= 0 || $maxFiles <= 0) {
            return;
        }
        if (!file_exists($path)) {
            return;
        }
        $size = @filesize($path);
        if ($size === false || $size < $maxBytes) {
            return;
        }

        $maxFiles = max(1, $maxFiles);
        $last = $path . '.' . $maxFiles;
        if (file_exists($last)) {
            @unlink($last);
        }
        for ($i = $maxFiles - 1; $i >= 1; $i--) {
            $src = $path . '.' . $i;
            if (file_exists($src)) {
                $dest = $path . '.' . ($i + 1);
                @rename($src, $dest);
            }
        }
        @rename($path, $path . '.1');
    }
}
