<?php
namespace App\Core;

class Auth
{
    private static $user;

    public static function issueToken(array $user, array $config): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $config['app']['url'] ?? 'selo',
            'sub' => $user['id'],
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24 * 7), // 7 days
        ];
        $base64Header = Utils::base64UrlEncode(json_encode($header));
        $base64Payload = Utils::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $config['app']['jwt_secret'], true);
        $base64Signature = Utils::base64UrlEncode($signature);
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public static function user(array $config): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $token = self::getBearerToken();
        if (!$token) {
            return null;
        }
        $payload = self::decodeToken($token, $config['app']['jwt_secret']);
        if (!$payload) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, phone, bio, language, active_photo_id FROM ' . $config['db']['prefix'] . 'users WHERE id = ? LIMIT 1');
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();
        self::$user = $user ?: null;
        return self::$user;
    }

    public static function requireUser(array $config): array
    {
        $user = self::user($config);
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }
        return $user;
    }

    private static function getBearerToken(): ?string
    {
        $header = Request::header('Authorization');
        if (!$header) {
            return null;
        }
        if (preg_match('/Bearer\s+(.*)$/i', $header, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    private static function decodeToken(string $token, string $secret): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $validSignature = Utils::base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        if (!hash_equals($validSignature, $signature)) {
            return null;
        }
        $decoded = json_decode(Utils::base64UrlDecode($payload), true);
        if (!$decoded || ($decoded['exp'] ?? 0) < time()) {
            return null;
        }
        return $decoded;
    }
}
