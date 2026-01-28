<?php
namespace App\Core;

class LogContext
{
    private static $requestId = '-';
    private static $startTime = 0.0;
    private static $method = null;
    private static $path = null;
    private static $ip = null;
    private static $userId = null;
    private static $isApi = false;
    private static $responseLogged = false;

    public static function initFromGlobals(bool $isApi): void
    {
        self::$isApi = $isApi;
        self::$startTime = microtime(true);
        self::$requestId = bin2hex(random_bytes(8));
        self::$method = $_SERVER['REQUEST_METHOD'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        self::$path = $uri !== '' ? (parse_url($uri, PHP_URL_PATH) ?: $uri) : null;
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '-');
        if (strpos($ip, ',') !== false) {
            $parts = explode(',', $ip);
            $ip = trim($parts[0]);
        }
        self::$ip = $ip ?: '-';
    }

    public static function setIsApi(bool $isApi): void
    {
        self::$isApi = $isApi;
    }

    public static function isApi(): bool
    {
        return self::$isApi;
    }

    public static function setUserId(?int $userId): void
    {
        self::$userId = $userId;
    }

    public static function getUserId(): ?int
    {
        return self::$userId;
    }

    public static function getRequestId(): string
    {
        return self::$requestId ?: '-';
    }

    public static function getStartTime(): float
    {
        return self::$startTime;
    }

    public static function getDurationMs(): int
    {
        if (!self::$startTime) {
            return 0;
        }
        return (int)round((microtime(true) - self::$startTime) * 1000);
    }

    public static function getMethod(): ?string
    {
        return self::$method;
    }

    public static function getPath(): ?string
    {
        return self::$path;
    }

    public static function getIp(): ?string
    {
        return self::$ip;
    }

    public static function markResponseLogged(): void
    {
        self::$responseLogged = true;
    }

    public static function isResponseLogged(): bool
    {
        return self::$responseLogged;
    }
}
