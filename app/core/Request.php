<?php
namespace App\Core;

class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }

    public static function param(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $key): ?string
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $lookup = strtolower($key);
        foreach ($headers as $name => $value) {
            if (strtolower((string)$name) === $lookup) {
                return is_string($value) ? $value : null;
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        $candidates = [
            $_SERVER[$serverKey] ?? null,
            $_SERVER['REDIRECT_' . $serverKey] ?? null,
        ];
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }
        return null;
    }

    public static function method(): string
    {
        return strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function contentLength(): int
    {
        return max(0, (int)($_SERVER['CONTENT_LENGTH'] ?? 0));
    }
}
