<?php
require __DIR__ . '/../app/bootstrap.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    header('Location: /install/');
    exit;
}
$config = require $configFile;
if (empty($config['installed'])) {
    header('Location: /install/');
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath === '/') {
    $basePath = '';
}
if (strpos($path, '/api/') === 0) {
    require __DIR__ . '/../app/routes.php';
    exit;
}

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SELO (سلو)</title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/style.css">
</head>
<body data-theme="light">
    <div id="app">
        <div id="auth-view" class="auth-view">
            <div class="auth-card">
                <div class="brand">
                    <div class="brand-title">SELO</div>
                    <div class="brand-subtitle">سلو</div>
                </div>
                <div class="auth-tabs">
                    <button class="tab active" data-tab="login">ورود</button>
                    <button class="tab" data-tab="register">ثبت‌نام</button>
                </div>
                <div class="auth-content">
                    <form id="login-form" class="auth-form">
                        <label>نام کاربری یا ایمیل</label>
                        <input type="text" name="identifier" required>
                        <label>رمز عبور</label>
                        <input type="password" name="password" required>
                        <button type="submit">ورود</button>
                    </form>
                    <form id="register-form" class="auth-form hidden">
                        <label>نام کامل</label>
                        <input type="text" name="full_name" required>
                        <label>نام کاربری</label>
                        <input type="text" name="username" required>
                        <label>ایمیل (فقط Gmail)</label>
                        <input type="email" name="email" required>
                        <label>رمز عبور</label>
                        <input type="password" name="password" required>
                        <button type="submit">ثبت‌نام</button>
                    </form>
                </div>
                <div id="auth-error" class="auth-error"></div>
            </div>
        </div>

        <div id="main-view" class="main-view hidden">
            <aside class="sidebar">
                <div class="sidebar-header">
                    <div class="brand-mini">SELO</div>
                    <button id="theme-toggle" class="icon-btn" title="تغییر تم">🌓</button>
                </div>
                <div class="sidebar-search">
                    <input id="user-search" type="text" placeholder="جستجوی نام کاربری...">
                    <div id="search-results" class="search-results"></div>
                </div>
                <div id="chat-list" class="chat-list"></div>
            </aside>

            <section class="chat">
                <div class="chat-header">
                    <button id="back-to-chats" class="icon-btn mobile-only">بازگشت</button>
                    <div class="chat-user">
                        <div id="chat-user-avatar" class="avatar"></div>
                        <div>
                            <div id="chat-user-name" class="chat-user-name">گفتگو</div>
                            <div id="chat-user-username" class="chat-user-username"></div>
                        </div>
                    </div>
                </div>
                <div id="messages" class="messages"></div>
                <div id="attachment-preview" class="attachment-preview hidden"></div>
                <div id="voice-recorder" class="voice-recorder hidden">
                    <div class="voice-info">
                        <span class="voice-status">در حال ضبط</span>
                        <span id="voice-timer" class="voice-timer">00:00</span>
                    </div>
                    <div class="voice-actions">
                        <button id="voice-cancel" class="icon-btn" title="لغو">✖</button>
                        <button id="voice-stop" class="icon-btn" title="توقف">■</button>
                        <button id="voice-send" class="send-btn small hidden">ارسال</button>
                    </div>
                </div>
                <div id="reply-bar" class="reply-bar hidden">
                    <div class="reply-content">
                        <span>پاسخ به</span>
                        <div id="reply-preview"></div>
                    </div>
                    <button id="reply-cancel" class="icon-btn">×</button>
                </div>
                <div class="composer">
                    <button id="attach-btn" class="icon-btn" title="پیوست">📎</button>
                    <div id="attach-menu" class="attach-menu hidden">
                        <button type="button" data-type="media">عکس / ویدیو</button>
                        <button type="button" data-type="file">فایل</button>
                    </div>
                    <button id="emoji-btn" class="icon-btn">😊</button>
                    <div class="composer-input">
                        <textarea id="message-input" rows="1" placeholder="پیام بنویسید..."></textarea>
                        <div id="emoji-picker" class="emoji-picker hidden"></div>
                    </div>
                    <button id="voice-btn" class="icon-btn" title="پیام صوتی">🎤</button>
                    <button id="send-btn" class="send-btn">ارسال</button>
                </div>
                <input id="media-input" type="file" accept="image/*,video/*" class="hidden">
                <input id="file-input" type="file" class="hidden">
            </section>
        </div>
    </div>

    <div id="lightbox" class="lightbox hidden">
        <div class="lightbox-inner">
            <img id="lightbox-img" alt="preview">
            <button id="lightbox-close" class="icon-btn">✖</button>
        </div>
    </div>

    <script>
        window.SELO_CONFIG = {
            baseUrl: '<?php echo $config['app']['url'] ?? ''; ?>'
        };
    </script>
    <script src="<?php echo $basePath; ?>/assets/emoji-picker.js"></script>
    <script src="<?php echo $basePath; ?>/assets/app.js"></script>
</body>
</html>
