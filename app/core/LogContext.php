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
        self::$ip = self::resolveClientIp();
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

    private static function resolveClientIp(): string
    {
        $remoteAddr = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!self::isValidIp($remoteAddr)) {
            return '-';
        }

        // Only trust forwarding headers when request came from a trusted proxy.
        if (!self::isTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        $xff = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $parts = explode(',', $xff);
            foreach ($parts as $part) {
                $candidate = trim($part);
                if (self::isValidIp($candidate)) {
                    return $candidate;
                }
            }
        }

        $xRealIp = trim((string)($_SERVER['HTTP_X_REAL_IP'] ?? ''));
        if (self::isValidIp($xRealIp)) {
            return $xRealIp;
        }

        return $remoteAddr;
    }

    private static function isTrustedProxy(string $ip): bool
    {
        $raw = trim((string)getenv('SELO_TRUSTED_PROXIES'));
        if ($raw === '') {
            return false;
        }
        $proxies = array_filter(array_map('trim', explode(',', $raw)));
        return in_array($ip, $proxies, true);
    }

    private static function isValidIp(string $ip): bool
    {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
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
