<?php
namespace App\Core;

class Request
{
    public static function json(): array
    {
        $raw = file_get_contents('php://input');
        $trimmed = trim((string)$raw);
        if ($trimmed === '') {
            return [];
        }
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (LogContext::isApi()) {
                Response::json(['ok' => false, 'error' => 'بدنه JSON نامعتبر است.'], 400);
            }
            return [];
        }
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

        $rawKey = strtoupper(str_replace('-', '_', $key));
        $serverKey = 'HTTP_' . $rawKey;
        $candidates = [
            $_SERVER[$serverKey] ?? null,
            $_SERVER['REDIRECT_' . $serverKey] ?? null,
            $_SERVER['REDIRECT_REDIRECT_' . $serverKey] ?? null,
            $_SERVER[$rawKey] ?? null,
            $_SERVER['REDIRECT_' . $rawKey] ?? null,
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
