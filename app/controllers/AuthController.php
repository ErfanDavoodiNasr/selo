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

class AuthController
{
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
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'users (full_name, username, email, password_hash, language, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$fullName, $username, $email, $hash, 'fa', $now, $now]);

        $userId = $pdo->lastInsertId();
        $user = ['id' => (int)$userId, 'full_name' => $fullName, 'username' => $username, 'email' => $email];
        $token = Auth::issueToken($user, $config);
        $csrfToken = self::setAuthCookies($config, $token);
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

        if (RateLimiter::tooManyAttempts($ip, $identifier, $config)) {
            Logger::warn('login_failed', ['reason' => 'rate_limited', 'identifier' => $identifier], 'auth');
            Response::json(['ok' => false, 'error' => 'تلاش‌های ناموفق زیاد است. لطفاً بعداً تلاش کنید.'], 429);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, password_hash FROM ' . $config['db']['prefix'] . 'users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::hit($ip, $identifier, $config);
            Logger::warn('login_failed', ['reason' => 'invalid_credentials', 'identifier' => $identifier], 'auth');
            Response::json(['ok' => false, 'error' => 'نام کاربری/ایمیل یا رمز عبور اشتباه است.'], 401);
        }

        RateLimiter::clear($ip, $identifier, $config);
        $token = Auth::issueToken($user, $config);
        $csrfToken = self::setAuthCookies($config, $token);
        LastSeenService::touch($config, (int)$user['id']);
        Logger::info('login_success', ['user_id' => (int)$user['id'], 'username' => $user['username']], 'auth');
        Response::json(['ok' => true, 'data' => ['token' => $token, 'csrf_token' => $csrfToken]]);
    }

    private static function setAuthCookies(array $config, string $token): string
    {
        $ttlSeconds = (int)($config['app']['jwt_ttl_seconds'] ?? (60 * 60 * 24 * 7));
        if ($ttlSeconds <= 0) {
            $ttlSeconds = 60 * 60 * 24 * 7;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        setcookie('selo_token', $token, [
            'expires' => time() + $ttlSeconds,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $csrfToken = self::generateCsrfToken();
        setcookie('selo_csrf', $csrfToken, [
            'expires' => time() + $ttlSeconds,
            'path' => '/',
            'secure' => $secure,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
        return $csrfToken;
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

}
