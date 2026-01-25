(function () {
  const state = {
    token: localStorage.getItem('selo_token'),
    me: null,
    conversations: [],
    currentConversation: null,
    replyTo: null,
    loadingMessages: false,
    oldestMessageId: null,
    pendingAttachment: null,
    uploading: false,
    recording: {
      mediaRecorder: null,
      chunks: [],
      timerId: null,
      startTime: 0,
      blob: null,
      duration: 0
    }
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
  const attachBtn = document.getElementById('attach-btn');
  const attachMenu = document.getElementById('attach-menu');
  const mediaInput = document.getElementById('media-input');
  const fileInput = document.getElementById('file-input');
  const attachmentPreview = document.getElementById('attachment-preview');
  const voiceBtn = document.getElementById('voice-btn');
  const voiceRecorder = document.getElementById('voice-recorder');
  const voiceTimer = document.getElementById('voice-timer');
  const voiceCancel = document.getElementById('voice-cancel');
  const voiceStop = document.getElementById('voice-stop');
  const voiceSend = document.getElementById('voice-send');
  const lightbox = document.getElementById('lightbox');
  const lightboxImg = document.getElementById('lightbox-img');
  const lightboxClose = document.getElementById('lightbox-close');
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

  function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return '';
    const sizes = ['B', 'KB', 'MB', 'GB'];
    let i = 0;
    let value = bytes;
    while (value >= 1024 && i < sizes.length - 1) {
      value /= 1024;
      i++;
    }
    return `${value.toFixed(i === 0 ? 0 : 1)} ${sizes[i]}`;
  }

  function formatDuration(seconds) {
    if (!seconds && seconds !== 0) return '00:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
  }

  function getVideoMeta(att) {
    return new Promise((resolve) => {
      if (!att || !att.file) {
        resolve({});
        return;
      }
      const video = document.createElement('video');
      video.preload = 'metadata';
      const url = att.previewUrl || URL.createObjectURL(att.file);
      video.src = url;
      video.onloadedmetadata = () => {
        const info = {
          duration: Math.floor(video.duration || 0),
          width: video.videoWidth || null,
          height: video.videoHeight || null
        };
        if (!att.previewUrl) {
          URL.revokeObjectURL(url);
        }
        resolve(info);
      };
      video.onerror = () => {
        if (!att.previewUrl) {
          URL.revokeObjectURL(url);
        }
        resolve({});
      };
    });
  }

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

  function setAttachment(attachment) {
    state.pendingAttachment = attachment;
    renderAttachmentPreview();
  }

  function clearAttachment() {
    if (state.pendingAttachment?.previewUrl) {
      URL.revokeObjectURL(state.pendingAttachment.previewUrl);
    }
    state.pendingAttachment = null;
    attachmentPreview.classList.add('hidden');
    attachmentPreview.innerHTML = '';
  }

  function renderAttachmentPreview() {
    if (!state.pendingAttachment) {
      attachmentPreview.classList.add('hidden');
      attachmentPreview.innerHTML = '';
      return;
    }
    const att = state.pendingAttachment;
    attachmentPreview.classList.remove('hidden');
    attachmentPreview.innerHTML = '';

    const item = document.createElement('div');
    item.className = 'attachment-item';

    const thumb = document.createElement('div');
    thumb.className = 'attachment-thumb';
    if (att.type === 'photo') {
      const img = document.createElement('img');
      img.src = att.previewUrl;
      thumb.appendChild(img);
    } else if (att.type === 'video') {
      const video = document.createElement('video');
      video.src = att.previewUrl;
      video.muted = true;
      video.playsInline = true;
      video.addEventListener('loadedmetadata', () => {
        video.currentTime = Math.min(0.1, video.duration || 0);
      });
      thumb.appendChild(video);
    } else if (att.type === 'voice') {
      thumb.textContent = '🎤';
    } else {
      thumb.textContent = '📎';
    }

    const meta = document.createElement('div');
    meta.className = 'attachment-meta';
    const title = document.createElement('div');
    title.textContent = att.name || 'فایل';
    const size = document.createElement('div');
    size.textContent = formatBytes(att.size);
    meta.appendChild(title);
    meta.appendChild(size);

    const actions = document.createElement('div');
    actions.className = 'attachment-actions';
    const removeBtn = document.createElement('button');
    removeBtn.className = 'icon-btn';
    removeBtn.textContent = '✖';
    removeBtn.addEventListener('click', clearAttachment);
    actions.appendChild(removeBtn);

    const progress = document.createElement('div');
    progress.className = 'progress';
    const bar = document.createElement('div');
    bar.className = 'progress-bar';
    bar.style.width = `${att.progress || 0}%`;
    progress.appendChild(bar);

    item.appendChild(thumb);
    item.appendChild(meta);
    item.appendChild(actions);
    item.appendChild(progress);
    attachmentPreview.appendChild(item);
  }

  function updateAttachmentProgress(percent) {
    if (!state.pendingAttachment) return;
    state.pendingAttachment.progress = percent;
    const bar = attachmentPreview.querySelector('.progress-bar');
    if (bar) {
      bar.style.width = `${percent}%`;
    }
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

  function apiUpload(file, type, meta = {}) {
    return new Promise((resolve, reject) => {
      const form = new FormData();
      form.append('file', file);
      form.append('type', type);
      Object.keys(meta).forEach((key) => {
        if (meta[key] !== null && meta[key] !== undefined) {
          form.append(key, String(meta[key]));
        }
      });

      const xhr = new XMLHttpRequest();
      xhr.open('POST', makeUrl('/api/uploads'));
      if (state.token) {
        xhr.setRequestHeader('Authorization', 'Bearer ' + state.token);
      }
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const percent = Math.round((e.loaded / e.total) * 100);
          updateAttachmentProgress(percent);
        }
      });
      xhr.onload = () => {
        try {
          const res = JSON.parse(xhr.responseText || '{}');
          if (xhr.status >= 200 && xhr.status < 300 && res.ok) {
            resolve(res.data);
          } else {
            reject(new Error(res.error || 'آپلود ناموفق بود.'));
          }
        } catch (err) {
          reject(new Error('خطا در پاسخ سرور'));
        }
      };
      xhr.onerror = () => reject(new Error('خطا در ارتباط با سرور'));
      xhr.send(form);
    });
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

  function mediaLabel(type, name) {
    switch (type) {
      case 'photo':
        return 'عکس';
      case 'video':
        return 'ویدیو';
      case 'voice':
        return 'پیام صوتی';
      case 'file':
        return name ? `فایل: ${name}` : 'فایل';
      default:
        return '';
    }
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
      preview.textContent = conv.last_preview ? truncate(conv.last_preview, 40) : 'بدون پیام';
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

  function appendMessageContent(message, msg) {
    const type = msg.type || 'text';
    if (type === 'text') {
      const text = document.createElement('div');
      text.className = 'text';
      text.innerHTML = escapeHtml(msg.body || '');
      message.appendChild(text);
      return;
    }

    const mediaWrap = document.createElement('div');
    mediaWrap.className = 'media';

    if (type === 'photo' && msg.media) {
      const img = document.createElement('img');
      img.src = makeUrl('/api/media/' + msg.media.id);
      img.alt = msg.media.original_name || 'photo';
      img.addEventListener('click', () => {
        lightboxImg.src = img.src;
        lightbox.classList.remove('hidden');
      });
      mediaWrap.appendChild(img);
    } else if (type === 'video' && msg.media) {
      const video = document.createElement('video');
      video.src = makeUrl('/api/media/' + msg.media.id);
      video.controls = true;
      video.playsInline = true;
      mediaWrap.appendChild(video);
    } else if (type === 'file' && msg.media) {
      const card = document.createElement('div');
      card.className = 'file-card';
      const icon = document.createElement('div');
      icon.className = 'file-icon';
      icon.textContent = '📎';
      const meta = document.createElement('div');
      meta.className = 'file-meta';
      const link = document.createElement('a');
      link.href = makeUrl('/api/media/' + msg.media.id + '?download=1');
      link.textContent = msg.media.original_name || 'دانلود فایل';
      link.target = '_blank';
      link.rel = 'noopener';
      const size = document.createElement('div');
      size.textContent = formatBytes(msg.media.size_bytes);
      meta.appendChild(link);
      meta.appendChild(size);
      card.appendChild(icon);
      card.appendChild(meta);
      mediaWrap.appendChild(card);
    } else if (type === 'voice' && msg.media) {
      const player = document.createElement('div');
      player.className = 'voice-player';
      const playBtn = document.createElement('button');
      playBtn.className = 'icon-btn';
      playBtn.textContent = '▶️';
      const progress = document.createElement('div');
      progress.className = 'voice-progress';
      const bar = document.createElement('div');
      bar.className = 'voice-progress-bar';
      progress.appendChild(bar);
      const duration = document.createElement('span');
      duration.className = 'voice-duration';
      duration.textContent = formatDuration(msg.media.duration || 0);

      const audio = new Audio(makeUrl('/api/media/' + msg.media.id));
      audio.preload = 'metadata';
      audio.addEventListener('loadedmetadata', () => {
        duration.textContent = formatDuration(msg.media.duration || Math.floor(audio.duration || 0));
      });
      audio.addEventListener('timeupdate', () => {
        const percent = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
        bar.style.width = percent + '%';
        duration.textContent = formatDuration(Math.floor(audio.duration - audio.currentTime));
      });
      audio.addEventListener('ended', () => {
        playBtn.textContent = '▶️';
        bar.style.width = '0%';
        duration.textContent = formatDuration(msg.media.duration || 0);
      });
      playBtn.addEventListener('click', () => {
        if (audio.paused) {
          audio.play();
          playBtn.textContent = '⏸️';
        } else {
          audio.pause();
          playBtn.textContent = '▶️';
        }
      });
      progress.addEventListener('click', (e) => {
        const rect = progress.getBoundingClientRect();
        const ratio = (e.clientX - rect.left) / rect.width;
        if (audio.duration) {
          audio.currentTime = Math.max(0, Math.min(audio.duration, audio.duration * ratio));
        }
      });

      player.appendChild(playBtn);
      player.appendChild(progress);
      player.appendChild(duration);
      mediaWrap.appendChild(player);
    }

    message.appendChild(mediaWrap);

    if (msg.body) {
      const caption = document.createElement('div');
      caption.className = 'text';
      caption.innerHTML = escapeHtml(msg.body);
      message.appendChild(caption);
    }
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
        const replyText = msg.reply_type && msg.reply_type !== 'text'
          ? mediaLabel(msg.reply_type, msg.reply_media_name)
          : truncate(msg.reply_body || '', 60);
        reply.textContent = (msg.reply_sender_name || '') + ': ' + replyText;
        reply.addEventListener('click', () => {
          const target = document.getElementById(`msg-${msg.reply_id}`);
          if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        });
        message.appendChild(reply);
      }

      appendMessageContent(message, msg);

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
    if (state.pendingAttachment) {
      if (state.uploading) return;
      state.uploading = true;
      try {
        const att = state.pendingAttachment;
        let meta = {};
        if (att.type === 'voice' && att.duration) {
          meta.duration = att.duration;
        }
        if (att.type === 'video') {
          const info = await getVideoMeta(att);
          meta = { ...meta, ...info };
        }
        const uploadRes = await apiUpload(att.file, att.type, meta);
        const payload = {
          conversation_id: state.currentConversation.id,
          type: att.type,
          media_id: uploadRes.media_id
        };
        if (state.replyTo) {
          payload.reply_to_message_id = state.replyTo.id;
        }
        const res = await apiFetch('/api/messages', { method: 'POST', body: payload });
        if (res.data.ok) {
          clearAttachment();
          clearReply();
          await loadMessages();
          await loadConversations();
        }
      } catch (err) {
        alert(err.message || 'خطا در ارسال فایل');
      } finally {
        state.uploading = false;
      }
      return;
    }

    const body = messageInput.value.trim();
    if (!body) return;
    const payload = {
      conversation_id: state.currentConversation.id,
      type: 'text',
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
    const previewText = msg.type && msg.type !== 'text'
      ? mediaLabel(msg.type, msg.media?.original_name)
      : truncate(msg.body || '', 80);
    replyPreview.textContent = (msg.sender_name || '') + ': ' + previewText;
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
        const media = element.querySelector('.media');
        if (media) media.remove();
        let text = element.querySelector('.text');
        if (!text) {
          text = document.createElement('div');
          text.className = 'text';
          element.prepend(text);
        }
        text.textContent = 'این پیام حذف شد.';
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

  attachBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    attachMenu.classList.toggle('hidden');
  });

  attachMenu.addEventListener('click', (e) => {
    const button = e.target.closest('button');
    if (!button) return;
    const type = button.dataset.type;
    attachMenu.classList.add('hidden');
    if (type === 'media') {
      mediaInput.click();
    } else if (type === 'file') {
      fileInput.click();
    }
  });

  document.addEventListener('click', (e) => {
    if (!attachMenu.contains(e.target) && e.target !== attachBtn) {
      attachMenu.classList.add('hidden');
    }
  });

  mediaInput.addEventListener('change', () => {
    const file = mediaInput.files[0];
    mediaInput.value = '';
    if (!file) return;
    clearAttachment();
    const type = file.type.startsWith('image/') ? 'photo' : 'video';
    const previewUrl = URL.createObjectURL(file);
    setAttachment({
      type,
      file,
      name: file.name,
      size: file.size,
      previewUrl,
      duration: null,
      width: null,
      height: null,
      progress: 0
    });
  });

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    fileInput.value = '';
    if (!file) return;
    clearAttachment();
    setAttachment({
      type: 'file',
      file,
      name: file.name,
      size: file.size,
      previewUrl: null,
      duration: null,
      width: null,
      height: null,
      progress: 0
    });
  });

  function resetVoiceState() {
    if (state.recording.timerId) {
      clearInterval(state.recording.timerId);
    }
    state.recording = {
      mediaRecorder: null,
      chunks: [],
      timerId: null,
      startTime: 0,
      blob: null,
      duration: 0
    };
    voiceRecorder.classList.add('hidden');
    voiceSend.classList.add('hidden');
    voiceStop.classList.remove('hidden');
    voiceTimer.textContent = '00:00';
  }

  async function startRecording() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert('مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.');
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const recorder = new MediaRecorder(stream);
      state.recording.mediaRecorder = recorder;
      state.recording.chunks = [];
      state.recording.startTime = Date.now();
      voiceRecorder.classList.remove('hidden');
      voiceSend.classList.add('hidden');
      voiceStop.classList.remove('hidden');
      voiceTimer.textContent = '00:00';
      state.recording.timerId = setInterval(() => {
        const seconds = Math.floor((Date.now() - state.recording.startTime) / 1000);
        voiceTimer.textContent = formatDuration(seconds);
      }, 500);

      recorder.ondataavailable = (e) => {
        if (e.data && e.data.size) {
          state.recording.chunks.push(e.data);
        }
      };
      recorder.onstop = () => {
        stream.getTracks().forEach(track => track.stop());
        const blob = new Blob(state.recording.chunks, { type: recorder.mimeType || 'audio/webm' });
        state.recording.blob = blob;
        state.recording.duration = Math.floor((Date.now() - state.recording.startTime) / 1000);
        voiceSend.classList.remove('hidden');
        voiceStop.classList.add('hidden');
      };
      recorder.start();
    } catch (err) {
      alert('دسترسی به میکروفون ممکن نیست.');
    }
  }

  function stopRecording() {
    if (state.recording.mediaRecorder) {
      state.recording.mediaRecorder.stop();
      if (state.recording.timerId) {
        clearInterval(state.recording.timerId);
        state.recording.timerId = null;
      }
    }
  }

  function cancelRecording() {
    if (state.recording.mediaRecorder && state.recording.mediaRecorder.state !== 'inactive') {
      state.recording.mediaRecorder.stop();
    }
    resetVoiceState();
  }

  voiceBtn.addEventListener('click', () => {
    if (state.recording.mediaRecorder) {
      return;
    }
    clearAttachment();
    startRecording();
  });

  voiceStop.addEventListener('click', stopRecording);
  voiceCancel.addEventListener('click', cancelRecording);
  voiceSend.addEventListener('click', () => {
    if (!state.recording.blob) return;
    clearAttachment();
    setAttachment({
      type: 'voice',
      file: state.recording.blob,
      name: 'voice-message.webm',
      size: state.recording.blob.size,
      previewUrl: null,
      duration: state.recording.duration,
      width: null,
      height: null,
      progress: 0
    });
    resetVoiceState();
  });

  if (lightbox && lightboxClose) {
    lightboxClose.addEventListener('click', () => {
      lightbox.classList.add('hidden');
      lightboxImg.src = '';
    });
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        lightbox.classList.add('hidden');
        lightboxImg.src = '';
      }
    });
  }

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
