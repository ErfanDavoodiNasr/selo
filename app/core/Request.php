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
        return $headers[$key] ?? $headers[strtolower($key)] ?? null;
    }
}
