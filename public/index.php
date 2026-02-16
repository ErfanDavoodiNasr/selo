<?php
require __DIR__ . '/../app/bootstrap.php';

function installerUrl(): string
{
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $basePath = rtrim(dirname($scriptName), '/');
    if ($basePath === '/') {
        $basePath = '';
    }
    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    if ($docRoot !== '') {
        $basePathDisk = $docRoot . ($basePath === '' ? '' : $basePath);
        $installFile = $basePathDisk . '/install.php';
        if (is_file($installFile)) {
            return ($basePath === '' ? '' : $basePath) . '/install.php';
        }
    }
    return ($basePath === '' ? '' : $basePath) . '/install/';
}

function assetUrl(string $basePath, string $relativePath): string
{
    $cleanPath = ltrim($relativePath, '/');
    $absolutePath = __DIR__ . '/' . $cleanPath;
    $version = is_file($absolutePath) ? (string)filemtime($absolutePath) : '1';
    $prefix = $basePath === '' ? '' : $basePath;
    return $prefix . '/' . $cleanPath . '?v=' . rawurlencode($version);
}

function formatSourceHost(string $host): string
{
    if (strpos($host, ':') !== false && strpos($host, '[') !== 0) {
        return '[' . $host . ']';
    }
    return $host;
}

function cspConnectSources(array $config): array
{
    $sources = ["'self'"];
    $hostHeader = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($hostHeader !== '' && preg_match('/^[A-Za-z0-9.\\-:\\[\\]]+$/', $hostHeader)) {
        $sources[] = 'ws://' . $hostHeader;
        $sources[] = 'wss://' . $hostHeader;
    }

    $signalingUrl = trim((string)($config['calls']['signaling_url'] ?? ''));
    if ($signalingUrl !== '' && strpos($signalingUrl, '/') !== 0) {
        $parsed = @parse_url($signalingUrl);
        if (is_array($parsed) && !empty($parsed['host'])) {
            $host = formatSourceHost((string)$parsed['host']);
            $port = isset($parsed['port']) ? ':' . (int)$parsed['port'] : '';
            $scheme = strtolower((string)($parsed['scheme'] ?? ''));
            if (in_array($scheme, ['ws', 'wss', 'http', 'https'], true)) {
                $sources[] = 'ws://' . $host . $port;
                $sources[] = 'wss://' . $host . $port;
            }
        }
    }

    return array_values(array_unique($sources));
}

function buildCspHeader(array $config, string $nonce): string
{
    $connectSrc = implode(' ', cspConnectSources($config));
    $directives = [
        "default-src 'self'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'self'",
        "object-src 'none'",
        "script-src 'self' 'nonce-" . $nonce . "'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self'",
        "connect-src " . $connectSrc,
        "media-src 'self' blob:",
        "worker-src 'self'",
        "manifest-src 'self'",
    ];
    return implode('; ', $directives);
}

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    header('Location: ' . installerUrl());
    exit;
}
$config = require $configFile;
if (empty($config['installed'])) {
    header('Location: ' . installerUrl());
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($basePath === '/') {
    $basePath = '';
}
$relativePath = $path;
if ($basePath !== '' && strpos($relativePath, $basePath . '/') === 0) {
    $relativePath = substr($relativePath, strlen($basePath));
}
if ($relativePath === '') {
    $relativePath = '/';
}
$apiPrefix = $basePath !== '' ? ($basePath . '/api/') : '/api/';
if (strpos($path, $apiPrefix) === 0) {
    App\Core\LogContext::setIsApi(true);
    App\Core\Logger::info('request_start', [], 'api');
    $path = $relativePath;
    require __DIR__ . '/../app/routes.php';
    exit;
}

$cspNonce = base64_encode(random_bytes(16));
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Content-Security-Policy: ' . buildCspHeader($config, $cspNonce));

?><!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SELO (سلو)</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/css/fonts.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/style.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/css/app.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/css/call.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body data-theme="light">
    <div id="app">
        <div id="auth-view" class="auth-view">
            <div class="auth-card">
                <div class="auth-brand">
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
                    <button id="sidebar-menu-btn" class="icon-btn menu-btn" title="منو" aria-label="منو">
                        <span class="material-symbols-rounded">menu</span>
                    </button>
                    <div class="sidebar-brand">
                        <div class="brand-mini">SELO</div>
                        <div class="brand-subtitle">سلو</div>
                    </div>
                    <div class="sidebar-actions">
                        <button id="new-group-btn" class="icon-btn" title="گروه جدید" aria-label="گروه جدید">
                            <span class="material-symbols-rounded">group_add</span>
                        </button>
                        <button id="theme-toggle" class="icon-btn" title="تغییر تم" aria-label="تغییر تم">
                            <span class="material-symbols-rounded">dark_mode</span>
                        </button>
                    </div>
                    <button id="sidebar-profile-btn" class="sidebar-profile" title="پروفایل" aria-label="پروفایل">
                        <div id="sidebar-profile-avatar" class="avatar">👤</div>
                    </button>
                </div>
                <div class="sidebar-search">
                    <span class="material-symbols-rounded">search</span>
                    <input id="user-search" type="text" placeholder="جستجوی نام کاربری...">
                    <div id="search-results" class="search-results"></div>
                </div>
                <div id="unread-notice" class="unread-notice hidden" aria-live="polite">
                    <span class="unread-count" id="unread-count">0</span>
                    <span class="unread-text">پیام جدید</span>
                </div>
                <div id="chat-list" class="chat-list"></div>
            </aside>

            <section class="chat-panel">
                <div class="chat-header">
                    <button id="back-to-chats" class="icon-btn mobile-only" title="بازگشت" aria-label="بازگشت">
                        <span class="material-symbols-rounded">arrow_forward</span>
                    </button>
                    <div class="chat-user" id="chat-user-header">
                        <div id="chat-user-avatar" class="avatar"></div>
                        <div class="chat-user-meta">
                            <div id="chat-user-name" class="chat-user-name">گفتگو</div>
                            <div id="chat-user-username" class="chat-user-username"></div>
                            <div id="chat-user-status" class="chat-user-status"></div>
                        </div>
                    </div>
                    <div class="chat-header-actions">
                        <button id="audio-call-btn" class="icon-btn audio-call-btn hidden" title="تماس صوتی" aria-label="تماس صوتی">
                            <span class="material-symbols-rounded">call</span>
                        </button>
                        <button id="group-settings-btn" class="icon-btn hidden" title="تنظیمات گروه" aria-label="تنظیمات گروه">
                            <span class="material-symbols-rounded">tune</span>
                        </button>
                        <button id="info-toggle" class="icon-btn" title="اطلاعات گفتگو" aria-label="اطلاعات گفتگو">
                            <span class="material-symbols-rounded">info</span>
                        </button>
                    </div>
                </div>
                <div id="messages" class="messages"></div>
                <button id="jump-to-bottom" class="jump-to-bottom hidden" title="رفتن به پایین" aria-label="رفتن به پایین">
                    <span class="material-symbols-rounded">south</span>
                </button>
                <div id="attachment-preview" class="attachment-preview hidden"></div>
                <div id="voice-recorder" class="voice-recorder hidden">
                    <div class="voice-info">
                        <span class="voice-status">در حال ضبط</span>
                        <span id="voice-timer" class="voice-timer">00:00</span>
                    </div>
                    <div class="voice-actions">
                        <button id="voice-cancel" class="icon-btn" title="لغو" aria-label="لغو">✖</button>
                        <button id="voice-stop" class="icon-btn" title="توقف" aria-label="توقف">■</button>
                        <button id="voice-send" class="send-btn small hidden">ارسال</button>
                    </div>
                </div>
                <div id="reply-bar" class="reply-bar hidden">
                    <div class="reply-content">
                        <span>پاسخ به</span>
                        <div id="reply-preview"></div>
                    </div>
                    <button id="reply-cancel" class="icon-btn" aria-label="لغو پاسخ">×</button>
                </div>
                <div class="composer">
                    <button id="attach-btn" class="icon-btn" title="پیوست" aria-label="پیوست">
                        <span class="material-symbols-rounded">attach_file</span>
                    </button>
                    <div id="attach-menu" class="attach-menu hidden">
                        <button type="button" data-type="photo">عکس</button>
                        <button type="button" data-type="video">ویدیو</button>
                        <button type="button" data-type="file">فایل</button>
                    </div>
                    <button id="emoji-btn" class="icon-btn" title="ایموجی" aria-label="ایموجی">
                        <span class="material-symbols-rounded">sentiment_satisfied</span>
                    </button>
                    <div class="composer-input">
                        <textarea id="message-input" rows="1" placeholder="پیام بنویسید..."></textarea>
                        <div id="emoji-picker" class="emoji-picker hidden"></div>
                    </div>
                    <button id="voice-btn" class="icon-btn" title="پیام صوتی" aria-label="پیام صوتی">
                        <span class="material-symbols-rounded">mic</span>
                    </button>
                    <button id="send-btn" class="send-btn" title="ارسال" aria-label="ارسال">
                        <span class="material-symbols-rounded">send</span>
                    </button>
                </div>
                <input id="photo-input" type="file" accept="image/*" multiple class="hidden">
                <input id="video-input" type="file" accept="video/*" multiple class="hidden">
                <input id="file-input" type="file" multiple class="hidden">
            </section>

            <aside id="info-panel" class="info-panel hidden" aria-label="اطلاعات گفتگو">
                <div class="info-header">
                    <div class="info-title">اطلاعات گفتگو</div>
                    <button id="info-close" class="icon-btn" title="بستن" aria-label="بستن">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
                <div class="info-body">
                    <div class="info-hero">
                        <div id="info-avatar" class="info-avatar">👤</div>
                        <div class="info-text">
                            <div id="info-title" class="info-name">-</div>
                            <div id="info-subtitle" class="info-subtitle">-</div>
                        </div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">وضعیت</div>
                        <div id="info-status" class="info-value">-</div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">توضیحات</div>
                        <div id="info-description" class="info-value muted">-</div>
                    </div>
                    <div class="info-section">
                        <div class="info-label">اعضا</div>
                        <div id="info-members" class="info-value">-</div>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <aside id="profile-panel" class="profile-panel hidden" aria-label="پروفایل">
        <div class="profile-panel-header">
            <div class="profile-panel-title">پروفایل</div>
            <button id="profile-panel-close" class="icon-btn" title="بستن" aria-label="بستن">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="profile-panel-body">
            <div id="profile-panel-avatar" class="profile-panel-avatar">👤</div>
            <div id="profile-panel-name" class="profile-panel-name">-</div>
            <div id="profile-panel-username" class="profile-panel-username">-</div>
            <div id="profile-panel-status" class="profile-panel-status">-</div>
            <div class="profile-panel-section">
                <div class="profile-panel-label">درباره</div>
                <div id="profile-panel-bio" class="profile-panel-value muted">-</div>
            </div>
            <div class="profile-panel-section">
                <div class="profile-panel-label">ایمیل</div>
                <div id="profile-panel-email" class="profile-panel-value">-</div>
            </div>
            <div class="profile-panel-section">
                <div class="profile-panel-label">شماره تماس</div>
                <div id="profile-panel-phone" class="profile-panel-value">-</div>
            </div>
        </div>
    </aside>

    <div id="sidebar-menu-overlay" class="menu-overlay hidden"></div>
    <aside id="sidebar-menu" class="sidebar-menu hidden" aria-label="منوی کاربر">
        <div class="menu-header">
            <div id="menu-avatar" class="menu-avatar">👤</div>
            <div class="menu-user">
                <div id="menu-user-name" class="menu-user-name">-</div>
                <div id="menu-user-username" class="menu-user-username">-</div>
            </div>
        </div>
        <div class="menu-items">
            <button id="user-settings-btn" class="menu-item">
                <span class="material-symbols-rounded">settings</span>
                تنظیمات
            </button>
            <button id="menu-contacts-btn" class="menu-item">
                <span class="material-symbols-rounded">person</span>
                مخاطبین
            </button>
            <button id="menu-calls-btn" class="menu-item">
                <span class="material-symbols-rounded">call</span>
                تماس‌ها
            </button>
            <button id="menu-night-btn" class="menu-item">
                <span class="material-symbols-rounded">dark_mode</span>
                حالت شب
            </button>
            <button id="menu-logout-btn" class="menu-item danger">
                <span class="material-symbols-rounded">logout</span>
                خروج
            </button>
        </div>
    </aside>

    <div id="message-context-menu" class="context-menu hidden" role="menu"></div>
    <div id="message-action-sheet" class="action-sheet hidden">
        <div class="sheet">
            <div class="sheet-handle"></div>
            <div id="message-action-sheet-list" class="sheet-list"></div>
            <button id="message-action-sheet-cancel" class="sheet-cancel">لغو</button>
        </div>
    </div>

    <div id="delete-confirm-sheet" class="action-sheet hidden">
        <div class="sheet">
            <div class="sheet-handle"></div>
            <div class="sheet-title">حذف پیام</div>
            <div class="sheet-list">
                <button id="delete-for-me-btn" class="sheet-item">حذف برای من</button>
                <button id="delete-for-everyone-btn" class="sheet-item danger">حذف برای همه</button>
            </div>
            <button id="delete-cancel-btn" class="sheet-cancel">لغو</button>
        </div>
    </div>

    <div id="group-modal" class="modal hidden">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">گروه جدید</div>
                <button id="group-modal-close" class="icon-btn" aria-label="بستن">✖</button>
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
                <button id="group-settings-close" class="icon-btn" aria-label="بستن">✖</button>
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
                            <button id="group-invite-copy" type="button" class="icon-btn" title="کپی" aria-label="کپی">📋</button>
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

    <div id="user-settings-modal" class="modal hidden">
        <div class="modal-card wide">
            <div class="modal-header">
                <div class="modal-title">تنظیمات حساب</div>
                <button id="user-settings-close" class="icon-btn" aria-label="بستن">✖</button>
            </div>
            <div class="modal-body">
                <div class="settings-section">
                    <div class="section-title">پروفایل</div>
                    <div class="profile-row">
                        <div id="profile-avatar" class="profile-avatar">👤</div>
                        <div class="profile-actions">
                            <button id="profile-photo-change" class="send-btn small" type="button">تغییر عکس</button>
                            <button id="profile-photo-remove" class="icon-btn" type="button" title="حذف عکس" aria-label="حذف عکس">🗑️</button>
                            <input id="profile-photo-input" type="file" accept="image/*" class="hidden">
                        </div>
                    </div>
                    <div class="profile-form">
                        <label>نام کامل</label>
                        <input id="profile-name" type="text" required>
                        <label>نام کاربری</label>
                        <input id="profile-username" type="text" required>
                        <label>بیو</label>
                        <textarea id="profile-bio" rows="2" placeholder="درباره شما..."></textarea>
                        <label>ایمیل</label>
                        <input id="profile-email" type="email" required>
                        <label>شماره تماس</label>
                        <input id="profile-phone" type="text" placeholder="+98 ...">
                        <div id="profile-error" class="form-error"></div>
                        <button id="profile-save" class="send-btn" type="button">ذخیره پروفایل</button>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="section-title">حریم خصوصی</div>
                    <div class="toggle-row">
                        <div class="toggle-text">
                            <span>اجازه تماس صوتی</span>
                            <div class="toggle-desc">اگر خاموش باشد، هیچکس نمی‌تواند با شما تماس بگیرد.</div>
                        </div>
                        <input id="allow-voice-calls-toggle" type="checkbox">
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-text">
                            <span>آخرین بازدید</span>
                            <div class="toggle-desc">نحوه نمایش وضعیت آنلاین و آخرین بازدید شما.</div>
                        </div>
                        <select id="last-seen-privacy-select">
                            <option value="everyone">نمایش دقیق</option>
                            <option value="nobody">نمایش تقریبی (اخیراً / هفته اخیر / ماه اخیر)</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="reaction-modal" class="modal hidden">
        <div class="modal-card">
            <div class="modal-header">
                <div id="reaction-modal-title" class="modal-title">واکنش‌ها</div>
                <button id="reaction-modal-close" class="icon-btn" aria-label="بستن">✖</button>
            </div>
            <div id="reaction-modal-list" class="members-list"></div>
        </div>
    </div>

    <div id="calls-modal" class="modal hidden">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">تماس‌ها</div>
                <button id="calls-modal-close" class="icon-btn" aria-label="بستن">✖</button>
            </div>
            <div class="modal-body">
                <div id="calls-list" class="calls-list"></div>
            </div>
        </div>
    </div>

    <div id="lightbox" class="lightbox hidden">
        <div class="lightbox-inner">
            <img id="lightbox-img" alt="preview">
            <button id="lightbox-close" class="icon-btn" aria-label="بستن">✖</button>
        </div>
    </div>

    <?php include __DIR__ . '/templates/partials/call-overlay.html'; ?>

    <script nonce="<?php echo htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8'); ?>">
        window.SELO_CONFIG = {
            baseUrl: '<?php echo $config['app']['url'] ?? ''; ?>',
            basePath: '<?php echo $basePath; ?>',
            calls: <?php
                $callConfig = $config['calls'] ?? [];
                $iceServers = $callConfig['ice_servers'] ?? [];
                $callsPayload = [
                    'enabled' => isset($callConfig['enabled']) ? (bool)$callConfig['enabled'] : true,
                    'signalingUrl' => $callConfig['signaling_url'] ?? '',
                    'ringTimeoutSeconds' => (int)($callConfig['ring_timeout_seconds'] ?? 45),
                    'iceServers' => $iceServers,
                ];
                echo json_encode($callsPayload, JSON_UNESCAPED_UNICODE);
            ?>
        };
    </script>
    <script src="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/emoji-picker.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/js/call-ui.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script src="<?php echo htmlspecialchars(assetUrl($basePath, 'assets/app.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
