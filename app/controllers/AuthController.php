<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

class AuthController
{
    public static function register(array $config): void
    {
        $data = Request::json();
        $fullName = trim($data['full_name'] ?? '');
        $username = strtolower(trim($data['username'] ?? ''));
        $email = strtolower(trim($data['email'] ?? ''));
        $password = $data['password'] ?? '';

        if (!Validator::fullName($fullName)) {
            Response::json(['ok' => false, 'error' => 'نام کامل معتبر نیست.'], 422);
        }
        if (!Validator::username($username)) {
            Response::json(['ok' => false, 'error' => 'نام کاربری معتبر نیست.'], 422);
        }
        if (!Validator::gmail($email)) {
            Response::json(['ok' => false, 'error' => 'فقط ایمیل‌های Gmail مجاز هستند.'], 422);
        }
        $passwordErrors = Validator::password($password);
        if (!empty($passwordErrors)) {
            Response::json(['ok' => false, 'error' => implode(' ', $passwordErrors)], 422);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            Response::json(['ok' => false, 'error' => 'نام کاربری یا ایمیل قبلاً ثبت شده است.'], 409);
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now = date('Y-m-d H:i:s');
        $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'users (full_name, username, email, password_hash, language, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $insert->execute([$fullName, $username, $email, $hash, 'fa', $now, $now]);

        $userId = $pdo->lastInsertId();
        $user = ['id' => (int)$userId, 'full_name' => $fullName, 'username' => $username, 'email' => $email];
        $token = Auth::issueToken($user, $config);

        Response::json(['ok' => true, 'data' => ['token' => $token]]);
    }

    public static function login(array $config): void
    {
        $data = Request::json();
        $identifier = strtolower(trim($data['identifier'] ?? ''));
        $password = $data['password'] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        if ($identifier === '' || $password === '') {
            Response::json(['ok' => false, 'error' => 'اطلاعات ورود ناقص است.'], 422);
        }

        if (RateLimiter::tooManyAttempts($ip, $identifier, $config)) {
            Response::json(['ok' => false, 'error' => 'تلاش‌های ناموفق زیاد است. لطفاً بعداً تلاش کنید.'], 429);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, password_hash FROM ' . $config['db']['prefix'] . 'users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$identifier, $identifier]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::hit($ip, $identifier, $config);
            Response::json(['ok' => false, 'error' => 'نام کاربری/ایمیل یا رمز عبور اشتباه است.'], 401);
        }

        RateLimiter::clear($ip, $identifier, $config);
        $token = Auth::issueToken($user, $config);
        Response::json(['ok' => true, 'data' => ['token' => $token]]);
    }
}
