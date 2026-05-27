import React, {useCallback, useEffect, useMemo, useRef, useState} from 'react';

const config = window.SELO_CONFIG || {};
const basePath = (config.basePath || '').replace(/\/$/, '');

function csrfToken() {
    return document.cookie
        .split(';')
        .map((item) => item.trim())
        .find((item) => item.startsWith('selo_csrf='))
        ?.split('=')
        .slice(1)
        .join('=') || '';
}

function apiUrl(path, params = {}) {
    const url = new URL(`${basePath}/api${path}`, window.location.origin);
    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            url.searchParams.set(key, value);
        }
    });
    return url.toString();
}

async function apiRequest(path, options = {}, retry = true) {
    const headers = {
        Accept: 'application/json',
        ...(options.headers || {}),
    };

    const hasBody = options.body !== undefined;
    const isFormData = hasBody && options.body instanceof FormData;
    if (hasBody && !isFormData) {
        headers['Content-Type'] = 'application/json';
    }

    const csrf = csrfToken();
    if (csrf && ['POST', 'PUT', 'PATCH', 'DELETE'].includes((options.method || 'GET').toUpperCase())) {
        headers['X-CSRF-Token'] = csrf;
    }

    const response = await fetch(apiUrl(path, options.params), {
        credentials: 'same-origin',
        ...options,
        headers,
        body: hasBody && !isFormData ? JSON.stringify(options.body) : options.body,
    });

    if (response.status === 401 && retry && path !== '/token/refresh') {
        const refreshed = await apiRequest('/token/refresh', {method: 'POST'}, false).catch(() => null);
        if (refreshed?.ok) {
            return apiRequest(path, options, false);
        }
    }

    const payload = await response.json().catch(() => ({
        ok: false,
        error: 'پاسخ سرور قابل خواندن نیست.',
    }));

    if (!response.ok || payload.ok === false) {
        throw new Error(payload.error || 'درخواست ناموفق بود.');
    }

    return payload;
}

function formatTime(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return '';
    return new Intl.DateTimeFormat('fa-IR', {hour: '2-digit', minute: '2-digit'}).format(date);
}

function initials(name = '?') {
    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || '?';
}

function chatTitle(chat) {
    return chat?.chat_type === 'group' ? chat.title : chat?.other_name || 'گفتگو';
}

function chatSubtitle(chat) {
    if (!chat) return 'یک گفتگو را انتخاب کنید';
    if (chat.chat_type === 'group') {
        const onlineStr = chat.online_count > 0 ? ` (${chat.online_count} آنلاین)` : '';
        return `${chat.members_count || 0} عضو${onlineStr}`;
    }
    return chat.status_text || `@${chat.other_username}`;
}

function AuthScreen({onAuthenticated}) {
    const [mode, setMode] = useState('login');
    const [form, setForm] = useState({});
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    const update = (event) => setForm((current) => ({...current, [event.target.name]: event.target.value}));

    async function submit(event) {
        event.preventDefault();
        setError('');
        setLoading(true);
        try {
            const path = mode === 'login' ? '/login' : '/register';
            const body = mode === 'login'
                ? {identifier: form.identifier, password: form.password}
                : {
                    full_name: form.full_name,
                    username: form.username,
                    email: form.email,
                    password: form.password,
                };
            await apiRequest(path, {method: 'POST', body});
            await onAuthenticated();
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <main className="auth-view">
            <section className="auth-card" aria-label="ورود به سلو">
                <div className="auth-brand">
                    <div className="brand-mark">S</div>
                    <div>
                        <h1>SELO</h1>
                    </div>
                </div>

                <div className="segmented" role="tablist" aria-label="نوع ورود">
                    <button type="button" className={mode === 'login' ? 'active' : ''}
                            onClick={() => setMode('login')}>ورود
                    </button>
                    <button type="button" className={mode === 'register' ? 'active' : ''}
                            onClick={() => setMode('register')}>ثبت‌نام
                    </button>
                </div>

                <form className="auth-form" onSubmit={submit}>
                    {mode === 'register' && (
                        <>
                            <label>نام کامل<input name="full_name" autoComplete="name" required
                                                   onChange={update}/></label>
                            <label>نام کاربری<input name="username" autoComplete="username" dir="ltr" required
                                                    onChange={update}/></label>
                            <label>ایمیل Gmail<input name="email" type="email" autoComplete="email" dir="ltr" required
                                                     onChange={update}/></label>
                        </>
                    )}
                    {mode === 'login' && (
                        <label>نام کاربری یا ایمیل<input name="identifier" autoComplete="username" dir="ltr" required
                                                         onChange={update}/></label>
                    )}
                    <label>رمز عبور<input name="password" type="password"
                                          autoComplete={mode === 'login' ? 'current-password' : 'new-password'} required
                                          onChange={update}/></label>
                    {error && <div className="form-error" role="alert">{error}</div>}
                    <button className="primary-btn" type="submit"
                            disabled={loading}>{loading ? 'در حال پردازش...' : mode === 'login' ? 'ورود' : 'ساخت حساب'}</button>
                </form>
            </section>
        </main>
    );
}

function Icon({name}) {
    return <span className="material-symbols-rounded" aria-hidden="true">{name}</span>;
}

// Swiper-like carousel component for viewing multiple profile pictures
function PhotoCarousel({photos, fallbackName}) {
    const [index, setIndex] = useState(0);

    if (!photos || photos.length === 0) {
        return (
            <div className="avatar-carousel">
                <div className="brand-mark large" style={{width:'100%', height:'100%', borderRadius:'0', fontSize:'5rem'}}>
                    {initials(fallbackName)}
                </div>
            </div>
        );
    }

    const current = photos[index];
    const photoUrl = `${basePath}/photo.php?id=${current.id}`;

    return (
        <div className="avatar-carousel">
            <img src={photoUrl} alt="پروفایل" className="carousel-img" />
            {photos.length > 1 && (
                <>
                    <button type="button" className="carousel-ctrl prev" onClick={() => setIndex((index + 1) % photos.length)}>
                        <Icon name="chevron_right" />
                    </button>
                    <button type="button" className="carousel-ctrl next" onClick={() => setIndex((index - 1 + photos.length) % photos.length)}>
                        <Icon name="chevron_left" />
                    </button>
                    <div className="carousel-dots">
                        {photos.map((_, i) => (
                            <button
                                key={i}
                                type="button"
                                className={`carousel-dot ${i === index ? 'active' : ''}`}
                                onClick={() => setIndex(i)}
                            />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}

function Sidebar({
                     chats,
                     activeChat,
                     user,
                     searchQuery,
                     searchResults,
                     onSearch,
                     onSelectChat,
                     onStartChat,
                     onOpenSettings,
                 }) {
    const activePhotoUrl = user?.active_photo_id
        ? `${basePath}/photo.php?id=${user.active_photo_id}&thumb=1`
        : null;

    return (
        <aside className="sidebar">
            <header className="sidebar-header">
                <button className="icon-btn" type="button" title="تنظیمات" onClick={onOpenSettings}>
                    <Icon name="settings"/>
                </button>
                <div className="account-chip" style={{cursor: 'pointer'}} onClick={onOpenSettings}>
                    {activePhotoUrl ? (
                        <img src={activePhotoUrl} className="avatar" style={{objectFit: 'cover'}} alt="پروفایل" />
                    ) : (
                        <div className="avatar">{initials(user?.full_name)}</div>
                    )}
                    <div>
                        <strong>{user?.full_name || 'SELO'}</strong>
                        <span>@{user?.username || 'user'}</span>
                    </div>
                </div>
            </header>

            <div className="sidebar-tools">
                <div className="search-box">
                    <Icon name="search"/>
                    <input value={searchQuery} onChange={(event) => onSearch(event.target.value)}
                           placeholder="جستجوی نام کاربری..."/>
                </div>
            </div>

            {searchResults.length > 0 && (
                <div className="search-results">
                    {searchResults.map((result) => {
                        const avatarUrl = result.active_photo_id
                            ? `${basePath}/photo.php?id=${result.active_photo_id}&thumb=1`
                            : null;
                        return (
                            <button key={result.id} type="button" onClick={() => onStartChat(result)}>
                                {avatarUrl ? (
                                    <img src={avatarUrl} className="avatar small" style={{objectFit: 'cover'}} alt="پروفایل" />
                                ) : (
                                    <span className="avatar small">{initials(result.full_name)}</span>
                                )}
                                <span>
                                    <strong>{result.full_name}</strong>
                                    <small>@{result.username}</small>
                                </span>
                            </button>
                        );
                    })}
                </div>
            )}

            <div className="chat-list" role="listbox" aria-label="گفتگوها">
                {chats.map((chat) => {
                    const avatarUrl = chat.chat_type === 'group'
                        ? null
                        : (chat.other_photo ? `${basePath}/photo.php?id=${chat.other_photo}&thumb=1` : null);

                    return (
                        <button
                            key={`${chat.chat_type}-${chat.id}`}
                            type="button"
                            className={`chat-item ${activeChat?.id === chat.id && activeChat?.chat_type === chat.chat_type ? 'active' : ''}`}
                            onClick={() => onSelectChat(chat)}
                        >
                            {avatarUrl ? (
                                <img src={avatarUrl} className="avatar" style={{objectFit: 'cover'}} alt={chatTitle(chat)} />
                            ) : (
                                <span className="avatar">{initials(chatTitle(chat))}</span>
                            )}
                            <span className="chat-copy">
                              <span className="chat-row">
                                <strong>{chatTitle(chat)}</strong>
                                <small>{formatTime(chat.last_message_at)}</small>
                              </span>
                              <span className="chat-row muted">
                                <span>{chat.last_preview || chatSubtitle(chat)}</span>
                                  {chat.unread_count > 0 && <b>{chat.unread_count}</b>}
                              </span>
                            </span>
                        </button>
                    );
                })}
                {chats.length === 0 && <div className="empty-list">هنوز گفتگویی ندارید.</div>}
            </div>
        </aside>
    );
}

function MessageBubble({message, currentUserId, onUserClick}) {
    const mine = Number(message.sender_id) === Number(currentUserId);
    const media = message.media || message.attachments?.[0]?.media;

    return (
        <article className={`message ${mine ? 'outgoing' : 'incoming'}`}>
            {!mine && (
                <div className="sender-name" style={{cursor: 'pointer'}} onClick={() => onUserClick(message.sender_id)}>
                    {message.sender_name}
                </div>
            )}
            {message.reply_id && <div className="reply-preview">پاسخ
                به: {message.reply_body || message.reply_media_name || 'پیام'}</div>}
            {media && (
                <div style={{margin: '4px 0'}}>
                    {media.type === 'photo' && (
                        <img
                            className="message-image"
                            src={apiUrl(`/media/${media.id}`)}
                            alt={media.original_name}
                            onClick={() => window.open(apiUrl(`/media/${media.id}`), '_blank')}
                        />
                    )}
                    {media.type === 'video' && (
                        <video
                            className="message-video"
                            src={apiUrl(`/media/${media.id}`)}
                            controls
                        />
                    )}
                    {media.type === 'voice' && (
                        <audio
                            className="message-audio"
                            src={apiUrl(`/media/${media.id}`)}
                            controls
                        />
                    )}
                    {media.type !== 'photo' && media.type !== 'video' && media.type !== 'voice' && (
                        <a className="media-chip" href={apiUrl(`/media/${media.id}`)} target="_blank" rel="noreferrer">
                            <Icon name="attach_file"/>
                            {media.original_name || 'پیوست'}
                        </a>
                    )}
                </div>
            )}
            {message.body && <p>{message.body}</p>}
            <time>{formatTime(message.created_at)}</time>
        </article>
    );
}

function ChatPanel({
                       chat,
                       messages,
                       currentUserId,
                       draft,
                       onDraft,
                       onSend,
                       loading,
                       onBack,
                       onUploadFile,
                       onSendVoice,
                       onHeaderClick,
                       onUserClick,
                       uploadsInProgress
                   }) {
    const bottomRef = useRef(null);
    const fileInputRef = useRef(null);
    const [recording, setRecording] = useState(false);
    const [recSeconds, setRecSeconds] = useState(0);
    const mediaRecorderRef = useRef(null);
    const audioChunksRef = useRef([]);
    const timerRef = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({behavior: 'smooth', block: 'end'});
    }, [messages, chat?.id, uploadsInProgress]);

    useEffect(() => {
        if (recording) {
            setRecSeconds(0);
            timerRef.current = window.setInterval(() => {
                setRecSeconds((s) => s + 1);
            }, 1000);
        } else {
            if (timerRef.current) {
                window.clearInterval(timerRef.current);
            }
        }
        return () => {
            if (timerRef.current) window.clearInterval(timerRef.current);
        };
    }, [recording]);

    if (!chat) {
        return (
            <section className="chat-panel welcome-panel">
                <div className="welcome-copy">
                    <div className="brand-mark large">S</div>
                    <h2>SELO</h2>
                    <p>یک گفتگو را از لیست انتخاب کنید یا با جستجوی نام کاربری گفتگوی تازه بسازید.</p>
                </div>
            </section>
        );
    }

    function submit(event) {
        event.preventDefault();
        onSend();
    }

    async function handleFileChange(event) {
        const files = event.target.files;
        if (!files || files.length === 0) return;
        for (let i = 0; i < files.length; i++) {
            await onUploadFile(files[i]);
        }
        if (fileInputRef.current) fileInputRef.current.value = '';
    }

    async function startRecording() {
        try {
            const getMedia = (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) ||
                             navigator.getUserMedia ||
                             navigator.webkitGetUserMedia ||
                             navigator.mozGetUserMedia ||
                             navigator.msGetUserMedia;
            
            if (!getMedia) {
                alert('مرورگر شما ضبط صدا را پشتیبانی نمی‌کند یا برای استفاده از میکروفون به اتصال امن HTTPS نیاز است.');
                return;
            }

            const stream = navigator.mediaDevices
                ? await navigator.mediaDevices.getUserMedia({ audio: true })
                : await new Promise((resolve, reject) => {
                    getMedia.call(navigator, { audio: true }, resolve, reject);
                  });

            audioChunksRef.current = [];
            const mediaRecorder = new MediaRecorder(stream);
            mediaRecorderRef.current = mediaRecorder;
            mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    audioChunksRef.current.push(event.data);
                }
            };
            mediaRecorder.onstop = async () => {
                const audioBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });
                const file = new File([audioBlob], `voice-${Date.now()}.webm`, { type: 'audio/webm' });
                await onSendVoice(file);
                stream.getTracks().forEach((track) => track.stop());
            };
            mediaRecorder.start();
            setRecording(true);
        } catch (err) {
            alert('دسترسی به میکروفون امکان‌پذیر نیست. ممکن است نیاز به اتصال امن HTTPS باشد: ' + err.message);
        }
    }

    function stopRecording(send = true) {
        if (!mediaRecorderRef.current) return;
        if (send) {
            mediaRecorderRef.current.stop();
        } else {
            mediaRecorderRef.current.onstop = () => {};
            mediaRecorderRef.current.stop();
            mediaRecorderRef.current.stream.getTracks().forEach((track) => track.stop());
        }
        setRecording(false);
    }

    const formatRecTime = (sec) => {
        const m = Math.floor(sec / 60);
        const s = sec % 60;
        return `${m}:${s < 10 ? '0' : ''}${s}`;
    };

    const chatAvatarUrl = chat.chat_type === 'group'
        ? null
        : (chat.other_photo ? `${basePath}/photo.php?id=${chat.other_photo}&thumb=1` : null);

    return (
        <section className="chat-panel">
            <header className="chat-header">
                <button className="icon-btn back-btn" type="button" onClick={onBack} title="بازگشت">
                    <Icon name="arrow_back"/>
                </button>
                <div style={{display:'flex', alignItems:'center', cursor:'pointer', gap:'12px'}} onClick={onHeaderClick}>
                    {chatAvatarUrl ? (
                        <img src={chatAvatarUrl} className="avatar" style={{objectFit:'cover'}} alt={chatTitle(chat)} />
                    ) : (
                        <div className="avatar">{initials(chatTitle(chat))}</div>
                    )}
                    <div className="chat-meta">
                        <h2>{chatTitle(chat)}</h2>
                        <p>{chatSubtitle(chat)}</p>
                    </div>
                </div>
            </header>

            <div className="messages" role="log" aria-live="polite">
                {messages.map((message) => (
                    <MessageBubble key={message.id || message.client_id} message={message}
                                   currentUserId={currentUserId} onUserClick={onUserClick}/>
                ))}
                {uploadsInProgress && uploadsInProgress.map((u) => (
                    <article key={u.id} className="message outgoing" style={{opacity: 0.9, background: 'var(--bubble-out)'}}>
                        <div style={{display:'flex', alignItems:'center', gap:'8px', minWidth:'220px'}}>
                            <Icon name="cloud_upload" />
                            <div style={{flex: 1}}>
                                <div style={{fontSize:'12px', fontWeight:'bold', overflow:'hidden', textOverflow:'ellipsis', whiteSpace:'nowrap', maxWidth:'180px'}}>{u.name}</div>
                                <div style={{fontSize:'10px', color:'var(--muted)'}}>در حال آپلود... {u.progress}%</div>
                                <div style={{width:'100%', height:'4px', background:'rgba(0,0,0,0.1)', borderRadius:'2px', marginTop:'4px', overflow:'hidden'}}>
                                    <div style={{width:`${u.progress}%`, height:'100%', background:'var(--blue)', transition:'width 0.1s ease'}} />
                                </div>
                            </div>
                        </div>
                    </article>
                ))}
                {messages.length === 0 && uploadsInProgress.length === 0 && <div className="empty-chat">اینجا هنوز پیامی نیست.</div>}
                <div ref={bottomRef}/>
            </div>

            <form className="composer" onSubmit={submit}>
                <input
                    type="file"
                    ref={fileInputRef}
                    onChange={handleFileChange}
                    style={{display: 'none'}}
                    multiple
                />
                <button
                    className="icon-btn"
                    type="button"
                    title="پیوست فایل"
                    disabled={recording}
                    onClick={() => fileInputRef.current?.click()}
                >
                    <Icon name="attach_file"/>
                </button>

                {recording ? (
                    <div style={{display:'flex', flex:1, alignItems:'center', justifyContent:'space-between'}}>
                        <div className="recording-indicator">
                            <span className="recording-dot" />
                            <span>در حال ضبط پیام صوتی ({formatRecTime(recSeconds)})</span>
                        </div>
                        <button type="button" className="icon-btn danger" style={{marginLeft:'8px'}} onClick={() => stopRecording(false)}>
                            <Icon name="delete" />
                        </button>
                    </div>
                ) : (
                    <textarea
                        value={draft}
                        onChange={(event) => onDraft(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter' && !event.shiftKey) {
                                event.preventDefault();
                                onSend();
                            }
                        }}
                        rows={1}
                        placeholder="پیام بنویسید..."
                        aria-label="متن پیام"
                    />
                )}

                {draft.trim() === '' ? (
                    recording ? (
                        <button className="send-fab" type="button" onClick={() => stopRecording(true)} title="ارسال پیام صوتی">
                            <Icon name="send"/>
                        </button>
                    ) : (
                        <button className="voice-btn" type="button" onClick={startRecording} title="ضبط صدا">
                            <Icon name="mic"/>
                        </button>
                    )
                ) : (
                    <button className="send-fab" type="submit" disabled={loading} title="ارسال">
                        <Icon name="send"/>
                    </button>
                )}
            </form>
        </section>
    );
}

// User Profile Info Dialog
function UserProfileDialog({userId, onClose}) {
    const [profile, setProfile] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!userId) return;
        setLoading(true);
        apiRequest(`/users/${userId}`)
            .then((res) => {
                setProfile(res.data);
            })
            .catch((err) => {
                alert('خطا در بارگذاری پروفایل کاربر: ' + err.message);
            })
            .finally(() => setLoading(false));
    }, [userId]);

    if (!userId) return null;

    return (
        <div className="dialog-backdrop" role="presentation">
            <section className="dialog dialog-wide" role="dialog" aria-modal="true" aria-label="پروفایل کاربر">
                <header>
                    <h2>اطلاعات کاربر</h2>
                    <button className="icon-btn" type="button" onClick={onClose}><Icon name="close"/></button>
                </header>
                {loading ? (
                    <div style={{textAlign:'center', padding:'24px'}}>در حال دریافت...</div>
                ) : profile ? (
                    <div className="profile-card">
                        <PhotoCarousel photos={profile.photos} fallbackName={profile.user.full_name} />
                        <div className="profile-meta-grid">
                            <div className="profile-item">
                                <strong>نام کامل</strong>
                                <span>{profile.user.full_name}</span>
                            </div>
                            <div className="profile-item">
                                <strong>نام کاربری</strong>
                                <span dir="ltr">@{profile.user.username}</span>
                            </div>
                            <div className="profile-item">
                                <strong>آخرین بازدید</strong>
                                <span>{profile.user.status_text}</span>
                            </div>
                            {profile.user.bio && (
                                <div className="profile-item">
                                    <strong>بیوگرافی</strong>
                                    <span>{profile.user.bio}</span>
                                </div>
                            )}
                            {profile.user.phone && (
                                <div className="profile-item">
                                    <strong>شماره تلفن</strong>
                                    <span dir="ltr">{profile.user.phone}</span>
                                </div>
                            )}
                        </div>
                    </div>
                ) : (
                    <div>اطلاعات کاربر یافت نشد.</div>
                )}
            </section>
        </div>
    );
}

// Group Profile & Settings Info Dialog
function GroupProfileDialog({groupId, currentUser, onClose, onGroupUpdated}) {
    const [info, setInfo] = useState(null);
    const [loading, setLoading] = useState(true);
    const [updating, setUpdating] = useState(false);

    // Edit states
    const [title, setTitle] = useState('');
    const [description, setDescription] = useState('');
    const [allowInvites, setAllowInvites] = useState(true);
    const [allowPhotos, setAllowPhotos] = useState(true);
    const [allowVideos, setAllowVideos] = useState(true);
    const [allowVoice, setAllowVoice] = useState(true);
    const [allowFiles, setAllowFiles] = useState(true);

    const loadGroup = useCallback(() => {
        if (!groupId) return;
        setLoading(true);
        apiRequest(`/groups/${groupId}`)
            .then((res) => {
                setInfo(res.data);
                setTitle(res.data.group.title);
                setDescription(res.data.group.description || '');
                setAllowInvites(res.data.group.allow_member_invites === 1);
                setAllowPhotos(res.data.group.allow_photos === 1);
                setAllowVideos(res.data.group.allow_videos === 1);
                setAllowVoice(res.data.group.allow_voice === 1);
                setAllowFiles(res.data.group.allow_files === 1);
            })
            .catch((err) => {
                alert('خطا در بارگذاری اطلاعات گروه: ' + err.message);
            })
            .finally(() => setLoading(false));
    }, [groupId]);

    useEffect(() => {
        loadGroup();
    }, [loadGroup]);

    if (!groupId) return null;

    async function handleSaveSettings(event) {
        event.preventDefault();
        setUpdating(true);
        try {
            await apiRequest(`/groups/${groupId}`, {
                method: 'PATCH',
                body: {
                    title,
                    description,
                    allow_member_invites: allowInvites,
                    allow_photos: allowPhotos,
                    allow_videos: allowVideos,
                    allow_voice: allowVoice,
                    allow_files: allowFiles
                }
            });
            alert('تنظیمات گروه با موفقیت بروزرسانی شد.');
            onGroupUpdated();
            loadGroup();
        } catch (err) {
            alert('بروزرسانی گروه ناموفق بود: ' + err.message);
        } finally {
            setUpdating(false);
        }
    }

    const isOwner = info ? Number(info.group.owner_user_id) === Number(currentUser.id) : false;

    return (
        <div className="dialog-backdrop" role="presentation">
            <section className="dialog dialog-wide" role="dialog" aria-modal="true" aria-label="تنظیمات گروه">
                <header>
                    <h2>پروفایل و تنظیمات گروه</h2>
                    <button className="icon-btn" type="button" onClick={onClose}><Icon name="close"/></button>
                </header>
                {loading ? (
                    <div style={{textAlign:'center', padding:'24px'}}>در حال دریافت...</div>
                ) : info ? (
                    <div className="profile-card" style={{maxHeight:'80vh', overflowY:'auto'}}>
                        <div className="avatar-carousel" style={{background: 'linear-gradient(135deg, #52a8f7, #2478c8)', display:'grid', placeItems:'center', color:'#fff', fontSize:'4rem', height:'120px', borderRadius:'8px'}}>
                            {initials(info.group.title)}
                        </div>

                        <form onSubmit={handleSaveSettings} className="dialog-form">
                            <label>نام گروه
                                <input value={title} onChange={(e) => setTitle(e.target.value)} required disabled={!isOwner} />
                            </label>
                            <label>توضیحات گروه
                                <textarea value={description} onChange={(e) => setDescription(e.target.value)} placeholder="درباره گروه..." disabled={!isOwner} />
                            </label>

                            <div className="profile-meta-grid" style={{padding:'12px', gap:'6px'}}>
                                <div className="profile-item">
                                    <strong>نوع گروه</strong>
                                    <span>{info.group.privacy_type === 'public' ? 'عمومی' : 'خصوصی'}</span>
                                </div>
                                {info.group.privacy_type === 'public' ? (
                                    <div className="profile-item">
                                        <strong>لینک عمومی</strong>
                                        <span dir="ltr">@{info.group.public_handle}</span>
                                    </div>
                                ) : (
                                    info.invite_token && (
                                        <div className="profile-item" style={{flexDirection:'column', alignItems:'flex-start', gap:'4px'}}>
                                            <strong>لینک دعوت گروه خصوصی</strong>
                                            <code style={{background:'var(--panel)', padding:'6px', borderRadius:'4px', width:'100%', wordBreak:'break-all', dir:'ltr', textAlign:'left'}}>
                                                {apiUrl(`/groups/join-by-link`, {token: info.invite_token})}
                                            </code>
                                        </div>
                                    )
                                )}
                            </div>

                            {isOwner && (
                                <div style={{display:'grid', gap:'10px'}}>
                                    <h3>دسترسی و محدودیت‌های اعضا</h3>
                                    <div className="perms-grid">
                                        <label className="perm-toggle">
                                            <span>عکس</span>
                                            <input type="checkbox" checked={allowPhotos} onChange={(e) => setAllowPhotos(e.target.checked)} />
                                        </label>
                                        <label className="perm-toggle">
                                            <span>ویدیو</span>
                                            <input type="checkbox" checked={allowVideos} onChange={(e) => setAllowVideos(e.target.checked)} />
                                        </label>
                                        <label className="perm-toggle">
                                            <span>پیام صوتی</span>
                                            <input type="checkbox" checked={allowVoice} onChange={(e) => setAllowVoice(e.target.checked)} />
                                        </label>
                                        <label className="perm-toggle">
                                            <span>فایل</span>
                                            <input type="checkbox" checked={allowFiles} onChange={(e) => setAllowFiles(e.target.checked)} />
                                        </label>
                                        {info.group.privacy_type === 'public' && (
                                            <label className="perm-toggle" style={{gridColumn: 'span 2'}}>
                                                <span>دعوت دیگران توسط اعضا</span>
                                                <input type="checkbox" checked={allowInvites} onChange={(e) => setAllowInvites(e.target.checked)} />
                                            </label>
                                        )}
                                    </div>
                                    <button className="primary-btn" type="submit" disabled={updating}>
                                        {updating ? 'در حال ذخیره...' : 'ذخیره تنظیمات گروه'}
                                    </button>
                                </div>
                            )}
                        </form>

                        <div>
                            <h3>لیست اعضا ({info.group.members_count || 0} عضو، {info.group.online_count || 0} آنلاین)</h3>
                            <div className="members-list">
                                {info.members.map((m) => (
                                    <div key={m.id} className="member-row">
                                        <div className="member-info">
                                            <span className={m.is_online ? 'member-online-dot' : 'member-offline-dot'} />
                                            <strong>{m.full_name}</strong>
                                            <small style={{color:'var(--muted)'}}>@{m.username}</small>
                                        </div>
                                        <span style={{fontSize:'12px', color:'var(--muted)', fontWeight:'bold'}}>
                                            {m.role === 'owner' ? 'مالک' : 'عضو'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                ) : (
                    <div>اطلاعات گروه یافت نشد.</div>
                )}
            </section>
        </div>
    );
}

// Sleek Telegram-like Settings Dialog
function SettingsDialog({user, photos, onClose, onUserUpdated, onLogout, theme, onToggleTheme}) {
    const [tab, setTab] = useState('profile');

    // Profile Info States
    const [fullName, setFullName] = useState(user?.full_name || '');
    const [username, setUsername] = useState(user?.username || '');
    const [email, setEmail] = useState(user?.email || '');
    const [phone, setPhone] = useState(user?.phone || '');
    const [bio, setBio] = useState(user?.bio || '');
    const [password, setPassword] = useState('');
    const [saving, setSaving] = useState(false);

    // Group Creation States
    const [groupTitle, setGroupTitle] = useState('');
    const [groupPrivacy, setGroupPrivacy] = useState('private');
    const [groupHandle, setGroupHandle] = useState('');
    const [creatingGroup, setCreatingGroup] = useState(false);

    // Last seen privacy state
    const [lastSeenPrivacy, setLastSeenPrivacy] = useState(user?.last_seen_privacy || 'everyone');

    async function handleSaveProfile(event) {
        event.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/me', {
                method: 'POST',
                body: {
                    full_name: fullName,
                    username,
                    email,
                    phone,
                    bio,
                    password: password !== '' ? password : undefined
                }
            });
            setPassword('');
            alert('اطلاعات کاربری با موفقیت بروزرسانی شد.');
            onUserUpdated();
        } catch (err) {
            alert('بروزرسانی اطلاعات ناموفق بود: ' + err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleSavePrivacy(event) {
        event.preventDefault();
        setSaving(true);
        try {
            await apiRequest('/me/settings', {
                method: 'PATCH',
                body: {
                    last_seen_privacy: lastSeenPrivacy
                }
            });
            alert('حریم خصوصی با موفقیت تنظیم شد.');
            onUserUpdated();
        } catch (err) {
            alert('تنظیم حریم خصوصی ناموفق بود: ' + err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleCreateGroup(event) {
        event.preventDefault();
        setCreatingGroup(true);
        try {
            const res = await apiRequest('/groups', {
                method: 'POST',
                body: {
                    title: groupTitle,
                    privacy_type: groupPrivacy,
                    public_handle: groupPrivacy === 'public' ? groupHandle : ''
                }
            });
            setGroupTitle('');
            setGroupHandle('');
            alert('گروه با موفقیت ساخته شد.');
            onClose();
            onUserUpdated(res.data.group_id);
        } catch (err) {
            alert('ساخت گروه ناموفق بود: ' + err.message);
        } finally {
            setCreatingGroup(false);
        }
    }

    async function handleUploadPhoto(event) {
        const file = event.target.files?.[0];
        if (!file) return;
        const formData = new FormData();
        formData.append('photo', file);
        try {
            await apiRequest('/profile/photo', {
                method: 'POST',
                body: formData
            });
            alert('تصویر پروفایل جدید با موفقیت اضافه شد.');
            onUserUpdated();
        } catch (err) {
            alert('آپلود تصویر ناموفق بود: ' + err.message);
        }
    }

    async function handleSetActivePhoto(photoId) {
        try {
            await apiRequest('/profile/photo/active', {
                method: 'POST',
                body: { photo_id: photoId }
            });
            alert('تصویر پروفایل فعال تغییر کرد.');
            onUserUpdated();
        } catch (err) {
            alert('خطا در تغییر تصویر فعال: ' + err.message);
        }
    }

    async function handleDeletePhoto(photoId) {
        if (!confirm('آیا مایل به حذف این عکس پروفایل هستید؟')) return;
        try {
            await apiRequest(`/profile/photo/${photoId}`, {
                method: 'DELETE'
            });
            alert('عکس پروفایل با موفقیت حذف شد.');
            onUserUpdated();
        } catch (err) {
            alert('خطا در حذف عکس: ' + err.message);
        }
    }

    return (
        <div className="dialog-backdrop" role="presentation">
            <section className="dialog dialog-wide" role="dialog" aria-modal="true" aria-label="تنظیمات">
                <header>
                    <h2>تنظیمات سلو</h2>
                    <button className="icon-btn" type="button" onClick={onClose}><Icon name="close"/></button>
                </header>

                <div className="segmented" style={{gridTemplateColumns: 'repeat(4, 1fr)', marginBottom: '12px'}}>
                    <button type="button" className={tab === 'profile' ? 'active' : ''} onClick={() => setTab('profile')}>پروفایل</button>
                    <button type="button" className={tab === 'photos' ? 'active' : ''} onClick={() => setTab('photos')}>عکس‌ها</button>
                    <button type="button" className={tab === 'privacy' ? 'active' : ''} onClick={() => setTab('privacy')}>امنیت</button>
                    <button type="button" className={tab === 'group' ? 'active' : ''} onClick={() => setTab('group')}>گروه جدید</button>
                </div>

                <div style={{maxHeight:'65vh', overflowY:'auto', paddingRight:'4px'}}>
                    {tab === 'profile' && (
                        <form onSubmit={handleSaveProfile} className="dialog-form">
                            <label>نام کامل
                                <input value={fullName} onChange={(e) => setFullName(e.target.value)} required />
                            </label>
                            <label>نام کاربری
                                <input value={username} onChange={(e) => setUsername(e.target.value)} dir="ltr" required />
                            </label>
                            <label>ایمیل Gmail
                                <input value={email} type="email" onChange={(e) => setEmail(e.target.value)} dir="ltr" required />
                            </label>
                            <label>شماره تلفن
                                <input value={phone} onChange={(e) => setPhone(e.target.value)} dir="ltr" />
                            </label>
                            <label>بیوگرافی
                                <textarea value={bio} onChange={(e) => setBio(e.target.value)} placeholder="درباره شما..." />
                            </label>
                            <button className="primary-btn" type="submit" disabled={saving}>
                                {saving ? 'در حال ذخیره...' : 'ذخیره مشخصات'}
                            </button>
                        </form>
                    )}

                    {tab === 'photos' && (
                        <div style={{display:'grid', gap:'14px'}}>
                            <PhotoCarousel photos={photos} fallbackName={user.full_name} />
                            
                            <label className="upload-profile-btn">
                                <Icon name="upload" /> اضافه کردن تصویر جدید
                                <input type="file" onChange={handleUploadPhoto} style={{display:'none'}} accept="image/*" />
                            </label>

                            {photos && photos.length > 0 && (
                                <div>
                                    <h3>مدیریت تمام تصاویر پروفایل</h3>
                                    <div className="photos-grid">
                                        {photos.map((p) => {
                                            const thumbUrl = `${basePath}/photo.php?id=${p.id}&thumb=1`;
                                            return (
                                                <div key={p.id} className={`photo-thumb-container ${p.is_active === 1 ? 'active' : ''}`}>
                                                    <img src={thumbUrl} alt="پیکسل پروفایل" />
                                                    <div className="photo-actions">
                                                        {p.is_active !== 1 && (
                                                            <button type="button" className="photo-action-btn" title="پروفایل اصلی کردن" onClick={() => handleSetActivePhoto(p.id)}>
                                                                <Icon name="check" />
                                                            </button>
                                                        )}
                                                        <button type="button" className="photo-action-btn delete" title="حذف" onClick={() => handleDeletePhoto(p.id)}>
                                                            <Icon name="delete" />
                                                        </button>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </div>
                    )}

                    {tab === 'privacy' && (
                        <div style={{display:'grid', gap:'16px'}}>
                            <form onSubmit={handleSavePrivacy} className="dialog-form">
                                <label>حریم خصوصی آخرین بازدید
                                    <select value={lastSeenPrivacy} onChange={(e) => setLastSeenPrivacy(e.target.value)} style={{width:'100%', minHeight:'44px', border:'1px solid var(--line)', background:'var(--panel-soft)', borderRadius:'8px', padding:'0 10px'}}>
                                        <option value="everyone">نمایش برای همه (Everyone)</option>
                                        <option value="nobody">هیچ کس (Nobody)</option>
                                    </select>
                                </label>
                                <button className="primary-btn" type="submit" disabled={saving}>
                                    {saving ? 'در حال ذخیره...' : 'ذخیره حریم خصوصی'}
                                </button>
                            </form>

                            <hr style={{border:'0', borderBottom:'1px solid var(--line)', margin:'8px 0'}} />

                            <form onSubmit={handleSaveProfile} className="dialog-form">
                                <label>تغییر رمز عبور (خالی بگذارید تا بدون تغییر بماند)
                                    <input value={password} type="password" onChange={(e) => setPassword(e.target.value)} placeholder="رمز عبور جدید..." />
                                </label>
                                <button className="primary-btn" type="submit" disabled={saving}>
                                    {saving ? 'در حال ذخیره...' : 'تغییر رمز عبور'}
                                </button>
                            </form>

                            <hr style={{border:'0', borderBottom:'1px solid var(--line)', margin:'8px 0'}} />

                            <div style={{display:'flex', alignItems:'center', justifySelf:'stretch', justifyContent:'space-between', background:'var(--panel-soft)', padding:'14px', borderRadius:'8px', border:'1px solid var(--line)'}}>
                                <strong style={{fontSize:'14px'}}>تغییر تم رنگی برنامه</strong>
                                <button className="tool-btn" type="button" onClick={onToggleTheme}>
                                    <Icon name={theme === 'dark' ? 'light_mode' : 'dark_mode'} />
                                    {theme === 'dark' ? 'تم روشن' : 'تم تاریک'}
                                </button>
                            </div>

                            <button className="primary-btn danger" type="button" style={{background:'var(--danger)', width:'100%', minHeight:'44px', marginTop:'12px'}} onClick={onLogout}>
                                <Icon name="logout" style={{marginLeft:'6px'}} /> خروج از حساب کاربری
                            </button>
                        </div>
                    )}

                    {tab === 'group' && (
                        <form onSubmit={handleCreateGroup} className="dialog-form">
                            <label>نام گروه
                                <input value={groupTitle} onChange={(e) => setGroupTitle(e.target.value)} required />
                            </label>
                            <div className="segmented">
                                <button type="button" className={groupPrivacy === 'private' ? 'active' : ''} onClick={() => setGroupPrivacy('private')}>خصوصی</button>
                                <button type="button" className={groupPrivacy === 'public' ? 'active' : ''} onClick={() => setGroupPrivacy('public')}>عمومی</button>
                            </div>
                            {groupPrivacy === 'public' && (
                                <label>شناسه عمومی گروه (Public Handle)
                                    <input value={groupHandle} onChange={(e) => setGroupHandle(e.target.value)} dir="ltr" required />
                                </label>
                            )}
                            <button className="primary-btn" type="submit" disabled={creatingGroup}>
                                {creatingGroup ? 'در حال ساخت...' : 'ایجاد گروه جدید'}
                            </button>
                        </form>
                    )}
                </div>
            </section>
        </div>
    );
}

export default function App() {
    const [booting, setBooting] = useState(true);
    const [user, setUser] = useState(null);
    const [userPhotos, setUserPhotos] = useState([]);
    const [chats, setChats] = useState([]);
    const [activeChat, setActiveChat] = useState(null);
    const [messages, setMessages] = useState([]);
    const [draft, setDraft] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);

    // State for live file uploads
    const [uploadsInProgress, setUploadsInProgress] = useState([]);

    // Dialog state controllers
    const [settingsOpen, setSettingsOpen] = useState(false);
    const [targetProfileUserId, setTargetProfileUserId] = useState(null);
    const [groupProfileId, setGroupProfileId] = useState(null);

    const [theme, setTheme] = useState(() => localStorage.getItem('selo-theme') || 'light');

    useEffect(() => {
        document.body.dataset.theme = theme;
        localStorage.setItem('selo-theme', theme);
    }, [theme]);

    const loadMe = useCallback(async () => {
        const response = await apiRequest('/me');
        setUser(response.data.user);
        setUserPhotos(response.data.photos || []);
    }, []);

    const loadChats = useCallback(async () => {
        const response = await apiRequest('/conversations');
        const data = response.data || [];
        setChats(data);
        return data;
    }, []);

    const loadMessages = useCallback(async (chat = activeChat) => {
        if (!chat) return;
        const path = chat.chat_type === 'group' ? `/groups/${chat.id}/messages` : '/messages';
        const response = await apiRequest(path, {
            params: chat.chat_type === 'group' ? {limit: 80} : {conversation_id: chat.id, limit: 80},
        });
        setMessages(response.data || []);
    }, [activeChat]);

    const authenticate = useCallback(async () => {
        await loadMe();
        await loadChats();
    }, [loadChats, loadMe]);

    useEffect(() => {
        authenticate()
            .catch(() => setUser(null))
            .finally(() => setBooting(false));
    }, [authenticate]);

    // Optimize performance by pausing background polling if document is hidden (tab inactive)
    useEffect(() => {
        if (!user) return undefined;
        const timer = window.setInterval(() => {
            if (document.hidden) return;
            loadChats().catch(() => {});
        }, 6000);
        return () => window.clearInterval(timer);
    }, [loadChats, user]);

    useEffect(() => {
        if (!activeChat) return undefined;
        loadMessages(activeChat).catch((err) => setError(err.message));
        const timer = window.setInterval(() => {
            if (document.hidden) return;
            loadMessages(activeChat).catch(() => {});
        }, 3500);
        return () => window.clearInterval(timer);
    }, [activeChat, loadMessages]);

    // Automatically mark unread messages as read when a private conversation opens
    useEffect(() => {
        if (!activeChat) return;
        if (activeChat.chat_type === 'direct') {
            apiRequest('/messages/mark-read', {method: 'POST', body: {conversation_id: activeChat.id}})
                .then(() => loadChats())
                .catch(() => {});
        }
    }, [activeChat, loadChats]);

    useEffect(() => {
        if (searchQuery.trim().length < 2) {
            setSearchResults([]);
            return undefined;
        }
        const timer = window.setTimeout(() => {
            apiRequest('/users/search', {params: {query: searchQuery.trim()}})
                .then((response) => setSearchResults(response.data || []))
                .catch(() => setSearchResults([]));
        }, 250);
        return () => window.clearTimeout(timer);
    }, [searchQuery]);

    const activeChatKey = useMemo(() => activeChat ? `${activeChat.chat_type}-${activeChat.id}` : '', [activeChat]);

    async function startChat(result) {
        setError('');
        const response = await apiRequest('/conversations', {method: 'POST', body: {user_id: result.id}});
        await loadChats();
        setSearchQuery('');
        setSearchResults([]);
        setActiveChat({
            id: response.data.conversation_id,
            chat_type: 'direct',
            other_id: result.id,
            other_name: result.full_name,
            other_username: result.username,
        });
    }

    async function handleUploadFile(file) {
        if (!activeChat) return;
        const uploadId = `upload-${Date.now()}-${Math.random()}`;
        setUploadsInProgress((current) => [...current, { id: uploadId, name: file.name, progress: 0 }]);

        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            xhr.open('POST', apiUrl('/uploads'));

            const csrf = csrfToken();
            if (csrf) {
                xhr.setRequestHeader('X-CSRF-Token', csrf);
            }
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) {
                    const percent = Math.round((event.loaded / event.total) * 100);
                    setUploadsInProgress((current) =>
                        current.map((u) => (u.id === uploadId ? { ...u, progress: percent } : u))
                    );
                }
            });

            xhr.addEventListener('load', async () => {
                setUploadsInProgress((current) => current.filter((u) => u.id !== uploadId));
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const res = JSON.parse(xhr.responseText);
                        if (res.ok === false) {
                            setError(res.error || 'آپلود ناموفق بود.');
                            reject(new Error(res.error));
                            return;
                        }
                        const mediaData = res.data;

                        const path = activeChat.chat_type === 'group' ? `/groups/${activeChat.id}/messages` : '/messages';
                        const payload = {
                            type: mediaData.type,
                            body: '',
                            media_id: mediaData.media_id,
                            ...(activeChat.chat_type === 'direct' ? {conversation_id: activeChat.id} : {}),
                        };
                        await apiRequest(path, {method: 'POST', body: payload});
                        await loadMessages(activeChat);
                        await loadChats();
                        resolve();
                    } catch (err) {
                        setError(err.message);
                        reject(err);
                    }
                } else {
                    setError('آپلود فایل ناموفق بود.');
                    reject(new Error('Upload failed'));
                }
            });

            xhr.addEventListener('error', () => {
                setUploadsInProgress((current) => current.filter((u) => u.id !== uploadId));
                setError('خطا در شبکه هنگام آپلود.');
                reject(new Error('Network error'));
            });

            const formData = new FormData();
            formData.append('file', file);
            formData.append('type', 'auto');
            xhr.send(formData);
        });
    }

    async function handleSendVoice(file) {
        if (!activeChat) return;
        setSending(true);
        setError('');
        const formData = new FormData();
        formData.append('file', file);
        formData.append('type', 'voice');
        try {
            const uploadRes = await apiRequest('/uploads', {
                method: 'POST',
                body: formData
            });
            const mediaData = uploadRes.data;

            const optimistic = {
                id: `local-${Date.now()}`,
                client_id: `web-${Date.now()}`,
                sender_id: user.id,
                sender_name: user.full_name,
                body: '',
                type: 'voice',
                media: mediaData,
                created_at: new Date().toISOString(),
            };
            setMessages((current) => [...current, optimistic]);

            const path = activeChat.chat_type === 'group' ? `/groups/${activeChat.id}/messages` : '/messages';
            const payload = {
                type: 'voice',
                body: '',
                client_id: optimistic.client_id,
                media_id: mediaData.media_id,
                ...(activeChat.chat_type === 'direct' ? {conversation_id: activeChat.id} : {}),
            };
            await apiRequest(path, {method: 'POST', body: payload});
            await loadMessages(activeChat);
            await loadChats();
        } catch (err) {
            setError(err.message);
        } finally {
            setSending(false);
        }
    }

    async function sendMessage() {
        const body = draft.trim();
        if (!activeChat || !body || sending) return;
        setSending(true);
        setError('');
        const optimistic = {
            id: `local-${Date.now()}`,
            client_id: `web-${Date.now()}`,
            sender_id: user.id,
            sender_name: user.full_name,
            body,
            type: 'text',
            created_at: new Date().toISOString(),
        };
        setMessages((current) => [...current, optimistic]);
        setDraft('');
        try {
            const path = activeChat.chat_type === 'group' ? `/groups/${activeChat.id}/messages` : '/messages';
            const payload = {
                type: 'text',
                body,
                client_id: optimistic.client_id,
                ...(activeChat.chat_type === 'direct' ? {conversation_id: activeChat.id} : {}),
            };
            await apiRequest(path, {method: 'POST', body: payload});
            await loadMessages(activeChat);
            await loadChats();
        } catch (err) {
            setError(err.message);
            setMessages((current) => current.filter((message) => message.id !== optimistic.id));
            setDraft(body);
        } finally {
            setSending(false);
        }
    }

    async function logout() {
        await apiRequest('/logout', {method: 'POST'}).catch(() => null);
        setUser(null);
        setChats([]);
        setActiveChat(null);
        setMessages([]);
        setSettingsOpen(false);
    }

    async function handleSettingsUserUpdated(navigateGroupId = null) {
        await loadMe();
        const nextChats = await loadChats();
        if (navigateGroupId) {
            const created = nextChats.find((chat) => chat.chat_type === 'group' && Number(chat.id) === Number(navigateGroupId));
            if (created) setActiveChat(created);
        }
    }

    const handleHeaderClick = () => {
        if (!activeChat) return;
        if (activeChat.chat_type === 'group') {
            setGroupProfileId(activeChat.id);
        } else {
            setTargetProfileUserId(activeChat.other_id);
        }
    };

    if (booting) {
        return <main className="splash">
            <div className="brand-mark large">S</div>
            <span>در حال آماده‌سازی...</span></main>;
    }

    if (!user) {
        return <AuthScreen onAuthenticated={authenticate}/>;
    }

    return (
        <div className="messenger-shell" data-active-chat={activeChatKey}>
            <Sidebar
                chats={chats}
                activeChat={activeChat}
                user={user}
                searchQuery={searchQuery}
                searchResults={searchResults}
                onSearch={setSearchQuery}
                onSelectChat={setActiveChat}
                onStartChat={startChat}
                onOpenSettings={() => setSettingsOpen(true)}
            />
            <ChatPanel
                chat={activeChat}
                messages={messages}
                currentUserId={user.id}
                draft={draft}
                onDraft={setDraft}
                onSend={sendMessage}
                loading={sending}
                onBack={() => setActiveChat(null)}
                onUploadFile={handleUploadFile}
                onSendVoice={handleSendVoice}
                onHeaderClick={handleHeaderClick}
                onUserClick={(uid) => setTargetProfileUserId(uid)}
                uploadsInProgress={uploadsInProgress}
            />
            {error && <div className="toast" role="alert">{error}
                <button type="button" onClick={() => setError('')}><Icon name="close"/></button>
            </div>}

            {settingsOpen && (
                <SettingsDialog
                    user={user}
                    photos={userPhotos}
                    onClose={() => setSettingsOpen(false)}
                    onUserUpdated={handleSettingsUserUpdated}
                    onLogout={logout}
                    theme={theme}
                    onToggleTheme={() => setTheme((v) => v === 'dark' ? 'light' : 'dark')}
                />
            )}

            {targetProfileUserId && (
                <UserProfileDialog
                    userId={targetProfileUserId}
                    onClose={() => setTargetProfileUserId(null)}
                />
            )}

            {groupProfileId && (
                <GroupProfileDialog
                    groupId={groupProfileId}
                    currentUser={user}
                    onClose={() => setGroupProfileId(null)}
                    onGroupUpdated={loadChats}
                />
            )}
        </div>
    );
}
