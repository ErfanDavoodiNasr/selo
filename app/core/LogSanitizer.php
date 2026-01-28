<?php
namespace App\Core;

class LogSanitizer
{
    private const SENSITIVE_KEY_PATTERN = '/(pass(word)?|pwd|secret|token|authorization|cookie|jwt|db|database|sdp|ice|candidate)/i';
    private const SDP_PATTERN = '/(a=candidate|candidate:|ice-ufrag|ice-pwd|m=audio|v=0|sdp)/i';
    private const JWT_PATTERN = '/[A-Za-z0-9_-]+\\.[A-Za-z0-9_-]+\\.[A-Za-z0-9_-]+/';

    public static function sanitizeMessage(string $message): string
    {
        return self::sanitizeString($message);
    }

    public static function sanitizeContext(array $context): array
    {
        $flat = self::flatten($context);
        $clean = [];
        foreach ($flat as $key => $value) {
            $safeKey = self::sanitizeKey($key);
            if (self::isSensitiveKey($safeKey)) {
                $clean[$safeKey] = '***';
                continue;
            }
            $stringValue = self::stringify($value);
            if (self::isSensitiveValue($stringValue)) {
                $clean[$safeKey] = '***';
                continue;
            }
            $clean[$safeKey] = self::sanitizeString($stringValue);
        }
        return $clean;
    }

    public static function escape(string $value): string
    {
        return str_replace('"', '\\"', $value);
    }

    private static function flatten(array $context, string $prefix = ''): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            $key = is_int($key) ? (string)$key : (string)$key;
            $fullKey = $prefix === '' ? $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += self::flatten($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    private static function sanitizeKey(string $key): string
    {
        $key = preg_replace('/\\s+/', '_', $key);
        $key = preg_replace('/[^A-Za-z0-9_.-]/', '_', $key);
        return $key ?: 'key';
    }

    private static function sanitizeString(string $value): string
    {
        $value = str_replace(["\r", "\n", "\t"], ' ', $value);
        $value = preg_replace('/[\\x00-\\x1F\\x7F]/u', ' ', $value);
        $value = preg_replace('/\\s+/', ' ', $value);
        return trim($value);
    }

    private static function stringify($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string)$value;
            }
            return get_class($value);
        }
        if (is_array($value)) {
            return 'array';
        }
        return (string)$value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        return (bool)preg_match(self::SENSITIVE_KEY_PATTERN, $key);
    }

    private static function isSensitiveValue(string $value): bool
    {
        if ($value === '') {
            return false;
        }
        if (preg_match('/Bearer\\s+[A-Za-z0-9\\-\\._~\\+\\/]+=*/i', $value)) {
            return true;
        }
        if (preg_match(self::JWT_PATTERN, $value)) {
            return true;
        }
        if (preg_match(self::SDP_PATTERN, $value)) {
            return true;
        }
        if (preg_match('/(BEGIN|END)\\s+PRIVATE\\s+KEY/i', $value)) {
            return true;
        }
        if (strlen($value) > 256 && preg_match('/^[A-Za-z0-9+\\/=_.-]+$/', $value)) {
            return true;
        }
        return false;
    }
}
