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
                    <div class="sidebar-actions">
                        <button id="new-group-btn" class="icon-btn" title="گروه جدید">👥+</button>
                        <button id="theme-toggle" class="icon-btn" title="تغییر تم">🌓</button>
                    </div>
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
                    <button id="group-settings-btn" class="icon-btn hidden" title="تنظیمات گروه">⚙️</button>
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
                        <button type="button" data-type="photo">عکس</button>
                        <button type="button" data-type="video">ویدیو</button>
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
                <input id="photo-input" type="file" accept="image/*" class="hidden">
                <input id="video-input" type="file" accept="video/*" class="hidden">
                <input id="file-input" type="file" class="hidden">
            </section>
        </div>
    </div>

    <div id="group-modal" class="modal hidden">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">گروه جدید</div>
                <button id="group-modal-close" class="icon-btn">✖</button>
            </div>
            <form id="group-form" class="modal-body">
                <label>عنوان گروه</label>
                <input id="group-title" type="text" required>
                <label>نوع گروه</label>
                <select id="group-privacy">
                    <option value="private">خصوصی (لینک دعوت)</option>
                    <option value="public">عمومی (شناسه)</option>
                </select>
                <div id="group-handle-row" class="hidden">
                    <label>شناسه عمومی (باید با group تمام شود)</label>
                    <input id="group-handle" type="text" placeholder="مثال: funnygroup">
                </div>
                <label>توضیحات (اختیاری)</label>
                <textarea id="group-description" rows="2" placeholder="درباره گروه..."></textarea>
                <label>افزودن اعضا با نام کاربری (اختیاری)</label>
                <input id="group-members" type="text" placeholder="user1, user2">
                <div id="group-error" class="form-error"></div>
                <button id="group-submit" type="submit" class="send-btn">ساخت گروه</button>
            </form>
        </div>
    </div>

    <div id="group-settings-modal" class="modal hidden">
        <div class="modal-card wide">
            <div class="modal-header">
                <div class="modal-title">تنظیمات گروه</div>
                <button id="group-settings-close" class="icon-btn">✖</button>
            </div>
            <div class="modal-body">
                <div class="settings-section">
                    <div class="section-title">اطلاعات گروه</div>
                    <div class="info-row">
                        <span>شناسه عمومی:</span>
                        <span id="group-info-handle">-</span>
                    </div>
                    <div id="group-invite-row" class="info-row hidden">
                        <span>لینک دعوت:</span>
                        <div class="invite-wrap">
                            <input id="group-invite-link" type="text" readonly>
                            <button id="group-invite-copy" type="button" class="icon-btn" title="کپی">📋</button>
                        </div>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="section-title">مجوزها</div>
                    <div class="toggle-row">
                        <span>اجازه دعوت اعضا</span>
                        <input id="group-allow-invites" type="checkbox">
                    </div>
                    <div class="toggle-row">
                        <span>ارسال عکس</span>
                        <input id="group-allow-photos" type="checkbox">
                    </div>
                    <div class="toggle-row">
                        <span>ارسال ویدیو</span>
                        <input id="group-allow-videos" type="checkbox">
                    </div>
                    <div class="toggle-row">
                        <span>ارسال پیام صوتی</span>
                        <input id="group-allow-voice" type="checkbox">
                    </div>
                    <div class="toggle-row">
                        <span>ارسال فایل</span>
                        <input id="group-allow-files" type="checkbox">
                    </div>
                    <button id="group-settings-save" class="send-btn">ذخیره تغییرات</button>
                </div>
                <div class="settings-section">
                    <div class="section-title">دعوت عضو</div>
                    <div class="invite-action">
                        <input id="group-invite-username" type="text" placeholder="نام کاربری">
                        <button id="group-invite-submit" class="send-btn small">دعوت</button>
                    </div>
                    <div id="group-invite-error" class="form-error"></div>
                </div>
                <div class="settings-section">
                    <div class="section-title">اعضا</div>
                    <div id="group-members-list" class="members-list"></div>
                </div>
            </div>
        </div>
    </div>

    <div id="reaction-modal" class="modal hidden">
        <div class="modal-card">
            <div class="modal-header">
                <div id="reaction-modal-title" class="modal-title">واکنش‌ها</div>
                <button id="reaction-modal-close" class="icon-btn">✖</button>
            </div>
            <div id="reaction-modal-list" class="members-list"></div>
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
