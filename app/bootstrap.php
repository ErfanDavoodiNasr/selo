<?php
declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    echo 'SELO requires PHP 8.2 or newer.';
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

$configFile = BASE_PATH . '/config/config.php';
$config = is_file($configFile) ? require $configFile : null;
if ($config !== null && !is_array($config)) {
    $config = null;
}

if ($config && isset($config['app']['timezone']) && is_string($config['app']['timezone'])) {
    @date_default_timezone_set($config['app']['timezone']);
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', '/', $relativeClass);
    $file = $baseDir . $relativePath . '.php';
    if (!is_file($file)) {
        $parts = explode('/', $relativePath);
        if (count($parts) > 1) {
            $parts[0] = strtolower($parts[0]);
            $altFile = $baseDir . implode('/', $parts) . '.php';
            if (is_file($altFile)) {
                $file = $altFile;
            }
        }
    }
    if (is_file($file)) {
        require $file;
    }
});

App\Core\LogContext::initFromGlobals(false);
App\Core\Filesystem::init($config ?? []);
App\Core\Logger::init($config ?? []);
App\Core\Logger::installErrorHandlers();

if ($config && !defined('SELO_SKIP_DB_BOOTSTRAP')) {
    App\Core\Database::init($config);
}
