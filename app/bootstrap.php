<?php
// Bootstrap for SELO (سلو)

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

mb_internal_encoding('UTF-8');

$configFile = BASE_PATH . '/config/config.php';
$config = file_exists($configFile) ? require $configFile : null;

if ($config && isset($config['app']['timezone'])) {
    date_default_timezone_set($config['app']['timezone']);
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
    if (!file_exists($file)) {
        $parts = explode('/', $relativePath);
        if (count($parts) > 1) {
            $parts[0] = strtolower($parts[0]);
            $altFile = $baseDir . implode('/', $parts) . '.php';
            if (file_exists($altFile)) {
                $file = $altFile;
            }
        }
    }
    if (file_exists($file)) {
        require $file;
    }
});

App\Core\LogContext::initFromGlobals(false);
App\Core\Logger::init($config ?? []);
App\Core\Logger::installErrorHandlers();

if ($config) {
    App\Core\Database::init($config);
}
