<?php
namespace App\Core;

use Throwable;

class Logger
{
    private static $levelMap = [
        'DEBUG' => 100,
        'INFO' => 200,
        'WARNING' => 300,
        'ERROR' => 400,
        'CRITICAL' => 500,
    ];
    private static $level = 200;
    private static $appFile = '';
    private static $errorFile = '';
    private static $maxBytes = 10485760;
    private static $maxFiles = 5;
    private static $initialized = false;
    private static $handling = false;

    public static function init(?array $config): void
    {
        if (self::$initialized) {
            return;
        }
        $logging = $config['logging'] ?? [];
        $level = strtoupper((string)($logging['level'] ?? 'INFO'));
        self::$level = self::$levelMap[$level] ?? self::$levelMap['INFO'];
        self::$appFile = (string)($logging['app_file'] ?? (BASE_PATH . '/storage/logs/app.log'));
        self::$errorFile = (string)($logging['error_file'] ?? (BASE_PATH . '/storage/logs/error.log'));
        $maxSizeMb = (int)($logging['max_size_mb'] ?? 10);
        self::$maxBytes = max(1, $maxSizeMb) * 1024 * 1024;
        self::$maxFiles = max(1, (int)($logging['max_files'] ?? 5));
        self::$initialized = true;
    }

    public static function debug(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('DEBUG', $message, $context, $channel);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('INFO', $message, $context, $channel);
    }

    public static function warn(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('WARNING', $message, $context, $channel);
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('ERROR', $message, $context, $channel, true);
    }

    public static function critical(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('CRITICAL', $message, $context, $channel, true);
    }

    public static function installErrorHandlers(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return false;
            }
            $level = in_array($errno, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)
                ? 'ERROR'
                : 'WARNING';
            $context = [
                'code' => $errno,
                'at' => basename($errfile) . ':' . $errline,
                'trace' => self::traceSummary(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6)),
            ];
            self::log($level, $errstr, $context, 'error', true);
            return true;
        });

        set_exception_handler(function (Throwable $e) {
            $context = [
                'ex' => get_class($e),
                'at' => basename($e->getFile()) . ':' . $e->getLine(),
                'trace' => self::traceSummary($e->getTrace()),
            ];
            self::log('CRITICAL', $e->getMessage(), $context, 'error', true);

            if (LogContext::isApi() && !headers_sent()) {
                Response::json(['ok' => false, 'error' => 'خطای سرور.'], 500);
            } elseif (!headers_sent()) {
                http_response_code(500);
                echo 'خطای سرور';
            }
        });

        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $context = [
                    'code' => $error['type'],
                    'at' => basename($error['file']) . ':' . $error['line'],
                ];
                self::log('CRITICAL', $error['message'], $context, 'error', true);
            }

            if (LogContext::isApi() && !LogContext::isResponseLogged()) {
                self::info('request_end', [
                    'status' => http_response_code(),
                    'dur_ms' => LogContext::getDurationMs(),
                ], 'api');
            }
        });
    }

    private static function traceSummary(array $trace): string
    {
        if (empty($trace)) {
            return '-';
        }
        $parts = [];
        foreach ($trace as $frame) {
            $name = '';
            if (!empty($frame['class'])) {
                $name .= $frame['class'];
                $name .= $frame['type'] ?? '::';
            }
            $name .= $frame['function'] ?? '';
            if ($name === '') {
                $file = $frame['file'] ?? '';
                $name = $file !== '' ? basename($file) : 'unknown';
            }
            $parts[] = $name;
            if (count($parts) >= 6) {
                break;
            }
        }
        return implode('>', $parts);
    }

    private static function log(string $levelName, string $message, array $context, string $channel, bool $forceErrorLog = false): void
    {
        if (!self::$initialized || self::$handling) {
            return;
        }
        $levelValue = self::$levelMap[$levelName] ?? self::$levelMap['INFO'];
        $shouldWriteApp = $levelValue >= self::$level;
        $shouldWriteError = $forceErrorLog || $levelValue >= self::$levelMap['ERROR'];
        if (!$shouldWriteApp && !$shouldWriteError) {
            return;
        }

        self::$handling = true;
        try {
            $line = self::formatLine($levelName, $channel, $message, $context);
            if ($shouldWriteApp && self::$appFile) {
                LogRotator::append(self::$appFile, $line, self::$maxBytes, self::$maxFiles);
            }
            if ($shouldWriteError && self::$errorFile) {
                LogRotator::append(self::$errorFile, $line, self::$maxBytes, self::$maxFiles);
            }
        } catch (Throwable $e) {
            // ignore logging failures
        }
        self::$handling = false;
    }

    private static function formatLine(string $level, string $channel, string $message, array $context): string
    {
        $status = $context['status'] ?? null;
        $durMs = $context['dur_ms'] ?? null;
        unset($context['status'], $context['dur_ms']);

        $timestamp = date('Y-m-d H:i:s');
        $rid = LogContext::getRequestId();
        $uid = LogContext::getUserId();
        $ip = LogContext::getIp();
        $method = LogContext::getMethod();
        $path = LogContext::getPath();

        $parts = [
            '[' . $timestamp . ']',
            'LEVEL=' . $level,
            'CHANNEL=' . $channel,
            'RID=' . ($rid ?: '-'),
            'UID=' . ($uid !== null ? $uid : '-'),
            'IP=' . ($ip ?: '-'),
        ];
        if ($method) {
            $parts[] = 'METHOD=' . $method;
        }
        if ($path) {
            $parts[] = 'PATH=' . $path;
        }
        if ($status !== null) {
            $parts[] = 'STATUS=' . (int)$status;
        }
        if ($durMs !== null) {
            $parts[] = 'DUR_MS=' . (int)$durMs;
        }

        $msg = LogSanitizer::escape(LogSanitizer::sanitizeMessage($message));
        $parts[] = 'MSG="' . $msg . '"';

        $ctxPairs = [];
        $cleanContext = LogSanitizer::sanitizeContext($context);
        foreach ($cleanContext as $key => $value) {
            $ctxPairs[] = $key . '=' . $value;
        }
        $ctx = $ctxPairs ? implode(' ', $ctxPairs) : '-';
        $ctx = LogSanitizer::escape(LogSanitizer::sanitizeMessage($ctx));
        $parts[] = 'CTX="' . $ctx . '"';

        return implode(' ', $parts);
    }
}
