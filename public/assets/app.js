(function () {
  const state = {
    token: null,
    me: null,
    profilePhotos: [],
    conversations: [],
    currentConversation: null,
    currentGroup: null,
    replyTo: null,
    loadingMessages: false,
    oldestMessageId: null,
    pendingAttachments: [],
    uploading: false,
    uploadProgress: {
      overall: 0
    },
    realtime: {
      mode: null,
      es: null,
      pollTimer: null,
      backoffMs: 1000,
      lastMessageId: 0,
      lastReceiptId: 0,
      connected: false,
      hiddenTimer: null
    },
    pendingSends: {},
    receiptQueue: {
      delivered: new Set(),
      seen: new Set(),
      timer: null
    },
    unread: {
      total: 0,
      byConversation: {}
    },
    readMarkers: {},
    recording: {
      mediaRecorder: null,
      chunks: [],
      timerId: null,
      startTime: 0,
      blob: null,
      duration: 0
    },
    search: {
      results: [],
      activeIndex: -1,
      requestSeq: 0,
      query: ''
    },
    ui: {
      contextMessageId: null,
      contextMessageEl: null,
      contextMessageData: null,
      activeModal: null,
      modalReturnFocus: null,
      lightboxReturnFocus: null
    }
  };

  const authView = document.getElementById('auth-view');
  const mainView = document.getElementById('main-view');
  const loginForm = document.getElementById('login-form');
  const registerForm = document.getElementById('register-form');
  const authError = document.getElementById('auth-error');
  const tabs = document.querySelectorAll('.auth-tabs .tab');

  const chatList = document.getElementById('chat-list');
  const unreadNotice = document.getElementById('unread-notice');
  const unreadCountEl = document.getElementById('unread-count');
  const messagesEl = document.getElementById('messages');
  const messageInput = document.getElementById('message-input');
  const sendBtn = document.getElementById('send-btn');
  const emojiBtn = document.getElementById('emoji-btn');
  const emojiPicker = document.getElementById('emoji-picker');
  const attachBtn = document.getElementById('attach-btn');
  const attachMenu = document.getElementById('attach-menu');
  const photoInput = document.getElementById('photo-input');
  const videoInput = document.getElementById('video-input');
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
  const chatUserStatus = document.getElementById('chat-user-status');
  const chatUserAvatar = document.getElementById('chat-user-avatar');
  const replyBar = document.getElementById('reply-bar');
  const replyPreview = document.getElementById('reply-preview');
  const replyCancel = document.getElementById('reply-cancel');
  const backToChats = document.getElementById('back-to-chats');
  const groupSettingsBtn = document.getElementById('group-settings-btn');
  const infoToggle = document.getElementById('info-toggle');
  const infoPanel = document.getElementById('info-panel');
  const infoClose = document.getElementById('info-close');
  const infoAvatar = document.getElementById('info-avatar');
  const infoTitle = document.getElementById('info-title');
  const infoSubtitle = document.getElementById('info-subtitle');
  const infoStatus = document.getElementById('info-status');
  const infoDescription = document.getElementById('info-description');
  const infoMembers = document.getElementById('info-members');
  const newGroupBtn = document.getElementById('new-group-btn');
  const groupModal = document.getElementById('group-modal');
  const groupModalClose = document.getElementById('group-modal-close');
  const groupForm = document.getElementById('group-form');
  const groupTitleInput = document.getElementById('group-title');
  const groupPrivacySelect = document.getElementById('group-privacy');
  const groupHandleRow = document.getElementById('group-handle-row');
  const groupHandleInput = document.getElementById('group-handle');
  const groupDescriptionInput = document.getElementById('group-description');
  const groupMembersInput = document.getElementById('group-members');
  const groupError = document.getElementById('group-error');

  const groupSettingsModal = document.getElementById('group-settings-modal');
  const groupSettingsClose = document.getElementById('group-settings-close');
  const groupInfoHandle = document.getElementById('group-info-handle');
  const groupInviteRow = document.getElementById('group-invite-row');
  const groupInviteLink = document.getElementById('group-invite-link');
  const groupInviteCopy = document.getElementById('group-invite-copy');
  const groupAllowInvites = document.getElementById('group-allow-invites');
  const groupAllowPhotos = document.getElementById('group-allow-photos');
  const groupAllowVideos = document.getElementById('group-allow-videos');
  const groupAllowVoice = document.getElementById('group-allow-voice');
  const groupAllowFiles = document.getElementById('group-allow-files');
  const groupSettingsSave = document.getElementById('group-settings-save');
  const groupInviteUsername = document.getElementById('group-invite-username');
  const groupInviteSubmit = document.getElementById('group-invite-submit');
  const groupInviteError = document.getElementById('group-invite-error');
  const groupMembersList = document.getElementById('group-members-list');
  const reactionModal = document.getElementById('reaction-modal');
  const reactionModalClose = document.getElementById('reaction-modal-close');
  const reactionModalTitle = document.getElementById('reaction-modal-title');
  const reactionModalList = document.getElementById('reaction-modal-list');
  const userSettingsBtn = document.getElementById('user-settings-btn');
  const sidebarMenuBtn = document.getElementById('sidebar-menu-btn');
  const sidebarMenu = document.getElementById('sidebar-menu');
  const sidebarMenuOverlay = document.getElementById('sidebar-menu-overlay');
  const sidebarProfileBtn = document.getElementById('sidebar-profile-btn');
  const sidebarProfileAvatar = document.getElementById('sidebar-profile-avatar');
  const chatUserHeader = document.getElementById('chat-user-header');
  const menuAvatar = document.getElementById('menu-avatar');
  const menuUserName = document.getElementById('menu-user-name');
  const menuUserUsername = document.getElementById('menu-user-username');
  const menuContactsBtn = document.getElementById('menu-contacts-btn');
  const menuNightBtn = document.getElementById('menu-night-btn');
  const menuLogoutBtn = document.getElementById('menu-logout-btn');
  const profilePanel = document.getElementById('profile-panel');
  const profilePanelClose = document.getElementById('profile-panel-close');
  const profilePanelAvatar = document.getElementById('profile-panel-avatar');
  const profilePanelName = document.getElementById('profile-panel-name');
  const profilePanelUsername = document.getElementById('profile-panel-username');
  const profilePanelStatus = document.getElementById('profile-panel-status');
  const profilePanelBio = document.getElementById('profile-panel-bio');
  const profilePanelEmail = document.getElementById('profile-panel-email');
  const profilePanelPhone = document.getElementById('profile-panel-phone');
  const messageContextMenu = document.getElementById('message-context-menu');
  const messageActionSheet = document.getElementById('message-action-sheet');
  const messageActionSheetList = document.getElementById('message-action-sheet-list');
  const messageActionSheetCancel = document.getElementById('message-action-sheet-cancel');
  const deleteConfirmSheet = document.getElementById('delete-confirm-sheet');
  const deleteForMeBtn = document.getElementById('delete-for-me-btn');
  const deleteForEveryoneBtn = document.getElementById('delete-for-everyone-btn');
  const deleteCancelBtn = document.getElementById('delete-cancel-btn');
  const jumpToBottom = document.getElementById('jump-to-bottom');
  const userSettingsModal = document.getElementById('user-settings-modal');
  const userSettingsClose = document.getElementById('user-settings-close');
  const lastSeenPrivacySelect = document.getElementById('last-seen-privacy-select');
  const profileAvatar = document.getElementById('profile-avatar');
  const profileNameInput = document.getElementById('profile-name');
  const profileUsernameInput = document.getElementById('profile-username');
  const profileBioInput = document.getElementById('profile-bio');
  const profileEmailInput = document.getElementById('profile-email');
  const profilePhoneInput = document.getElementById('profile-phone');
  const profileSaveBtn = document.getElementById('profile-save');
  const profilePhotoInput = document.getElementById('profile-photo-input');
  const profilePhotoChange = document.getElementById('profile-photo-change');
  const profilePhotoRemove = document.getElementById('profile-photo-remove');
  const profileError = document.getElementById('profile-error');

  const toastRegion = document.getElementById('toast-region');
  const liveRegion = document.getElementById('live-region');
  const networkStatus = document.getElementById('network-status');

  const cfg = window.SELO_CONFIG || {};
  const apiBase = cfg.baseUrl || '';
  const basePath = (cfg.basePath || '').replace(/\/$/, '');
  const origin = window.location.origin || '';
  const baseUrl = (() => {
    if (apiBase) {
      try {
        const resolved = new URL(apiBase, origin || undefined);
        if (origin && resolved.origin !== origin) {
          return origin + basePath;
        }
        return resolved.origin + resolved.pathname.replace(/\/$/, '');
      } catch (err) {
        return apiBase.replace(/\/$/, '');
      }
    }
    return origin ? origin + basePath : basePath;
  })();
  const makeUrl = (path) => (baseUrl ? baseUrl + path : path);
  const appUrl = () => (baseUrl || origin || '');
  const API = {
    login: '/api/login',
    register: '/api/register',
    logout: '/api/logout',
    tokenRefresh: '/api/token/refresh',
    me: '/api/me',
    meSettings: '/api/me/settings',
    usersSearch: '/api/users/search',
    conversations: '/api/conversations',
    unreadCount: '/api/unread-count',
    messages: '/api/messages',
    messageAck: '/api/messages/ack',
    messageMarkRead: '/api/messages/mark-read',
    messageStatus: '/api/messages/status',
    messageReaction: (id) => `/api/messages/${id}/reaction`,
    messageReactions: (id) => `/api/messages/${id}/reactions`,
    messageReactionsByEmoji: (id, emoji) => `/api/messages/${id}/reactions?emoji=${encodeURIComponent(emoji)}`,
    deleteForMe: '/api/messages/delete-for-me',
    deleteForEveryone: '/api/messages/delete-for-everyone',
    uploads: '/api/uploads',
    media: (id) => `/api/media/${id}`,
    mediaThumb: (id) => `/api/media/${id}?thumb=1`,
    mediaDownload: (id) => `/api/media/${id}?download=1`,
    stream: '/api/stream',
    poll: '/api/poll',
    groups: '/api/groups',
    group: (id) => `/api/groups/${id}`,
    groupMessages: (id) => `/api/groups/${id}/messages`,
    groupInvite: (id) => `/api/groups/${id}/invite`,
    groupMembers: (groupId, memberId) => `/api/groups/${groupId}/members/${memberId}`,
    groupJoinByLink: '/api/groups/join-by-link',
    profilePhoto: '/api/profile/photo',
    profilePhotoActive: '/api/profile/photo/active',
    profilePhotoDelete: (id) => `/api/profile/photo/${id}`
  };
  const allowedReactions = ['😂', '😜', '👍', '😘', '😁', '🤣', '😍', '🥰', '🤩', '😐', '😑', '🙄', '😬', '🤮', '😎', '🥳', '👎', '🙏'];

  const realtimeConfig = (() => {
    const cfg = window.SELO_CONFIG?.realtime || {};
    const raw = typeof cfg.mode === 'string' ? cfg.mode.trim().toLowerCase() : 'poll';
    const mode = ['auto', 'sse', 'poll'].includes(raw) ? raw : 'poll';
    const sseEnabled = Boolean(cfg.sse_enabled);
    const minDelayMs = 1200;
    const maxDelayMs = 8000;
    const hiddenDelayMs = 4000;
    const jitterMs = 900;
    const errorBaseMs = 2000;
    const errorMaxMs = 10000;
    return { mode, sseEnabled, minDelayMs, maxDelayMs, hiddenDelayMs, jitterMs, errorBaseMs, errorMaxMs };
  })();

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    const swPath = `${basePath || ''}/sw.js`;
    const scope = basePath ? `${basePath}/` : '/';
    window.addEventListener('load', () => {
      navigator.serviceWorker.register(swPath, { scope }).catch(() => {
        // Ignore registration errors in restricted browser modes.
      });
    });
  }

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

  function announceToLiveRegion(message, assertive = false) {
    if (!liveRegion) return;
    liveRegion.setAttribute('aria-live', assertive ? 'assertive' : 'polite');
    liveRegion.textContent = '';
    setTimeout(() => {
      liveRegion.textContent = message;
    }, 20);
  }

  function notify(message, tone = 'error') {
    const text = String(message || '').trim();
    if (!text) return;
    const assertive = tone === 'error';
    announceToLiveRegion(text, assertive);
    if (!toastRegion) return;

    const toast = document.createElement('div');
    toast.className = `app-toast ${tone}`;
    toast.setAttribute('role', assertive ? 'alert' : 'status');

    const body = document.createElement('div');
    body.className = 'app-toast-text';
    body.textContent = text;

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'app-toast-close';
    closeBtn.setAttribute('aria-label', 'بستن اعلان');
    closeBtn.textContent = '×';

    let removed = false;
    const removeToast = () => {
      if (removed) return;
      removed = true;
      toast.remove();
    };

    closeBtn.addEventListener('click', removeToast);
    toast.appendChild(body);
    toast.appendChild(closeBtn);
    toastRegion.appendChild(toast);
    setTimeout(removeToast, assertive ? 7000 : 4500);
  }

  let networkStatusTimer = null;

  function showNetworkStatus(message, tone = 'degraded', autoHideMs = 0) {
    if (!networkStatus) return;
    const text = String(message || '').trim();
    if (!text) return;
    if (networkStatusTimer) {
      clearTimeout(networkStatusTimer);
      networkStatusTimer = null;
    }
    networkStatus.textContent = text;
    networkStatus.classList.remove('hidden', 'offline', 'degraded', 'restored');
    networkStatus.classList.add(tone);
    if (autoHideMs > 0) {
      networkStatusTimer = setTimeout(() => {
        networkStatus.classList.add('hidden');
        networkStatusTimer = null;
      }, autoHideMs);
    }
  }

  function hideNetworkStatus() {
    if (!networkStatus) return;
    if (networkStatusTimer) {
      clearTimeout(networkStatusTimer);
      networkStatusTimer = null;
    }
    networkStatus.classList.add('hidden');
    networkStatus.classList.remove('offline', 'degraded', 'restored');
    networkStatus.textContent = '';
  }

  function updateNetworkStatus() {
    if (!networkStatus) return;
    if (!state.token) {
      hideNetworkStatus();
      return;
    }
    if (navigator.onLine === false) {
      showNetworkStatus('شما آفلاین هستید. پیام‌ها بعد از اتصال ارسال/دریافت می‌شوند.', 'offline');
      return;
    }
    if (!state.realtime.mode) {
      hideNetworkStatus();
      return;
    }
    if (!state.realtime.connected) {
      showNetworkStatus('اتصال ناپایدار است. در حال تلاش برای بازیابی...', 'degraded');
      return;
    }
    if (!networkStatus.classList.contains('hidden')) {
      showNetworkStatus('اتصال دوباره برقرار شد.', 'restored', 2500);
    } else {
      hideNetworkStatus();
    }
  }

  function generateClientId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
  }

  function detectAttachmentType(file, typeHint = 'auto') {
    if (typeHint && typeHint !== 'auto') return typeHint;
    const mime = file.type || '';
    if (mime.startsWith('image/')) return 'photo';
    if (mime.startsWith('video/')) return 'video';
    if (mime.startsWith('audio/')) return 'voice';
    const ext = (file.name || '').split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) return 'photo';
    if (['mp4', 'webm', 'ogv', 'mov'].includes(ext)) return 'video';
    if (['mp3', 'wav', 'ogg', 'webm', 'm4a', 'aac'].includes(ext)) return 'voice';
    return 'file';
  }

  function buildAttachment(file, typeHint = 'auto') {
    const type = detectAttachmentType(file, typeHint);
    const previewable = type === 'photo' || type === 'video';
    const previewUrl = previewable ? URL.createObjectURL(file) : null;
    return {
      id: generateClientId(),
      type,
      file,
      name: file.name || 'file',
      size: file.size || 0,
      previewUrl,
      duration: null,
      width: null,
      height: null,
      progress: 0,
      forceType: type === 'voice' && file.type === 'video/webm' ? 'voice' : null
    };
  }

  function filterAllowedAttachments(attachments) {
    const allowed = [];
    let rejected = 0;
    attachments.forEach(att => {
      if (groupAllows(att.type)) {
        allowed.push(att);
      } else {
        rejected++;
      }
    });
    if (rejected > 0) {
      notify('برخی فایل‌ها به دلیل محدودیت‌های گروه ارسال نمی‌شوند.');
    }
    return allowed;
  }

  function isGroupChat() {
    return state.currentConversation?.chat_type === 'group';
  }

  function validGroupHandle(handle) {
    return /^[a-z0-9_]+group$/.test(handle);
  }

  function toggleButton(btn, enabled) {
    if (!btn) return;
    btn.disabled = !enabled;
    btn.classList.toggle('disabled', !enabled);
  }

  function applyComposerPermissions() {
    const isGroup = isGroupChat();
    if (!isGroup) {
      toggleButton(voiceBtn, true);
      toggleAttachOption('photo', true);
      toggleAttachOption('video', true);
      toggleAttachOption('file', true);
      return;
    }
    const group = state.currentGroup?.group;
    if (!group) {
      return;
    }
    toggleButton(voiceBtn, !!group.allow_voice);
    toggleAttachOption('photo', !!group.allow_photos);
    toggleAttachOption('video', !!group.allow_videos);
    toggleAttachOption('file', !!group.allow_files);
  }

  function toggleAttachOption(type, enabled) {
    const btn = attachMenu?.querySelector(`button[data-type=\"${type}\"]`);
    toggleButton(btn, enabled);
  }

  function groupAllows(type) {
    if (!isGroupChat()) return true;
    const group = state.currentGroup?.group;
    if (!group) return true;
    if (type === 'photo') return !!group.allow_photos;
    if (type === 'video') return !!group.allow_videos;
    if (type === 'voice') return !!group.allow_voice;
    if (type === 'file') return !!group.allow_files;
    return true;
  }

  function buildReactionBar(messageId, currentReaction) {
    const bar = document.createElement('div');
    bar.className = 'reaction-bar';
    allowedReactions.forEach((emoji) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = emoji;
      if (currentReaction === emoji) {
        btn.classList.add('active');
      }
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleReaction(messageId, emoji);
      });
      bar.appendChild(btn);
    });
    return bar;
  }

  function buildReactionChips(messageId, reactions, currentReaction, animate = false) {
    if (!reactions || reactions.length === 0) return null;
    const wrap = document.createElement('div');
    wrap.className = 'reaction-chips';
    reactions.forEach((reaction) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'reaction-chip';
      if (animate) {
        chip.classList.add('pop');
      }
      if (currentReaction === reaction.emoji) {
        chip.classList.add('active');
      }
      chip.textContent = `${reaction.emoji} ${reaction.count}`;
      chip.addEventListener('click', (e) => {
        e.stopPropagation();
        openReactionModal(messageId, reaction.emoji);
      });
      wrap.appendChild(chip);
    });
    return wrap;
  }

  async function copyMessageText(text) {
    if (!text) return;
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        await navigator.clipboard.writeText(text);
        return;
      }
    } catch (err) {
      // fallback below
    }
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
    } finally {
      document.body.removeChild(textarea);
    }
  }

  function closeMessageMenus() {
    if (messageContextMenu) {
      messageContextMenu.classList.add('hidden');
      messageContextMenu.innerHTML = '';
    }
    if (messageActionSheet) {
      messageActionSheet.classList.remove('show');
      messageActionSheet.classList.add('hidden');
    }
  }

  function closeDeleteConfirm() {
    if (!deleteConfirmSheet) return;
    deleteConfirmSheet.classList.remove('show');
    deleteConfirmSheet.classList.add('hidden');
    state.ui.contextMessageId = null;
    state.ui.contextMessageEl = null;
    state.ui.contextMessageData = null;
  }

  function openDeleteConfirm(messageId, element, msg) {
    if (!deleteConfirmSheet) return;
    state.ui.contextMessageId = messageId;
    state.ui.contextMessageEl = element;
    state.ui.contextMessageData = msg || null;
    if (deleteForEveryoneBtn) {
      const canDeleteForEveryone = !!(state.me && msg && Number(msg.sender_id) === Number(state.me.id));
      deleteForEveryoneBtn.classList.toggle('hidden', !canDeleteForEveryone);
      deleteForEveryoneBtn.disabled = !canDeleteForEveryone;
    }
    deleteConfirmSheet.classList.remove('hidden');
    requestAnimationFrame(() => deleteConfirmSheet.classList.add('show'));
  }

  function getMessageMediaItems(msg) {
    const attachments = Array.isArray(msg.attachments) ? msg.attachments : [];
    const fallback = msg.media ? [msg.media] : [];
    return attachments.length ? attachments : fallback;
  }

  function buildMessageActions(msg, element) {
    const actions = [];
    actions.push({
      id: 'reply',
      label: 'پاسخ',
      icon: 'reply',
      handler: () => {
        setReply(msg);
      }
    });

    if (msg.body && msg.type === 'text') {
      actions.push({
        id: 'copy',
        label: 'کپی متن',
        icon: 'content_copy',
        handler: async () => {
          await copyMessageText(msg.body);
        }
      });
    }

    const mediaItems = getMessageMediaItems(msg);
    const hasPhotoOrVideo = mediaItems.some(att => att.type === 'photo' || att.type === 'video');
    const hasFile = mediaItems.some(att => !['photo', 'video', 'voice'].includes(att.type));
    if (hasPhotoOrVideo) {
      const target = mediaItems.find(att => att.type === 'photo' || att.type === 'video');
      if (target) {
        actions.push({
          id: 'save',
          label: 'ذخیره در گالری',
          icon: 'image',
          handler: () => {
            window.open(makeUrl(API.mediaDownload(target.id)), '_blank');
          }
        });
      }
    }
    if (hasFile) {
      const target = mediaItems.find(att => !['photo', 'video', 'voice'].includes(att.type));
      if (target) {
        actions.push({
          id: 'download',
          label: 'دانلود',
          icon: 'download',
          handler: () => {
            window.open(makeUrl(API.mediaDownload(target.id)), '_blank');
          }
        });
      }
    }

    actions.push({
      id: 'delete',
      label: 'حذف',
      icon: 'delete',
      danger: true,
      handler: () => {
        openDeleteConfirm(msg.id, element, msg);
      }
    });

    return actions;
  }

  function openMessageContextMenu(messageId, element, msg, x, y) {
    if (!messageContextMenu) return;
    closeMessageMenus();
    const actions = buildMessageActions(msg, element);
    messageContextMenu.innerHTML = '';
    actions.forEach(action => {
      const btn = document.createElement('button');
      btn.type = 'button';
      if (action.danger) btn.classList.add('danger');
      const icon = document.createElement('span');
      icon.className = 'material-symbols-rounded';
      icon.textContent = action.icon;
      btn.appendChild(icon);
      btn.appendChild(document.createTextNode(action.label));
      btn.addEventListener('click', async () => {
        closeMessageMenus();
        await action.handler();
      });
      messageContextMenu.appendChild(btn);
    });
    messageContextMenu.classList.remove('hidden');
    const menuRect = messageContextMenu.getBoundingClientRect();
    const maxX = window.innerWidth - menuRect.width - 8;
    const maxY = window.innerHeight - menuRect.height - 8;
    const posX = Math.min(maxX, Math.max(8, x));
    const posY = Math.min(maxY, Math.max(8, y));
    messageContextMenu.style.left = posX + 'px';
    messageContextMenu.style.top = posY + 'px';
  }

  function openMessageActionSheet(messageId, element, msg) {
    if (!messageActionSheet || !messageActionSheetList) return;
    closeMessageMenus();
    const actions = buildMessageActions(msg, element);
    messageActionSheetList.innerHTML = '';
    actions.forEach(action => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sheet-item' + (action.danger ? ' danger' : '');
      const icon = document.createElement('span');
      icon.className = 'material-symbols-rounded';
      icon.textContent = action.icon;
      btn.appendChild(icon);
      btn.appendChild(document.createTextNode(action.label));
      btn.addEventListener('click', async () => {
        closeMessageMenus();
        await action.handler();
      });
      messageActionSheetList.appendChild(btn);
    });
    messageActionSheet.classList.remove('hidden');
    requestAnimationFrame(() => messageActionSheet.classList.add('show'));
  }

  async function toggleReaction(messageId, emoji) {
    const messageEl = document.getElementById(`msg-${messageId}`);
    if (messageEl?.dataset.reactionBusy === '1') return;
    if (messageEl) {
      messageEl.dataset.reactionBusy = '1';
    }
    const current = messageEl?.dataset.currentReaction || '';
    try {
      if (current === emoji) {
        await apiFetch(API.messageReaction(messageId), { method: 'DELETE' });
      } else {
        await apiFetch(API.messageReaction(messageId), { method: 'PUT', body: { emoji } });
      }
      await refreshReactions(messageId);
      if (messageEl) {
        messageEl.classList.remove('show-reactions');
      }
    } catch (err) {
      notify(err.message || 'خطا در واکنش پیام');
    } finally {
      if (messageEl) {
        messageEl.dataset.reactionBusy = '0';
      }
    }
  }

  async function refreshReactions(messageId) {
    const res = await apiFetch(API.messageReactions(messageId));
    if (!res.data.ok) return;
    const info = res.data.data;
    const messageEl = document.getElementById(`msg-${messageId}`);
    if (!messageEl) return;
    messageEl.dataset.currentReaction = info.current_user_reaction || '';
    const bar = messageEl.querySelector('.reaction-bar');
    if (bar) {
      bar.querySelectorAll('button').forEach(btn => {
        btn.classList.toggle('active', btn.textContent === info.current_user_reaction);
      });
    }
    const oldChips = messageEl.querySelector('.reaction-chips');
    if (oldChips) oldChips.remove();
    const chips = buildReactionChips(messageId, info.reactions, info.current_user_reaction, true);
    if (chips) {
      messageEl.appendChild(chips);
    }
  }

  async function openReactionModal(messageId, emoji) {
    const res = await apiFetch(API.messageReactionsByEmoji(messageId, emoji));
    if (!res.data.ok) return;
    const reactions = res.data.data.reactions || [];
    const entry = reactions.find(r => r.emoji === emoji);
    const users = entry?.users || [];
    reactionModalTitle.textContent = `واکنش‌ها ${emoji}`;
    reactionModalList.innerHTML = '';
    users.forEach((user) => {
      const item = document.createElement('div');
      item.className = 'member-item';
      const meta = document.createElement('div');
      meta.className = 'member-meta';
      const avatar = document.createElement('div');
      avatar.className = 'member-avatar';
      if (user.photo_id) {
        avatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + user.photo_id + '&thumb=1')})`;
      } else {
        avatar.textContent = '👤';
      }
      const text = document.createElement('div');
      text.textContent = `${user.full_name} (@${user.username})`;
      meta.appendChild(avatar);
      meta.appendChild(text);
      item.appendChild(meta);
      reactionModalList.appendChild(item);
    });
    openModal(reactionModal);
  }

  function attachMessageLongPress(messageEl, msg) {
    let timer = null;
    messageEl.addEventListener('touchstart', (e) => {
      if (e.target.closest('.reaction-bar') || e.target.closest('.reaction-chip')) return;
      timer = setTimeout(() => {
        openMessageActionSheet(msg.id, messageEl, msg);
      }, 400);
    }, { passive: true });
    messageEl.addEventListener('touchend', () => {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
    }, { passive: true });
    messageEl.addEventListener('touchcancel', () => {
      if (timer) {
        clearTimeout(timer);
        timer = null;
      }
    }, { passive: true });
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

  function openSidebarMenu() {
    if (!sidebarMenu || !sidebarMenuOverlay) return;
    syncSidebarProfile();
    sidebarMenu.classList.add('show');
    sidebarMenuOverlay.classList.add('show');
    sidebarMenu.classList.remove('hidden');
    sidebarMenuOverlay.classList.remove('hidden');
  }

  function closeSidebarMenu() {
    if (!sidebarMenu || !sidebarMenuOverlay) return;
    sidebarMenu.classList.remove('show');
    sidebarMenuOverlay.classList.remove('show');
    setTimeout(() => {
      if (!sidebarMenu.classList.contains('show')) {
        sidebarMenu.classList.add('hidden');
        sidebarMenuOverlay.classList.add('hidden');
      }
    }, 160);
  }

  function openProfilePanel() {
    if (!profilePanel) return;
    setInfoPanelVisible(false);
    populateProfilePanel();
    profilePanel.classList.remove('hidden');
    document.body.classList.add('show-profile');
  }

  function closeProfilePanel() {
    document.body.classList.remove('show-profile');
    if (profilePanel) {
      setTimeout(() => {
        if (!document.body.classList.contains('show-profile')) {
          profilePanel.classList.add('hidden');
        }
      }, 160);
    }
  }

  function populateProfilePanel() {
    if (!state.me) return;
    const avatarUrl = state.me.active_photo_id ? makeUrl('/photo.php?id=' + state.me.active_photo_id + '&thumb=1') : '';
    const initial = (state.me.full_name || '👤').trim().slice(0, 1) || '👤';
    if (profilePanelAvatar) {
      if (avatarUrl) {
        profilePanelAvatar.style.backgroundImage = `url(${avatarUrl})`;
        profilePanelAvatar.textContent = '';
      } else {
        profilePanelAvatar.style.backgroundImage = '';
        profilePanelAvatar.textContent = initial;
      }
    }
    if (profilePanelName) profilePanelName.textContent = state.me.full_name || '—';
    if (profilePanelUsername) profilePanelUsername.textContent = state.me.username ? '@' + state.me.username : '';
    if (profilePanelStatus) profilePanelStatus.textContent = 'آنلاین';
    if (profilePanelBio) profilePanelBio.textContent = state.me.bio || '—';
    if (profilePanelEmail) profilePanelEmail.textContent = state.me.email || '—';
    if (profilePanelPhone) profilePanelPhone.textContent = state.me.phone || '—';
  }

  function syncSidebarProfile() {
    if (!state.me) return;
    const avatarUrl = state.me.active_photo_id ? makeUrl('/photo.php?id=' + state.me.active_photo_id + '&thumb=1') : '';
    const initial = (state.me.full_name || '👤').trim().slice(0, 1) || '👤';
    if (sidebarProfileAvatar) {
      if (avatarUrl) {
        sidebarProfileAvatar.style.backgroundImage = `url(${avatarUrl})`;
        sidebarProfileAvatar.textContent = '';
      } else {
        sidebarProfileAvatar.style.backgroundImage = '';
        sidebarProfileAvatar.textContent = initial;
      }
    }
    if (menuAvatar) {
      if (avatarUrl) {
        menuAvatar.style.backgroundImage = `url(${avatarUrl})`;
        menuAvatar.textContent = '';
      } else {
        menuAvatar.style.backgroundImage = '';
        menuAvatar.textContent = initial;
      }
    }
    if (menuUserName) menuUserName.textContent = state.me.full_name || '';
    if (menuUserUsername) menuUserUsername.textContent = state.me.username ? '@' + state.me.username : '';
  }

  function isMobileLayout() {
    return window.matchMedia('(max-width: 768px)').matches;
  }

  function motionBehavior() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth';
  }

  const savedTheme = localStorage.getItem('selo_theme') || 'light';
  setTheme(savedTheme);

  function bindKeyboardActivation(element, callback) {
    if (!element || typeof callback !== 'function') return;
    element.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      callback();
    });
  }

  themeToggle.addEventListener('click', () => {
    const newTheme = document.body.dataset.theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
  });

  sidebarMenuBtn?.addEventListener('click', openSidebarMenu);
  sidebarMenuOverlay?.addEventListener('click', closeSidebarMenu);
  sidebarProfileBtn?.addEventListener('click', () => {
    closeSidebarMenu();
    openProfilePanel();
  });
  const openProfileFromMenu = () => {
    closeSidebarMenu();
    openProfilePanel();
  };
  menuAvatar?.addEventListener('click', openProfileFromMenu);
  bindKeyboardActivation(menuAvatar, openProfileFromMenu);
  profilePanelClose?.addEventListener('click', closeProfilePanel);

  menuNightBtn?.addEventListener('click', () => {
    const newTheme = document.body.dataset.theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
    closeSidebarMenu();
  });

  menuContactsBtn?.addEventListener('click', () => {
    closeSidebarMenu();
    document.body.classList.add('show-chats');
    userSearch?.focus();
  });

  menuLogoutBtn?.addEventListener('click', async () => {
    try {
      await apiFetch(API.logout, { method: 'POST' });
    } catch (err) {
      // Ignore logout transport failures; local state is still cleared.
    }
    state.token = null;
    location.reload();
  });

  infoToggle?.addEventListener('click', () => {
    if (!state.currentConversation) return;
    const willShow = infoPanel ? infoPanel.classList.contains('hidden') : false;
    setInfoPanelVisible(willShow);
    updateInfoPanel();
  });

  const openCurrentChatInfo = () => {
    if (!state.currentConversation) return;
    setInfoPanelVisible(true);
    updateInfoPanel();
  };
  chatUserHeader?.addEventListener('click', openCurrentChatInfo);
  bindKeyboardActivation(chatUserHeader, openCurrentChatInfo);

  infoClose?.addEventListener('click', () => {
    setInfoPanelVisible(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      if (state.ui.activeModal) {
        e.preventDefault();
        closeModal(state.ui.activeModal);
        return;
      }
      if (isLightboxOpen()) {
        e.preventDefault();
        closeLightbox();
        return;
      }
      if (isSearchResultsOpen()) {
        e.preventDefault();
        closeSearchResults();
        return;
      }
      if (document.body.classList.contains('show-info')) {
        setInfoPanelVisible(false);
      }
      if (document.body.classList.contains('show-profile')) {
        closeProfilePanel();
      }
      closeMessageMenus();
      closeDeleteConfirm();
      closeSidebarMenu();
    }
  });
  document.addEventListener('keydown', trapModalFocus);

  document.addEventListener('click', (e) => {
    if (!document.body.classList.contains('show-info') || !infoPanel || !infoToggle) return;
    if (infoPanel.contains(e.target) || infoToggle.contains(e.target)) return;
    setInfoPanelVisible(false);
  });

  document.addEventListener('click', (e) => {
    if (!document.body.classList.contains('show-profile') || !profilePanel || !sidebarProfileBtn) return;
    if (profilePanel.contains(e.target) || sidebarProfileBtn.contains(e.target)) return;
    closeProfilePanel();
  });

  document.addEventListener('click', (e) => {
    if (messageContextMenu && !messageContextMenu.contains(e.target)) {
      closeMessageMenus();
    }
  });

  window.addEventListener('resize', closeMessageMenus);

  userSettingsBtn?.addEventListener('click', async () => {
    closeSidebarMenu();
    await refreshMe();
    syncUserSettingsUI();
    populateProfileForm();
    openModal(userSettingsModal);
  });

  profileSaveBtn?.addEventListener('click', (e) => {
    e.preventDefault();
    saveProfile();
  });

  profilePhotoChange?.addEventListener('click', () => {
    profilePhotoInput?.click();
  });

  profilePhotoInput?.addEventListener('change', () => {
    const file = profilePhotoInput.files[0];
    profilePhotoInput.value = '';
    if (!file) return;
    handleProfilePhoto(file);
  });

  profilePhotoRemove?.addEventListener('click', async () => {
    if (!state.me?.active_photo_id) return;
    try {
      const res = await apiFetch(API.profilePhotoDelete(state.me.active_photo_id), { method: 'DELETE' });
      if (!res.data.ok) {
        throw new Error(res.data.error || 'خطا در حذف عکس');
      }
      await refreshMe();
      populateProfileForm();
      scheduleConversationsRefresh();
    } catch (err) {
      if (profileError) {
        profileError.textContent = err.message || 'خطا در حذف عکس';
      } else {
        notify(err.message || 'خطا در حذف عکس');
      }
    }
  });

  profileUsernameInput?.addEventListener('input', () => {
    profileUsernameInput.value = profileUsernameInput.value.toLowerCase();
  });

  newGroupBtn?.addEventListener('click', () => {
    groupError.textContent = '';
    groupForm.reset();
    groupHandleRow.classList.add('hidden');
    openModal(groupModal);
  });

  groupModalClose?.addEventListener('click', () => closeModal(groupModal));
  groupSettingsClose?.addEventListener('click', () => closeModal(groupSettingsModal));
  userSettingsClose?.addEventListener('click', () => closeModal(userSettingsModal));
  reactionModalClose?.addEventListener('click', () => closeModal(reactionModal));
  messageActionSheetCancel?.addEventListener('click', closeMessageMenus);
  messageActionSheet?.addEventListener('click', (e) => {
    if (e.target === messageActionSheet) closeMessageMenus();
  });
  deleteCancelBtn?.addEventListener('click', closeDeleteConfirm);
  deleteConfirmSheet?.addEventListener('click', (e) => {
    if (e.target === deleteConfirmSheet) closeDeleteConfirm();
  });

  deleteForMeBtn?.addEventListener('click', async () => {
    if (!state.ui.contextMessageId) return;
    const messageId = state.ui.contextMessageId;
    closeDeleteConfirm();
    await deleteForMe(messageId);
  });

  deleteForEveryoneBtn?.addEventListener('click', async () => {
    if (!state.ui.contextMessageId) return;
    const messageId = state.ui.contextMessageId;
    const element = state.ui.contextMessageEl;
    closeDeleteConfirm();
    await deleteForEveryone(messageId, element);
  });

  groupModal?.addEventListener('click', (e) => {
    if (e.target === groupModal) closeModal(groupModal);
  });

  groupSettingsModal?.addEventListener('click', (e) => {
    if (e.target === groupSettingsModal) closeModal(groupSettingsModal);
  });

  userSettingsModal?.addEventListener('click', (e) => {
    if (e.target === userSettingsModal) closeModal(userSettingsModal);
  });

  reactionModal?.addEventListener('click', (e) => {
    if (e.target === reactionModal) closeModal(reactionModal);
  });

  lastSeenPrivacySelect?.addEventListener('change', () => {
    updateLastSeenPrivacy(lastSeenPrivacySelect.value);
  });

  groupPrivacySelect?.addEventListener('change', () => {
    if (groupPrivacySelect.value === 'public') {
      groupHandleRow.classList.remove('hidden');
    } else {
      groupHandleRow.classList.add('hidden');
    }
  });

  groupHandleInput?.addEventListener('input', () => {
    groupHandleInput.value = groupHandleInput.value.toLowerCase();
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
    state.pendingSends = {};
    setInfoPanelVisible(false);
    closeProfilePanel();
    closeSidebarMenu();
    closeMessageMenus();
    hideNetworkStatus();
  }

  function showMain() {
    authView.classList.add('hidden');
    mainView.classList.remove('hidden');
    document.body.classList.add('show-chats');
    setInfoPanelVisible(false);
    updateNetworkStatus();
  }

  function setAttachments(attachments) {
    clearAttachments();
    state.pendingAttachments = attachments.slice(0, 10);
    state.uploadProgress.overall = 0;
    renderAttachmentPreview();
  }

  function clearAttachments() {
    state.pendingAttachments.forEach((att) => {
      if (att.previewUrl) {
        URL.revokeObjectURL(att.previewUrl);
      }
    });
    state.pendingAttachments = [];
    state.uploadProgress.overall = 0;
    attachmentPreview.classList.add('hidden');
    attachmentPreview.innerHTML = '';
  }

  function removeAttachment(id) {
    const next = state.pendingAttachments.filter(att => att.id !== id);
    if (next.length === 0) {
      clearAttachments();
      return;
    }
    state.pendingAttachments = next;
    renderAttachmentPreview();
  }

  function renderAttachmentPreview() {
    if (!state.pendingAttachments.length) {
      attachmentPreview.classList.add('hidden');
      attachmentPreview.innerHTML = '';
      return;
    }
    attachmentPreview.classList.remove('hidden');
    attachmentPreview.innerHTML = '';

    const header = document.createElement('div');
    header.className = 'attachment-header';
    const count = document.createElement('div');
    count.textContent = `${state.pendingAttachments.length} پیوست`;
    const clearBtn = document.createElement('button');
    clearBtn.className = 'icon-btn';
    clearBtn.textContent = '✖';
    clearBtn.title = 'حذف همه';
    clearBtn.addEventListener('click', clearAttachments);
    header.appendChild(count);
    header.appendChild(clearBtn);

    const overall = document.createElement('div');
    overall.className = 'progress overall-progress';
    const overallBar = document.createElement('div');
    overallBar.className = 'progress-bar';
    overallBar.style.width = `${state.uploadProgress.overall || 0}%`;
    overallBar.dataset.overall = '1';
    overall.appendChild(overallBar);

    const list = document.createElement('div');
    list.className = 'attachment-list';

    state.pendingAttachments.forEach((att) => {
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
      removeBtn.addEventListener('click', () => removeAttachment(att.id));
      actions.appendChild(removeBtn);

      const progress = document.createElement('div');
      progress.className = 'progress';
      const bar = document.createElement('div');
      bar.className = 'progress-bar';
      bar.style.width = `${att.progress || 0}%`;
      bar.dataset.attachmentId = att.id;
      progress.appendChild(bar);

      item.appendChild(thumb);
      item.appendChild(meta);
      item.appendChild(actions);
      item.appendChild(progress);
      list.appendChild(item);
    });

    attachmentPreview.appendChild(header);
    attachmentPreview.appendChild(overall);
    attachmentPreview.appendChild(list);
  }

  function updateUploadProgress(loaded, total) {
    if (!state.pendingAttachments.length) return;
    const overall = total > 0 ? Math.round((loaded / total) * 100) : 0;
    state.uploadProgress.overall = overall;
    const overallBar = attachmentPreview.querySelector('.progress-bar[data-overall="1"]');
    if (overallBar) {
      overallBar.style.width = `${overall}%`;
    }

    let offset = 0;
    state.pendingAttachments.forEach((att) => {
      const size = att.size || 1;
      const progress = Math.max(0, Math.min(1, (loaded - offset) / size));
      att.progress = Math.round(progress * 100);
      const bar = attachmentPreview.querySelector(`.progress-bar[data-attachment-id="${att.id}"]`);
      if (bar) {
        bar.style.width = `${att.progress}%`;
      }
      offset += size;
    });
  }

  function getCookie(name) {
    const escaped = name.replace(/[-[\]{}()*+?.,\\^$|#\s]/g, '\\$&');
    const match = document.cookie.match(new RegExp('(?:^|; )' + escaped + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }

  async function apiFetch(path, options = {}) {
    const headers = options.headers || {};
    if (state.token) {
      headers['Authorization'] = 'Bearer ' + state.token;
    }
    const csrfToken = getCookie('selo_csrf');
    if (csrfToken) {
      headers['X-CSRF-Token'] = csrfToken;
    }
    if (options.body && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
      options.body = JSON.stringify(options.body);
    }
    const response = await fetch(makeUrl(path), {
      ...options,
      credentials: 'same-origin',
      headers
    });
    if (response.status === 204) {
      return { status: 204, data: null };
    }
    const text = await response.text();
    const data = text ? JSON.parse(text) : null;
    if (!data) {
      throw new Error('خطا در پاسخ سرور');
    }
    return { status: response.status, data };
  }

  function apiUploadAttachments(attachments) {
    return new Promise((resolve, reject) => {
      const form = new FormData();
      attachments.forEach((att) => {
        form.append('files[]', att.file, att.name || 'file');
      });
      form.append('type', 'auto');
      form.append('multi', '1');
      const meta = attachments.map((att) => ({
        duration: att.duration || null,
        width: att.width || null,
        height: att.height || null,
        force_type: att.forceType || null
      }));
      form.append('meta', JSON.stringify(meta));

      const xhr = new XMLHttpRequest();
      xhr.open('POST', makeUrl(API.uploads));
      if (state.token) {
        xhr.setRequestHeader('Authorization', 'Bearer ' + state.token);
      }
      const csrfToken = getCookie('selo_csrf');
      if (csrfToken) {
        xhr.setRequestHeader('X-CSRF-Token', csrfToken);
      }
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          updateUploadProgress(e.loaded, e.total);
        }
      });
      xhr.onload = () => {
        try {
          const res = JSON.parse(xhr.responseText || '{}');
          if (xhr.status >= 200 && xhr.status < 300 && res.ok) {
            const data = res.data || {};
            if (Array.isArray(data.items)) {
              resolve(data.items);
            } else if (data.media_id) {
              resolve([data]);
            } else {
              resolve([]);
            }
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

  let conversationsRefreshTimer = null;
  let conversationsAutoRefreshTimer = null;
  function scheduleConversationsRefresh() {
    if (conversationsRefreshTimer) return;
    conversationsRefreshTimer = setTimeout(async () => {
      conversationsRefreshTimer = null;
      await loadConversations();
    }, 1200);
  }

  function updateUnreadUI() {
    if (!unreadNotice || !unreadCountEl) return;
    const total = Number(state.unread.total || 0);
    unreadCountEl.textContent = total > 99 ? '99+' : String(total);
    unreadNotice.classList.toggle('hidden', total <= 0);
  }

  function applyUnreadCounts(total, byConversation = {}, rerender = true) {
    state.unread.total = Number(total || 0);
    state.unread.byConversation = byConversation || {};
    updateUnreadUI();
    if (rerender && state.conversations.length) {
      state.conversations.forEach((conv) => {
        if (conv.chat_type !== 'direct') return;
        conv.unread_count = Number(state.unread.byConversation[conv.id] || 0);
      });
      renderConversations();
    }
  }

  function syncUnreadFromConversations() {
    const map = {};
    let total = 0;
    state.conversations.forEach((conv) => {
      if (conv.chat_type !== 'direct') return;
      const count = Number(conv.unread_count || conv.unread || 0);
      if (count > 0) {
        map[conv.id] = count;
        total += count;
      }
    });
    applyUnreadCounts(total, map, false);
  }

  async function fetchUnreadCount() {
    if (!state.token) return;
    try {
      const res = await apiFetch(API.unreadCount);
      if (res.data && res.data.ok) {
        const payload = res.data.data || {};
        applyUnreadCounts(payload.total_unread || 0, payload.by_conversation || {});
      }
    } catch (err) {
      // ignore
    }
  }

  function incrementUnread(conversationId) {
    if (!conversationId) return;
    const current = state.unread.byConversation[conversationId] || 0;
    state.unread.byConversation[conversationId] = current + 1;
    state.unread.total = Math.max(0, (state.unread.total || 0) + 1);
    updateUnreadUI();
  }

  function isCurrentMessage(msg) {
    if (!state.currentConversation) return false;
    if (state.currentConversation.chat_type === 'group') {
      return msg.group_id === state.currentConversation.id;
    }
    return msg.conversation_id === state.currentConversation.id;
  }

  function handleIncomingMessage(msg) {
    if (!msg || !msg.id) return;
    state.realtime.lastMessageId = Math.max(state.realtime.lastMessageId, msg.id);
    const isCurrent = isCurrentMessage(msg);
    const wasAtBottom = isAtBottom();
    if (isCurrent) {
      renderMessages([msg], false);
      queueReceipt([msg.id], 'delivered');
      if (wasAtBottom) {
        markSeenForVisible();
      } else if (msg.sender_id !== state.me.id && msg.conversation_id) {
        incrementUnread(msg.conversation_id);
      }
    } else if (msg.sender_id !== state.me.id) {
      queueReceipt([msg.id], 'delivered');
      if (msg.conversation_id) {
        incrementUnread(msg.conversation_id);
      }
    }
    scheduleConversationsRefresh();
  }

  function handleReceiptEvent(receipt) {
    if (!receipt || !receipt.id) return;
    state.realtime.lastReceiptId = Math.max(state.realtime.lastReceiptId, receipt.id);
    if (!receipt.message_id || !receipt.status) return;
    updateMessageReceiptUI(receipt.message_id, { status: receipt.status });
  }

  function stopRealtime() {
    if (state.realtime.es) {
      state.realtime.es.close();
      state.realtime.es = null;
    }
    if (state.realtime.pollTimer) {
      clearTimeout(state.realtime.pollTimer);
      state.realtime.pollTimer = null;
    }
    state.realtime.connected = false;
    state.realtime.mode = null;
    updateNetworkStatus();
  }

  function startSSE() {
    if (!window.EventSource) return false;
    if (state.realtime.es) return true;
    if (state.realtime.pollTimer) {
      clearTimeout(state.realtime.pollTimer);
      state.realtime.pollTimer = null;
    }
    const params = new URLSearchParams({
      last_message_id: state.realtime.lastMessageId,
      last_receipt_id: state.realtime.lastReceiptId
    });
    const es = new EventSource(makeUrl(API.stream + '?' + params.toString()));
    state.realtime.es = es;
    state.realtime.mode = 'sse';
    state.realtime.connected = false;
    updateNetworkStatus();

    es.addEventListener('open', () => {
      state.realtime.connected = true;
      state.realtime.backoffMs = 1000;
      updateNetworkStatus();
    });
    es.addEventListener('message', (event) => {
      try {
        const msg = JSON.parse(event.data || '{}');
        handleIncomingMessage(msg);
      } catch (err) {
        // ignore
      }
    });
    es.addEventListener('receipt', (event) => {
      try {
        const receipt = JSON.parse(event.data || '{}');
        handleReceiptEvent(receipt);
      } catch (err) {
        // ignore
      }
    });
    es.addEventListener('error', () => {
      if (state.realtime.es) {
        state.realtime.es.close();
        state.realtime.es = null;
      }
      state.realtime.connected = false;
      updateNetworkStatus();
      startPolling(true);
    });
    return true;
  }

  async function pollLoop() {
    state.realtime.pollTimer = null;
    if (!state.token) return;
    const params = new URLSearchParams({
      last_message_id: state.realtime.lastMessageId,
      last_receipt_id: state.realtime.lastReceiptId,
      timeout: 0,
      wait: 0
    });
    let hadError = false;
    try {
      const etag = `m:${state.realtime.lastMessageId}-r:${state.realtime.lastReceiptId}`;
      const res = await apiFetch(API.poll + '?' + params.toString(), { headers: { 'If-None-Match': etag } });
      state.realtime.connected = true;
      if (res.status !== 204 && res.data && res.data.ok) {
        const payload = res.data.data || {};
        const messages = payload.messages || [];
        const receipts = payload.receipts || [];
        messages.forEach(handleIncomingMessage);
        receipts.forEach(handleReceiptEvent);
        if (payload.last_message_id) {
          state.realtime.lastMessageId = Math.max(state.realtime.lastMessageId, payload.last_message_id);
        }
        if (payload.last_receipt_id) {
          state.realtime.lastReceiptId = Math.max(state.realtime.lastReceiptId, payload.last_receipt_id);
        }
        state.realtime.backoffMs = realtimeConfig.errorBaseMs;
      }
      updateNetworkStatus();
    } catch (err) {
      hadError = true;
      state.realtime.connected = false;
      updateNetworkStatus();
    }

    let delay;
    if (hadError) {
      const jitter = Math.floor(Math.random() * 500);
      delay = Math.min(realtimeConfig.errorMaxMs, state.realtime.backoffMs + jitter);
      state.realtime.backoffMs = Math.min(realtimeConfig.errorMaxMs, Math.round(state.realtime.backoffMs * 1.5));
    } else {
      const base = document.hidden ? realtimeConfig.hiddenDelayMs : realtimeConfig.minDelayMs;
      const jitter = Math.floor(Math.random() * realtimeConfig.jitterMs);
      delay = Math.min(realtimeConfig.maxDelayMs, base + jitter);
      state.realtime.backoffMs = realtimeConfig.errorBaseMs;
    }
    state.realtime.pollTimer = setTimeout(pollLoop, delay);
  }

  function startPolling(force = false) {
    if (state.realtime.mode === 'poll' && !force) return;
    if (state.realtime.es) {
      state.realtime.es.close();
      state.realtime.es = null;
    }
    if (state.realtime.pollTimer) {
      clearTimeout(state.realtime.pollTimer);
    }
    state.realtime.mode = 'poll';
    state.realtime.connected = false;
    state.realtime.backoffMs = realtimeConfig.errorBaseMs;
    updateNetworkStatus();
    pollLoop();
  }

  function startRealtime() {
    if (!state.token) return;
    if (realtimeConfig.mode === 'poll') {
      startPolling(true);
      return;
    }
    if (!realtimeConfig.sseEnabled) {
      startPolling(true);
      return;
    }
    if (realtimeConfig.mode === 'sse') {
      const okForced = startSSE();
      if (!okForced) {
        startPolling(true);
      }
      return;
    }
    if (document.hidden) {
      startPolling(true);
      return;
    }
    const ok = startSSE();
    if (!ok) {
      startPolling(true);
    }
  }

  document.addEventListener('visibilitychange', () => {
    if (!state.token) return;
    if (realtimeConfig.mode === 'poll') {
      startPolling(true);
      return;
    }
    if (document.hidden) {
      startPolling(true);
    } else {
      startRealtime();
    }
  });

  window.addEventListener('online', updateNetworkStatus);
  window.addEventListener('offline', updateNetworkStatus);

  async function handleLogin(payload, endpoint) {
    authError.textContent = '';
    try {
      const res = await apiFetch(endpoint, { method: 'POST', body: payload });
      if (!res.data.ok) {
        authError.textContent = res.data.error || 'خطا در ورود/ثبت‌نام';
        return;
      }
      state.token = res.data.data.token;
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
    }, API.login);
  });

  registerForm.addEventListener('submit', (e) => {
    e.preventDefault();
    const formData = new FormData(registerForm);
    handleLogin({
      full_name: formData.get('full_name'),
      username: formData.get('username'),
      email: formData.get('email'),
      password: formData.get('password')
    }, API.register);
  });

  groupForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    createGroupFromForm();
  });

  groupSettingsBtn?.addEventListener('click', async () => {
    if (!isGroupChat()) return;
    if (!state.currentGroup) {
      await loadGroupInfo(state.currentConversation.id);
    }
    populateGroupSettings();
    openModal(groupSettingsModal);
  });

  groupSettingsSave?.addEventListener('click', async () => {
    if (!state.currentGroup?.is_owner || !state.currentConversation) return;
    const payload = {
      allow_member_invites: groupAllowInvites.checked,
      allow_photos: groupAllowPhotos.checked,
      allow_videos: groupAllowVideos.checked,
      allow_voice: groupAllowVoice.checked,
      allow_files: groupAllowFiles.checked
    };
    const res = await apiFetch(API.group(state.currentConversation.id), { method: 'PATCH', body: payload });
    if (res.data.ok) {
      Object.assign(state.currentGroup.group, payload);
      applyComposerPermissions();
      await loadConversations();
      populateGroupSettings();
    }
  });

  groupInviteCopy?.addEventListener('click', async () => {
    const text = groupInviteLink.value;
    if (!text) return;
    try {
      await navigator.clipboard.writeText(text);
    } catch (err) {
      groupInviteLink.select();
      document.execCommand('copy');
    }
  });

  groupInviteSubmit?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (!state.currentConversation || !isGroupChat()) return;
    if (!state.currentGroup?.can_invite) return;
    const username = groupInviteUsername.value.trim().replace(/^@/, '');
    groupInviteError.textContent = '';
    if (!username) {
      groupInviteError.textContent = 'نام کاربری را وارد کنید.';
      return;
    }
    const res = await apiFetch(API.groupInvite(state.currentConversation.id), { method: 'POST', body: { username } });
    if (!res.data.ok) {
      groupInviteError.textContent = res.data.error || 'خطا در دعوت عضو';
      return;
    }
    groupInviteUsername.value = '';
    await loadGroupInfo(state.currentConversation.id);
    populateGroupSettings();
    await loadConversations();
  });

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function modalFocusableElements(modal) {
    if (!modal) return [];
    const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
    return Array.from(modal.querySelectorAll(selector)).filter((el) => {
      return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    });
  }

  function focusModal(modal) {
    const focusables = modalFocusableElements(modal);
    const target = focusables[0] || modal;
    if (!target) return;
    requestAnimationFrame(() => {
      if (typeof target.focus === 'function') {
        target.focus();
      }
    });
  }

  function openModal(modal) {
    if (!modal) return;
    if (state.ui.activeModal && state.ui.activeModal !== modal) {
      closeModal(state.ui.activeModal, { restoreFocus: false });
    }
    if (!modal.hasAttribute('tabindex')) {
      modal.setAttribute('tabindex', '-1');
    }
    if (!modal.getAttribute('role')) {
      modal.setAttribute('role', 'dialog');
    }
    modal.setAttribute('aria-modal', 'true');
    const title = modal.querySelector('.modal-title');
    if (title && !modal.getAttribute('aria-labelledby')) {
      if (!title.id) {
        title.id = modal.id ? `${modal.id}-title` : `modal-title-${Date.now()}`;
      }
      modal.setAttribute('aria-labelledby', title.id);
    }
    state.ui.modalReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    state.ui.activeModal = modal;
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    focusModal(modal);
  }

  function closeModal(modal, options = {}) {
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
    if (state.ui.activeModal === modal) {
      const returnFocus = state.ui.modalReturnFocus;
      state.ui.activeModal = null;
      state.ui.modalReturnFocus = null;
      document.body.classList.remove('modal-open');
      if (options.restoreFocus !== false && returnFocus && typeof returnFocus.focus === 'function') {
        requestAnimationFrame(() => {
          returnFocus.focus();
        });
      }
    }
  }

  function trapModalFocus(event) {
    if (event.key !== 'Tab') return;
    const modal = state.ui.activeModal;
    if (!modal || modal.classList.contains('hidden')) return;
    const focusables = modalFocusableElements(modal);
    if (!focusables.length) {
      event.preventDefault();
      modal.focus();
      return;
    }
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey) {
      if (document.activeElement === first || !modal.contains(document.activeElement)) {
        event.preventDefault();
        last.focus();
      }
      return;
    }
    if (document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function syncUserSettingsUI() {
    if (lastSeenPrivacySelect) {
      const privacy = state.me?.last_seen_privacy === 'everyone' ? 'everyone' : 'nobody';
      lastSeenPrivacySelect.value = privacy;
    }
  }

  async function refreshMe() {
    const res = await apiFetch(API.me);
    if (res.data.ok) {
      state.me = res.data.data.user;
      state.profilePhotos = res.data.data.photos || [];
      syncUserSettingsUI();
      syncSidebarProfile();
      populateProfilePanel();
    }
  }

  function populateProfileForm() {
    if (!state.me) return;
    if (profileNameInput) profileNameInput.value = state.me.full_name || '';
    if (profileUsernameInput) profileUsernameInput.value = state.me.username || '';
    if (profileBioInput) profileBioInput.value = state.me.bio || '';
    if (profileEmailInput) profileEmailInput.value = state.me.email || '';
    if (profilePhoneInput) profilePhoneInput.value = state.me.phone || '';
    if (profileAvatar) {
      if (state.me.active_photo_id) {
        profileAvatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + state.me.active_photo_id + '&thumb=1')})`;
        profileAvatar.textContent = '';
      } else {
        profileAvatar.style.backgroundImage = '';
        profileAvatar.textContent = (state.me.full_name || '👤').slice(0, 1);
      }
    }
    if (profilePhotoRemove) {
      profilePhotoRemove.disabled = !state.me.active_photo_id;
      profilePhotoRemove.classList.toggle('disabled', !state.me.active_photo_id);
    }
    if (profileError) profileError.textContent = '';
  }

  function cropImageToSquare(file) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      const url = URL.createObjectURL(file);
      img.onload = () => {
        const size = Math.min(img.width, img.height);
        const sx = (img.width - size) / 2;
        const sy = (img.height - size) / 2;
        const canvas = document.createElement('canvas');
        const target = 512;
        canvas.width = target;
        canvas.height = target;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(img, sx, sy, size, size, 0, 0, target, target);
        canvas.toBlob((blob) => {
          URL.revokeObjectURL(url);
          if (blob) resolve(blob);
          else reject(new Error('خطا در برش تصویر'));
        }, 'image/jpeg', 0.9);
      };
      img.onerror = () => {
        URL.revokeObjectURL(url);
        reject(new Error('خطا در بارگذاری تصویر'));
      };
      img.src = url;
    });
  }

  async function handleProfilePhoto(file) {
    if (!file) return;
    try {
      const blob = await cropImageToSquare(file);
      const form = new FormData();
      form.append('photo', blob, file.name || 'profile.jpg');
      const res = await apiFetch(API.profilePhoto, { method: 'POST', body: form });
      if (!res.data.ok) {
        throw new Error(res.data.error || 'آپلود ناموفق بود');
      }
      await refreshMe();
      populateProfileForm();
      scheduleConversationsRefresh();
    } catch (err) {
      if (profileError) {
        profileError.textContent = err.message || 'خطا در آپلود عکس';
      } else {
        notify(err.message || 'خطا در آپلود عکس');
      }
    }
  }

  async function saveProfile() {
    if (!state.me) return;
    const payload = {
      full_name: profileNameInput?.value.trim() || '',
      username: profileUsernameInput?.value.trim().toLowerCase() || '',
      bio: profileBioInput?.value.trim() || '',
      email: profileEmailInput?.value.trim().toLowerCase() || '',
      phone: profilePhoneInput?.value.trim() || ''
    };
    if (profileError) profileError.textContent = '';
    try {
      const res = await apiFetch(API.me, { method: 'POST', body: payload });
      if (!res.data.ok) {
        throw new Error(res.data.error || 'خطا در ذخیره پروفایل');
      }
      await refreshMe();
      populateProfileForm();
      scheduleConversationsRefresh();
    } catch (err) {
      if (profileError) {
        profileError.textContent = err.message || 'خطا در ذخیره پروفایل';
      } else {
        notify(err.message || 'خطا در ذخیره پروفایل');
      }
    }
  }

  async function updateLastSeenPrivacy(value) {
    if (!state.me || !lastSeenPrivacySelect) return;
    const previous = lastSeenPrivacySelect.value;
    lastSeenPrivacySelect.disabled = true;
    try {
      const res = await apiFetch(API.meSettings, { method: 'PATCH', body: { last_seen_privacy: value } });
      if (!res.data.ok) {
        throw new Error(res.data.message || res.data.error || 'خطا در ذخیره تنظیمات');
      }
      state.me.last_seen_privacy = res.data.data.last_seen_privacy;
      lastSeenPrivacySelect.value = state.me.last_seen_privacy || 'everyone';
      await loadConversations();
      updateHeaderStatus();
      updateInfoPanel();
    } catch (err) {
      lastSeenPrivacySelect.value = previous;
      notify(err.message || 'خطا در ذخیره تنظیمات');
    } finally {
      lastSeenPrivacySelect.disabled = false;
    }
  }

  async function loadGroupInfo(groupId) {
    const res = await apiFetch(API.group(groupId));
    if (res.data.ok) {
      state.currentGroup = res.data.data;
      applyComposerPermissions();
      updateHeaderStatus();
      updateInfoPanel();
    }
  }

  function populateGroupSettings() {
    if (!state.currentGroup) return;
    const group = state.currentGroup.group;
    const isOwner = state.currentGroup.is_owner;
    const canInvite = state.currentGroup.can_invite;

    groupInfoHandle.textContent = group.public_handle ? '@' + group.public_handle : 'خصوصی';
    if (group.privacy_type === 'private' && state.currentGroup.invite_token && canInvite) {
      groupInviteRow.classList.remove('hidden');
      groupInviteLink.value = appUrl() + '/#invite=' + encodeURIComponent(state.currentGroup.invite_token);
    } else {
      groupInviteRow.classList.add('hidden');
      groupInviteLink.value = '';
    }

    groupAllowInvites.checked = !!group.allow_member_invites;
    groupAllowPhotos.checked = !!group.allow_photos;
    groupAllowVideos.checked = !!group.allow_videos;
    groupAllowVoice.checked = !!group.allow_voice;
    groupAllowFiles.checked = !!group.allow_files;

    groupAllowInvites.disabled = !isOwner;
    groupAllowPhotos.disabled = !isOwner;
    groupAllowVideos.disabled = !isOwner;
    groupAllowVoice.disabled = !isOwner;
    groupAllowFiles.disabled = !isOwner;
    groupSettingsSave.classList.toggle('hidden', !isOwner);

    groupInviteUsername.disabled = !canInvite;
    toggleButton(groupInviteSubmit, canInvite);
    groupInviteError.textContent = '';
    if (!canInvite) {
      groupInviteUsername.value = '';
    }

    groupMembersList.innerHTML = '';
    (state.currentGroup.members || []).forEach(member => {
      const item = document.createElement('div');
      item.className = 'member-item';
      const meta = document.createElement('div');
      meta.className = 'member-meta';
      const avatar = document.createElement('div');
      avatar.className = 'member-avatar';
      if (member.photo_id) {
        avatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + member.photo_id + '&thumb=1')})`;
      } else {
        avatar.textContent = '👤';
      }
      const text = document.createElement('div');
      text.textContent = `${member.full_name} (@${member.username})`;
      meta.appendChild(avatar);
      meta.appendChild(text);

      const actions = document.createElement('div');
      if (isOwner && member.role !== 'owner') {
        const removeBtn = document.createElement('button');
        removeBtn.className = 'action-btn';
        removeBtn.textContent = 'حذف';
        removeBtn.addEventListener('click', async () => {
          await removeGroupMember(member.id);
        });
        actions.appendChild(removeBtn);
      }

      item.appendChild(meta);
      item.appendChild(actions);
      groupMembersList.appendChild(item);
    });
  }

  async function removeGroupMember(memberId) {
    if (!state.currentConversation || !isGroupChat()) return;
    const res = await apiFetch(API.groupMembers(state.currentConversation.id, memberId), { method: 'DELETE' });
    if (res.data.ok) {
      await loadGroupInfo(state.currentConversation.id);
      populateGroupSettings();
      await loadConversations();
    }
  }

  function parseDateTime(dateStr) {
    const raw = typeof dateStr === 'string' ? dateStr.trim() : '';
    const normalized = raw ? raw.replace(' ', 'T') : '';
    const parsed = normalized ? new Date(normalized) : new Date();
    if (Number.isNaN(parsed.getTime())) {
      return new Date();
    }
    return parsed;
  }

  function formatTime(dateStr) {
    const date = parseDateTime(dateStr);
    return new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit' }).format(date);
  }

  function formatDateTime(dateStr) {
    const date = parseDateTime(dateStr);
    return new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  }

  function toIsoDateTime(dateStr) {
    return parseDateTime(dateStr).toISOString();
  }

  function messageReceiptStatusLabel(status) {
    if (status === 'sending') return 'در حال ارسال';
    if (status === 'failed') return 'ناموفق';
    if (status === 'seen') return 'خوانده‌شده';
    if (status === 'delivered') return 'تحویل‌شده';
    return 'ارسال‌شده';
  }

  function messageContentSummary(msg) {
    if (!msg) return 'پیام';
    const text = String(msg.body || '').replace(/\s+/g, ' ').trim();
    if (text) {
      return truncate(text, 120);
    }
    const attachments = Array.isArray(msg.attachments) ? msg.attachments : [];
    if (attachments.length) {
      return attachmentsLabel(attachments).replace(/[📷🎬🎤📎]/g, '').trim() || 'پیوست';
    }
    if (msg.type && msg.type !== 'text') {
      return mediaLabel(msg.type, msg.media?.original_name) || 'پیوست';
    }
    return 'پیام';
  }

  function buildMessageAriaLabelBase(msg) {
    if (!msg) return '';
    const sender = msg.sender_id === state.me?.id ? 'شما' : (msg.sender_name || 'کاربر');
    const summary = messageContentSummary(msg);
    const sentAt = formatDateTime(msg.created_at);
    return `${sender}، ${summary}، ${sentAt}`;
  }

  function buildMessageAriaLabel(msg, statusOverride = null) {
    let label = buildMessageAriaLabelBase(msg);
    if (msg.sender_id === state.me?.id) {
      const status = statusOverride || msg.receipt?.status || 'sent';
      label += `، وضعیت: ${messageReceiptStatusLabel(status)}`;
    }
    return label;
  }

  function isEmojiOnly(text) {
    if (!text) return false;
    const stripped = text.replace(/\s+/g, '');
    if (!stripped) return false;
    if (stripped.length > 12) return false;
    return /^[\\p{Extended_Pictographic}\\uFE0F\\u200D]+$/u.test(stripped);
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
      case 'media':
        return 'پیوست';
      default:
        return '';
    }
  }

  function attachmentsLabel(attachments) {
    if (!attachments || !attachments.length) return '';
    const count = attachments.length;
    const types = new Set(attachments.map(att => att.type));
    if (types.size === 1) {
      const type = attachments[0].type;
      if (type === 'photo') return `📷 ${count} عکس`;
      if (type === 'video') return `🎬 ${count} ویدیو`;
      if (type === 'voice') return `🎤 ${count} پیام صوتی`;
      if (type === 'file') return `📎 ${count} فایل`;
    }
    return `📎 ${count} پیوست`;
  }

  function avatarInitial(name) {
    if (!name) return '👤';
    const trimmed = String(name).trim();
    return trimmed ? trimmed.charAt(0) : '👤';
  }

  function setAvatar(el, photoUrl, fallbackText) {
    if (!el) return;
    if (photoUrl) {
      el.style.backgroundImage = `url(${photoUrl})`;
      el.textContent = '';
    } else {
      el.style.backgroundImage = '';
      el.textContent = fallbackText || '';
    }
  }

  function setChatStatus(text) {
    if (!chatUserStatus) return;
    chatUserStatus.textContent = text || '';
  }

  function updateHeaderStatus() {
    if (!state.currentConversation) {
      setChatStatus('');
      return;
    }
    if (state.currentConversation.chat_type === 'group') {
      const count = state.currentGroup?.members?.length || 0;
      setChatStatus(count ? `${count} عضو` : 'گروه');
      return;
    }
    const statusText = state.currentConversation.status_text || 'last seen recently';
    setChatStatus(statusText);
  }

  function setInfoPanelVisible(visible) {
    if (!infoPanel) return;
    infoPanel.classList.toggle('hidden', !visible);
    document.body.classList.toggle('show-info', visible);
    if (visible) {
      closeProfilePanel();
    }
  }

  function updateInfoPanel() {
    if (!infoPanel) return;
    if (!state.currentConversation) {
      if (!infoPanel.classList.contains('hidden')) {
        setInfoPanelVisible(false);
      }
      return;
    }

    const conv = state.currentConversation;
    const isGroup = conv.chat_type === 'group';
    const title = isGroup ? (conv.title || 'گروه') : (conv.other_name || conv.other_username || 'گفتگو');
    const subtitle = isGroup
      ? (conv.public_handle ? '@' + conv.public_handle : 'گروه خصوصی')
      : (conv.other_username ? '@' + conv.other_username : '');

    if (infoTitle) infoTitle.textContent = title;
    if (infoSubtitle) infoSubtitle.textContent = subtitle;

    if (isGroup) {
      setAvatar(infoAvatar, '', '👥');
    } else if (conv.other_photo) {
      setAvatar(infoAvatar, makeUrl('/photo.php?id=' + conv.other_photo + '&thumb=1'), avatarInitial(title));
    } else {
      setAvatar(infoAvatar, '', avatarInitial(title));
    }

    if (infoStatus) {
      if (isGroup) {
        infoStatus.textContent = chatUserStatus?.textContent || '';
      } else {
        infoStatus.textContent = state.currentConversation.status_text || 'last seen recently';
      }
    }

    if (infoDescription) {
      const desc = isGroup ? (state.currentGroup?.group?.description || '') : '';
      infoDescription.textContent = desc || '—';
    }

    if (infoMembers) {
      if (isGroup) {
        const count = state.currentGroup?.members?.length || 0;
        infoMembers.textContent = count ? `${count} عضو` : '—';
      } else {
        infoMembers.textContent = 'گفتگوی خصوصی';
      }
    }
  }

  function sameSender(a, b) {
    if (!a || !b) return false;
    return a.dataset.senderId && a.dataset.senderId === b.dataset.senderId;
  }

  function updateGroupingAround(messageEl) {
    const nodes = [messageEl?.previousElementSibling, messageEl, messageEl?.nextElementSibling];
    nodes.forEach(node => {
      if (!node || !node.classList.contains('message')) return;
      const prev = node.previousElementSibling;
      const next = node.nextElementSibling;
      node.classList.toggle('grouped-top', !!prev && prev.classList.contains('message') && sameSender(prev, node));
      node.classList.toggle('grouped-bottom', !!next && next.classList.contains('message') && sameSender(node, next));
    });
  }

  function setCurrentChatHeader(conversation) {
    if (!conversation) {
      chatUserName.textContent = 'گفتگو';
      chatUserUsername.textContent = '';
      setAvatar(chatUserAvatar, '', '');
      chatUserHeader?.setAttribute('aria-disabled', 'true');
      groupSettingsBtn.classList.add('hidden');
      updateHeaderStatus();
      updateInfoPanel();
      return;
    }
    if (conversation.chat_type === 'group') {
      chatUserName.textContent = conversation.title || 'گروه';
      if (conversation.public_handle) {
        chatUserUsername.textContent = '@' + conversation.public_handle;
      } else {
        chatUserUsername.textContent = 'گروه خصوصی';
      }
      setAvatar(chatUserAvatar, '', '👥');
      chatUserHeader?.setAttribute('aria-disabled', 'false');
      groupSettingsBtn.classList.remove('hidden');
      updateHeaderStatus();
      updateInfoPanel();
      return;
    }
    const displayName = conversation.other_name || conversation.other_username || '';
    chatUserName.textContent = displayName;
    chatUserUsername.textContent = conversation.other_username ? '@' + conversation.other_username : '';
    if (conversation.other_photo) {
      setAvatar(chatUserAvatar, makeUrl('/photo.php?id=' + conversation.other_photo + '&thumb=1'), avatarInitial(displayName));
    } else {
      setAvatar(chatUserAvatar, '', avatarInitial(displayName));
    }
    chatUserHeader?.setAttribute('aria-disabled', 'false');
    groupSettingsBtn.classList.add('hidden');
    updateHeaderStatus();
    updateInfoPanel();
  }

  function conversationKey(conversation) {
    if (!conversation) return '';
    return `${conversation.chat_type}:${conversation.id}`;
  }

  function setActiveChatItem(conversation) {
    if (!conversation) return;
    const key = conversationKey(conversation);
    let activeDescendant = '';
    document.querySelectorAll('.chat-item').forEach(item => {
      const active = item.dataset.key === key;
      item.classList.toggle('active', active);
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      if (active) {
        activeDescendant = item.id || '';
      }
    });
    if (activeDescendant) {
      chatList?.setAttribute('aria-activedescendant', activeDescendant);
    } else {
      chatList?.removeAttribute('aria-activedescendant');
    }
  }

  function renderConversations() {
    chatList.innerHTML = '';
    state.conversations.forEach(conv => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'chat-item';
      item.id = `chat-item-${conv.chat_type}-${conv.id}`;
      item.setAttribute('role', 'option');
      item.dataset.key = conversationKey(conv);
      const active = !!state.currentConversation
        && state.currentConversation.id === conv.id
        && state.currentConversation.chat_type === conv.chat_type;
      item.setAttribute('aria-selected', active ? 'true' : 'false');
      if (active) {
        item.classList.add('active');
      }
      const avatar = document.createElement('div');
      avatar.className = 'avatar';
      if (conv.chat_type === 'group') {
        avatar.textContent = '👥';
      } else if (conv.other_photo) {
        avatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + conv.other_photo + '&thumb=1')})`;
      } else {
        avatar.textContent = avatarInitial(conv.other_name || conv.other_username || '');
      }
      const meta = document.createElement('div');
      meta.className = 'chat-meta';

      const topRow = document.createElement('div');
      topRow.className = 'chat-row';
      const name = document.createElement('div');
      name.className = 'name';
      name.textContent = conv.chat_type === 'group' ? (conv.title || 'گروه') : (conv.other_name || conv.other_username);
      const time = document.createElement('div');
      time.className = 'chat-time';
      time.textContent = conv.last_message_at ? formatTime(conv.last_message_at) : '';
      topRow.appendChild(name);
      topRow.appendChild(time);

      const bottomRow = document.createElement('div');
      bottomRow.className = 'chat-row';
      const preview = document.createElement('div');
      preview.className = 'preview';
      preview.textContent = conv.last_preview ? truncate(conv.last_preview, 40) : 'بدون پیام';
      const badges = document.createElement('div');
      badges.className = 'chat-badges';
      const unread = Number(conv.unread_count || conv.unread || 0);
      const conversationName = conv.chat_type === 'group'
        ? (conv.title || 'گروه')
        : (conv.other_name || conv.other_username || 'گفتگو');
      const ariaLabel = unread > 0
        ? `${conversationName}، ${unread} پیام خوانده‌نشده`
        : conversationName;
      item.setAttribute('aria-label', ariaLabel);
      if (unread > 0) {
        const badge = document.createElement('span');
        badge.className = 'unread-badge';
        badge.textContent = unread > 99 ? '99+' : String(unread);
        badges.appendChild(badge);
      }
      bottomRow.appendChild(preview);
      bottomRow.appendChild(badges);

      meta.appendChild(topRow);
      meta.appendChild(bottomRow);

      item.appendChild(avatar);
      item.appendChild(meta);
      item.addEventListener('click', () => selectConversation(conv));
      chatList.appendChild(item);
    });
    setActiveChatItem(state.currentConversation);
  }

  async function loadConversations() {
    const res = await apiFetch(API.conversations);
    if (res.data.ok) {
      state.conversations = res.data.data;
      if (state.currentConversation) {
        const current = state.conversations.find(c => c.id === state.currentConversation.id && c.chat_type === state.currentConversation.chat_type);
        if (current) {
          state.currentConversation = current;
        }
      }
      renderConversations();
      syncUnreadFromConversations();
      if (state.currentConversation) {
        setCurrentChatHeader(state.currentConversation);
      }
    }
  }

  function parseUsernames(value) {
    return value
      .split(',')
      .map(part => part.trim())
      .filter(Boolean)
      .map(name => name.replace(/^@/, ''))
      .filter(Boolean);
  }

  async function createGroupFromForm() {
    const title = groupTitleInput.value.trim();
    const privacy = groupPrivacySelect.value;
    const handle = groupHandleInput.value.trim().toLowerCase();
    const description = groupDescriptionInput.value.trim();
    const membersRaw = groupMembersInput.value.trim();

    groupError.textContent = '';
    if (!title) {
      groupError.textContent = 'عنوان گروه را وارد کنید.';
      return;
    }
    if (privacy === 'public') {
      if (!validGroupHandle(handle)) {
        groupError.textContent = 'شناسه عمومی باید فقط حروف کوچک/عدد/زیرخط داشته باشد و با group تمام شود.';
        return;
      }
    }

    const payload = {
      title,
      privacy_type: privacy,
      description
    };
    if (privacy === 'public') {
      payload.public_handle = handle;
    }

    const res = await apiFetch(API.groups, { method: 'POST', body: payload });
    if (!res.data.ok) {
      groupError.textContent = res.data.error || 'خطا در ساخت گروه';
      return;
    }

    const groupId = res.data.data.group_id;
    const members = parseUsernames(membersRaw);
    for (const username of members) {
      await apiFetch(API.groupInvite(groupId), { method: 'POST', body: { username } });
    }

    closeModal(groupModal);
    groupForm.reset();
    groupHandleRow.classList.add('hidden');
    await loadConversations();
    const conv = state.conversations.find(c => c.chat_type === 'group' && c.id === groupId);
    if (conv) {
      await selectConversation(conv);
    }
  }

  async function handleInviteLink() {
    const hash = (window.location.hash || '').replace(/^#/, '');
    const hashParams = new URLSearchParams(hash);
    const token = hashParams.get('invite');
    if (!token || !state.token) return;
    const res = await apiFetch(API.groupJoinByLink, { method: 'POST', body: { token } });
    if (res.data.ok) {
      await loadConversations();
      const conv = state.conversations.find(c => c.chat_type === 'group' && c.id === res.data.data.group_id);
      if (conv) {
        await selectConversation(conv);
      }
    }
    hashParams.delete('invite');
    const hashText = hashParams.toString();
    const newUrl = hashText ? `${window.location.pathname}#${hashText}` : window.location.pathname;
    window.history.replaceState({}, '', newUrl);
  }

  async function selectConversation(conv) {
    state.currentConversation = conv;
    state.currentGroup = null;
    state.oldestMessageId = null;
    messagesEl.innerHTML = '';
    clearReply();
    clearAttachments();
    setCurrentChatHeader(conv);
    setActiveChatItem(conv);
    document.body.classList.remove('show-chats');
    if (isGroupChat()) {
      await loadGroupInfo(conv.id);
    }
    applyComposerPermissions();
    await loadMessages();
    renderPendingMessagesForCurrentConversation();
  }

  async function loadMessages(beforeId = null) {
    if (!state.currentConversation || state.loadingMessages) return;
    const initialLoad = beforeId === null;
    if (initialLoad && !messagesEl.querySelector('.message')) {
      showMessagesLoadingState();
    }
    state.loadingMessages = true;
    try {
      let res = null;
      if (isGroupChat()) {
        const params = new URLSearchParams({ limit: 30 });
        if (beforeId) {
          params.set('cursor', beforeId);
        }
        res = await apiFetch(API.groupMessages(state.currentConversation.id) + '?' + params.toString());
      } else {
        const params = new URLSearchParams({
          conversation_id: state.currentConversation.id,
          limit: 30
        });
        if (beforeId) {
          params.set('before_id', beforeId);
        }
        res = await apiFetch(API.messages + '?' + params.toString());
      }

      if (!res.data.ok) {
        if (initialLoad && !messagesEl.querySelector('.message')) {
          showMessagesState('error', 'دریافت پیام‌ها ممکن نشد.');
        }
        return;
      }

      const list = res.data.data;
      if (list.length > 0) {
        state.oldestMessageId = list[0].id;
        const maxId = list[list.length - 1].id;
        state.realtime.lastMessageId = Math.max(state.realtime.lastMessageId, maxId);
      }
      renderMessages(list, beforeId !== null);
      if (initialLoad) {
        if (!list.length) {
          renderPendingMessagesForCurrentConversation();
          if (!messagesEl.querySelector('.message')) {
            showMessagesState('empty', 'هنوز پیامی در این گفتگو ثبت نشده است.');
          }
        }
        queueReceipt(list.filter(msg => msg.sender_id !== state.me.id).map(msg => msg.id), 'delivered');
        markSeenForVisible();
      }
    } catch (err) {
      if (initialLoad && !messagesEl.querySelector('.message')) {
        showMessagesState('error', 'خطا در دریافت پیام‌ها. دوباره تلاش کنید.');
      } else {
        notify(err.message || 'خطا در دریافت پیام‌ها');
      }
    } finally {
      state.loadingMessages = false;
    }
  }

  function createVoicePlayer(att) {
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
    duration.textContent = formatDuration(att.duration || 0);

    const audio = new Audio(makeUrl(API.media(att.id)));
    audio.preload = 'metadata';
    audio.addEventListener('loadedmetadata', () => {
      duration.textContent = formatDuration(att.duration || Math.floor(audio.duration || 0));
    });
    audio.addEventListener('timeupdate', () => {
      const percent = audio.duration ? (audio.currentTime / audio.duration) * 100 : 0;
      bar.style.width = percent + '%';
      duration.textContent = formatDuration(Math.floor(audio.duration - audio.currentTime));
    });
    audio.addEventListener('ended', () => {
      playBtn.textContent = '▶️';
      bar.style.width = '0%';
      duration.textContent = formatDuration(att.duration || 0);
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
    const dl = createDownloadButton(att, 'ذخیره پیام صوتی');
    dl.classList.add('media-download-inline');
    player.appendChild(dl);
    return player;
  }

  function createFileCard(att) {
    const card = document.createElement('div');
    card.className = 'file-card';
    const icon = document.createElement('div');
    icon.className = 'file-icon';
    icon.textContent = '📎';
    const meta = document.createElement('div');
    meta.className = 'file-meta';
    const link = document.createElement('a');
    link.href = makeUrl(API.mediaDownload(att.id));
    link.textContent = att.original_name || 'دانلود فایل';
    link.target = '_blank';
    link.rel = 'noopener';
    const size = document.createElement('div');
    size.textContent = formatBytes(att.size_bytes);
    meta.appendChild(link);
    meta.appendChild(size);
    card.appendChild(icon);
    card.appendChild(meta);
    return card;
  }

  function openMediaDownload(att) {
    if (!att || !att.id) return;
    window.open(makeUrl(API.mediaDownload(att.id)), '_blank');
  }

  function createDownloadButton(att, title = 'دانلود') {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'media-download icon-btn';
    btn.title = title;
    btn.setAttribute('aria-label', title);
    btn.innerHTML = '<span class="material-symbols-rounded">download</span>';
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      openMediaDownload(att);
    });
    return btn;
  }

  function isLightboxOpen() {
    return !!lightbox && !lightbox.classList.contains('hidden');
  }

  function openLightboxForMedia(mediaId) {
    if (!lightbox || !lightboxImg || !mediaId) return;
    state.ui.lightboxReturnFocus = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    lightboxImg.src = makeUrl(API.media(mediaId));
    lightboxImg.dataset.mediaId = String(mediaId);
    lightbox.classList.remove('hidden');
    lightbox.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(() => {
      lightboxClose?.focus();
    });
  }

  function closeLightbox() {
    if (!lightbox || !lightboxImg) return;
    lightbox.classList.add('hidden');
    lightbox.setAttribute('aria-hidden', 'true');
    lightboxImg.src = '';
    lightboxImg.dataset.mediaId = '';
    const returnFocus = state.ui.lightboxReturnFocus;
    state.ui.lightboxReturnFocus = null;
    if (returnFocus && typeof returnFocus.focus === 'function') {
      requestAnimationFrame(() => {
        returnFocus.focus();
      });
    }
  }

  function appendMessageContent(message, msg) {
    const attachments = Array.isArray(msg.attachments) ? msg.attachments : [];
    const fallback = msg.media ? [msg.media] : [];
    const mediaItems = attachments.length ? attachments : fallback;
    const hasMedia = mediaItems.length > 0;

    if (!hasMedia && (!msg.type || msg.type === 'text')) {
      const text = document.createElement('div');
      text.className = 'text';
      text.innerHTML = escapeHtml(msg.body || '');
      message.appendChild(text);
      return;
    }

    if (hasMedia) {
      const mediaWrap = document.createElement('div');
      mediaWrap.className = 'media';

      const photos = mediaItems.filter(att => att.type === 'photo');
      const videos = mediaItems.filter(att => att.type === 'video');
      const voices = mediaItems.filter(att => att.type === 'voice');
      const files = mediaItems.filter(att => !['photo', 'video', 'voice'].includes(att.type));

      if (photos.length) {
        const grid = document.createElement('div');
        grid.className = `photo-grid count-${Math.min(photos.length, 6)}`;
        photos.forEach(att => {
          const item = document.createElement('div');
          item.className = 'photo-item';
          const img = document.createElement('img');
          const src = att.thumbnail_name ? makeUrl(API.mediaThumb(att.id)) : makeUrl(API.media(att.id));
          img.src = src;
          img.alt = att.original_name || 'photo';
          img.tabIndex = 0;
          img.setAttribute('role', 'button');
          img.setAttribute('aria-label', `نمایش بزرگ عکس${att.original_name ? ` ${att.original_name}` : ''}`);
          img.addEventListener('click', () => openLightboxForMedia(att.id));
          img.addEventListener('keydown', (event) => {
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            openLightboxForMedia(att.id);
          });
          const dl = createDownloadButton(att, 'ذخیره عکس');
          dl.classList.add('media-download-overlay');
          item.appendChild(img);
          item.appendChild(dl);
          grid.appendChild(item);
        });
        mediaWrap.appendChild(grid);
      }

      videos.forEach(att => {
        const wrap = document.createElement('div');
        wrap.className = 'video-wrap';
        const video = document.createElement('video');
        video.src = makeUrl(API.media(att.id));
        video.controls = true;
        video.playsInline = true;
        const dl = createDownloadButton(att, 'ذخیره ویدیو');
        dl.classList.add('media-download-inline');
        wrap.appendChild(video);
        wrap.appendChild(dl);
        mediaWrap.appendChild(wrap);
      });

      voices.forEach(att => {
        mediaWrap.appendChild(createVoicePlayer(att));
      });

      files.forEach(att => {
        mediaWrap.appendChild(createFileCard(att));
      });

      message.appendChild(mediaWrap);
    }

    if (msg.body) {
      const caption = document.createElement('div');
      caption.className = 'text';
      caption.innerHTML = escapeHtml(msg.body);
      message.appendChild(caption);
    }
  }

  function applyReceiptToTicks(element, receipt) {
    const status = receipt?.status || 'sent';
    element.classList.remove('seen', 'failed', 'sending');
    element.setAttribute('role', 'img');
    const statusLabel = messageReceiptStatusLabel(status);
    element.setAttribute('aria-label', `وضعیت پیام: ${statusLabel}`);
    element.title = statusLabel;
    if (status === 'sending') {
      element.textContent = '…';
      element.classList.add('sending');
      return;
    }
    if (status === 'failed') {
      element.textContent = '!';
      element.classList.add('failed');
      return;
    }
    if (status === 'seen') {
      element.textContent = '✓✓';
      element.classList.add('seen');
    } else {
      element.textContent = '✓';
    }
  }

  function updateMessageReceiptUI(messageId, receipt) {
    const message = document.getElementById(`msg-${messageId}`);
    if (!message) return;
    const ticks = message.querySelector('.meta-ticks');
    if (ticks) {
      applyReceiptToTicks(ticks, receipt);
    }
    const baseLabel = message.dataset.ariaLabelBase || '';
    if (baseLabel) {
      const status = receipt?.status || 'sent';
      message.setAttribute('aria-label', `${baseLabel}، وضعیت: ${messageReceiptStatusLabel(status)}`);
    }
  }

  function clearMessagesState() {
    messagesEl.querySelectorAll('.messages-state').forEach((node) => node.remove());
  }

  function showMessagesState(type, text) {
    if (!messagesEl) return;
    clearMessagesState();
    const stateBox = document.createElement('div');
    stateBox.className = `messages-state ${type}`;
    stateBox.textContent = text;
    messagesEl.appendChild(stateBox);
  }

  function showMessagesLoadingState() {
    showMessagesState('loading', 'در حال بارگذاری پیام‌ها...');
  }

  function nowSqlDateTime() {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hour = String(now.getHours()).padStart(2, '0');
    const minute = String(now.getMinutes()).padStart(2, '0');
    const second = String(now.getSeconds()).padStart(2, '0');
    return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
  }

  function getMessageElementByClientId(clientId) {
    if (!clientId) return null;
    const all = Array.from(messagesEl.querySelectorAll('.message'));
    return all.find((element) => element.dataset.clientId === clientId) || null;
  }

  function reconcilePendingSend(clientId) {
    if (!clientId) return;
    const pendingEl = getMessageElementByClientId(clientId);
    if (pendingEl && pendingEl.dataset.pending === '1') {
      pendingEl.remove();
    }
    delete state.pendingSends[clientId];
  }

  function setPendingMessageStatus(clientId, status) {
    const pending = state.pendingSends[clientId];
    if (pending?.optimisticMessage) {
      pending.optimisticMessage.local_status = status;
    }
    const messageEl = getMessageElementByClientId(clientId);
    if (!messageEl) return;
    messageEl.dataset.localStatus = status;
    messageEl.dataset.pending = '1';
    const baseLabel = messageEl.dataset.ariaLabelBase || '';
    if (baseLabel) {
      messageEl.setAttribute('aria-label', `${baseLabel}، وضعیت: ${messageReceiptStatusLabel(status)}`);
    }
    const ticks = messageEl.querySelector('.meta-ticks');
    if (ticks) {
      applyReceiptToTicks(ticks, { status });
    }
    const meta = messageEl.querySelector('.meta');
    if (!meta) return;
    const existingRetry = meta.querySelector('.message-retry-btn');
    if (status === 'failed') {
      if (!existingRetry) {
        const retryBtn = document.createElement('button');
        retryBtn.type = 'button';
        retryBtn.className = 'message-retry-btn';
        retryBtn.textContent = 'تلاش مجدد';
        retryBtn.addEventListener('click', () => {
          retryPendingMessage(clientId);
        });
        meta.appendChild(retryBtn);
      }
    } else if (existingRetry) {
      existingRetry.remove();
    }
  }

  function buildOptimisticMessage(payload, options = {}) {
    const { attachments = [], replyMessage = null } = options;
    const clientId = payload.client_id;
    const id = `temp-${clientId}`;
    return {
      id,
      client_id: clientId,
      sender_id: state.me?.id,
      sender_name: state.me?.full_name || 'شما',
      sender_photo_id: state.me?.active_photo_id || null,
      type: payload.type || 'text',
      body: payload.body || '',
      media: attachments.length ? attachments[0] : null,
      attachments,
      created_at: nowSqlDateTime(),
      receipt: { status: 'sending' },
      local_status: 'sending',
      current_user_reaction: '',
      reactions: [],
      reply_id: replyMessage?.id || null,
      reply_type: replyMessage?.type || null,
      reply_media_name: replyMessage?.media?.original_name || null,
      reply_body: replyMessage?.body || '',
      reply_sender_name: replyMessage?.sender_name || ''
    };
  }

  async function retryPendingMessage(clientId) {
    const pending = state.pendingSends[clientId];
    if (!pending || pending.busy) return;
    pending.busy = true;
    setPendingMessageStatus(clientId, 'sending');
    try {
      const res = await apiFetch(pending.endpoint, { method: 'POST', body: pending.payload });
      if (!res.data.ok) {
        throw new Error(res.data.error || 'خطا در ارسال پیام');
      }
      setPendingMessageStatus(clientId, 'sent');
      scheduleConversationsRefresh();
      const sameConversation = state.currentConversation && conversationKey(state.currentConversation) === pending.conversationKey;
      if (!state.realtime.connected || state.realtime.mode === 'poll') {
        if (sameConversation) {
          await loadMessages();
        }
        await loadConversations();
      }
    } catch (err) {
      setPendingMessageStatus(clientId, 'failed');
      notify(err.message || 'خطا در ارسال پیام');
    } finally {
      pending.busy = false;
    }
  }

  function enqueuePendingMessage(endpoint, payload, options = {}) {
    const clientId = payload.client_id;
    if (!clientId) return;
    const optimisticMessage = buildOptimisticMessage(payload, options);
    state.pendingSends[clientId] = {
      endpoint,
      payload: { ...payload },
      conversationKey: state.currentConversation ? conversationKey(state.currentConversation) : '',
      busy: false,
      optimisticMessage: { ...optimisticMessage }
    };
    renderMessages([optimisticMessage]);
  }

  function renderPendingMessagesForCurrentConversation() {
    if (!state.currentConversation) return;
    const currentKey = conversationKey(state.currentConversation);
    const pendingMessages = Object.values(state.pendingSends)
      .filter((pending) => pending.conversationKey === currentKey && pending.optimisticMessage)
      .map((pending) => ({ ...pending.optimisticMessage }))
      .sort((a, b) => parseDateTime(a.created_at).getTime() - parseDateTime(b.created_at).getTime());
    if (!pendingMessages.length) return;
    renderMessages(pendingMessages);
  }

  function renderMessages(messages, prepend = false) {
    if (Array.isArray(messages) && messages.length) {
      clearMessagesState();
    }
    const fragment = document.createDocumentFragment();
    const inserted = [];
    messages.forEach(msg => {
      const localStatus = String(msg.local_status || msg.pending_status || '').trim();
      const isPendingMessage = msg.is_pending === true || localStatus !== '' || String(msg.id).startsWith('temp-');
      if (msg.client_id && !isPendingMessage && msg.sender_id === state.me.id) {
        reconcilePendingSend(String(msg.client_id));
      }
      if (document.getElementById(`msg-${msg.id}`)) {
        return;
      }
      const message = document.createElement('article');
      message.className = 'message ' + (msg.sender_id === state.me.id ? 'outgoing' : 'incoming');
      if (isPendingMessage) {
        message.classList.add('pending');
      }
      message.id = `msg-${msg.id}`;
      message.dataset.currentReaction = msg.current_user_reaction || '';
      message.dataset.senderId = String(msg.sender_id || '');
      if (msg.client_id) {
        message.dataset.clientId = String(msg.client_id);
      }
      if (localStatus) {
        message.dataset.localStatus = localStatus;
        message.dataset.pending = '1';
      }
      message.setAttribute('role', 'article');
      message.setAttribute('aria-roledescription', 'پیام');
      message.dataset.ariaLabelBase = buildMessageAriaLabelBase(msg);
      message.setAttribute('aria-label', buildMessageAriaLabel(msg, localStatus || null));

      const attachments = Array.isArray(msg.attachments) ? msg.attachments : [];
      const hasMedia = attachments.length > 0 || !!msg.media;
      if (!hasMedia && isEmojiOnly(msg.body || '')) {
        message.classList.add('emoji-only');
      }

      if (!isPendingMessage) {
        const reactionBar = buildReactionBar(msg.id, msg.current_user_reaction);
        message.appendChild(reactionBar);
      }

      if (!isPendingMessage) {
        const moreBtn = document.createElement('button');
        moreBtn.className = 'message-more';
        moreBtn.type = 'button';
        moreBtn.innerHTML = '<span class="material-symbols-rounded">more_vert</span>';
        moreBtn.setAttribute('aria-label', 'گزینه‌های پیام');
        moreBtn.title = 'گزینه‌های پیام';
        moreBtn.addEventListener('click', (e) => {
          e.stopPropagation();
          const rect = moreBtn.getBoundingClientRect();
          if (isMobileLayout()) {
            openMessageActionSheet(msg.id, message, msg);
          } else {
            openMessageContextMenu(msg.id, message, msg, rect.left, rect.bottom + 6);
          }
        });
        message.appendChild(moreBtn);
      }

      if (isGroupChat()) {
        const senderWrap = document.createElement('div');
        senderWrap.className = 'message-sender';
        senderWrap.setAttribute('aria-hidden', 'true');
        const senderAvatar = document.createElement('div');
        senderAvatar.className = 'sender-avatar';
        if (msg.sender_photo_id) {
          senderAvatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + msg.sender_photo_id + '&thumb=1')})`;
        } else {
          senderAvatar.textContent = '👤';
        }
        const senderName = document.createElement('span');
        senderName.textContent = msg.sender_id === state.me.id ? 'شما' : (msg.sender_name || '');
        senderWrap.appendChild(senderAvatar);
        senderWrap.appendChild(senderName);
        message.appendChild(senderWrap);
      }

      if (msg.reply_id) {
        const reply = document.createElement('button');
        reply.type = 'button';
        reply.className = 'reply-preview';
        const replyText = msg.reply_type && msg.reply_type !== 'text'
          ? mediaLabel(msg.reply_type, msg.reply_media_name)
          : truncate(msg.reply_body || '', 60);
        reply.textContent = (msg.reply_sender_name || '') + ': ' + replyText;
        reply.setAttribute('aria-label', `رفتن به پیام پاسخ داده شده: ${truncate(replyText, 80)}`);
        reply.addEventListener('click', () => {
          const target = document.getElementById(`msg-${msg.reply_id}`);
          if (target) {
            target.scrollIntoView({ behavior: motionBehavior(), block: 'center' });
          }
        });
        message.appendChild(reply);
      }

      appendMessageContent(message, msg);

      if (!isPendingMessage) {
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
        if (state.me && Number(msg.sender_id) === Number(state.me.id)) {
          actions.appendChild(delAllBtn);
        }
        message.appendChild(actions);

        const chips = buildReactionChips(msg.id, msg.reactions || [], msg.current_user_reaction);
        if (chips) {
          message.appendChild(chips);
        }
      }

      const meta = document.createElement('div');
      meta.className = 'meta';
      const time = document.createElement('time');
      time.className = 'meta-time';
      time.dateTime = toIsoDateTime(msg.created_at);
      time.setAttribute('aria-label', formatDateTime(msg.created_at));
      time.textContent = formatTime(msg.created_at);
      meta.appendChild(time);
      if (msg.sender_id === state.me.id) {
        const ticks = document.createElement('span');
        ticks.className = 'meta-ticks';
        const receipt = localStatus
          ? { status: localStatus }
          : (msg.receipt || null);
        applyReceiptToTicks(ticks, receipt);
        meta.appendChild(ticks);
        if (localStatus === 'failed' && msg.client_id) {
          const retryBtn = document.createElement('button');
          retryBtn.type = 'button';
          retryBtn.className = 'message-retry-btn';
          retryBtn.textContent = 'تلاش مجدد';
          retryBtn.addEventListener('click', () => {
            retryPendingMessage(String(msg.client_id));
          });
          meta.appendChild(retryBtn);
        }
      }
      message.appendChild(meta);

      if (!isPendingMessage) {
        message.addEventListener('contextmenu', (e) => {
          e.preventDefault();
          if (isMobileLayout()) {
            openMessageActionSheet(msg.id, message, msg);
            return;
          }
          openMessageContextMenu(msg.id, message, msg, e.clientX, e.clientY);
        });

        attachMessageLongPress(message, msg);
      }
      fragment.appendChild(message);
      inserted.push(message);
    });

    const shouldScroll = !prepend && (isAtBottom() || messages.some(m => m.sender_id === state.me.id));
    if (prepend) {
      messagesEl.prepend(fragment);
    } else {
      messagesEl.appendChild(fragment);
      if (shouldScroll) {
        messagesEl.scrollTop = messagesEl.scrollHeight;
      }
    }
    inserted.forEach(updateGroupingAround);
    if (jumpToBottom) {
      jumpToBottom.classList.toggle('hidden', isAtBottom());
    }
  }

  function isAtBottom() {
    return (messagesEl.scrollHeight - messagesEl.scrollTop - messagesEl.clientHeight) < 80;
  }

  function queueReceipt(messageIds, status) {
    if (!Array.isArray(messageIds) || messageIds.length === 0) return;
    const target = status === 'seen' ? state.receiptQueue.seen : state.receiptQueue.delivered;
    messageIds.forEach((id) => target.add(id));
    if (state.receiptQueue.timer) return;
    state.receiptQueue.timer = setTimeout(flushReceipts, 400);
  }

  async function flushReceipts() {
    const deliveredIds = Array.from(state.receiptQueue.delivered);
    const seenIds = Array.from(state.receiptQueue.seen);
    state.receiptQueue.delivered.clear();
    state.receiptQueue.seen.clear();
    state.receiptQueue.timer = null;

    const filteredDelivered = deliveredIds.filter(id => !seenIds.includes(id));
    try {
      if (filteredDelivered.length) {
        await apiFetch(API.messageAck, { method: 'POST', body: { message_ids: filteredDelivered, status: 'delivered' } });
      }
      if (seenIds.length) {
        await apiFetch(API.messageAck, { method: 'POST', body: { message_ids: seenIds, status: 'seen' } });
      }
    } catch (err) {
      // Best-effort ack; ignore failures.
    }
  }

  let markReadTimer = null;
  function getLastMessageId() {
    const messages = Array.from(messagesEl.querySelectorAll('.message')).reverse();
    for (const messageEl of messages) {
      const id = Number(String(messageEl.id || '').replace('msg-', ''));
      if (Number.isFinite(id) && id > 0) {
        return id;
      }
    }
    return 0;
  }

  async function markReadForCurrentConversation() {
    if (!state.currentConversation || !state.me || isGroupChat()) return;
    if (!isAtBottom()) return;
    const lastId = getLastMessageId();
    if (!lastId) return;
    const convId = state.currentConversation.id;
    const prev = state.readMarkers[convId] || 0;
    if (lastId <= prev) return;
    state.readMarkers[convId] = lastId;
    try {
      const res = await apiFetch(API.messageMarkRead, {
        method: 'POST',
        body: { conversation_id: convId, up_to_message_id: lastId }
      });
      if (res.data && res.data.ok) {
        const payload = res.data.data || {};
        if (typeof payload.total_unread !== 'undefined') {
          applyUnreadCounts(payload.total_unread || 0, payload.by_conversation || {});
        }
      }
    } catch (err) {
      // ignore
    }
  }

  function scheduleMarkRead() {
    if (markReadTimer) return;
    markReadTimer = setTimeout(() => {
      markReadTimer = null;
      markReadForCurrentConversation();
    }, 300);
  }

  function markSeenForVisible() {
    if (!state.currentConversation || !state.me) return;
    if (!isAtBottom()) return;
    if (isGroupChat()) {
      const incoming = Array.from(messagesEl.querySelectorAll('.message.incoming'));
      const ids = incoming.map(node => Number(node.id.replace('msg-', ''))).filter(Boolean);
      if (ids.length) {
        queueReceipt(ids, 'seen');
      }
      return;
    }
    scheduleMarkRead();
  }

  async function sendMessage() {
    if (!state.currentConversation) return;
    const endpoint = isGroupChat()
      ? API.groupMessages(state.currentConversation.id)
      : API.messages;
    const replyMessage = state.replyTo ? { ...state.replyTo } : null;

    if (state.pendingAttachments.length) {
      if (state.uploading) return;
      if (state.pendingAttachments.some(att => !groupAllows(att.type))) {
        notify('ارسال این نوع پیام در گروه مجاز نیست.');
        clearAttachments();
        return;
      }
      state.uploading = true;
      try {
        await Promise.all(state.pendingAttachments.map(async (att) => {
          if (att.type === 'voice' && att.duration) {
            return;
          }
          if (att.type === 'video') {
            const info = await getVideoMeta(att);
            att.duration = info.duration || att.duration;
            att.width = info.width || att.width;
            att.height = info.height || att.height;
          }
        }));

        const uploadItems = await apiUploadAttachments(state.pendingAttachments);
        const mediaIds = uploadItems.map(item => item.media_id).filter(Boolean);
        if (!mediaIds.length) {
          throw new Error('آپلود ناموفق بود.');
        }

        const body = messageInput.value.trim();
        const payload = {
          client_id: generateClientId(),
          media_ids: mediaIds,
          type: mediaIds.length > 1 ? 'media' : (uploadItems[0]?.type || 'file')
        };
        if (body) {
          payload.body = body;
        }
        if (!isGroupChat()) {
          payload.conversation_id = state.currentConversation.id;
        }
        if (state.replyTo) {
          payload.reply_to_message_id = state.replyTo.id;
        }
        const optimisticAttachments = uploadItems.map((item) => ({
          id: item.media_id,
          type: item.type || 'file',
          original_name: item.original_name || '',
          size_bytes: item.size_bytes || 0,
          duration: item.duration || null,
          width: item.width || null,
          height: item.height || null,
          thumbnail_name: item.thumbnail_name || null
        }));
        messageInput.value = '';
        clearAttachments();
        clearReply();
        enqueuePendingMessage(endpoint, payload, {
          attachments: optimisticAttachments,
          replyMessage
        });
        await retryPendingMessage(payload.client_id);
      } catch (err) {
        notify(err.message || 'خطا در ارسال فایل');
      } finally {
        state.uploading = false;
      }
      return;
    }

    const body = messageInput.value.trim();
    if (!body) return;
    const payload = {
      type: 'text',
      body: body,
      client_id: generateClientId()
    };
    if (!isGroupChat()) {
      payload.conversation_id = state.currentConversation.id;
    }
    if (state.replyTo) {
      payload.reply_to_message_id = state.replyTo.id;
    }
    messageInput.value = '';
    clearReply();
    enqueuePendingMessage(endpoint, payload, { replyMessage });
    await retryPendingMessage(payload.client_id);
  }

  function setReply(msg) {
    state.replyTo = msg;
    const previewText = msg.attachments && msg.attachments.length
      ? attachmentsLabel(msg.attachments)
      : (msg.type && msg.type !== 'text'
        ? mediaLabel(msg.type, msg.media?.original_name)
        : truncate(msg.body || '', 80));
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
    const res = await apiFetch(API.deleteForMe, { method: 'POST', body: { message_id: messageId } });
    if (res.data.ok) {
      const el = document.getElementById(`msg-${messageId}`);
      if (el) el.remove();
      await loadConversations();
    }
  }

  async function deleteForEveryone(messageId, element) {
    const res = await apiFetch(API.deleteForEveryone, { method: 'POST', body: { message_id: messageId } });
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
        const chips = element.querySelector('.reaction-chips');
        if (chips) chips.remove();
        const bar = element.querySelector('.reaction-bar');
        if (bar) bar.remove();
        element.dataset.currentReaction = '';
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
    if (!button || button.disabled) return;
    const type = button.dataset.type;
    attachMenu.classList.add('hidden');
    if (type === 'photo') {
      photoInput.click();
    } else if (type === 'video') {
      videoInput.click();
    } else if (type === 'file') {
      fileInput.click();
    }
  });

  document.addEventListener('click', (e) => {
    if (!attachMenu.contains(e.target) && e.target !== attachBtn) {
      attachMenu.classList.add('hidden');
    }
  });

  document.addEventListener('click', (e) => {
    if (!e.target.closest('.reaction-bar')) {
      document.querySelectorAll('.message.show-reactions').forEach((el) => {
        el.classList.remove('show-reactions');
      });
    }
  });

  photoInput.addEventListener('change', () => {
    const files = Array.from(photoInput.files || []);
    photoInput.value = '';
    if (!files.length) return;
    attachMenu.classList.add('hidden');
    if (!groupAllows('photo')) {
      notify('ارسال عکس در این گروه مجاز نیست.');
      return;
    }
    const attachments = files.map(file => buildAttachment(file, 'photo'));
    const allowed = filterAllowedAttachments(attachments).slice(0, 10);
    setAttachments(allowed);
  });

  videoInput.addEventListener('change', () => {
    const files = Array.from(videoInput.files || []);
    videoInput.value = '';
    if (!files.length) return;
    attachMenu.classList.add('hidden');
    if (!groupAllows('video')) {
      notify('ارسال ویدیو در این گروه مجاز نیست.');
      return;
    }
    const attachments = files.map(file => buildAttachment(file, 'video'));
    const allowed = filterAllowedAttachments(attachments).slice(0, 10);
    setAttachments(allowed);
  });

  fileInput.addEventListener('change', () => {
    const files = Array.from(fileInput.files || []);
    fileInput.value = '';
    if (!files.length) return;
    attachMenu.classList.add('hidden');
    const attachments = files.map(file => buildAttachment(file, 'auto'));
    const allowed = filterAllowedAttachments(attachments).slice(0, 10);
    setAttachments(allowed);
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
      notify('مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.');
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const options = {};
      if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/webm')) {
        options.mimeType = 'audio/webm';
      } else if (window.MediaRecorder && MediaRecorder.isTypeSupported('audio/ogg')) {
        options.mimeType = 'audio/ogg';
      }
      const recorder = new MediaRecorder(stream, options);
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
      notify('دسترسی به میکروفون ممکن نیست.');
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
    if (!groupAllows('voice')) {
      notify('ارسال پیام صوتی در این گروه مجاز نیست.');
      return;
    }
    if (state.recording.mediaRecorder) {
      return;
    }
    clearAttachments();
    startRecording();
  });

  voiceStop.addEventListener('click', stopRecording);
  voiceCancel.addEventListener('click', cancelRecording);
  voiceSend.addEventListener('click', () => {
    if (!state.recording.blob) return;
    clearAttachments();
    const attachment = {
      id: generateClientId(),
      type: 'voice',
      file: state.recording.blob,
      name: 'voice-message.webm',
      size: state.recording.blob.size,
      previewUrl: null,
      duration: state.recording.duration,
      width: null,
      height: null,
      progress: 0,
      forceType: 'voice'
    };
    setAttachments([attachment]);
    resetVoiceState();
  });

  if (lightbox && lightboxClose) {
    lightboxClose.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
      if (e.target === lightbox) {
        closeLightbox();
      }
    });
  }

  chatList?.addEventListener('keydown', (event) => {
    const currentItem = event.target instanceof HTMLElement ? event.target.closest('.chat-item') : null;
    if (!currentItem) return;
    const items = Array.from(chatList.querySelectorAll('.chat-item'));
    if (!items.length) return;
    const currentIndex = items.indexOf(currentItem);
    if (currentIndex < 0) return;
    let nextIndex = currentIndex;
    if (event.key === 'ArrowDown') {
      nextIndex = Math.min(items.length - 1, currentIndex + 1);
    } else if (event.key === 'ArrowUp') {
      nextIndex = Math.max(0, currentIndex - 1);
    } else if (event.key === 'Home') {
      nextIndex = 0;
    } else if (event.key === 'End') {
      nextIndex = items.length - 1;
    } else {
      return;
    }
    event.preventDefault();
    items[nextIndex].focus();
  });

  function isSearchResultsOpen() {
    return !!searchResults && searchResults.style.display === 'block';
  }

  function closeSearchResults(options = {}) {
    const { clearInput = false, invalidateRequest = true } = options;
    if (invalidateRequest) {
      state.search.requestSeq += 1;
    }
    state.search.results = [];
    state.search.activeIndex = -1;
    state.search.query = '';
    if (searchResults) {
      searchResults.innerHTML = '';
      searchResults.style.display = 'none';
    }
    if (userSearch) {
      userSearch.setAttribute('aria-expanded', 'false');
      userSearch.removeAttribute('aria-activedescendant');
      if (clearInput) {
        userSearch.value = '';
      }
    }
  }

  function setSearchActiveIndex(index, options = {}) {
    const { focusOption = false } = options;
    if (!searchResults) return;
    const optionElements = Array.from(searchResults.querySelectorAll('.search-item'));
    if (!optionElements.length) {
      state.search.activeIndex = -1;
      userSearch?.removeAttribute('aria-activedescendant');
      return;
    }
    const total = optionElements.length;
    const normalizedIndex = ((index % total) + total) % total;
    state.search.activeIndex = normalizedIndex;
    optionElements.forEach((optionElement, optionIndex) => {
      const active = optionIndex === normalizedIndex;
      optionElement.classList.toggle('is-active', active);
      optionElement.setAttribute('aria-selected', active ? 'true' : 'false');
      if (active) {
        userSearch?.setAttribute('aria-activedescendant', optionElement.id);
        optionElement.scrollIntoView({ block: 'nearest' });
        if (focusOption) {
          optionElement.focus();
        }
      }
    });
  }

  async function selectSearchResult(index) {
    const user = state.search.results[index];
    if (!user) return;
    try {
      const convRes = await apiFetch(API.conversations, { method: 'POST', body: { user_id: user.id } });
      if (!convRes.data.ok) {
        throw new Error(convRes.data.error || 'خطا در ایجاد گفتگو');
      }
      await loadConversations();
      const conv = state.conversations.find(c => c.id === convRes.data.data.conversation_id && c.chat_type === 'direct');
      if (conv) {
        await selectConversation(conv);
      }
      closeSearchResults({ clearInput: true });
    } catch (err) {
      notify(err.message || 'خطا در شروع گفتگو');
    }
  }

  function appendHighlightedText(container, text, query) {
    if (!container) return;
    const source = String(text || '');
    const needle = String(query || '').trim();
    if (!needle) {
      container.textContent = source;
      return;
    }
    const lowerSource = source.toLocaleLowerCase('fa-IR');
    const lowerNeedle = needle.toLocaleLowerCase('fa-IR');
    const index = lowerSource.indexOf(lowerNeedle);
    if (index < 0) {
      container.textContent = source;
      return;
    }
    container.textContent = '';
    container.appendChild(document.createTextNode(source.slice(0, index)));
    const mark = document.createElement('mark');
    mark.textContent = source.slice(index, index + needle.length);
    container.appendChild(mark);
    container.appendChild(document.createTextNode(source.slice(index + needle.length)));
  }

  function renderSearchResults() {
    if (!searchResults || !userSearch) return;
    searchResults.innerHTML = '';
    if (!state.search.results.length) {
      searchResults.style.display = 'none';
      userSearch.setAttribute('aria-expanded', 'false');
      userSearch.removeAttribute('aria-activedescendant');
      return;
    }
    const query = state.search.query;
    const groupLabel = document.createElement('div');
    groupLabel.className = 'search-group-label';
    groupLabel.textContent = 'کاربران';
    groupLabel.setAttribute('aria-hidden', 'true');
    searchResults.appendChild(groupLabel);
    state.search.results.forEach((user, index) => {
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'search-item';
      item.id = `search-result-${user.id}-${index}`;
      item.dataset.index = String(index);
      item.setAttribute('role', 'option');
      item.setAttribute('aria-selected', 'false');
      const fullName = user.full_name || user.username || 'کاربر';
      const username = user.username || '';
      const namePart = document.createElement('span');
      appendHighlightedText(namePart, fullName, query);
      const userPart = document.createElement('span');
      appendHighlightedText(userPart, username, query);
      item.appendChild(namePart);
      item.appendChild(document.createTextNode(' (@'));
      item.appendChild(userPart);
      item.appendChild(document.createTextNode(')'));
      item.addEventListener('mousedown', (event) => {
        event.preventDefault();
      });
      item.addEventListener('mouseenter', () => {
        setSearchActiveIndex(index);
      });
      item.addEventListener('click', async () => {
        await selectSearchResult(index);
      });
      searchResults.appendChild(item);
    });
    searchResults.style.display = 'block';
    userSearch.setAttribute('aria-expanded', 'true');
    userSearch.removeAttribute('aria-activedescendant');
  }

  async function updateSearchResults(query) {
    const requestId = ++state.search.requestSeq;
    try {
      const res = await apiFetch(API.usersSearch + '?query=' + encodeURIComponent(query));
      if (requestId !== state.search.requestSeq) {
        return;
      }
      if (!res.data.ok) {
        closeSearchResults({ invalidateRequest: false });
        return;
      }
      state.search.query = query;
      state.search.results = Array.isArray(res.data.data) ? res.data.data : [];
      state.search.activeIndex = -1;
      renderSearchResults();
    } catch (err) {
      if (requestId !== state.search.requestSeq) {
        return;
      }
      closeSearchResults({ invalidateRequest: false });
      notify(err.message || 'خطا در جستجوی کاربر');
    }
  }

  let searchTimeout = null;
  userSearch?.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
      const query = userSearch.value.trim();
      if (query.length < 2) {
        closeSearchResults();
        return;
      }
      await updateSearchResults(query);
    }, 300);
  });

  userSearch?.addEventListener('keydown', (event) => {
    if (!state.search.results.length) {
      if (event.key === 'Escape' && isSearchResultsOpen()) {
        event.preventDefault();
        closeSearchResults();
      }
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const offset = event.key === 'ArrowDown' ? 1 : -1;
      const nextIndex = state.search.activeIndex < 0
        ? (offset > 0 ? 0 : state.search.results.length - 1)
        : state.search.activeIndex + offset;
      setSearchActiveIndex(nextIndex);
      return;
    }
    if (event.key === 'Enter' && state.search.activeIndex >= 0) {
      event.preventDefault();
      selectSearchResult(state.search.activeIndex);
      return;
    }
    if (event.key === 'Escape' && isSearchResultsOpen()) {
      event.preventDefault();
      closeSearchResults();
    }
  });

  userSearch?.addEventListener('blur', () => {
    window.setTimeout(() => {
      const activeEl = document.activeElement;
      if (activeEl === userSearch) return;
      if (searchResults && activeEl instanceof HTMLElement && searchResults.contains(activeEl)) return;
      closeSearchResults();
    }, 120);
  });

  searchResults?.addEventListener('keydown', (event) => {
    const optionElement = event.target instanceof HTMLElement ? event.target.closest('.search-item') : null;
    if (!optionElement) return;
    const currentIndex = Number(optionElement.dataset.index || -1);
    if (currentIndex < 0) return;
    if (event.key === 'ArrowDown') {
      event.preventDefault();
      setSearchActiveIndex(currentIndex + 1, { focusOption: true });
      return;
    }
    if (event.key === 'ArrowUp') {
      event.preventDefault();
      setSearchActiveIndex(currentIndex - 1, { focusOption: true });
      return;
    }
    if (event.key === 'Home') {
      event.preventDefault();
      setSearchActiveIndex(0, { focusOption: true });
      return;
    }
    if (event.key === 'End') {
      event.preventDefault();
      setSearchActiveIndex(state.search.results.length - 1, { focusOption: true });
      return;
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      closeSearchResults();
      userSearch?.focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (!isSearchResultsOpen()) return;
    if (!searchResults || !userSearch) return;
    const target = event.target;
    if (target === userSearch) return;
    if (target instanceof HTMLElement && searchResults.contains(target)) return;
    closeSearchResults();
  });

  backToChats.addEventListener('click', () => {
    document.body.classList.add('show-chats');
  });

  function syncMobileView() {
    const isMobile = window.innerWidth <= 900;
    if (!isMobile) return;
    if (!state.currentConversation) {
      document.body.classList.add('show-chats');
    }
  }

  window.addEventListener('resize', () => {
    syncMobileView();
  });

  messagesEl.addEventListener('scroll', () => {
    if (messagesEl.scrollTop === 0 && state.oldestMessageId) {
      loadMessages(state.oldestMessageId);
    }
    markSeenForVisible();
    if (jumpToBottom) {
      jumpToBottom.classList.toggle('hidden', isAtBottom());
    }
    closeMessageMenus();
  }, { passive: true });

  jumpToBottom?.addEventListener('click', () => {
    messagesEl.scrollTo({ top: messagesEl.scrollHeight, behavior: motionBehavior() });
    markSeenForVisible();
  });

  async function initialize() {
    if (!state.token) {
      try {
        const refreshRes = await apiFetch(API.tokenRefresh, { method: 'POST' });
        if (refreshRes.data && refreshRes.data.ok && refreshRes.data.data && refreshRes.data.data.token) {
          state.token = refreshRes.data.data.token;
        } else {
          showAuth();
          return;
        }
      } catch (err) {
        showAuth();
        return;
      }
    }
    try {
      const meRes = await apiFetch(API.me);
      if (!meRes.data.ok) {
        throw new Error('ورود نامعتبر است');
      }
      state.me = meRes.data.data.user;
      state.profilePhotos = meRes.data.data.photos || [];
      showMain();
      syncMobileView();
      syncUserSettingsUI();
      syncSidebarProfile();
      populateProfilePanel();
      initEmojiPicker();
      await loadConversations();
      await fetchUnreadCount();
      await handleInviteLink();
      if (!state.currentConversation && state.conversations.length > 0) {
        await selectConversation(state.conversations[0]);
      }
      startRealtime();
      updateNetworkStatus();
      if (!conversationsAutoRefreshTimer) {
        conversationsAutoRefreshTimer = setInterval(() => {
      if (state.token) {
        loadConversations();
      }
    }, 20000);
  }
    } catch (err) {
      state.token = null;
      stopRealtime();
      showAuth();
    }
  }

  registerServiceWorker();
  initialize();
})();
