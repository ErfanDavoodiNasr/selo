<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LastSeenService;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Core\Logger;
use App\Core\LogContext;
use PDOException;

class AuthController
{
    private const REFRESH_COOKIE = 'selo_refresh';
    private static $refreshTableEnsured = false;

    public static function logout(array $config): void
    {
        $token = Auth::requestToken();
        if (is_string($token) && trim($token) !== '') {
            Auth::revokeToken($config, $token);
        }
        self::revokeRefreshFromCookie($config);
        self::clearAuthCookies($config);
        Response::json(['ok' => true]);
    }

    public static function refresh(array $config): void
    {
        $rawRefresh = self::readRefreshCookie();
        if ($rawRefresh === null) {
            Response::json(['ok' => false, 'error' => 'نشست منقضی شده است.'], 401);
        }
        self::ensureRefreshTokensTable($config);
        $pdo = Database::pdo();
        $hash = hash('sha256', $rawRefresh);
        $table = $config['db']['prefix'] . 'refresh_tokens';
        $newRefresh = null;
        $token = null;
        $csrfToken = null;

        $pdo->beginTransaction();
        try {
            $lock = $pdo->prepare('SELECT id, user_id FROM ' . $table . ' WHERE token_hash = ? AND revoked_at IS NULL AND expires_at > NOW() LIMIT 1 FOR UPDATE');
            $lock->execute([$hash]);
            $row = $lock->fetch();
            if (!$row) {
                $pdo->rollBack();
                self::clearAuthCookies($config);
                Response::json(['ok' => false, 'error' => 'نشست منقضی شده است.'], 401);
            }

            $userId = (int)$row['user_id'];
            $newRefresh = bin2hex(random_bytes(32));
            $refreshTtlSeconds = self::refreshTtlSeconds($config);
            $newExpiresAt = date('Y-m-d H:i:s', time() + $refreshTtlSeconds);

            $revoke = $pdo->prepare('UPDATE ' . $table . ' SET revoked_at = NOW() WHERE id = ? AND revoked_at IS NULL');
            $revoke->execute([(int)$row['id']]);
            if ((int)$revoke->rowCount() !== 1) {
                $pdo->rollBack();
                self::clearAuthCookies($config);
                Response::json(['ok' => false, 'error' => 'نشست منقضی شده است.'], 401);
            }

            $insert = $pdo->prepare('INSERT INTO ' . $table . ' (user_id, token_hash, expires_at, created_at, revoked_at, rotated_from_id) VALUES (?, ?, ?, NOW(), NULL, ?)');
            $insert->execute([$userId, hash('sha256', $newRefresh), $newExpiresAt, (int)$row['id']]);

            $token = Auth::issueToken(['id' => $userId], $config);
            $csrfToken = self::generateCsrfToken();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::json(['ok' => false, 'error' => 'تمدید نشست ممکن نیست.'], 500);
        }

        self::setAuthCookiesWithCsrf($config, (string)$token, (string)$csrfToken);
        self::setRefreshCookie($config, $newRefresh);
        Response::json(['ok' => true, 'data' => ['token' => (string)$token, 'csrf_token' => (string)$csrfToken]]);
    }

    public static function register(array $config): void
    {
        self::enforceBodyLimit($config, 'register');
        if (RateLimiter::endpointIsLimited($config, 'register', null)) {
            Response::json(['ok' => false, 'error' => 'درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitEndpoint($config, 'register', null);

        $data = Request::json();
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!Validator::fullName($fullName)) {
            Logger::warn('register_failed', ['reason' => 'invalid_full_name', 'username' => $username], 'auth');
            Response::json(['ok' => false, 'error' => 'نام کامل معتبر نیست.'], 422);
        }
        if (Validator::usernameEndsWithGroup($username)) {
            Logger::warn('register_failed', ['reason' => 'username_reserved', 'username' => $username], 'auth');
            Response::json(['ok' => false, 'error' => 'نام کاربری نباید با "group" تمام شود.'], 422);
        }
        if (!Validator::username($username)) {
            Logger::warn('register_failed', ['reason' => 'invalid_username', 'username' => $username], 'auth');
            Response::json(['ok' => false, 'error' => 'نام کاربری معتبر نیست.'], 422);
        }
        if (!Validator::gmail($email)) {
            Logger::warn('register_failed', ['reason' => 'invalid_email_policy', 'username' => $username], 'auth');
            Response::json(['ok' => false, 'error' => 'ایمیل باید از نوع Gmail باشد.'], 422);
        }
        $passwordErrors = Validator::password($password);
        if (!empty($passwordErrors)) {
            Logger::warn('register_failed', ['reason' => 'weak_password', 'username' => $username], 'auth');
            Response::json(['ok' => false, 'error' => implode(' ', $passwordErrors)], 422);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            Logger::warn('register_failed', ['reason' => 'duplicate_user', 'username' => $username], 'auth');
            Response::json(['ok' => false, 'error' => 'نام کاربری یا ایمیل قبلاً ثبت شده است.'], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');
        try {
            $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'users (full_name, username, email, password_hash, language, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $insert->execute([$fullName, $username, $email, $hash, 'fa', $now, $now]);
        } catch (PDOException $e) {
            if (self::isUniqueViolation($e)) {
                Logger::warn('register_failed', ['reason' => 'duplicate_user_race', 'username' => $username], 'auth');
                Response::json(['ok' => false, 'error' => 'نام کاربری یا ایمیل قبلاً ثبت شده است.'], 409);
            }
            throw $e;
        }

        $userId = $pdo->lastInsertId();
        $user = ['id' => (int)$userId, 'full_name' => $fullName, 'username' => $username, 'email' => $email];
        $token = Auth::issueToken($user, $config);
        $csrfToken = self::setAuthCookies($config, $token);
        self::issueRefreshToken($config, (int)$userId);
        LastSeenService::touch($config, (int)$userId);

        Logger::info('register_success', ['user_id' => (int)$userId, 'username' => $username], 'auth');
        Response::json(['ok' => true, 'data' => ['token' => $token, 'csrf_token' => $csrfToken]]);
    }

    public static function login(array $config): void
    {
        self::enforceBodyLimit($config, 'login');
        if (RateLimiter::endpointIsLimited($config, 'login', null)) {
            Response::json(['ok' => false, 'error' => 'درخواست‌ها بیش از حد مجاز است. کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitEndpoint($config, 'login', null);

        $data = Request::json();
        $identifier = strtolower(trim($data['identifier'] ?? ''));
        $password = $data['password'] ?? '';
        $ip = LogContext::getIp() ?: 'unknown';

        if ($identifier === '' || $password === '') {
            Logger::warn('login_failed', ['reason' => 'missing_credentials', 'identifier' => $identifier], 'auth');
            Response::json(['ok' => false, 'error' => 'اطلاعات ورود ناقص است.'], 422);
        }
        if (mb_strlen($identifier) > 190) {
            Logger::warn('login_failed', ['reason' => 'identifier_too_long'], 'auth');
            Response::json(['ok' => false, 'error' => 'شناسه ورود بیش از حد طولانی است.'], 422);
        }

        $rateIdentifier = hash('sha256', $identifier);

        if (RateLimiter::tooManyAttempts($ip, $rateIdentifier, $config)) {
            Logger::warn('login_failed', ['reason' => 'rate_limited', 'identifier' => $identifier], 'auth');
            Response::json(['ok' => false, 'error' => 'تلاش‌های ناموفق زیاد است. لطفاً بعداً تلاش کنید.'], 429);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, password_hash FROM ' . $config['db']['prefix'] . 'users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::hit($ip, $rateIdentifier, $config);
            Logger::warn('login_failed', ['reason' => 'invalid_credentials', 'identifier' => $identifier], 'auth');
            Response::json(['ok' => false, 'error' => 'نام کاربری/ایمیل یا رمز عبور اشتباه است.'], 401);
        }

        RateLimiter::clear($ip, $rateIdentifier, $config);
        $token = Auth::issueToken($user, $config);
        $csrfToken = self::setAuthCookies($config, $token);
        self::issueRefreshToken($config, (int)$user['id']);
        LastSeenService::touch($config, (int)$user['id']);
        Logger::info('login_success', ['user_id' => (int)$user['id'], 'username' => $user['username']], 'auth');
        Response::json(['ok' => true, 'data' => ['token' => $token, 'csrf_token' => $csrfToken]]);
    }

    private static function setAuthCookies(array $config, string $token): string
    {
        $ttlSeconds = (int)($config['app']['jwt_ttl_seconds'] ?? 3600);
        if ($ttlSeconds <= 0) {
            $ttlSeconds = 3600;
        }
        $csrfToken = self::generateCsrfToken();
        self::setAuthCookiesWithCsrf($config, $token, $csrfToken);
        return $csrfToken;
    }

    private static function setAuthCookiesWithCsrf(array $config, string $token, string $csrfToken): void
    {
        $ttlSeconds = (int)($config['app']['jwt_ttl_seconds'] ?? 3600);
        if ($ttlSeconds <= 0) {
            $ttlSeconds = 3600;
        }
        $secure = self::isSecureRequest();
        setcookie('selo_token', $token, [
            'expires' => time() + $ttlSeconds,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('selo_csrf', $csrfToken, [
            'expires' => time() + $ttlSeconds,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    private static function clearAuthCookies(array $config): void
    {
        $secure = self::isSecureRequest();
        $expireAt = time() - 3600;
        setcookie('selo_token', '', [
            'expires' => $expireAt,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        setcookie('selo_csrf', '', [
            'expires' => $expireAt,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        setcookie(self::REFRESH_COOKIE, '', [
            'expires' => $expireAt,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private static function isSecureRequest(): bool
    {
        if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443')) {
            return true;
        }

        $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!self::isTrustedProxy($remoteIp)) {
            return false;
        }

        $forwardedProto = trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto === '') {
            return false;
        }
        $firstProto = strtolower(trim(explode(',', $forwardedProto)[0]));
        return $firstProto === 'https';
    }

    private static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private static function enforceBodyLimit(array $config, string $endpoint): void
    {
        $max = (int)($config['rate_limits']['max_body_bytes'][$endpoint] ?? 0);
        if ($max <= 0) {
            return;
        }
        if (Request::contentLength() > $max) {
            Response::json(['ok' => false, 'error' => 'حجم درخواست بیش از حد مجاز است.'], 413);
        }
    }

    private static function isUniqueViolation(PDOException $e): bool
    {
        $sqlState = (string)$e->getCode();
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        return $sqlState === '23000' || $driverCode === 1062;
    }

    private static function refreshTtlSeconds(array $config): int
    {
        $ttl = (int)($config['app']['refresh_ttl_seconds'] ?? (60 * 60 * 24 * 30));
        if ($ttl <= 0) {
            $ttl = 60 * 60 * 24 * 30;
        }
        return $ttl;
    }

    private static function readRefreshCookie(): ?string
    {
        $token = $_COOKIE[self::REFRESH_COOKIE] ?? null;
        if (!is_string($token)) {
            return null;
        }
        $token = trim($token);
        return $token === '' ? null : $token;
    }

    private static function setRefreshCookie(array $config, string $token): void
    {
        $secure = self::isSecureRequest();
        setcookie(self::REFRESH_COOKIE, $token, [
            'expires' => time() + self::refreshTtlSeconds($config),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }

    private static function issueRefreshToken(array $config, int $userId): void
    {
        self::ensureRefreshTokensTable($config);
        $raw = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);
        $expiresAt = date('Y-m-d H:i:s', time() + self::refreshTtlSeconds($config));
        $table = $config['db']['prefix'] . 'refresh_tokens';
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('INSERT INTO ' . $table . ' (user_id, token_hash, expires_at, created_at, revoked_at, rotated_from_id) VALUES (?, ?, ?, NOW(), NULL, NULL)');
        $stmt->execute([$userId, $hash, $expiresAt]);
        self::setRefreshCookie($config, $raw);
    }

    private static function revokeRefreshFromCookie(array $config): void
    {
        $raw = self::readRefreshCookie();
        if ($raw === null) {
            return;
        }
        self::ensureRefreshTokensTable($config);
        $hash = hash('sha256', $raw);
        $table = $config['db']['prefix'] . 'refresh_tokens';
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE ' . $table . ' SET revoked_at = NOW() WHERE token_hash = ? AND revoked_at IS NULL');
        $stmt->execute([$hash]);
    }

    private static function ensureRefreshTokensTable(array $config): void
    {
        if (self::$refreshTableEnsured) {
            return;
        }
        $table = $config['db']['prefix'] . 'refresh_tokens';
        if (!Database::tableExists($table)) {
            Response::json(['ok' => false, 'error' => 'ساختار پایگاه‌داده ناقص است. لطفاً migration نصب را اجرا کنید.'], 500);
        }
        self::$refreshTableEnsured = true;
    }

    private static function isTrustedProxy(string $ip): bool
    {
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }
        $raw = trim((string)getenv('SELO_TRUSTED_PROXIES'));
        if ($raw === '') {
            return false;
        }
        $proxies = array_filter(array_map('trim', explode(',', $raw)));
        return in_array($ip, $proxies, true);
    }

}
