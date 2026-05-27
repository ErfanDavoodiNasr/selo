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
        return chat.public_handle ? `@${chat.public_handle}` : 'گروه خصوصی';
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

function Sidebar({
                     chats,
                     activeChat,
                     user,
                     searchQuery,
                     searchResults,
                     onSearch,
                     onSelectChat,
                     onStartChat,
                     onOpenGroup,
                     onLogout,
                     onToggleTheme,
                 }) {
    return (
        <aside className="sidebar">
            <header className="sidebar-header">
                <button className="icon-btn" type="button" title="تغییر تم" onClick={onToggleTheme}><Icon
                    name="dark_mode"/></button>
                <div className="account-chip">
                    <div className="avatar">{initials(user?.full_name)}</div>
                    <div>
                        <strong>{user?.full_name || 'SELO'}</strong>
                        <span>@{user?.username || 'user'}</span>
                    </div>
                </div>
                <button className="icon-btn danger" type="button" title="خروج" onClick={onLogout}><Icon name="logout"/>
                </button>
            </header>

            <div className="sidebar-tools">
                <div className="search-box">
                    <Icon name="search"/>
                    <input value={searchQuery} onChange={(event) => onSearch(event.target.value)}
                           placeholder="جستجوی نام کاربری..."/>
                </div>
                <button className="tool-btn" type="button" onClick={onOpenGroup}><Icon name="group_add"/>گروه</button>
            </div>

            {searchResults.length > 0 && (
                <div className="search-results">
                    {searchResults.map((result) => (
                        <button key={result.id} type="button" onClick={() => onStartChat(result)}>
                            <span className="avatar small">{initials(result.full_name)}</span>
                            <span>
                <strong>{result.full_name}</strong>
                <small>@{result.username}</small>
              </span>
                        </button>
                    ))}
                </div>
            )}

            <div className="chat-list" role="listbox" aria-label="گفتگوها">
                {chats.map((chat) => (
                    <button
                        key={`${chat.chat_type}-${chat.id}`}
                        type="button"
                        className={`chat-item ${activeChat?.id === chat.id && activeChat?.chat_type === chat.chat_type ? 'active' : ''}`}
                        onClick={() => onSelectChat(chat)}
                    >
                        <span className="avatar">{initials(chatTitle(chat))}</span>
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
                ))}
                {chats.length === 0 && <div className="empty-list">هنوز گفتگویی ندارید.</div>}
            </div>
        </aside>
    );
}

function MessageBubble({message, currentUserId}) {
    const mine = Number(message.sender_id) === Number(currentUserId);
    const media = message.media || message.attachments?.[0]?.media;
    return (
        <article className={`message ${mine ? 'outgoing' : 'incoming'}`}>
            {!mine && <div className="sender-name">{message.sender_name}</div>}
            {message.reply_id && <div className="reply-preview">پاسخ
                به: {message.reply_body || message.reply_media_name || 'پیام'}</div>}
            {media && (
                <a className="media-chip" href={apiUrl(`/media/${media.id}`)} target="_blank" rel="noreferrer">
                    <Icon name="attach_file"/>
                    {media.original_name || 'پیوست'}
                </a>
            )}
            {message.body && <p>{message.body}</p>}
            <time>{formatTime(message.created_at)}</time>
        </article>
    );
}

function ChatPanel({chat, messages, currentUserId, draft, onDraft, onSend, loading}) {
    const bottomRef = useRef(null);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({behavior: 'smooth', block: 'end'});
    }, [messages, chat?.id]);

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

    return (
        <section className="chat-panel">
            <header className="chat-header">
                <div className="avatar">{initials(chatTitle(chat))}</div>
                <div>
                    <h2>{chatTitle(chat)}</h2>
                    <p>{chatSubtitle(chat)}</p>
                </div>
            </header>

            <div className="messages" role="log" aria-live="polite">
                {messages.map((message) => (
                    <MessageBubble key={message.id || message.client_id} message={message}
                                   currentUserId={currentUserId}/>
                ))}
                {messages.length === 0 && <div className="empty-chat">اینجا هنوز پیامی نیست.</div>}
                <div ref={bottomRef}/>
            </div>

            <form className="composer" onSubmit={submit}>
                <button className="icon-btn" type="button" title="پیوست"><Icon name="attach_file"/></button>
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
                <button className="send-fab" type="submit" disabled={loading || draft.trim() === ''} title="ارسال">
                    <Icon name="send"/>
                </button>
            </form>
        </section>
    );
}

function GroupDialog({open, onClose, onCreated}) {
    const [title, setTitle] = useState('');
    const [privacy, setPrivacy] = useState('private');
    const [handle, setHandle] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);

    if (!open) return null;

    async function submit(event) {
        event.preventDefault();
        setError('');
        setLoading(true);
        try {
            const payload = {
                title,
                privacy_type: privacy,
                public_handle: privacy === 'public' ? handle : '',
            };
            const response = await apiRequest('/groups', {method: 'POST', body: payload});
            setTitle('');
            setHandle('');
            onCreated(response.data.group_id);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }

    return (
        <div className="dialog-backdrop" role="presentation">
            <section className="dialog" role="dialog" aria-modal="true" aria-label="گروه جدید">
                <header>
                    <h2>گروه جدید</h2>
                    <button className="icon-btn" type="button" onClick={onClose}><Icon name="close"/></button>
                </header>
                <form className="dialog-form" onSubmit={submit}>
                    <label>نام گروه<input value={title} onChange={(event) => setTitle(event.target.value)}
                                          required/></label>
                    <div className="segmented">
                        <button type="button" className={privacy === 'private' ? 'active' : ''}
                                onClick={() => setPrivacy('private')}>خصوصی
                        </button>
                        <button type="button" className={privacy === 'public' ? 'active' : ''}
                                onClick={() => setPrivacy('public')}>عمومی
                        </button>
                    </div>
                    {privacy === 'public' &&
                        <label>شناسه عمومی<input value={handle} onChange={(event) => setHandle(event.target.value)}
                                                 dir="ltr" required/></label>}
                    {error && <div className="form-error" role="alert">{error}</div>}
                    <button className="primary-btn"
                            disabled={loading}>{loading ? 'در حال ساخت...' : 'ساخت گروه'}</button>
                </form>
            </section>
        </div>
    );
}

export default function App() {
    const [booting, setBooting] = useState(true);
    const [user, setUser] = useState(null);
    const [chats, setChats] = useState([]);
    const [activeChat, setActiveChat] = useState(null);
    const [messages, setMessages] = useState([]);
    const [draft, setDraft] = useState('');
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [error, setError] = useState('');
    const [sending, setSending] = useState(false);
    const [groupOpen, setGroupOpen] = useState(false);
    const [theme, setTheme] = useState(() => localStorage.getItem('selo-theme') || 'light');

    useEffect(() => {
        document.body.dataset.theme = theme;
        localStorage.setItem('selo-theme', theme);
    }, [theme]);

    const loadMe = useCallback(async () => {
        const response = await apiRequest('/me');
        setUser(response.data.user);
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

    useEffect(() => {
        if (!user) return undefined;
        const timer = window.setInterval(() => loadChats().catch(() => {
        }), 6000);
        return () => window.clearInterval(timer);
    }, [loadChats, user]);

    useEffect(() => {
        if (!activeChat) return undefined;
        loadMessages(activeChat).catch((err) => setError(err.message));
        const timer = window.setInterval(() => loadMessages(activeChat).catch(() => {
        }), 3500);
        return () => window.clearInterval(timer);
    }, [activeChat, loadMessages]);

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
    }

    async function afterGroupCreated(groupId) {
        setGroupOpen(false);
        const nextChats = await loadChats();
        const created = nextChats.find((chat) => chat.chat_type === 'group' && Number(chat.id) === Number(groupId));
        if (created) setActiveChat(created);
    }

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
                onOpenGroup={() => setGroupOpen(true)}
                onLogout={logout}
                onToggleTheme={() => setTheme((value) => value === 'dark' ? 'light' : 'dark')}
            />
            <ChatPanel
                chat={activeChat}
                messages={messages}
                currentUserId={user.id}
                draft={draft}
                onDraft={setDraft}
                onSend={sendMessage}
                loading={sending}
            />
            {error && <div className="toast" role="alert">{error}
                <button type="button" onClick={() => setError('')}><Icon name="close"/></button>
            </div>}
            <GroupDialog open={groupOpen} onClose={() => setGroupOpen(false)} onCreated={afterGroupCreated}/>
        </div>
    );
}
