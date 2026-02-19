<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\LastSeenService;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

class UserController
{
    public static function me(array $config): void
    {
        $user = Auth::requireUser($config);
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, email, phone, bio, language, active_photo_id, last_seen_privacy FROM ' . $config['db']['prefix'] . 'users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $fresh = $stmt->fetch();
        $photosStmt = $pdo->prepare('SELECT id, file_name, thumbnail_name, width, height, is_active FROM ' . $config['db']['prefix'] . 'user_profile_photos WHERE user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC');
        $photosStmt->execute([$user['id']]);
        $photos = $photosStmt->fetchAll();
        Response::json(['ok' => true, 'data' => ['user' => $fresh, 'photos' => $photos]]);
    }

    public static function update(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $fullName = trim($data['full_name'] ?? $user['full_name']);
        $username = strtolower(trim($data['username'] ?? $user['username']));
        $bio = trim($data['bio'] ?? ($user['bio'] ?? ''));
        $phone = trim($data['phone'] ?? ($user['phone'] ?? ''));
        $email = strtolower(trim($data['email'] ?? $user['email']));

        if (!Validator::fullName($fullName)) {
            Response::json(['ok' => false, 'error' => 'نام کامل معتبر نیست.'], 422);
        }
        if (Validator::usernameEndsWithGroup($username)) {
            Response::json(['ok' => false, 'error' => 'نام کاربری نباید با "group" تمام شود.'], 422);
        }
        if (!Validator::username($username)) {
            Response::json(['ok' => false, 'error' => 'نام کاربری معتبر نیست.'], 422);
        }
        if (!Validator::bio($bio)) {
            Response::json(['ok' => false, 'error' => 'بیوگرافی بیش از حد طولانی است.'], 422);
        }
        if (!Validator::phone($phone)) {
            Response::json(['ok' => false, 'error' => 'شماره تلفن معتبر نیست.'], 422);
        }
        $pdo = Database::pdo();
        if ($username !== $user['username']) {
            $checkUsername = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'users WHERE username = ? AND id != ? LIMIT 1');
            $checkUsername->execute([$username, $user['id']]);
            if ($checkUsername->fetch()) {
                Response::json(['ok' => false, 'error' => 'این نام کاربری قبلاً استفاده شده است.'], 409);
            }
        }
        $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'users WHERE email = ? AND id != ? LIMIT 1');
        $check->execute([$email, $user['id']]);
        if ($check->fetch()) {
            Response::json(['ok' => false, 'error' => 'این ایمیل قبلاً استفاده شده است.'], 409);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'users SET full_name = ?, username = ?, bio = ?, phone = ?, email = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$fullName, $username, $bio, $phone, $email, $now, $user['id']]);
        Response::json(['ok' => true]);
    }

    public static function updateSettings(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $updates = [];
        $values = [];
        $response = [];

        if (array_key_exists('last_seen_privacy', $data)) {
            $privacy = LastSeenService::normalizePrivacy($data['last_seen_privacy'] ?? null);
            if ($privacy === null) {
                Response::json(['ok' => false, 'error' => 'مقدار حریم خصوصی نامعتبر است.'], 422);
            }
            $updates[] = 'last_seen_privacy = ?';
            $values[] = $privacy;
            $response['last_seen_privacy'] = $privacy;
        }

        if (empty($updates)) {
            Response::json(['ok' => false, 'error' => 'پارامتر نامعتبر است.'], 422);
        }

        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');
        $sql = 'UPDATE ' . $config['db']['prefix'] . 'users SET ' . implode(', ', $updates) . ', updated_at = ? WHERE id = ?';
        $values[] = $now;
        $values[] = $user['id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        Response::json(['ok' => true, 'data' => $response]);
    }

    public static function search(array $config): void
    {
        Auth::requireUser($config);
        $query = strtolower(trim(Request::param('query', '')));
        if ($query === '' || strlen($query) < 2) {
            Response::json(['ok' => true, 'data' => []]);
        }
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id, full_name, username, active_photo_id FROM ' . $config['db']['prefix'] . 'users WHERE username LIKE ? ORDER BY username ASC LIMIT 20');
        $stmt->execute([$query . '%']);
        $users = $stmt->fetchAll();
        Response::json(['ok' => true, 'data' => $users]);
    }
}
