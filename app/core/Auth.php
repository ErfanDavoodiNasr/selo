<?php
namespace App\Core;

class Auth
{
    private static $user;
    private static $authSource = null;
    private static $revokedTableEnsured = false;

    public static function issueToken(array $user, array $config): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $ttlSeconds = (int)($config['app']['jwt_ttl_seconds'] ?? 3600);
        if ($ttlSeconds <= 0) {
            $ttlSeconds = 3600;
        }
        $payload = [
            'iss' => $config['app']['url'] ?? 'selo',
            'sub' => $user['id'],
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
            'jti' => bin2hex(random_bytes(16)),
        ];
        $base64Header = Utils::base64UrlEncode(json_encode($header));
        $base64Payload = Utils::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $config['app']['jwt_secret'], true);
        $base64Signature = Utils::base64UrlEncode($signature);
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public static function requestToken(): ?string
    {
        $auth = self::resolveRequestToken();
        return $auth['token'] ?: null;
    }

    public static function revokeToken(array $config, string $token): void
    {
        $payload = self::decodeToken($token, $config, false);
        if (!$payload) {
            return;
        }
        self::ensureRevokedTable($config);
        $pdo = Database::pdo();
        if (!$pdo) {
            return;
        }
        $jti = isset($payload['jti']) ? (string)$payload['jti'] : null;
        if ($jti === '') {
            $jti = null;
        }
        $expiresAtTs = (int)($payload['exp'] ?? 0);
        if ($expiresAtTs <= 0) {
            $expiresAtTs = time() + 3600;
        }
        $expiresAt = date('Y-m-d H:i:s', $expiresAtTs);
        $tokenHash = hash('sha256', $token);
        $table = $config['db']['prefix'] . 'revoked_tokens';
        $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (jti, token_hash, expires_at, revoked_at) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE expires_at = VALUES(expires_at), revoked_at = VALUES(revoked_at), token_hash = VALUES(token_hash)');
        $stmt->execute([$jti, $tokenHash, $expiresAt]);
        self::cleanupExpiredRevocations($config);
    }

    public static function user(array $config): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        $auth = self::resolveRequestToken();
        $token = $auth['token'];
        if (!$token) {
            return null;
        }
        $user = self::userFromToken($config, $token);
        self::$authSource = $user ? $auth['source'] : null;
        self::$user = $user ?: null;
        return self::$user;
    }

    public static function userFromToken(array $config, string $token): ?array
    {
        $payload = self::decodeToken($token, $config, true);
        if (!$payload) {
            return null;
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, phone, bio, language, active_photo_id, last_seen_privacy FROM ' . $config['db']['prefix'] . 'users WHERE id = ? LIMIT 1');
        $stmt->execute([$payload['sub']]);
        $user = $stmt->fetch();
        if ($user) {
            LogContext::setUserId((int)$user['id']);
        }
        return $user ?: null;
    }

    public static function requireUser(array $config): array
    {
        $user = self::user($config);
        if (!$user) {
            Response::json(['ok' => false, 'error' => 'احراز هویت نامعتبر است.'], 401);
        }
        if (self::$authSource === 'cookie' && self::isStateChangingMethod() && !self::hasValidCsrfToken()) {
            Response::json(['ok' => false, 'error' => 'توکن CSRF نامعتبر است.'], 403);
        }
        return $user;
    }

    private static function resolveRequestToken(): array
    {
        $header = Request::header('Authorization');
        if ($header && preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
            return ['token' => trim($matches[1]), 'source' => 'header'];
        }
        $cookieToken = $_COOKIE['selo_token'] ?? null;
        if (is_string($cookieToken)) {
            $cookieToken = trim($cookieToken);
            if ($cookieToken !== '') {
                return ['token' => $cookieToken, 'source' => 'cookie'];
            }
        }
        return ['token' => null, 'source' => null];
    }

    private static function isStateChangingMethod(): bool
    {
        $method = Request::method();
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    private static function hasValidCsrfToken(): bool
    {
        $cookieToken = $_COOKIE['selo_csrf'] ?? null;
        if (!is_string($cookieToken) || trim($cookieToken) === '') {
            return false;
        }
        $headerToken = Request::header('X-CSRF-Token');
        if (!is_string($headerToken) || trim($headerToken) === '') {
            return false;
        }
        return hash_equals($cookieToken, trim($headerToken));
    }

    private static function decodeToken(string $token, array $config, bool $checkRevoked = true): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$header, $payload, $signature] = $parts;
        $secret = (string)($config['app']['jwt_secret'] ?? '');
        $validSignature = Utils::base64UrlEncode(hash_hmac('sha256', $header . '.' . $payload, $secret, true));
        if (!hash_equals($validSignature, $signature)) {
            return null;
        }
        $decoded = json_decode(Utils::base64UrlDecode($payload), true);
        if (!$decoded || ($decoded['exp'] ?? 0) < time()) {
            return null;
        }
        if ($checkRevoked && self::isTokenRevoked($config, $token, $decoded)) {
            return null;
        }
        return $decoded;
    }

    private static function isTokenRevoked(array $config, string $token, array $payload): bool
    {
        self::ensureRevokedTable($config);
        $pdo = Database::pdo();
        if (!$pdo) {
            return false;
        }
        $table = $config['db']['prefix'] . 'revoked_tokens';
        $jti = isset($payload['jti']) ? (string)$payload['jti'] : '';
        if ($jti !== '') {
            $byJti = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE jti = ? AND expires_at > NOW() LIMIT 1');
            $byJti->execute([$jti]);
            if ($byJti->fetchColumn()) {
                return true;
            }
        }
        $tokenHash = hash('sha256', $token);
        $byHash = $pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE token_hash = ? AND expires_at > NOW() LIMIT 1');
        $byHash->execute([$tokenHash]);
        return (bool)$byHash->fetchColumn();
    }

    private static function ensureRevokedTable(array $config): void
    {
        if (self::$revokedTableEnsured) {
            return;
        }
        $table = $config['db']['prefix'] . 'revoked_tokens';
        if (!Database::tableExists($table)) {
            Response::json(['ok' => false, 'error' => 'ساختار پایگاه‌داده ناقص است. لطفاً migration نصب را اجرا کنید.'], 500);
        }
        self::$revokedTableEnsured = true;
    }

    private static function cleanupExpiredRevocations(array $config): void
    {
        if (mt_rand(1, 50) !== 1) {
            return;
        }
        $pdo = Database::pdo();
        if (!$pdo) {
            return;
        }
        $table = $config['db']['prefix'] . 'revoked_tokens';
        $pdo->exec('DELETE FROM `' . $table . '` WHERE expires_at < NOW() LIMIT 200');
    }
}
