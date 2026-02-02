<?php
namespace App\Core;

class LastSeenService
{
    public const PRIVACY_EVERYONE = 'everyone';
    public const PRIVACY_NOBODY = 'nobody';

    private const DEFAULT_TOUCH_INTERVAL = 60;

    public static function touch(array $config, int $userId): void
    {
        $interval = (int)($config['presence']['last_seen_touch_seconds'] ?? self::DEFAULT_TOUCH_INTERVAL);
        $interval = max(30, min(300, $interval));

        $now = gmdate('Y-m-d H:i:s');
        $threshold = gmdate('Y-m-d H:i:s', time() - $interval);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE ' . $config['db']['prefix'] . 'users SET last_seen_at = ? WHERE id = ? AND (last_seen_at IS NULL OR last_seen_at < ?)');
        $stmt->execute([$now, $userId, $threshold]);
    }

    public static function statusFor(?string $lastSeenAt, ?string $privacy, array $config, bool $isOnline): array
    {
        if ($isOnline) {
            return [
                'kind' => 'online',
                'text' => 'online',
                'is_online' => true,
            ];
        }

        $privacy = self::normalizePrivacy($privacy) ?? self::PRIVACY_NOBODY;
        $lastSeenUtc = self::parseUtc($lastSeenAt);
        $lastSeenTs = $lastSeenUtc ? $lastSeenUtc->getTimestamp() : null;

        if ($privacy === self::PRIVACY_EVERYONE && $lastSeenUtc) {
            return [
                'kind' => 'exact',
                'text' => self::formatExact($lastSeenUtc, $config),
                'is_online' => false,
            ];
        }

        return [
            'kind' => 'approx',
            'text' => self::formatApprox($lastSeenTs, time()),
            'is_online' => false,
        ];
    }

    public static function validPrivacy(?string $value): bool
    {
        return self::normalizePrivacy($value) !== null;
    }

    public static function normalizePrivacy(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $normalized = strtolower(trim($value));
        if (!in_array($normalized, [self::PRIVACY_EVERYONE, self::PRIVACY_NOBODY], true)) {
            return null;
        }
        return $normalized;
    }

    private static function formatExact(\DateTimeImmutable $utc, array $config): string
    {
        $tzName = $config['app']['timezone'] ?? 'UTC';
        $tz = new \DateTimeZone($tzName);
        $local = $utc->setTimezone($tz);

        $today = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
        $yesterday = (new \DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
        $day = $local->format('Y-m-d');
        $time = $local->format('H:i');

        if ($day === $today) {
            return 'last seen today at ' . $time;
        }
        if ($day === $yesterday) {
            return 'last seen yesterday at ' . $time;
        }
        $currentYear = (new \DateTimeImmutable('now', $tz))->format('Y');
        if ($local->format('Y') === $currentYear) {
            return 'last seen on ' . $local->format('M d') . ' at ' . $time;
        }
        return 'last seen on ' . $local->format('Y-m-d H:i');
    }

    private static function formatApprox(?int $ts, int $nowTs): string
    {
        if (!$ts) {
            return 'last seen a long time ago';
        }
        $diff = max(0, $nowTs - $ts);
        if ($diff <= 3 * 86400) {
            return 'last seen recently';
        }
        if ($diff <= 7 * 86400) {
            return 'last seen within a week';
        }
        if ($diff <= 30 * 86400) {
            return 'last seen within a month';
        }
        return 'last seen a long time ago';
    }

    private static function parseUtc(?string $value): ?\DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $dt ?: null;
    }
}
