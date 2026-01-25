<?php
session_start();

$basePath = dirname(__DIR__, 2);
$configFile = $basePath . '/config/config.php';
if (file_exists($configFile)) {
    $config = require $configFile;
    if (!empty($config['installed'])) {
        header('Location: /');
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
                header('Location: /install/?step=3');
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
                    header('Location: /install/?step=4');
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
            $appUrl = (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $baseDir = rtrim(str_replace('/install', '', dirname($_SERVER['SCRIPT_NAME'])), '/');
            if ($baseDir !== '') {
                $appUrl .= $baseDir;
            }

            $config = [
                'installed' => true,
                'app' => [
                    'name' => 'SELO',
                    'name_fa' => 'سلو',
                    'url' => $appUrl,
                    'timezone' => 'Asia/Tehran',
                    'jwt_secret' => bin2hex(random_bytes(32)),
                ],
                'db' => $db,
                'uploads' => [
                    'dir' => $basePath . '/storage/uploads',
                    'max_size' => 2 * 1024 * 1024,
                    'media_dir' => $basePath . '/storage/uploads/media',
                    'media_max_size' => 20 * 1024 * 1024,
                    'photo_max_size' => 5 * 1024 * 1024,
                    'video_max_size' => 25 * 1024 * 1024,
                    'voice_max_size' => 10 * 1024 * 1024,
                    'file_max_size' => 20 * 1024 * 1024,
                ],
            ];

            $export = "<?php\nreturn " . var_export($config, true) . ";\n";
            if (!is_dir($basePath . '/config')) {
                mkdir($basePath . '/config', 0755, true);
            }
            file_put_contents($configFile, $export);
            $_SESSION['install_done'] = true;
            header('Location: /install/?step=finish');
            exit;
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
        button { margin-top: 16px; background: #2563eb; color: #fff; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; }
        .secondary { background: #6b7280; }
        .row { display: flex; gap: 16px; }
        .row > div { flex: 1; }
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
            <div class="step">۴. پایان</div>
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
            <a href="/install/?step=2"><button>ادامه</button></a>

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
            <h3>تولید فایل تنظیمات</h3>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo csrfToken(); ?>">
                <button type="submit">ایجاد فایل تنظیمات</button>
            </form>

        <?php elseif ($stepView === 'finish'): ?>
            <h3>نصب کامل شد ✅</h3>
            <p>اکنون می‌توانید وارد شوید.</p>
            <a href="/"><button>ورود به SELO</button></a>
        <?php endif; ?>
    </div>
</body>
</html>
