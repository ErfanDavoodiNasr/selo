<?php
declare(strict_types=1);

/**
 * One-time permission fixer for shared hosting without SSH.
 *
 * Usage:
 * 1) Create file: /storage/.permfix.key (any strong random string)
 * 2) Open: /fix-permissions.php?key=YOUR_KEY
 * 3) Delete /storage/.permfix.key after success
 */

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

header('Content-Type: text/plain; charset=UTF-8');

$keyFile = BASE_PATH . '/storage/.permfix.key';
$provided = trim((string)($_GET['key'] ?? ''));

if (!is_file($keyFile)) {
    http_response_code(403);
    echo "Access denied.\nCreate storage/.permfix.key first.\n";
    exit;
}

$expected = trim((string)@file_get_contents($keyFile));
if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(403);
    echo "Access denied.\n";
    exit;
}

$root = BASE_PATH;
$dirMode = 0755;
$fileMode = 0644;
$runtimeDirMode = 0775;
$runtimeFileMode = 0664;

$runtimeDirs = [
    $root . '/config',
    $root . '/storage',
    $root . '/storage/logs',
    $root . '/storage/uploads',
    $root . '/storage/uploads/media',
];

$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$ok = 0;
$fail = 0;

foreach ($rii as $item) {
    $path = $item->getPathname();

    // Skip VCS and bulky vendor-like dirs if present.
    if (strpos($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) !== false) {
        continue;
    }

    if ($item->isDir()) {
        if (@chmod($path, $dirMode)) {
            $ok++;
        } else {
            $fail++;
        }
    } elseif ($item->isFile()) {
        if (@chmod($path, $fileMode)) {
            $ok++;
        } else {
            $fail++;
        }
    }
}

foreach ($runtimeDirs as $dir) {
    if (!is_dir($dir) && !@mkdir($dir, $runtimeDirMode, true) && !is_dir($dir)) {
        $fail++;
        continue;
    }
    if (@chmod($dir, $runtimeDirMode)) {
        $ok++;
    } else {
        $fail++;
    }
}

$configFile = $root . '/config/config.php';
if (is_file($configFile)) {
    if (@chmod($configFile, $runtimeFileMode)) {
        $ok++;
    } else {
        $fail++;
    }
}

echo "Permission fix completed.\n";
echo "Updated: {$ok}\n";
echo "Failed: {$fail}\n";
echo "Now delete storage/.permfix.key\n";

