<?php
namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Utils;
use App\Core\Logger;

class CallController
{
    private static function callSecret(array $config): string
    {
        $secret = $config['calls']['signaling_secret'] ?? '';
        if ($secret === '') {
            $secret = $config['app']['jwt_secret'] ?? '';
        }
        return $secret;
    }

    private static function requireSignalingSecret(array $config): void
    {
        $secret = self::callSecret($config);
        $provided = Request::header('X-Signaling-Secret') ?? Request::header('X-Signal-Secret');
        if ($secret === '' || !$provided || !hash_equals($secret, $provided)) {
            Response::json(['ok' => false, 'error' => 'Unauthorized.'], 401);
        }
    }

    private static function issueToken(int $userId, int $ttlSeconds, array $config): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $config['app']['url'] ?? 'selo',
            'sub' => $userId,
            'iat' => time(),
            'exp' => time() + $ttlSeconds,
            'scope' => 'call',
        ];
        $base64Header = Utils::base64UrlEncode(json_encode($header));
        $base64Payload = Utils::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, self::callSecret($config), true);
        $base64Signature = Utils::base64UrlEncode($signature);
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public static function token(array $config): void
    {
        $user = Auth::requireUser($config);
        $data = Request::json();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rate = $config['calls']['rate_limit'] ?? [];
        $max = (int)($rate['max_attempts'] ?? 6);
        $window = (int)($rate['window_minutes'] ?? 1);
        $lock = (int)($rate['lock_minutes'] ?? 2);
        $identifier = 'call_token_' . $user['id'];

        if (RateLimiter::tooManyAttemptsCustom($ip, $identifier, $config, $max, $window, $lock)) {
            Response::json(['ok' => false, 'error' => 'تلاش‌های زیاد. لطفاً کمی بعد تلاش کنید.'], 429);
        }
        RateLimiter::hitCustom($ip, $identifier, $config, $max, $window, $lock);

        $calleeId = (int)($data['callee_id'] ?? 0);
        $conversationId = (int)($data['conversation_id'] ?? 0);
        if ($calleeId > 0) {
            if ($calleeId === (int)$user['id']) {
                Response::json(['ok' => false, 'error' => 'Invalid payload.'], 422);
            }
            $pdo = Database::pdo();
            if ($conversationId > 0) {
                $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1');
                $check->execute([$conversationId, $user['id'], $user['id']]);
                $row = $check->fetch();
                if (!$row) {
                    Response::json(['ok' => false, 'error' => 'Unauthorized.'], 403);
                }
                $pair = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? AND ((user_one_id = ? AND user_two_id = ?) OR (user_one_id = ? AND user_two_id = ?)) LIMIT 1');
                $pair->execute([$conversationId, $user['id'], $calleeId, $calleeId, $user['id']]);
                if (!$pair->fetch()) {
                    Response::json(['ok' => false, 'error' => 'Unauthorized.'], 403);
                }
            } else {
                $u1 = min($user['id'], $calleeId);
                $u2 = max($user['id'], $calleeId);
                $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE user_one_id = ? AND user_two_id = ? LIMIT 1');
                $check->execute([$u1, $u2]);
                if (!$check->fetch()) {
                    Response::json(['ok' => false, 'error' => 'Unauthorized.'], 403);
                }
            }

            $stmt = $pdo->prepare('SELECT allow_voice_calls FROM ' . $config['db']['prefix'] . 'users WHERE id = ? LIMIT 1');
            $stmt->execute([$calleeId]);
            $callee = $stmt->fetch();
            if (!$callee) {
                Response::json(['ok' => false, 'error' => 'User not found.'], 404);
            }
            if ((int)$callee['allow_voice_calls'] !== 1) {
                Response::json([
                    'ok' => false,
                    'error' => 'CALLS_DISABLED',
                    'message' => 'این کاربر تماس صوتی را غیرفعال کرده است.'
                ], 403);
            }
        }

        $ttl = (int)($config['calls']['token_ttl_seconds'] ?? 120);
        $ttl = $ttl > 0 ? $ttl : 120;
        $token = self::issueToken((int)$user['id'], $ttl, $config);

        Response::json(['ok' => true, 'data' => ['token' => $token, 'expires_in' => $ttl]]);
    }

    public static function history(array $config): void
    {
        $user = Auth::requireUser($config);
        $conversationId = (int)Request::param('conversation_id', 0);
        if ($conversationId <= 0) {
            Response::json(['ok' => false, 'error' => 'گفتگو نامعتبر است.'], 422);
        }

        $pdo = Database::pdo();
        $check = $pdo->prepare('SELECT id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? AND (user_one_id = ? OR user_two_id = ?) LIMIT 1');
        $check->execute([$conversationId, $user['id'], $user['id']]);
        if (!$check->fetch()) {
            Response::json(['ok' => false, 'error' => 'دسترسی غیرمجاز.'], 403);
        }

        $sql = 'SELECT id, caller_id, callee_id, started_at, answered_at, ended_at, end_reason, duration_seconds
                FROM ' . $config['db']['prefix'] . 'call_logs
                WHERE conversation_id = ?
                ORDER BY started_at DESC
                LIMIT 50';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$conversationId]);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['caller_id'] = (int)$row['caller_id'];
            $row['callee_id'] = (int)$row['callee_id'];
            $row['duration_seconds'] = $row['duration_seconds'] !== null ? (int)$row['duration_seconds'] : null;
            $row['direction'] = ($row['caller_id'] === (int)$user['id']) ? 'outgoing' : 'incoming';
        }
        Response::json(['ok' => true, 'data' => $rows]);
    }

    public static function validate(array $config): void
    {
        self::requireSignalingSecret($config);
        $data = Request::json();
        $conversationId = (int)($data['conversation_id'] ?? 0);
        $callerId = (int)($data['caller_id'] ?? 0);
        $calleeId = (int)($data['callee_id'] ?? 0);

        if ($conversationId <= 0 || $callerId <= 0 || $calleeId <= 0 || $callerId === $calleeId) {
            Response::json(['ok' => false, 'error' => 'Invalid payload.'], 422);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT user_one_id, user_two_id FROM ' . $config['db']['prefix'] . 'conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        if (!$conv) {
            Response::json(['ok' => false, 'error' => 'Conversation not found.'], 404);
        }

        $valid = ($conv['user_one_id'] == $callerId && $conv['user_two_id'] == $calleeId)
            || ($conv['user_one_id'] == $calleeId && $conv['user_two_id'] == $callerId);
        if (!$valid) {
            Response::json(['ok' => false, 'error' => 'Unauthorized.'], 403);
        }

        $uStmt = $pdo->prepare('SELECT id, full_name, username, active_photo_id, allow_voice_calls FROM ' . $config['db']['prefix'] . 'users WHERE id IN (?, ?)');
        $uStmt->execute([$callerId, $calleeId]);
        $users = $uStmt->fetchAll();
        $map = [];
        foreach ($users as $u) {
            $map[(int)$u['id']] = [
                'id' => (int)$u['id'],
                'full_name' => $u['full_name'],
                'username' => $u['username'],
                'photo_id' => $u['active_photo_id'] !== null ? (int)$u['active_photo_id'] : null,
                'allow_voice_calls' => (int)$u['allow_voice_calls'],
            ];
        }
        if (!isset($map[$callerId]) || !isset($map[$calleeId])) {
            Response::json(['ok' => false, 'error' => 'User not found.'], 404);
        }
        if ((int)$map[$calleeId]['allow_voice_calls'] !== 1) {
            Response::json([
                'ok' => false,
                'error' => 'CALLS_DISABLED',
                'message' => 'این کاربر تماس صوتی را غیرفعال کرده است.'
            ], 403);
        }

        Response::json([
            'ok' => true,
            'data' => [
                'conversation_id' => $conversationId,
                'caller' => [
                    'id' => $map[$callerId]['id'],
                    'full_name' => $map[$callerId]['full_name'],
                    'username' => $map[$callerId]['username'],
                    'photo_id' => $map[$callerId]['photo_id'],
                ],
                'callee' => [
                    'id' => $map[$calleeId]['id'],
                    'full_name' => $map[$calleeId]['full_name'],
                    'username' => $map[$calleeId]['username'],
                    'photo_id' => $map[$calleeId]['photo_id'],
                ],
            ],
        ]);
    }

    public static function event(array $config): void
    {
        self::requireSignalingSecret($config);
        $data = Request::json();
        $event = strtolower(trim($data['event'] ?? ''));
        $pdo = Database::pdo();
        $now = date('Y-m-d H:i:s');

        $allowedReasons = ['completed', 'declined', 'missed', 'busy', 'failed', 'canceled'];

        if ($event === 'start') {
            $conversationId = (int)($data['conversation_id'] ?? 0);
            $callerId = (int)($data['caller_id'] ?? 0);
            $calleeId = (int)($data['callee_id'] ?? 0);
            if ($conversationId <= 0 || $callerId <= 0 || $calleeId <= 0) {
                Response::json(['ok' => false, 'error' => 'Invalid payload.'], 422);
            }
            $insert = $pdo->prepare('INSERT INTO ' . $config['db']['prefix'] . 'call_logs (conversation_id, caller_id, callee_id, started_at) VALUES (?, ?, ?, ?)');
            $insert->execute([$conversationId, $callerId, $calleeId, $now]);
            $callLogId = (int)$pdo->lastInsertId();
            Logger::info('call_start', [
                'call_log_id' => $callLogId,
                'conversation_id' => $conversationId,
                'caller_id' => $callerId,
                'callee_id' => $calleeId,
            ], 'call');
            Response::json(['ok' => true, 'data' => ['call_log_id' => $callLogId]]);
        }

        if ($event === 'answer') {
            $callLogId = (int)($data['call_log_id'] ?? 0);
            if ($callLogId <= 0) {
                Response::json(['ok' => false, 'error' => 'Invalid payload.'], 422);
            }
            $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'call_logs SET answered_at = ? WHERE id = ? AND answered_at IS NULL');
            $update->execute([$now, $callLogId]);
            Response::json(['ok' => true]);
        }

        if ($event === 'end' || $event === 'missed') {
            $callLogId = (int)($data['call_log_id'] ?? 0);
            $reason = $event === 'missed' ? 'missed' : strtolower(trim($data['end_reason'] ?? ''));
            if ($callLogId <= 0 || !in_array($reason, $allowedReasons, true)) {
                Response::json(['ok' => false, 'error' => 'Invalid payload.'], 422);
            }
            $stmt = $pdo->prepare('SELECT answered_at FROM ' . $config['db']['prefix'] . 'call_logs WHERE id = ? LIMIT 1');
            $stmt->execute([$callLogId]);
            $row = $stmt->fetch();
            if (!$row) {
                Response::json(['ok' => false, 'error' => 'Call log not found.'], 404);
            }
            $duration = null;
            if (!empty($row['answered_at'])) {
                $duration = max(0, strtotime($now) - strtotime($row['answered_at']));
            }
            $update = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'call_logs SET ended_at = ?, end_reason = ?, duration_seconds = ? WHERE id = ?');
            $update->execute([$now, $reason, $duration, $callLogId]);
            Logger::info('call_end', [
                'call_log_id' => $callLogId,
                'end_reason' => $reason,
                'duration_seconds' => $duration,
            ], 'call');
            Response::json(['ok' => true]);
        }

        Response::json(['ok' => false, 'error' => 'Invalid event.'], 422);
    }
}
