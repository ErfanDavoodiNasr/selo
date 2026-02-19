<?php
namespace App\Core;

class ImageSafety
{
    private const DEFAULT_MAX_WIDTH = 8000;
    private const DEFAULT_MAX_HEIGHT = 8000;
    private const DEFAULT_MAX_PIXELS = 12000000; // 12MP
    private const SHARED_MAX_PIXELS_128M = 8000000; // 8MP
    private const SHARED_MAX_PIXELS_64M = 4000000; // 4MP
    private const DEFAULT_MEM_HEADROOM = 16 * 1024 * 1024; // 16MB
    private const BYTES_PER_PIXEL_ESTIMATE = 12; // decode + GD structures
    private const DECODE_BUFFER_COUNT = 2; // source + working buffer

    public static function validateForDecode(string $path, array $uploadsCfg = [], int $extraPixels = 0): array
    {
        $info = @getimagesize($path);
        if (!$info || !isset($info[0], $info[1])) {
            return ['ok' => false, 'error' => 'فایل تصویر معتبر نیست.'];
        }

        $width = (int)$info[0];
        $height = (int)$info[1];
        if ($width <= 0 || $height <= 0) {
            return ['ok' => false, 'error' => 'ابعاد تصویر نامعتبر است.'];
        }

        $maxWidth = (int)($uploadsCfg['image_max_width'] ?? self::DEFAULT_MAX_WIDTH);
        $maxHeight = (int)($uploadsCfg['image_max_height'] ?? self::DEFAULT_MAX_HEIGHT);
        $maxPixels = (int)($uploadsCfg['image_max_pixels'] ?? self::DEFAULT_MAX_PIXELS);

        $maxWidth = max(64, min(20000, $maxWidth));
        $maxHeight = max(64, min(20000, $maxHeight));
        $maxPixels = max(4096, min(100000000, $maxPixels));
        $sharedPixelsCap = self::sharedHostPixelsCap();
        if ($sharedPixelsCap !== null) {
            $maxPixels = min($maxPixels, $sharedPixelsCap);
        }

        $pixels = $width * $height;
        if ($width > $maxWidth || $height > $maxHeight || $pixels > $maxPixels) {
            return ['ok' => false, 'error' => 'ابعاد تصویر بیش از حد مجاز است.'];
        }

        $totalPixels = $pixels + max(0, $extraPixels);
        $required = ($totalPixels * self::BYTES_PER_PIXEL_ESTIMATE * self::DECODE_BUFFER_COUNT) + self::DEFAULT_MEM_HEADROOM;
        $available = self::availableMemoryBytes();
        if ($available !== null && $required > $available) {
            return ['ok' => false, 'error' => 'تصویر برای پردازش بسیار سنگین است.'];
        }

        return [
            'ok' => true,
            'width' => $width,
            'height' => $height,
            'mime' => (string)($info['mime'] ?? ''),
        ];
    }

    private static function availableMemoryBytes(): ?int
    {
        $limit = self::memoryLimitBytes();
        if ($limit === null || $limit <= 0) {
            return null; // unlimited or unknown
        }
        $used = memory_get_usage(true);
        $available = $limit - $used;
        return $available > 0 ? $available : 0;
    }

    private static function sharedHostPixelsCap(): ?int
    {
        $limit = self::memoryLimitBytes();
        if ($limit === null || $limit <= 0) {
            return null;
        }
        if ($limit <= 64 * 1024 * 1024) {
            return self::SHARED_MAX_PIXELS_64M;
        }
        if ($limit <= 128 * 1024 * 1024) {
            return self::SHARED_MAX_PIXELS_128M;
        }
        return null;
    }

    private static function memoryLimitBytes(): ?int
    {
        $limitRaw = ini_get('memory_limit');
        if ($limitRaw === false) {
            return null;
        }
        return self::parseIniBytes((string)$limitRaw);
    }

    private static function parseIniBytes(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || $trimmed === '-1') {
            return null;
        }
        if (!preg_match('/^(\d+)\s*([KMG])?$/i', $trimmed, $m)) {
            return null;
        }
        $num = (int)$m[1];
        $unit = strtoupper($m[2] ?? '');
        switch ($unit) {
            case 'G':
                return $num * 1024 * 1024 * 1024;
            case 'M':
                return $num * 1024 * 1024;
            case 'K':
                return $num * 1024;
            default:
                return $num;
        }
    }
}
