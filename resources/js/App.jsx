import React from 'react';

const shellMarkup = `<div id="app">
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
                    <form id="login-form" class="auth-form" method="post" action="/api/login" autocomplete="on">
                        <label for="login-identifier">نام کاربری یا ایمیل</label>
                        <input id="login-identifier" type="text" name="identifier" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                        <label for="login-password">رمز عبور</label>
                        <input id="login-password" type="password" name="password" autocomplete="current-password" required>
                        <button type="submit">ورود</button>
                    </form>
                    <form id="register-form" class="auth-form hidden" method="post" action="/api/register" autocomplete="on">
                        <label for="register-name">نام کامل</label>
                        <input id="register-name" type="text" name="full_name" autocomplete="name" required>
                        <label for="register-username">نام کاربری</label>
                        <input id="register-username" type="text" name="username" autocomplete="username" autocapitalize="none" spellcheck="false" required>
                        <label for="register-email">ایمیل (فقط Gmail)</label>
                        <input id="register-email" type="email" name="email" autocomplete="email" autocapitalize="none" spellcheck="false" required>
                        <label for="register-password">رمز عبور</label>
                        <input id="register-password" type="password" name="password" autocomplete="new-password" required>
                        <button type="submit">ثبت‌نام</button>
                    </form>
                </div>
                <div id="auth-error" class="auth-error"></div>
            </div>
        </div>

        <div id="main-view" class="main-view hidden">
            <div id="network-status" class="network-status hidden" role="status" aria-live="polite"></div>
            <aside class="sidebar">
                <div class="sidebar-header">
                    <button id="sidebar-menu-btn" class="icon-btn menu-btn" title="منو" aria-label="منو">
                        <span class="material-symbols-rounded">menu</span>
                    </button>
                    <div class="sidebar-brand">
                        <div class="brand-mini">SELO</div>
                        <div class="brand-subtitle">سلو</div>
                    </div>
                    <div class="sidebar-actions legacy-header-actions" aria-hidden="true">
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
                    <label class="sr-only" for="user-search">جستجوی کاربر</label>
                    <input id="user-search" type="text" placeholder="جستجوی نام کاربری..." role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-results" aria-haspopup="listbox" autocomplete="off">
                    <div id="search-results" class="search-results" role="listbox" aria-label="نتایج جستجو"></div>
                </div>
                <div id="unread-notice" class="unread-notice hidden" aria-live="polite">
                    <span class="unread-count" id="unread-count">0</span>
                    <span class="unread-text">پیام جدید</span>
                </div>
                <div id="chat-list" class="chat-list" role="listbox" aria-label="لیست گفتگوها" aria-orientation="vertical"></div>
                <section id="sidebar-menu-view" class="sidebar-view sidebar-menu-view hidden" aria-label="منوی برنامه">
                    <div class="sidebar-view-header">
                        <button id="sidebar-menu-back" class="icon-btn" type="button" title="بازگشت" aria-label="بازگشت">
                            <span class="material-symbols-rounded">arrow_forward</span>
                        </button>
                        <div>
                            <div class="sidebar-view-title">منو</div>
                            <div class="sidebar-view-subtitle">تنظیمات و ابزارهای حساب</div>
                        </div>
                    </div>
                    <div class="sidebar-menu-card">
                        <div id="sidebar-menu-avatar" class="menu-avatar" role="button" tabindex="0" aria-label="تنظیمات حساب">👤</div>
                        <div class="menu-user">
                            <div id="sidebar-menu-user-name" class="menu-user-name">-</div>
                            <div id="sidebar-menu-user-username" class="menu-user-username">-</div>
                        </div>
                    </div>
                    <div class="sidebar-view-body menu-items">
                        <button id="sidebar-account-settings-btn" class="menu-item" type="button">
                            <span class="material-symbols-rounded">manage_accounts</span>
                            تنظیمات حساب کاربری
                        </button>
                        <button id="sidebar-new-group-btn" class="menu-item" type="button">
                            <span class="material-symbols-rounded">group_add</span>
                            ایجاد گروه جدید
                        </button>
                        <button id="sidebar-contacts-btn" class="menu-item" type="button">
                            <span class="material-symbols-rounded">person</span>
                            مخاطبین
                        </button>
                        <button id="sidebar-theme-btn" class="menu-item" type="button">
                            <span class="material-symbols-rounded">dark_mode</span>
                            حالت شب / روز
                        </button>
                        <button id="sidebar-logout-btn" class="menu-item danger" type="button">
                            <span class="material-symbols-rounded">logout</span>
                            خروج
                        </button>
                    </div>
                </section>
                <section id="sidebar-settings-view" class="sidebar-view sidebar-settings-view hidden" aria-label="تنظیمات حساب">
                    <div class="sidebar-view-header">
                        <button id="sidebar-settings-back" class="icon-btn" type="button" title="بازگشت" aria-label="بازگشت">
                            <span class="material-symbols-rounded">arrow_forward</span>
                        </button>
                        <div>
                            <div class="sidebar-view-title">تنظیمات</div>
                            <div class="sidebar-view-subtitle">حساب، گروه جدید و ظاهر برنامه</div>
                        </div>
                    </div>
                    <div id="sidebar-settings-body" class="sidebar-view-body"></div>
                </section>
            </aside>

            <section class="chat-panel">
                <div class="chat-header">
                    <button id="back-to-chats" class="icon-btn mobile-only" title="بازگشت" aria-label="بازگشت">
                        <span class="material-symbols-rounded">arrow_forward</span>
                    </button>
                    <div class="chat-user" id="chat-user-header" role="button" tabindex="0" aria-label="نمایش اطلاعات گفتگو">
                        <div id="chat-user-avatar" class="avatar"></div>
                        <div class="chat-user-meta">
                            <div id="chat-user-name" class="chat-user-name">گفتگو</div>
                            <div id="chat-user-username" class="chat-user-username"></div>
                            <div id="chat-user-status" class="chat-user-status"></div>
                        </div>
                    </div>
                    <div class="chat-header-actions">
                        <button id="chat-call-btn" class="icon-btn chat-call-action hidden" title="تماس" aria-label="تماس">
                            <span class="material-symbols-rounded">call</span>
                        </button>
                        <button id="chat-search-btn" class="icon-btn" title="جستجو در گفتگو" aria-label="جستجو در گفتگو">
                            <span class="material-symbols-rounded">search</span>
                        </button>
                        <button id="chat-more-btn" class="icon-btn" title="گزینه‌های گفتگو" aria-label="گزینه‌های گفتگو">
                            <span class="material-symbols-rounded">more_horiz</span>
                        </button>
                    </div>
                    <div class="legacy-header-actions" aria-hidden="true">
                        <button id="group-settings-btn" class="icon-btn hidden" title="تنظیمات گروه" aria-label="تنظیمات گروه">
                            <span class="material-symbols-rounded">tune</span>
                        </button>
                        <button id="info-toggle" class="icon-btn" title="اطلاعات گفتگو" aria-label="اطلاعات گفتگو">
                            <span class="material-symbols-rounded">info</span>
                        </button>
                    </div>
                </div>
                <div id="chat-search-bar" class="chat-search-bar hidden" role="search" aria-label="جستجو در پیام‌های گفتگو">
                    <span class="material-symbols-rounded">search</span>
                    <input id="chat-message-search" type="search" placeholder="جستجو در همین گفتگو..." autocomplete="off" />
                    <div id="chat-search-count" class="chat-search-count">۰ نتیجه</div>
                    <button id="chat-search-prev" class="icon-btn small" title="نتیجه قبلی" aria-label="نتیجه قبلی">
                        <span class="material-symbols-rounded">keyboard_arrow_up</span>
                    </button>
                    <button id="chat-search-next" class="icon-btn small" title="نتیجه بعدی" aria-label="نتیجه بعدی">
                        <span class="material-symbols-rounded">keyboard_arrow_down</span>
                    </button>
                    <button id="chat-search-close" class="icon-btn small" title="بستن جستجو" aria-label="بستن جستجو">
                        <span class="material-symbols-rounded">close</span>
                    </button>
                </div>
                <div id="conversation-menu" class="conversation-menu hidden" role="menu" aria-label="گزینه‌های گفتگو"></div>
                <div id="messages" class="messages" role="log" aria-live="polite" aria-relevant="additions text" aria-label="پیام‌ها"></div>
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
                <div id="pinned-bar" class="pinned-bar hidden">
                    <div class="pinned-content">
                        <span class="material-symbols-rounded">keep</span>
                        <span class="pinned-label">پیام پین‌شده</span>
                        <button id="pinned-preview" type="button" class="pinned-preview" aria-label="رفتن به پیام پین شده"></button>
                    </div>
                    <button id="pinned-clear" class="icon-btn" title="برداشتن پین" aria-label="برداشتن پین">
                        <span class="material-symbols-rounded">close</span>
                    </button>
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
                        <textarea id="message-input" rows="1" placeholder="پیام بنویسید..." aria-label="متن پیام"></textarea>
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
            <div class="profile-panel-head">
                <div id="profile-panel-title" class="profile-panel-title">پروفایل</div>
                <div id="profile-panel-mode" class="profile-panel-mode">کاربر</div>
            </div>
            <button id="profile-panel-close" class="icon-btn" title="بستن" aria-label="بستن">
                <span class="material-symbols-rounded">close</span>
            </button>
        </div>
        <div class="profile-panel-body">
            <div id="profile-panel-avatar" class="profile-panel-avatar">👤</div>
            <div id="profile-panel-name" class="profile-panel-name">-</div>
            <div id="profile-panel-username" class="profile-panel-username">-</div>
            <div id="profile-panel-status" class="profile-panel-status">-</div>
            <div class="profile-panel-actions">
                <button id="profile-panel-edit-btn" class="send-btn small" type="button">ویرایش / تنظیمات</button>
                <button id="profile-panel-secondary-btn" class="icon-btn" type="button" aria-label="عملیات تکمیلی">
                    <span class="material-symbols-rounded">tune</span>
                </button>
            </div>
            <div class="profile-panel-section">
                <div id="profile-panel-bio-label" class="profile-panel-label">درباره</div>
                <div id="profile-panel-bio" class="profile-panel-value muted">-</div>
            </div>
            <div class="profile-panel-section">
                <div id="profile-panel-email-label" class="profile-panel-label">ایمیل</div>
                <div id="profile-panel-email" class="profile-panel-value">-</div>
            </div>
            <div class="profile-panel-section">
                <div id="profile-panel-phone-label" class="profile-panel-label">شماره تماس</div>
                <div id="profile-panel-phone" class="profile-panel-value">-</div>
            </div>
            <div id="profile-panel-group-actions" class="profile-panel-section hidden">
                <div class="profile-panel-label">تنظیمات گروه</div>
                <div class="profile-panel-group-buttons">
                    <button id="profile-panel-group-settings" class="send-btn small" type="button">تنظیمات گروه</button>
                    <button id="profile-panel-group-invite" class="icon-btn" type="button" aria-label="دعوت عضو">
                        <span class="material-symbols-rounded">person_add</span>
                    </button>
                </div>
            </div>
            <div id="profile-panel-embedded" class="profile-panel-embedded"></div>
        </div>
    </aside>

    <div id="sidebar-menu-overlay" class="menu-overlay hidden"></div>
    <aside id="sidebar-menu" class="sidebar-menu hidden" aria-label="منوی کاربر">
        <div class="menu-header">
            <div id="menu-avatar" class="menu-avatar" role="button" tabindex="0" aria-label="نمایش پروفایل">👤</div>
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

    <div id="group-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="group-modal-title" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-header">
                <div id="group-modal-title" class="modal-title">گروه جدید</div>
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

    <div id="group-settings-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="group-settings-title" aria-hidden="true">
        <div class="modal-card wide">
            <div class="modal-header">
                <div id="group-settings-title" class="modal-title">تنظیمات گروه</div>
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

    <div id="user-settings-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="user-settings-title" aria-hidden="true">
        <div class="modal-card wide">
            <div class="modal-header">
                <div id="user-settings-title" class="modal-title">تنظیمات حساب</div>
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
                            <span>آخرین بازدید</span>
                            <div class="toggle-desc">نحوه نمایش وضعیت آنلاین و آخرین بازدید شما.</div>
                        </div>
                        <select id="last-seen-privacy-select">
                            <option value="everyone">نمایش دقیق</option>
                            <option value="nobody">نمایش تقریبی (اخیراً / هفته اخیر / ماه اخیر)</option>
                        </select>
                    </div>
                </div>
                <div class="settings-section">
                    <div class="section-title">برنامه</div>
                    <button id="settings-new-group-btn" class="menu-item" type="button">
                        <span class="material-symbols-rounded">group_add</span>
                        گروه جدید
                    </button>
                    <button id="settings-theme-toggle" class="menu-item" type="button">
                        <span class="material-symbols-rounded">dark_mode</span>
                        تغییر تم
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="reaction-modal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="reaction-modal-title" aria-hidden="true">
        <div class="modal-card">
            <div class="modal-header">
                <div id="reaction-modal-title" class="modal-title">واکنش‌ها</div>
                <button id="reaction-modal-close" class="icon-btn" aria-label="بستن">✖</button>
            </div>
            <div id="reaction-modal-list" class="members-list"></div>
        </div>
    </div>

    <div id="lightbox" class="lightbox hidden" role="dialog" aria-modal="true" aria-label="پیش‌نمایش تصویر" aria-hidden="true">
        <div class="lightbox-inner">
            <img id="lightbox-img" alt="preview">
            <button id="lightbox-close" class="icon-btn" aria-label="بستن">✖</button>
        </div>
    </div>
    <div id="toast-region" class="toast-region" aria-live="polite" aria-atomic="false"></div>
    <div id="live-region" class="sr-only" aria-live="polite" aria-atomic="true"></div>`;

export default function App() {
  return <div dangerouslySetInnerHTML={{ __html: shellMarkup }} />;
}
