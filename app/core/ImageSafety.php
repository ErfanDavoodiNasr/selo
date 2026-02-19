<?php
namespace App\Core;

class ImageSafety
{
    private const DEFAULT_MAX_WIDTH = 8000;
    private const DEFAULT_MAX_HEIGHT = 8000;
    private const DEFAULT_MAX_PIXELS = 25000000; // 25MP
    private const DEFAULT_MEM_HEADROOM = 8 * 1024 * 1024; // 8MB
    private const BYTES_PER_PIXEL_ESTIMATE = 5; // RGBA + overhead

    public static function validateForDecode(string $path, array $uploadsCfg = []): array
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

        $pixels = $width * $height;
        if ($width > $maxWidth || $height > $maxHeight || $pixels > $maxPixels) {
            return ['ok' => false, 'error' => 'ابعاد تصویر بیش از حد مجاز است.'];
        }

        $required = ($pixels * self::BYTES_PER_PIXEL_ESTIMATE) + self::DEFAULT_MEM_HEADROOM;
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
        $limitRaw = ini_get('memory_limit');
        if ($limitRaw === false) {
            return null;
        }
        $limit = self::parseIniBytes((string)$limitRaw);
        if ($limit === null || $limit <= 0) {
            return null; // unlimited or unknown
        }
        $used = memory_get_usage(true);
        $available = $limit - $used;
        return $available > 0 ? $available : 0;
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
