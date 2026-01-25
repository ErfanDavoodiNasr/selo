(function () {
  const state = {
    token: localStorage.getItem('selo_token'),
    me: null,
    conversations: [],
    currentConversation: null,
    replyTo: null,
    loadingMessages: false,
    oldestMessageId: null
  };

  const authView = document.getElementById('auth-view');
  const mainView = document.getElementById('main-view');
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');
  const authError = document.getElementById('auth-error');
  const tabs = document.querySelectorAll('.auth-tabs .tab');

  const chatList = document.getElementById('chat-list');
  const messagesEl = document.getElementById('messages');
  const messageInput = document.getElementById('message-input');
  const sendBtn = document.getElementById('send-btn');
  const emojiBtn = document.getElementById('emoji-btn');
  const emojiPicker = document.getElementById('emoji-picker');
  const userSearch = document.getElementById('user-search');
  const searchResults = document.getElementById('search-results');
  const themeToggle = document.getElementById('theme-toggle');
  const chatUserName = document.getElementById('chat-user-name');
  const chatUserUsername = document.getElementById('chat-user-username');
  const chatUserAvatar = document.getElementById('chat-user-avatar');
  const replyBar = document.getElementById('reply-bar');
  const replyPreview = document.getElementById('reply-preview');
  const replyCancel = document.getElementById('reply-cancel');
  const backToChats = document.getElementById('back-to-chats');

  const apiBase = window.SELO_CONFIG?.baseUrl || '';
  const baseUrl = apiBase.replace(/\/$/, '');
  const makeUrl = (path) => (baseUrl ? baseUrl + path : path);

  function setTheme(theme) {
    document.body.dataset.theme = theme;
    localStorage.setItem('selo_theme', theme);
  }

  const savedTheme = localStorage.getItem('selo_theme') || 'light';
  setTheme(savedTheme);

  themeToggle.addEventListener('click', () => {
    const newTheme = document.body.dataset.theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
  });

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      tabs.forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      authError.textContent = '';
      if (tab.dataset.tab === 'login') {
        loginForm.classList.remove('hidden');
        registerForm.classList.add('hidden');
      } else {
        registerForm.classList.remove('hidden');
        loginForm.classList.add('hidden');
      }
    });
  });

  function showAuth() {
    authView.classList.remove('hidden');
    mainView.classList.add('hidden');
  }

  function showMain() {
    authView.classList.add('hidden');
    mainView.classList.remove('hidden');
    document.body.classList.add('show-chats');
  }

  async function apiFetch(path, options = {}) {
    const headers = options.headers || {};
    if (state.token) {
      headers['Authorization'] = 'Bearer ' + state.token;
    }
    if (options.body && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    const response = await fetch(makeUrl(path), {
      ...options,
      headers
    });
    const data = await response.json().catch(() => null);
    if (!data) {
      throw new Error('خطا در پاسخ سرور');
    }
    return { status: response.status, data };
  }

  async function handleLogin(payload, endpoint) {
    authError.textContent = '';
    try {
      const res = await apiFetch(endpoint, { method: 'POST', body: payload });
      if (!res.data.ok) {
        authError.textContent = res.data.error || 'خطا در ورود/ثبت‌نام';
        return;
      }
      state.token = res.data.data.token;
      localStorage.setItem('selo_token', state.token);
      await initialize();
    } catch (err) {
      authError.textContent = err.message;
    }
  }

  loginForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(loginForm);
    handleLogin({
      identifier: formData.get('identifier'),
      password: formData.get('password')
    }, '/api/login');
  });

  registerForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(registerForm);
    handleLogin({
      full_name: formData.get('full_name'),
      username: formData.get('username'),
      email: formData.get('email'),
      password: formData.get('password')
    }, '/api/register');
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function formatTime(dateStr) {
    const date = new Date(dateStr.replace(' ', 'T'));
    return new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit' }).format(date);
  }

  function truncate(text, max) {
    if (!text) return '';
    return text.length > max ? text.slice(0, max) + '…' : text;
  }

  function setCurrentChatHeader(conversation) {
    if (!conversation) {
      chatUserName.textContent = 'گفتگو';
      chatUserUsername.textContent = '';
      chatUserAvatar.style.backgroundImage = '';
      return;
    }
    chatUserName.textContent = conversation.other_name || conversation.other_username || '';
    chatUserUsername.textContent = '@' + conversation.other_username;
    if (conversation.other_photo) {
      chatUserAvatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + conversation.other_photo)})`;
    } else {
      chatUserAvatar.style.backgroundImage = '';
    }
  }

  function renderConversations() {
    chatList.innerHTML = '';
    state.conversations.forEach(conv => {
      const item = document.createElement('div');
      item.className = 'chat-item';
      const avatar = document.createElement('div');
      avatar.className = 'avatar';
      if (conv.other_photo) {
        avatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + conv.other_photo)})`;
      }
      const details = document.createElement('div');
      details.className = 'details';
      const name = document.createElement('div');
      name.className = 'name';
      name.textContent = conv.other_name || conv.other_username;
      const preview = document.createElement('div');
      preview.className = 'preview';
      preview.textContent = conv.last_body ? truncate(conv.last_body, 40) : 'بدون پیام';
      details.appendChild(name);
      details.appendChild(preview);
      item.appendChild(avatar);
      item.appendChild(details);
      item.addEventListener('click', () => selectConversation(conv));
      chatList.appendChild(item);
    });
  }

  async function loadConversations() {
    const res = await apiFetch('/api/conversations');
    if (res.data.ok) {
      state.conversations = res.data.data;
      renderConversations();
    }
  }

  async function selectConversation(conv) {
    state.currentConversation = conv;
    state.oldestMessageId = null;
    messagesEl.innerHTML = '';
    setCurrentChatHeader(conv);
    document.body.classList.remove('show-chats');
    await loadMessages();
  }

  async function loadMessages(beforeId = null) {
    if (!state.currentConversation || state.loadingMessages) return;
    state.loadingMessages = true;
    const params = new URLSearchParams({
      conversation_id: state.currentConversation.id,
      limit: 30
    });
    if (beforeId) {
      params.set('before_id', beforeId);
    }
    const res = await apiFetch('/api/messages?' + params.toString());
    if (res.data.ok) {
      const list = res.data.data;
      if (list.length > 0) {
        state.oldestMessageId = list[0].id;
      }
      renderMessages(list, beforeId !== null);
    }
    state.loadingMessages = false;
  }

  function renderMessages(messages, prepend = false) {
    const fragment = document.createDocumentFragment();
    messages.forEach(msg => {
      const message = document.createElement('div');
      message.className = 'message ' + (msg.sender_id === state.me.id ? 'outgoing' : 'incoming');
      message.id = `msg-${msg.id}`;

      if (msg.reply_id) {
        const reply = document.createElement('div');
        reply.className = 'reply-preview';
        reply.textContent = (msg.reply_sender_name || '') + ': ' + truncate(msg.reply_body || '', 60);
        reply.addEventListener('click', () => {
          const target = document.getElementById(`msg-${msg.reply_id}`);
          if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
        message.appendChild(reply);
      }

      const text = document.createElement('div');
      text.className = 'text';
      text.innerHTML = escapeHtml(msg.body);
      message.appendChild(text);

      const actions = document.createElement('div');
      actions.className = 'actions';

      const replyBtn = document.createElement('button');
      replyBtn.className = 'action-btn';
      replyBtn.textContent = 'پاسخ';
      replyBtn.addEventListener('click', () => setReply(msg));

      const delMeBtn = document.createElement('button');
      delMeBtn.className = 'action-btn';
      delMeBtn.textContent = 'حذف برای من';
      delMeBtn.addEventListener('click', () => deleteForMe(msg.id));

      const delAllBtn = document.createElement('button');
      delAllBtn.className = 'action-btn';
      delAllBtn.textContent = 'حذف برای همه';
      delAllBtn.addEventListener('click', () => deleteForEveryone(msg.id, message));

      actions.appendChild(replyBtn);
      actions.appendChild(delMeBtn);
      actions.appendChild(delAllBtn);
      message.appendChild(actions);

      const meta = document.createElement('div');
      meta.className = 'meta';
      meta.textContent = formatTime(msg.created_at);
      message.appendChild(meta);

      fragment.appendChild(message);
    });

    if (prepend) {
      messagesEl.prepend(fragment);
    } else {
      messagesEl.appendChild(fragment);
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }
  }

  async function sendMessage() {
    if (!state.currentConversation) return;
    const body = messageInput.value.trim();
    if (!body) return;
    const payload = {
      conversation_id: state.currentConversation.id,
      body: body
    };
    if (state.replyTo) {
      payload.reply_to_message_id = state.replyTo.id;
    }
    const res = await apiFetch('/api/messages', { method: 'POST', body: payload });
    if (res.data.ok) {
      messageInput.value = '';
      clearReply();
      await loadMessages();
      await loadConversations();
    }
  }

  function setReply(msg) {
    state.replyTo = msg;
    replyPreview.textContent = (msg.sender_name || '') + ': ' + truncate(msg.body || '', 80);
    replyBar.classList.remove('hidden');
  }

  function clearReply() {
    state.replyTo = null;
    replyBar.classList.add('hidden');
  }

  replyCancel.addEventListener('click', clearReply);

  sendBtn.addEventListener('click', sendMessage);
  messageInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  async function deleteForMe(messageId) {
    const res = await apiFetch('/api/messages/delete-for-me', { method: 'POST', body: { message_id: messageId } });
    if (res.data.ok) {
      const el = document.getElementById(`msg-${messageId}`);
      if (el) el.remove();
      await loadConversations();
    }
  }

  async function deleteForEveryone(messageId, element) {
    const res = await apiFetch('/api/messages/delete-for-everyone', { method: 'POST', body: { message_id: messageId } });
    if (res.data.ok) {
      if (element) {
        const text = element.querySelector('.text');
        if (text) text.textContent = 'این پیام حذف شد.';
      }
      await loadConversations();
    }
  }

  function initEmojiPicker() {
    if (!window.SeloEmojiPicker) return;
    window.SeloEmojiPicker.init(emojiPicker, (emoji) => {
      messageInput.value += emoji;
      messageInput.focus();
    });
  }

  emojiBtn.addEventListener('click', () => {
    emojiPicker.classList.toggle('hidden');
  });

  document.addEventListener('click', (e) => {
    if (!emojiPicker.contains(e.target) && e.target !== emojiBtn) {
      emojiPicker.classList.add('hidden');
    }
  });

  let searchTimeout = null;
  userSearch.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
      const query = userSearch.value.trim();
      if (query.length < 2) {
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
        return;
      }
      const res = await apiFetch('/api/users/search?query=' + encodeURIComponent(query));
      if (res.data.ok) {
        searchResults.innerHTML = '';
        res.data.data.forEach(user => {
          const item = document.createElement('div');
          item.className = 'search-item';
          item.textContent = `${user.full_name} (@${user.username})`;
          item.addEventListener('click', async () => {
            const convRes = await apiFetch('/api/conversations', { method: 'POST', body: { user_id: user.id } });
            if (convRes.data.ok) {
              await loadConversations();
              const conv = state.conversations.find(c => c.id === convRes.data.data.conversation_id);
              if (conv) {
                await selectConversation(conv);
              }
            }
            searchResults.style.display = 'none';
            userSearch.value = '';
          });
          searchResults.appendChild(item);
        });
        searchResults.style.display = res.data.data.length ? 'block' : 'none';
      }
    }, 300);
  });

  backToChats.addEventListener('click', () => {
    document.body.classList.add('show-chats');
  });

  messagesEl.addEventListener('scroll', () => {
    if (messagesEl.scrollTop === 0 && state.oldestMessageId) {
      loadMessages(state.oldestMessageId);
    }
  });

  async function initialize() {
    if (!state.token) {
      showAuth();
      return;
    }
    try {
      const meRes = await apiFetch('/api/me');
      if (!meRes.data.ok) {
        throw new Error('ورود نامعتبر است');
      }
      state.me = meRes.data.data.user;
      showMain();
      initEmojiPicker();
      await loadConversations();
      if (state.conversations.length > 0) {
        selectConversation(state.conversations[0]);
      }
    } catch (err) {
      localStorage.removeItem('selo_token');
      state.token = null;
      showAuth();
    }
  }

  initialize();
})();
