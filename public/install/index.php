<?php
session_start();

$basePath = dirname(__DIR__, 2);
$configFile = $basePath . '/config/config.php';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = rtrim(dirname($scriptName), '/');
$scriptBase = basename($scriptName);
$isIndexScript = strtolower($scriptBase) === 'index.php';
if ($isIndexScript) {
    $installBaseUrl = $scriptDir === '' ? '/install' : $scriptDir;
    $appBasePath = rtrim(str_replace('/install', '', $scriptDir), '/');
} else {
    $installBaseUrl = $scriptName === '' ? '/install' : $scriptName;
    $appBasePath = rtrim(str_replace('/' . $scriptBase, '', $scriptName), '/');
}
$appBasePath = $appBasePath === '' ? '/' : $appBasePath;
$isInstallFile = (bool)preg_match('/\\.php$/i', $installBaseUrl);

function installUrl(string $query = ''): string
{
    global $installBaseUrl, $isInstallFile;
    if ($query === '') {
        return $installBaseUrl;
    }
    $query = ltrim($query, '?');
    if ($isInstallFile) {
        return $installBaseUrl . '?' . $query;
    }
    return rtrim($installBaseUrl, '/') . '/?' . $query;
}
if (file_exists($configFile)) {
    $config = require $configFile;
    if (!empty($config['installed'])) {
        header('Location: ' . $appBasePath);
        exit;
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function checkCsrf(): bool
{
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token']);
}

$step = $_GET['step'] ?? '1';
$errors = [];

function defaultAppUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $appUrl = $scheme . '://' . $host;
    $baseDir = $GLOBALS['appBasePath'] ?? '/';
    if ($baseDir !== '' && $baseDir !== '/') {
        $appUrl .= $baseDir;
    }
    return $appUrl;
}

function bytesToMb(int $bytes): int
{
    return (int)round($bytes / (1024 * 1024));
}

function parseUploadMb(string $key, int $defaultBytes, array &$errors, string $label): int
{
    $raw = trim($_POST[$key] ?? '');
    if ($raw === '') {
        return $defaultBytes;
    }
    if (!is_numeric($raw)) {
        $errors[] = $label . ' نامعتبر است.';
        return $defaultBytes;
    }
    $value = (float)$raw;
    if ($value <= 0) {
        $errors[] = $label . ' باید بزرگ‌تر از صفر باشد.';
        return $defaultBytes;
    }
    return (int)round($value * 1024 * 1024);
}

function parseDurationSeconds(string $valueKey, string $unitKey, int $defaultSeconds, array &$errors, string $label): int
{
    $rawValue = trim($_POST[$valueKey] ?? '');
    if ($rawValue === '') {
        return $defaultSeconds;
    }
    if (!is_numeric($rawValue)) {
        $errors[] = $label . ' نامعتبر است.';
        return $defaultSeconds;
    }
    $value = (float)$rawValue;
    if ($value <= 0) {
        $errors[] = $label . ' باید بزرگ‌تر از صفر باشد.';
        return $defaultSeconds;
    }

    $unit = strtolower(trim($_POST[$unitKey] ?? ''));
    $unitMap = [
        'minute' => 60,
        'hour' => 3600,
        'day' => 86400,
        'week' => 604800,
    ];
    if (!isset($unitMap[$unit])) {
        $errors[] = $label . ' واحد نامعتبر است.';
        return $defaultSeconds;
    }
    $seconds = (int)round($value * $unitMap[$unit]);
    if ($seconds <= 0) {
        $errors[] = $label . ' نامعتبر است.';
        return $defaultSeconds;
    }
    return $seconds;
}

$defaultUploads = [
    'max_size' => 2 * 1024 * 1024,
    'media_max_size' => 20 * 1024 * 1024,
    'photo_max_size' => 5 * 1024 * 1024,
    'video_max_size' => 25 * 1024 * 1024,
    'voice_max_size' => 10 * 1024 * 1024,
    'file_max_size' => 20 * 1024 * 1024,
];
$defaultCalls = [
    'token_ttl_seconds' => 120,
    'ring_timeout_seconds' => 45,
    'rate_limit' => [
        'max_attempts' => 6,
        'window_minutes' => 1,
        'lock_minutes' => 2,
    ],
    'ice_servers' => [],
];
$defaultLogging = [
    'level' => 'INFO',
    'app_file' => $basePath . '/storage/logs/app.log',
    'error_file' => $basePath . '/storage/logs/error.log',
    'max_size_mb' => 10,
    'max_files' => 5,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!checkCsrf()) {
        $errors[] = 'توکن امنیتی نامعتبر است.';
    }

    if ($step === '2' && empty($errors)) {
        $dbHost = trim($_POST['db_host'] ?? '');
        $dbName = trim($_POST['db_name'] ?? '');
        $dbUser = trim($_POST['db_user'] ?? '');
        $dbPass = trim($_POST['db_pass'] ?? '');
        $dbPrefix = trim($_POST['db_prefix'] ?? 'selo_');

        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            $errors[] = 'اطلاعات پایگاه داده ناقص است.';
        } else {
            try {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbHost, $dbName);
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('SET NAMES utf8mb4');

                $schema = file_get_contents($basePath . '/database/schema.sql');
                $schema = str_replace('{{prefix}}', $dbPrefix, $schema);
                $queries = array_filter(array_map('trim', explode(';', $schema)));
                foreach ($queries as $query) {
                    $pdo->exec($query);
                }

                $_SESSION['install_db'] = [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'prefix' => $dbPrefix,
                ];
                header('Location: ' . installUrl('step=3'));
                exit;
            } catch (Exception $e) {
                $errors[] = 'اتصال یا ایجاد جداول ناموفق بود: ' . $e->getMessage();
            }
        }
    }

    if ($step === '3' && empty($errors)) {
        $createAdmin = isset($_POST['create_admin']);
        if ($createAdmin) {
            $fullName = trim($_POST['full_name'] ?? '');
            $username = strtolower(trim($_POST['username'] ?? ''));
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            if ($fullName === '' || $username === '' || $email === '' || $password === '') {
                $errors[] = 'اطلاعات مدیر ناقص است.';
            }
            if (!preg_match('/^[a-z0-9_]{3,32}$/', $username)) {
                $errors[] = 'نام کاربری مدیر معتبر نیست.';
            }
            if (!preg_match('/^[A-Z0-9._%+-]+@gmail\.com$/i', $email)) {
                $errors[] = 'فقط ایمیل‌های Gmail مجاز هستند.';
            }
            if (strlen($password) < 8) {
                $errors[] = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
            }
        }

        if (empty($errors)) {
            $db = $_SESSION['install_db'] ?? null;
            if (!$db) {
                $errors[] = 'اطلاعات پایگاه داده یافت نشد.';
            } else {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $db['host'], $db['name']);
                $pdo = new PDO($dsn, $db['user'], $db['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('SET NAMES utf8mb4');

                if ($createAdmin) {
                    $stmt = $pdo->prepare('SELECT id FROM ' . $db['prefix'] . 'users WHERE username = ? OR email = ? LIMIT 1');
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        $errors[] = 'کاربر با این نام کاربری یا ایمیل وجود دارد.';
                    } else {
                        $hash = password_hash($password, PASSWORD_BCRYPT);
                        $now = date('Y-m-d H:i:s');
                        $insert = $pdo->prepare('INSERT INTO ' . $db['prefix'] . 'users (full_name, username, email, password_hash, language, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
                        $insert->execute([$fullName, $username, $email, $hash, 'fa', $now, $now]);
                    }
                }

                if (empty($errors)) {
                    header('Location: ' . installUrl('step=4'));
                    exit;
                }
            }
        }
    }

    if ($step === '4' && empty($errors)) {
        $db = $_SESSION['install_db'] ?? null;
        if (!$db) {
            $errors[] = 'اطلاعات پایگاه داده یافت نشد.';
        } else {
            $defaultAppUrl = defaultAppUrl();
            $appUrlInput = trim($_POST['app_url'] ?? '');
            $appUrl = $appUrlInput !== '' ? $appUrlInput : $defaultAppUrl;
            $jwtSecret = trim($_POST['jwt_secret'] ?? '');
            if ($jwtSecret === '') {
                $jwtSecret = bin2hex(random_bytes(32));
            }
            $signalingSecret = trim($_POST['signaling_secret'] ?? '');
            if ($signalingSecret === '') {
                $signalingSecret = bin2hex(random_bytes(32));
            }

            $uploads = [
                'dir' => $basePath . '/storage/uploads',
                'max_size' => parseUploadMb('upload_max_size', $defaultUploads['max_size'], $errors, 'حداکثر حجم آواتار'),
                'media_dir' => $basePath . '/storage/uploads/media',
                'media_max_size' => parseUploadMb('upload_media_max_size', $defaultUploads['media_max_size'], $errors, 'حداکثر حجم رسانه'),
                'photo_max_size' => parseUploadMb('upload_photo_max_size', $defaultUploads['photo_max_size'], $errors, 'حداکثر حجم عکس'),
                'video_max_size' => parseUploadMb('upload_video_max_size', $defaultUploads['video_max_size'], $errors, 'حداکثر حجم ویدیو'),
                'voice_max_size' => parseUploadMb('upload_voice_max_size', $defaultUploads['voice_max_size'], $errors, 'حداکثر حجم ویس'),
                'file_max_size' => parseUploadMb('upload_file_max_size', $defaultUploads['file_max_size'], $errors, 'حداکثر حجم فایل'),
            ];

            if (empty($errors)) {
                $jwtTtlSeconds = parseDurationSeconds('jwt_ttl_value', 'jwt_ttl_unit', 60 * 60 * 24 * 7, $errors, 'مدت اعتبار توکن');
            }

            if (empty($errors)) {
                $signalingUrl = preg_replace('#^http#i', 'ws', $appUrl);
                $signalingUrl = rtrim($signalingUrl, '/') . '/ws';
                $calls = [
                    'signaling_url' => $signalingUrl,
                    'signaling_secret' => $signalingSecret,
                    'token_ttl_seconds' => $defaultCalls['token_ttl_seconds'],
                    'ring_timeout_seconds' => $defaultCalls['ring_timeout_seconds'],
                    'rate_limit' => $defaultCalls['rate_limit'],
                    'ice_servers' => $defaultCalls['ice_servers'],
                ];
                $config = [
                    'installed' => true,
                    'app' => [
                        'name' => 'SELO',
                        'name_fa' => 'سلو',
                        'url' => $appUrl,
                        'timezone' => 'Asia/Tehran',
                        'jwt_secret' => $jwtSecret,
                        'jwt_ttl_seconds' => $jwtTtlSeconds,
                    ],
                    'db' => $db,
                    'uploads' => $uploads,
                    'calls' => $calls,
                    'logging' => $defaultLogging,
                ];

                $export = "<?php\nreturn " . var_export($config, true) . ";\n";
                if (!is_dir($basePath . '/config')) {
                    mkdir($basePath . '/config', 0755, true);
                }
                file_put_contents($configFile, $export);
                $_SESSION['install_done'] = true;
                header('Location: ' . installUrl('step=finish'));
                exit;
            }
        }
    }
}

$requirements = [
    'PHP >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'PDO MySQL' => extension_loaded('pdo_mysql'),
    'MBString' => extension_loaded('mbstring'),
    'OpenSSL' => extension_loaded('openssl'),
    'JSON' => extension_loaded('json'),
    'Fileinfo' => extension_loaded('fileinfo'),
];

$writable = [
    $basePath . '/config' => is_writable($basePath . '/config') || !file_exists($basePath . '/config'),
    $basePath . '/storage' => is_writable($basePath . '/storage'),
    $basePath . '/storage/uploads' => is_writable($basePath . '/storage/uploads'),
    $basePath . '/storage/uploads/media' => is_writable($basePath . '/storage/uploads') || !file_exists($basePath . '/storage/uploads/media'),
];

$stepView = $step;
if ($step === 'finish') {
    $stepView = 'finish';
}

$step4Defaults = [
    'app_url' => defaultAppUrl(),
    'jwt_secret' => '',
    'signaling_secret' => '',
    'jwt_ttl_value' => '1',
    'jwt_ttl_unit' => 'week',
    'upload_max_size' => bytesToMb($defaultUploads['max_size']),
    'upload_media_max_size' => bytesToMb($defaultUploads['media_max_size']),
    'upload_photo_max_size' => bytesToMb($defaultUploads['photo_max_size']),
    'upload_video_max_size' => bytesToMb($defaultUploads['video_max_size']),
    'upload_voice_max_size' => bytesToMb($defaultUploads['voice_max_size']),
    'upload_file_max_size' => bytesToMb($defaultUploads['file_max_size']),
];

$step4Values = [
    'app_url' => $_POST['app_url'] ?? $step4Defaults['app_url'],
    'jwt_secret' => $_POST['jwt_secret'] ?? $step4Defaults['jwt_secret'],
    'signaling_secret' => $_POST['signaling_secret'] ?? $step4Defaults['signaling_secret'],
    'jwt_ttl_value' => $_POST['jwt_ttl_value'] ?? $step4Defaults['jwt_ttl_value'],
    'jwt_ttl_unit' => $_POST['jwt_ttl_unit'] ?? $step4Defaults['jwt_ttl_unit'],
    'upload_max_size' => $_POST['upload_max_size'] ?? $step4Defaults['upload_max_size'],
    'upload_media_max_size' => $_POST['upload_media_max_size'] ?? $step4Defaults['upload_media_max_size'],
    'upload_photo_max_size' => $_POST['upload_photo_max_size'] ?? $step4Defaults['upload_photo_max_size'],
    'upload_video_max_size' => $_POST['upload_video_max_size'] ?? $step4Defaults['upload_video_max_size'],
    'upload_voice_max_size' => $_POST['upload_voice_max_size'] ?? $step4Defaults['upload_voice_max_size'],
    'upload_file_max_size' => $_POST['upload_file_max_size'] ?? $step4Defaults['upload_file_max_size'],
];

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نصب SELO (سلو)</title>
    <style>
        body { font-family: Tahoma, sans-serif; background: #f5f6fa; color: #1f2a37; margin: 0; padding: 0; }
        .container { max-width: 760px; margin: 40px auto; background: #fff; border-radius: 12px; padding: 24px 32px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
        .brand { font-size: 28px; font-weight: bold; }
        .subtitle { color: #6b7280; }
        .steps { display: flex; gap: 12px; margin: 16px 0 24px; }
        .step { padding: 8px 12px; border-radius: 8px; background: #eef2ff; }
        .error { background: #fee2e2; color: #991b1b; padding: 10px 12px; border-radius: 8px; margin-bottom: 12px; }
        .list { list-style: none; padding: 0; }
        .list li { margin: 6px 0; }
        label { display: block; margin: 8px 0 4px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; }
        select { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        button { margin-top: 16px; background: #2563eb; color: #fff; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; }
        .secondary { background: #6b7280; }
        .row { display: flex; gap: 16px; }
        .row > div { flex: 1; }
        .input-row { display: flex; gap: 12px; align-items: flex-start; }
        .input-row .grow { flex: 1; }
        .input-row .actions { width: 200px; display: flex; flex-direction: column; gap: 10px; }
        .input-row .actions button { width: 100%; margin-top: 0; }
        .checkbox-inline { display: flex; gap: 8px; align-items: center; margin: 0; }
        .hint { color: #6b7280; font-size: 12px; margin: 4px 0 0; }
        @media (max-width: 640px) {
            .input-row { flex-direction: column; }
            .input-row .actions { width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="brand">نصب SELO (سلو)</div>
        <div class="subtitle">راهنمای نصب مرحله به مرحله</div>
        <div class="steps">
            <div class="step">۱. بررسی</div>
            <div class="step">۲. پایگاه داده</div>
            <div class="step">۳. مدیر</div>
            <div class="step">۴. تنظیمات</div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="error"><?php echo implode('<br>', $errors); ?></div>
        <?php endif; ?>

        <?php if ($stepView === '1'): ?>
            <h3>بررسی پیش‌نیازها</h3>
            <ul class="list">
                <?php foreach ($requirements as $label => $ok): ?>
                    <li><?php echo $label . ': ' . ($ok ? '✅' : '❌'); ?></li>
                <?php endforeach; ?>
            </ul>
            <h4>بررسی دسترسی نوشتن</h4>
            <ul class="list">
                <?php foreach ($writable as $path => $ok): ?>
                    <li><?php echo $path . ': ' . ($ok ? '✅' : '❌'); ?></li>
                <?php endforeach; ?>
            </ul>
            <a href="<?php echo htmlspecialchars(installUrl('step=2'), ENT_QUOTES, 'UTF-8'); ?>"><button>ادامه</button></a>

        <?php elseif ($stepView === '2'): ?>
            <h3>اطلاعات پایگاه داده</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <label>هاست</label>
                <input type="text" name="db_host" value="localhost" required>
                <label>نام پایگاه داده</label>
                <input type="text" name="db_name" required>
                <label>نام کاربری</label>
                <input type="text" name="db_user" required>
                <label>رمز عبور</label>
                <input type="password" name="db_pass">
                <label>پیشوند جداول</label>
                <input type="text" name="db_prefix" value="selo_">
                <button type="submit">ایجاد جداول و ادامه</button>
            </form>

        <?php elseif ($stepView === '3'): ?>
            <h3>ایجاد مدیر (اختیاری)</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <label>
                    <input type="checkbox" name="create_admin" value="1" checked>
                    ایجاد کاربر مدیر
                </label>
                <label>نام کامل</label>
                <input type="text" name="full_name">
                <label>نام کاربری</label>
                <input type="text" name="username">
                <label>ایمیل (Gmail)</label>
                <input type="email" name="email">
                <label>رمز عبور</label>
                <input type="password" name="password">
                <button type="submit">ادامه</button>
            </form>

        <?php elseif ($stepView === '4'): ?>
            <h3>تنظیمات برنامه</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <label>آدرس برنامه (URL)</label>
                <input type="text" name="app_url" value="<?php echo htmlspecialchars($step4Values['app_url'], ENT_QUOTES, 'UTF-8'); ?>" required>
                <label>کلید JWT (خالی بگذارید تا خودکار ساخته شود)</label>
                <div class="input-row">
                    <div class="grow">
                        <input type="password" id="jwt_secret" name="jwt_secret" value="<?php echo htmlspecialchars($step4Values['jwt_secret'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="actions">
                        <button type="button" id="generate-jwt" class="secondary">تولید کلید</button>
                        <label class="checkbox-inline">
                            <input type="checkbox" id="toggle-jwt">
                            نمایش کلید
                        </label>
                    </div>
                </div>
                <label>کلید سیگنالینگ تماس (خالی بگذارید تا خودکار ساخته شود)</label>
                <div class="input-row">
                    <div class="grow">
                        <input type="password" id="signaling_secret" name="signaling_secret" value="<?php echo htmlspecialchars($step4Values['signaling_secret'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="actions">
                        <button type="button" id="generate-signaling" class="secondary">تولید کلید</button>
                        <label class="checkbox-inline">
                            <input type="checkbox" id="toggle-signaling">
                            نمایش کلید
                        </label>
                    </div>
                </div>
                <label>مدت اعتبار توکن JWT</label>
                <div class="row">
                    <div>
                        <label>مقدار</label>
                        <input type="number" name="jwt_ttl_value" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['jwt_ttl_value'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label>واحد</label>
                        <select name="jwt_ttl_unit">
                            <option value="minute" <?php echo $step4Values['jwt_ttl_unit'] === 'minute' ? 'selected' : ''; ?>>دقیقه</option>
                            <option value="hour" <?php echo $step4Values['jwt_ttl_unit'] === 'hour' ? 'selected' : ''; ?>>ساعت</option>
                            <option value="day" <?php echo $step4Values['jwt_ttl_unit'] === 'day' ? 'selected' : ''; ?>>روز</option>
                            <option value="week" <?php echo $step4Values['jwt_ttl_unit'] === 'week' ? 'selected' : ''; ?>>هفته</option>
                        </select>
                    </div>
                </div>
                <div class="hint">مثال: ۵ دقیقه یا ۳ هفته</div>
                <h4>محدودیت حجم آپلود (مگابایت)</h4>
                <div class="row">
                    <div>
                        <label>آواتار</label>
                        <input type="number" name="upload_max_size" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['upload_max_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label>رسانه عمومی</label>
                        <input type="number" name="upload_media_max_size" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['upload_media_max_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>عکس</label>
                        <input type="number" name="upload_photo_max_size" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['upload_photo_max_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label>ویدیو</label>
                        <input type="number" name="upload_video_max_size" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['upload_video_max_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <div class="row">
                    <div>
                        <label>ویس</label>
                        <input type="number" name="upload_voice_max_size" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['upload_voice_max_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div>
                        <label>فایل</label>
                        <input type="number" name="upload_file_max_size" min="1" step="0.1" value="<?php echo htmlspecialchars((string)$step4Values['upload_file_max_size'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>
                <button type="submit">ایجاد فایل تنظیمات</button>
            </form>

        <?php elseif ($stepView === 'finish'): ?>
            <h3>نصب کامل شد ✅</h3>
            <p>اکنون می‌توانید وارد شوید.</p>
            <a href="<?php echo htmlspecialchars($appBasePath, ENT_QUOTES, 'UTF-8'); ?>"><button>ورود به SELO</button></a>
        <?php endif; ?>
    </div>
</body>
<script>
    (function () {
        function generateHex(bytes) {
            if (!window.crypto || !window.crypto.getRandomValues) {
                return '';
            }
            var array = new Uint8Array(bytes);
            window.crypto.getRandomValues(array);
            var out = '';
            for (var i = 0; i < array.length; i++) {
                out += array[i].toString(16).padStart(2, '0');
            }
            return out;
        }

        function bindSecretControls(inputId, toggleId, buttonId) {
            var input = document.getElementById(inputId);
            var toggle = document.getElementById(toggleId);
            var generateBtn = document.getElementById(buttonId);
            if (!input || !toggle || !generateBtn) {
                return;
            }

            toggle.addEventListener('change', function () {
                input.type = toggle.checked ? 'text' : 'password';
            });

            generateBtn.addEventListener('click', function () {
                var secret = generateHex(32);
                if (secret) {
                    input.value = secret;
                    input.type = 'text';
                    toggle.checked = true;
                } else {
                    alert('مرورگر شما از تولید امن کلید پشتیبانی نمی‌کند.');
                }
            });
        }

        bindSecretControls('jwt_secret', 'toggle-jwt', 'generate-jwt');
        bindSecretControls('signaling_secret', 'toggle-signaling', 'generate-signaling');
    })();
</script>
</html>
