(function () {
  const state = {
    token: localStorage.getItem('selo_token'),
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
    receiptQueue: {
      delivered: new Set(),
      seen: new Set(),
      timer: null
    },
    recording: {
      mediaRecorder: null,
      chunks: [],
      timerId: null,
      startTime: 0,
      blob: null,
      duration: 0
    },
    call: {
      ws: null,
      wsConnected: false,
      wsConnecting: false,
      reconnectTimer: null,
      session: null,
      pendingCandidates: [],
      pendingOffer: null,
      pendingAccept: false,
      pendingCancel: false,
      pc: null,
      localStream: null,
      remoteStream: null,
      callTimerId: null,
      callStartAt: 0,
      restarting: false,
      muted: false,
      sinkId: null,
      outputDevices: []
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
  const audioCallBtn = document.getElementById('audio-call-btn');
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
  const userSettingsModal = document.getElementById('user-settings-modal');
  const userSettingsClose = document.getElementById('user-settings-close');
  const allowVoiceCallsToggle = document.getElementById('allow-voice-calls-toggle');
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

  const callOverlay = document.getElementById('call-overlay');
  const callAvatar = document.getElementById('call-avatar');
  const callName = document.getElementById('call-name');
  const callStatus = document.getElementById('call-status');
  const callTimer = document.getElementById('call-timer');
  const callAcceptBtn = document.getElementById('call-accept-btn');
  const callDeclineBtn = document.getElementById('call-decline-btn');
  const callHangupBtn = document.getElementById('call-hangup-btn');
  const callMuteBtn = document.getElementById('call-mute-btn');
  const callSpeakerBtn = document.getElementById('call-speaker-btn');
  const remoteAudio = document.getElementById('remote-audio');

  const apiBase = window.SELO_CONFIG?.baseUrl || '';
  const baseUrl = apiBase.replace(/\/$/, '');
  const makeUrl = (path) => (baseUrl ? baseUrl + path : path);
  const appUrl = () => (baseUrl || window.location.origin || '');
  const API = {
    login: '/api/login',
    register: '/api/register',
    me: '/api/me',
    meSettings: '/api/me/settings',
    usersSearch: '/api/users/search',
    conversations: '/api/conversations',
    messages: '/api/messages',
    messageAck: '/api/messages/ack',
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
    profilePhotoDelete: (id) => `/api/profile/photo/${id}`,
    callsToken: '/api/calls/token',
    callsHistory: '/api/calls/history',
    callsValidate: '/api/calls/validate',
    callsEvent: '/api/calls/event'
  };
  const allowedReactions = ['😂', '😜', '👍', '😘', '😁', '🤣', '😍', '🥰', '🤩', '😐', '😑', '🙄', '😬', '🤮', '😎', '🥳', '👎', '🙏'];

  function deriveSignalingUrl() {
    const base = appUrl();
    if (!base) return '';
    return base.replace(/^http/i, 'ws').replace(/\/$/, '') + '/ws';
  }

  const callConfig = (() => {
    const cfg = window.SELO_CONFIG?.calls || {};
    const signalingUrl = cfg.signalingUrl || deriveSignalingUrl();
    const ringTimeoutSeconds = Number(cfg.ringTimeoutSeconds || 45);
    const iceServers = Array.isArray(cfg.iceServers) && cfg.iceServers.length
      ? cfg.iceServers
      : [{ urls: ['stun:stun.l.google.com:19302'] }];
    return { signalingUrl, ringTimeoutSeconds, iceServers };
  })();

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
      alert('برخی فایل‌ها به دلیل محدودیت‌های گروه ارسال نمی‌شوند.');
    }
    return allowed;
  }

  function isGroupChat() {
    return state.currentConversation?.chat_type === 'group';
  }

  function peerAllowsCalls(conversation) {
    if (!conversation || conversation.chat_type !== 'direct') return false;
    if (conversation.other_allow_voice_calls === false) return false;
    if (conversation.other_allow_voice_calls === 0) return false;
    return true;
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

  const callStatusLabels = {
    calling: 'در حال تماس...',
    ringing: 'در حال زنگ...',
    incoming: 'تماس ورودی',
    connecting: 'در حال اتصال...',
    connected: 'در تماس',
    reconnecting: 'در حال اتصال مجدد...',
    busy: 'مشغول',
    declined: 'رد شد',
    missed: 'بی‌پاسخ',
    canceled: 'لغو شد',
    failed: 'ناموفق',
    ended: 'پایان تماس'
  };

  function setCallPeer(peer) {
    if (!peer) return;
    if (window.CallUI && typeof window.CallUI.setPeer === 'function') {
      window.CallUI.setPeer(peer);
      return;
    }
    if (callName) {
      callName.textContent = peer.full_name || peer.username || 'تماس';
    }
    if (callAvatar) {
      if (peer.photo_id) {
        callAvatar.style.backgroundImage = `url(${makeUrl('/photo.php?id=' + peer.photo_id + '&thumb=1')})`;
        callAvatar.textContent = '';
      } else {
        callAvatar.style.backgroundImage = '';
        callAvatar.textContent = '';
      }
    }
  }

  function showCallOverlay() {
    if (window.CallUI && typeof window.CallUI.open === 'function') {
      window.CallUI.open();
      return;
    }
    callOverlay?.classList.remove('hidden');
    callOverlay?.classList.add('is-visible');
  }

  function hideCallOverlay() {
    if (window.CallUI && typeof window.CallUI.close === 'function') {
      window.CallUI.close();
      return;
    }
    callOverlay?.classList.add('hidden');
    callOverlay?.classList.remove('is-visible');
  }

  function setCallStatus(status) {
    if (state.call.session) {
      state.call.session.status = status;
    }
    if (window.CallUI && typeof window.CallUI.setState === 'function') {
      window.CallUI.setState(status);
      updateCallControls();
      return;
    }
    if (callStatus) {
      callStatus.textContent = callStatusLabels[status] || '';
    }
    updateCallControls();
  }

  function updateCallControls() {
    if (window.CallUI && typeof window.CallUI.setMuted === 'function') {
      window.CallUI.setMuted(state.call.muted);
      if (typeof window.CallUI.setSpeaker === 'function') {
        window.CallUI.setSpeaker(!!state.call.sinkId);
      }
      return;
    }
    if (!state.call.session) return;
    const status = state.call.session.status || '';
    const isIncoming = state.call.session.direction === 'incoming' && !state.call.session.accepted;
    callAcceptBtn?.classList.toggle('hidden', !isIncoming);
    callDeclineBtn?.classList.toggle('hidden', !isIncoming);

    const showHangup = ['calling', 'ringing', 'connecting', 'connected', 'reconnecting'].includes(status);
    callHangupBtn?.classList.toggle('hidden', !showHangup);
    if (callHangupBtn) {
      callHangupBtn.textContent = status === 'connected' ? 'قطع' : 'لغو';
    }

    const showAudioControls = ['connected', 'reconnecting'].includes(status);
    callMuteBtn?.classList.toggle('hidden', !showAudioControls);
    callSpeakerBtn?.classList.toggle('hidden', !showAudioControls);

    if (callMuteBtn) {
      callMuteBtn.textContent = state.call.muted ? 'وصل میکروفون' : 'بی‌صدا';
    }
  }

  function startCallTimer() {
    if (window.CallUI && typeof window.CallUI.startTimer === 'function') {
      state.call.callStartAt = Date.now();
      window.CallUI.startTimer(state.call.callStartAt);
      return;
    }
    if (state.call.callTimerId) return;
    state.call.callStartAt = Date.now();
    if (callTimer) {
      callTimer.textContent = '00:00';
    }
    state.call.callTimerId = setInterval(() => {
      const seconds = Math.floor((Date.now() - state.call.callStartAt) / 1000);
      if (callTimer) {
        callTimer.textContent = formatDuration(seconds);
      }
    }, 1000);
  }

  function stopCallTimer() {
    if (window.CallUI && typeof window.CallUI.stopTimer === 'function') {
      window.CallUI.stopTimer();
      return;
    }
    if (state.call.callTimerId) {
      clearInterval(state.call.callTimerId);
      state.call.callTimerId = null;
    }
    if (callTimer) {
      callTimer.textContent = '00:00';
    }
  }

  function resetCallState() {
    stopCallTimer();
    if (state.call.localStream) {
      state.call.localStream.getTracks().forEach(track => track.stop());
    }
    if (state.call.pc) {
      state.call.pc.onicecandidate = null;
      state.call.pc.ontrack = null;
      state.call.pc.onconnectionstatechange = null;
      state.call.pc.oniceconnectionstatechange = null;
      state.call.pc.close();
    }
    state.call.pc = null;
    if (remoteAudio) {
      remoteAudio.srcObject = null;
    }
    state.call.session = null;
    state.call.pendingCandidates = [];
    state.call.pendingOffer = null;
    state.call.pendingAccept = false;
    state.call.pendingCancel = false;
    state.call.localStream = null;
    state.call.remoteStream = null;
    state.call.restarting = false;
    state.call.muted = false;
    state.call.sinkId = null;
    state.call.outputDevices = [];
  }

  function finalizeCall(status) {
    if (!state.call.session) return;
    setCallStatus(status);
    stopCallTimer();
    setTimeout(() => {
      hideCallOverlay();
      resetCallState();
    }, 1200);
  }

  async function fetchCallToken(payload = null) {
    const res = await apiFetch(API.callsToken, { method: 'POST', body: payload || {} });
    if (!res.data.ok) {
      const message = res.data.message || res.data.error || 'خطا در دریافت توکن تماس';
      const err = new Error(message);
      err.code = res.data.error || '';
      throw err;
    }
    return res.data.data.token;
  }

  function waitForSignalingReady(timeoutMs = 6000) {
    return new Promise((resolve) => {
      if (state.call.wsConnected) return resolve(true);
      const started = Date.now();
      const timer = setInterval(() => {
        if (state.call.wsConnected) {
          clearInterval(timer);
          resolve(true);
          return;
        }
        if (Date.now() - started > timeoutMs) {
          clearInterval(timer);
          resolve(false);
        }
      }, 100);
    });
  }

  async function connectSignaling() {
    if (state.call.wsConnecting || state.call.wsConnected || !callConfig.signalingUrl || !state.token) return;
    state.call.wsConnecting = true;
    try {
      const token = await fetchCallToken();
      const ws = new WebSocket(callConfig.signalingUrl);
      state.call.ws = ws;
      ws.addEventListener('open', () => {
        ws.send(JSON.stringify({ type: 'join', token }));
      });
      ws.addEventListener('message', handleSignalingMessage);
      ws.addEventListener('close', handleSignalingClose);
      ws.addEventListener('error', () => {});
    } catch (err) {
      // Silent connect failure; UI will show when call is attempted
    } finally {
      state.call.wsConnecting = false;
    }
  }

  async function ensureSignalingConnected() {
    if (state.call.wsConnected && state.call.ws?.readyState === WebSocket.OPEN) return true;
    await connectSignaling();
    return await waitForSignalingReady();
  }

  function signalingSend(type, payload) {
    const ws = state.call.ws;
    if (!ws || ws.readyState !== WebSocket.OPEN) return false;
    ws.send(JSON.stringify({ type, ...payload }));
    return true;
  }

  function handleSignalingClose() {
    state.call.wsConnected = false;
    if (state.call.session) {
      finalizeCall('failed');
    }
    if (!state.call.reconnectTimer && state.token) {
      state.call.reconnectTimer = setTimeout(() => {
        state.call.reconnectTimer = null;
        connectSignaling();
      }, 2000);
    }
  }

  function handleSignalingMessage(event) {
    let msg = null;
    try {
      msg = JSON.parse(event.data);
    } catch (err) {
      return;
    }
    if (!msg || !msg.type) return;
    if (msg.type === 'join_ok') {
      state.call.wsConnected = true;
      return;
    }
    switch (msg.type) {
      case 'incoming_call':
        handleIncomingCall(msg);
        break;
      case 'call_ringing':
        handleCallRinging(msg);
        break;
      case 'call_offer':
        handleCallOffer(msg);
        break;
      case 'call_answer':
        handleCallAnswer(msg);
        break;
      case 'ice_candidate':
        handleIceCandidate(msg);
        break;
      case 'call_busy':
        handleRemoteEnd(msg, 'busy');
        break;
      case 'call_declined':
        handleRemoteEnd(msg, 'declined');
        break;
      case 'call_missed':
        handleRemoteEnd(msg, 'missed');
        break;
      case 'call_canceled':
        handleRemoteEnd(msg, 'canceled');
        break;
      case 'call_end':
        handleRemoteEnd(msg, msg.reason === 'completed' ? 'ended' : (msg.reason || 'ended'));
        break;
      case 'call_failed':
        handleCallFailed(msg);
        break;
      default:
        break;
    }
  }

  function handleCallFailed(msg) {
    if (msg?.code === 'CALLS_DISABLED' || msg?.message === 'calls_disabled') {
      alert('این کاربر تماس صوتی را غیرفعال کرده است.');
    } else if (msg?.message === 'rate_limited') {
      alert('تلاش‌های زیاد. لطفاً کمی بعد تلاش کنید.');
    } else if (msg?.message === 'not_allowed') {
      alert('دسترسی تماس مجاز نیست.');
    } else if (msg?.message === 'invalid_payload') {
      alert('درخواست تماس نامعتبر است.');
    }
    if (state.call.session) {
      finalizeCall('failed');
    }
  }

  function handleRemoteEnd(msg, status) {
    if (!state.call.session) return;
    if (msg.call_id && state.call.session.callId && state.call.session.callId !== msg.call_id) return;
    finalizeCall(status);
  }

  function handleCallRinging(msg) {
    if (!state.call.session || state.call.session.callId) return;
    state.call.session.callId = msg.call_id;
    flushPendingCandidates();
    if (state.call.pendingCancel) {
      signalingSend('call_cancel', { call_id: msg.call_id });
      state.call.pendingCancel = false;
      finalizeCall('canceled');
      return;
    }
    setCallStatus('ringing');
  }

  function handleIncomingCall(msg) {
    if (state.call.session) return;
    const peer = {
      id: msg.from_user_id,
      full_name: msg.caller_name,
      username: msg.caller_username,
      photo_id: msg.caller_photo_id
    };
    state.call.session = {
      callId: msg.call_id,
      conversationId: msg.conversation_id,
      peer,
      direction: 'incoming',
      status: 'incoming',
      isCaller: false,
      accepted: false
    };
    setCallPeer(peer);
    showCallOverlay();
    setCallStatus('incoming');
    stopCallTimer();
  }

  async function handleCallOffer(msg) {
    if (!state.call.session) {
      const peer = {
        id: msg.from_user_id,
        full_name: msg.caller_name,
        username: msg.caller_username,
        photo_id: msg.caller_photo_id
      };
      state.call.session = {
        callId: msg.call_id,
        conversationId: msg.conversation_id,
        peer,
        direction: 'incoming',
        status: 'incoming',
        isCaller: false,
        accepted: false
      };
      setCallPeer(peer);
      showCallOverlay();
      setCallStatus('incoming');
    }
    if (!state.call.session || state.call.session.callId !== msg.call_id) return;
    state.call.pendingOffer = msg.sdp;
    if (state.call.session.direction === 'incoming' && (state.call.session.accepted || state.call.pendingAccept)) {
      await acceptIncomingOffer();
    }
  }

  async function handleCallAnswer(msg) {
    if (!state.call.session || state.call.session.callId !== msg.call_id) return;
    if (!state.call.pc) return;
    try {
      await state.call.pc.setRemoteDescription(new RTCSessionDescription(msg.sdp));
      setCallStatus('connecting');
    } catch (err) {
      finalizeCall('failed');
    }
  }

  async function handleIceCandidate(msg) {
    if (!state.call.session || state.call.session.callId !== msg.call_id) return;
    if (!state.call.pc || !msg.candidate) return;
    try {
      await state.call.pc.addIceCandidate(msg.candidate);
    } catch (err) {
      // Ignore
    }
  }

  function flushPendingCandidates() {
    if (!state.call.session?.callId || state.call.pendingCandidates.length === 0) return;
    const callId = state.call.session.callId;
    state.call.pendingCandidates.forEach((candidate) => {
      signalingSend('ice_candidate', { call_id: callId, candidate });
    });
    state.call.pendingCandidates = [];
  }

  async function getLocalStream() {
    if (state.call.localStream) return state.call.localStream;
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      throw new Error('browser_not_supported');
    }
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    state.call.localStream = stream;
    return stream;
  }

  function attachRemoteStream(stream) {
    state.call.remoteStream = stream;
    if (remoteAudio) {
      remoteAudio.srcObject = stream;
      remoteAudio.play().catch(() => {});
    }
  }

  function createPeerConnection(isCaller) {
    const pc = new RTCPeerConnection({ iceServers: callConfig.iceServers });
    pc.onicecandidate = (event) => {
      if (event.candidate) {
        if (!state.call.session?.callId) {
          state.call.pendingCandidates.push(event.candidate);
        } else {
          signalingSend('ice_candidate', { call_id: state.call.session.callId, candidate: event.candidate });
        }
      }
    };
    pc.ontrack = (event) => {
      const stream = event.streams && event.streams[0] ? event.streams[0] : null;
      if (stream) {
        attachRemoteStream(stream);
      }
    };
    pc.onconnectionstatechange = () => {
      if (!state.call.session) return;
      if (pc.connectionState === 'connected') {
        state.call.restarting = false;
        setCallStatus('connected');
        startCallTimer();
      }
      if (pc.connectionState === 'failed' && state.call.session.status !== 'failed') {
        setCallStatus('failed');
        finalizeCall('failed');
      }
    };
    pc.oniceconnectionstatechange = async () => {
      if (!state.call.session) return;
      const iceState = pc.iceConnectionState;
      if (iceState === 'connected' || iceState === 'completed') {
        state.call.restarting = false;
        setCallStatus('connected');
        startCallTimer();
      }
      if ((iceState === 'disconnected' || iceState === 'failed') && state.call.session.status === 'connected') {
        setCallStatus('reconnecting');
        if (isCaller && !state.call.restarting) {
          state.call.restarting = true;
          try {
            const offer = await pc.createOffer({ iceRestart: true });
            await pc.setLocalDescription(offer);
            signalingSend('call_offer', { call_id: state.call.session.callId, sdp: offer, ice_restart: true });
          } catch (err) {
            finalizeCall('failed');
          }
        }
      }
    };
    return pc;
  }

  async function startOutgoingCall() {
    if (!state.currentConversation || isGroupChat()) return;
    if (state.call.session) {
      alert('در حال تماس هستید.');
      return;
    }
    if (!callConfig.signalingUrl) {
      alert('سیگنالینگ تماس تنظیم نشده است.');
      return;
    }
    if (!peerAllowsCalls(state.currentConversation)) {
      alert('این کاربر تماس صوتی را غیرفعال کرده است.');
      return;
    }
    const ready = await ensureSignalingConnected();
    if (!ready) {
      alert('اتصال به سرور تماس ممکن نیست.');
      return;
    }

    const peer = {
      id: state.currentConversation.other_id,
      full_name: state.currentConversation.other_name,
      username: state.currentConversation.other_username,
      photo_id: state.currentConversation.other_photo
    };
    state.call.session = {
      callId: null,
      conversationId: state.currentConversation.id,
      peer,
      direction: 'outgoing',
      status: 'calling',
      isCaller: true,
      accepted: false
    };
    setCallPeer(peer);
    showCallOverlay();
    setCallStatus('calling');
    stopCallTimer();

    try {
      const stream = await getLocalStream();
      const pc = createPeerConnection(true);
      state.call.pc = pc;
      stream.getTracks().forEach(track => pc.addTrack(track, stream));
      const offer = await pc.createOffer({ offerToReceiveAudio: true });
      await pc.setLocalDescription(offer);
      signalingSend('call_start', {
        conversation_id: state.currentConversation.id,
        to_user_id: peer.id,
        sdp: offer
      });
    } catch (err) {
      finalizeCall('failed');
      if (err && err.name === 'NotAllowedError') {
        alert('دسترسی به میکروفون رد شد.');
      } else {
        alert('خطا در شروع تماس.');
      }
    }
  }

  async function acceptIncomingOffer() {
    if (!state.call.session || !state.call.pendingOffer) return;
    state.call.pendingAccept = false;
    try {
      const stream = await getLocalStream();
      let pc = state.call.pc;
      if (!pc) {
        pc = createPeerConnection(false);
        state.call.pc = pc;
      }
      if (pc.getSenders && pc.getSenders().length === 0) {
        stream.getTracks().forEach(track => pc.addTrack(track, stream));
      }
      await pc.setRemoteDescription(new RTCSessionDescription(state.call.pendingOffer));
      state.call.pendingOffer = null;
      const answer = await pc.createAnswer();
      await pc.setLocalDescription(answer);
      signalingSend('call_answer', { call_id: state.call.session.callId, sdp: answer });
      setCallStatus('connecting');
    } catch (err) {
      finalizeCall('failed');
      if (err && err.name === 'NotAllowedError') {
        alert('دسترسی به میکروفون رد شد.');
      }
    }
  }

  async function acceptIncomingCall() {
    if (!state.call.session || state.call.session.direction !== 'incoming') return;
    state.call.session.accepted = true;
    setCallStatus('connecting');
    if (!state.call.pendingOffer) {
      state.call.pendingAccept = true;
      return;
    }
    await acceptIncomingOffer();
  }

  function declineIncomingCall() {
    if (!state.call.session || state.call.session.direction !== 'incoming') return;
    if (state.call.session.callId) {
      signalingSend('call_decline', { call_id: state.call.session.callId });
    }
    finalizeCall('declined');
  }

  function hangupCall() {
    if (!state.call.session) return;
    const status = state.call.session.status;
    if (state.call.session.callId) {
      const reason = status === 'connected' ? 'completed' : 'canceled';
      signalingSend('call_end', { call_id: state.call.session.callId, reason });
    } else {
      state.call.pendingCancel = true;
    }
    finalizeCall(status === 'connected' ? 'ended' : 'canceled');
  }

  function toggleMute() {
    if (!state.call.localStream) return;
    state.call.muted = !state.call.muted;
    state.call.localStream.getAudioTracks().forEach(track => {
      track.enabled = !state.call.muted;
    });
    if (window.CallUI && typeof window.CallUI.setMuted === 'function') {
      window.CallUI.setMuted(state.call.muted);
    }
    updateCallControls();
  }

  async function loadOutputDevices() {
    if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return [];
    const devices = await navigator.mediaDevices.enumerateDevices();
    const outputs = devices.filter(d => d.kind === 'audiooutput');
    state.call.outputDevices = outputs;
    return outputs;
  }

  async function toggleSpeaker() {
    if (!remoteAudio || typeof remoteAudio.setSinkId !== 'function') return;
    const outputs = state.call.outputDevices.length ? state.call.outputDevices : await loadOutputDevices();
    if (!outputs || outputs.length === 0) return;
    const currentId = state.call.sinkId || outputs[0].deviceId;
    let next = outputs.find(d => d.deviceId !== currentId) || outputs[0];
    try {
      await remoteAudio.setSinkId(next.deviceId);
      state.call.sinkId = next.deviceId;
      if (window.CallUI && typeof window.CallUI.setSpeaker === 'function') {
        window.CallUI.setSpeaker(true);
      }
    } catch (err) {
      // Ignore
    }
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

  function buildReactionChips(messageId, reactions, currentReaction) {
    if (!reactions || reactions.length === 0) return null;
    const wrap = document.createElement('div');
    wrap.className = 'reaction-chips';
    reactions.forEach((reaction) => {
      const chip = document.createElement('button');
      chip.type = 'button';
      chip.className = 'reaction-chip';
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

  async function toggleReaction(messageId, emoji) {
    const messageEl = document.getElementById(`msg-${messageId}`);
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
      alert(err.message || 'خطا در واکنش پیام');
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
    const chips = buildReactionChips(messageId, info.reactions, info.current_user_reaction);
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

  function attachReactionLongPress(messageEl) {
    let timer = null;
    messageEl.addEventListener('touchstart', () => {
      timer = setTimeout(() => {
        messageEl.classList.add('show-reactions');
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
      messageEl.classList.remove('show-reactions');
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

  const savedTheme = localStorage.getItem('selo_theme') || 'light';
  setTheme(savedTheme);

  themeToggle.addEventListener('click', () => {
    const newTheme = document.body.dataset.theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
  });

  infoToggle?.addEventListener('click', () => {
    if (!state.currentConversation) return;
    const willShow = infoPanel ? infoPanel.classList.contains('hidden') : false;
    setInfoPanelVisible(willShow);
    updateInfoPanel();
  });

  infoClose?.addEventListener('click', () => {
    setInfoPanelVisible(false);
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && document.body.classList.contains('show-info')) {
      setInfoPanelVisible(false);
    }
  });

  document.addEventListener('click', (e) => {
    if (!document.body.classList.contains('show-info') || !infoPanel || !infoToggle) return;
    if (infoPanel.contains(e.target) || infoToggle.contains(e.target)) return;
    setInfoPanelVisible(false);
  });

  userSettingsBtn?.addEventListener('click', async () => {
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
        alert(err.message || 'خطا در حذف عکس');
      }
    }
  });

  profileUsernameInput?.addEventListener('input', () => {
    profileUsernameInput.value = profileUsernameInput.value.toLowerCase();
  });

  audioCallBtn?.addEventListener('click', startOutgoingCall);
  if (window.CallUI && typeof window.CallUI.on === 'function') {
    window.CallUI.on('accept', acceptIncomingCall);
    window.CallUI.on('decline', declineIncomingCall);
    window.CallUI.on('hangup', hangupCall);
    window.CallUI.on('mute', toggleMute);
    window.CallUI.on('speaker', toggleSpeaker);
    window.CallUI.on('close', () => {
      if (state.call.session?.direction === 'incoming' && !state.call.session?.accepted) {
        declineIncomingCall();
      } else if (state.call.session) {
        hangupCall();
      }
    });
  } else {
    callAcceptBtn?.addEventListener('click', acceptIncomingCall);
    callDeclineBtn?.addEventListener('click', declineIncomingCall);
    callHangupBtn?.addEventListener('click', hangupCall);
    callMuteBtn?.addEventListener('click', toggleMute);
    callSpeakerBtn?.addEventListener('click', toggleSpeaker);
  }
  if (callSpeakerBtn) {
    callSpeakerBtn.disabled = !remoteAudio || typeof remoteAudio.setSinkId !== 'function';
  }

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

  allowVoiceCallsToggle?.addEventListener('change', () => {
    updateAllowVoiceCalls(!!allowVoiceCallsToggle.checked);
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
    setInfoPanelVisible(false);
  }

  function showMain() {
    authView.classList.add('hidden');
    mainView.classList.remove('hidden');
    document.body.classList.add('show-chats');
    setInfoPanelVisible(false);
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
    if (isCurrent) {
      renderMessages([msg], false);
      queueReceipt([msg.id], 'delivered');
      markSeenForVisible();
    } else if (msg.sender_id !== state.me.id) {
      queueReceipt([msg.id], 'delivered');
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
  }

  function startSSE() {
    if (!window.EventSource) return false;
    if (state.realtime.es) return true;
    if (state.realtime.pollTimer) {
      clearTimeout(state.realtime.pollTimer);
      state.realtime.pollTimer = null;
    }
    const params = new URLSearchParams({
      token: state.token,
      last_message_id: state.realtime.lastMessageId,
      last_receipt_id: state.realtime.lastReceiptId
    });
    const es = new EventSource(makeUrl(API.stream + '?' + params.toString()));
    state.realtime.es = es;
    state.realtime.mode = 'sse';
    state.realtime.connected = false;

    es.addEventListener('open', () => {
      state.realtime.connected = true;
      state.realtime.backoffMs = 1000;
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
      timeout: document.hidden ? 10 : 25
    });
    try {
      const etag = `m:${state.realtime.lastMessageId}-r:${state.realtime.lastReceiptId}`;
      const res = await apiFetch(API.poll + '?' + params.toString(), { headers: { 'If-None-Match': etag } });
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
        state.realtime.backoffMs = 1000;
      }
    } catch (err) {
      // ignore and backoff
    }

    const jitter = Math.floor(Math.random() * 500);
    const delay = Math.min(15000, state.realtime.backoffMs + jitter);
    state.realtime.backoffMs = Math.min(15000, Math.round(state.realtime.backoffMs * 1.4));
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
    state.realtime.backoffMs = 1000;
    pollLoop();
  }

  function startRealtime() {
    if (!state.token) return;
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
    if (document.hidden) {
      startPolling(true);
    } else {
      startRealtime();
    }
  });

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

  function openModal(modal) {
    if (!modal) return;
    modal.classList.remove('hidden');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.add('hidden');
  }

  function syncUserSettingsUI() {
    if (allowVoiceCallsToggle) {
      allowVoiceCallsToggle.checked = !!state.me?.allow_voice_calls;
    }
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
        alert(err.message || 'خطا در آپلود عکس');
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
        alert(err.message || 'خطا در ذخیره پروفایل');
      }
    }
  }

  async function updateAllowVoiceCalls(enabled) {
    if (!state.me || !allowVoiceCallsToggle) return;
    const previous = !!state.me.allow_voice_calls;
    allowVoiceCallsToggle.disabled = true;
    try {
      const res = await apiFetch(API.meSettings, { method: 'PATCH', body: { allow_voice_calls: enabled } });
      if (!res.data.ok) {
        throw new Error(res.data.message || res.data.error || 'خطا در ذخیره تنظیمات');
      }
      state.me.allow_voice_calls = !!res.data.data.allow_voice_calls;
      allowVoiceCallsToggle.checked = !!state.me.allow_voice_calls;
    } catch (err) {
      allowVoiceCallsToggle.checked = previous;
      alert(err.message || 'خطا در ذخیره تنظیمات');
    } finally {
      allowVoiceCallsToggle.disabled = false;
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
      alert(err.message || 'خطا در ذخیره تنظیمات');
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
      groupInviteLink.value = appUrl() + '/?invite=' + state.currentGroup.invite_token;
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

  function formatTime(dateStr) {
    const date = new Date(dateStr.replace(' ', 'T'));
    return new Intl.DateTimeFormat('fa-IR', { hour: '2-digit', minute: '2-digit' }).format(date);
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
      groupSettingsBtn.classList.add('hidden');
      audioCallBtn?.classList.add('hidden');
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
      groupSettingsBtn.classList.remove('hidden');
      audioCallBtn?.classList.add('hidden');
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
    groupSettingsBtn.classList.add('hidden');
    if (audioCallBtn) {
      audioCallBtn.classList.remove('hidden');
      const allowed = peerAllowsCalls(conversation);
      const canCall = !!callConfig.signalingUrl && allowed;
      toggleButton(audioCallBtn, canCall);
      if (!callConfig.signalingUrl) {
        audioCallBtn.title = 'سیگنالینگ تماس تنظیم نشده است.';
      } else if (!allowed) {
        audioCallBtn.title = 'این کاربر تماس صوتی را غیرفعال کرده است.';
      } else {
        audioCallBtn.title = 'تماس صوتی';
      }
    }
    updateHeaderStatus();
    updateInfoPanel();
  }

  function setActiveChatItem(conversation) {
    if (!conversation) return;
    const key = conversation.chat_type + ':' + conversation.id;
    document.querySelectorAll('.chat-item').forEach(item => {
      item.classList.toggle('active', item.dataset.key === key);
    });
  }

  function renderConversations() {
    chatList.innerHTML = '';
    state.conversations.forEach(conv => {
      const item = document.createElement('div');
      item.className = 'chat-item';
      item.dataset.key = conv.chat_type + ':' + conv.id;
      if (state.currentConversation && state.currentConversation.id === conv.id && state.currentConversation.chat_type === conv.chat_type) {
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
    const params = new URLSearchParams(window.location.search);
    const token = params.get('invite');
    if (!token || !state.token) return;
    const res = await apiFetch(API.groupJoinByLink, { method: 'POST', body: { token } });
    if (res.data.ok) {
      await loadConversations();
      const conv = state.conversations.find(c => c.chat_type === 'group' && c.id === res.data.data.group_id);
      if (conv) {
        await selectConversation(conv);
      }
    }
    params.delete('invite');
    const newUrl = params.toString() ? `${window.location.pathname}?${params.toString()}` : window.location.pathname;
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
  }

  async function loadMessages(beforeId = null) {
    if (!state.currentConversation || state.loadingMessages) return;
    state.loadingMessages = true;
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
    if (res.data.ok) {
      const list = res.data.data;
      if (list.length > 0) {
        state.oldestMessageId = list[0].id;
        const maxId = list[list.length - 1].id;
        state.realtime.lastMessageId = Math.max(state.realtime.lastMessageId, maxId);
      }
      renderMessages(list, beforeId !== null);
      if (!beforeId) {
        queueReceipt(list.filter(msg => msg.sender_id !== state.me.id).map(msg => msg.id), 'delivered');
        markSeenForVisible();
      }
    }
    state.loadingMessages = false;
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
          const img = document.createElement('img');
          const src = att.thumbnail_name ? makeUrl(API.mediaThumb(att.id)) : makeUrl(API.media(att.id));
          img.src = src;
          img.alt = att.original_name || 'photo';
          img.addEventListener('click', () => {
            lightboxImg.src = makeUrl(API.media(att.id));
            lightbox.classList.remove('hidden');
          });
          grid.appendChild(img);
        });
        mediaWrap.appendChild(grid);
      }

      videos.forEach(att => {
        const video = document.createElement('video');
        video.src = makeUrl(API.media(att.id));
        video.controls = true;
        video.playsInline = true;
        mediaWrap.appendChild(video);
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
    if (element.classList.contains('seen') && status !== 'seen') {
      return;
    }
    element.classList.remove('seen');
    if (status === 'sent') {
      element.textContent = '✓';
    } else if (status === 'delivered') {
      element.textContent = '✓✓';
    } else if (status === 'seen') {
      element.textContent = '✓✓';
      element.classList.add('seen');
    }
  }

  function updateMessageReceiptUI(messageId, receipt) {
    const message = document.getElementById(`msg-${messageId}`);
    if (!message) return;
    const ticks = message.querySelector('.meta-ticks');
    if (ticks) {
      applyReceiptToTicks(ticks, receipt);
    }
  }

  function renderMessages(messages, prepend = false) {
    const fragment = document.createDocumentFragment();
    const inserted = [];
    messages.forEach(msg => {
      if (document.getElementById(`msg-${msg.id}`)) {
        return;
      }
      const message = document.createElement('div');
      message.className = 'message ' + (msg.sender_id === state.me.id ? 'outgoing' : 'incoming');
      message.id = `msg-${msg.id}`;
      message.dataset.currentReaction = msg.current_user_reaction || '';
      message.dataset.senderId = String(msg.sender_id || '');

      const attachments = Array.isArray(msg.attachments) ? msg.attachments : [];
      const hasMedia = attachments.length > 0 || !!msg.media;
      if (!hasMedia && isEmojiOnly(msg.body || '')) {
        message.classList.add('emoji-only');
      }

      const reactionBar = buildReactionBar(msg.id, msg.current_user_reaction);
      message.appendChild(reactionBar);

      if (isGroupChat()) {
        const senderWrap = document.createElement('div');
        senderWrap.className = 'message-sender';
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

      const chips = buildReactionChips(msg.id, msg.reactions || [], msg.current_user_reaction);
      if (chips) {
        message.appendChild(chips);
      }

      const meta = document.createElement('div');
      meta.className = 'meta';
      const time = document.createElement('span');
      time.className = 'meta-time';
      time.textContent = formatTime(msg.created_at);
      meta.appendChild(time);
      if (msg.sender_id === state.me.id) {
        const ticks = document.createElement('span');
        ticks.className = 'meta-ticks';
        applyReceiptToTicks(ticks, msg.receipt || null);
        meta.appendChild(ticks);
      }
      message.appendChild(meta);

      attachReactionLongPress(message);
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

  function markSeenForVisible() {
    if (!state.currentConversation || !state.me) return;
    if (!isAtBottom()) return;
    const incoming = Array.from(messagesEl.querySelectorAll('.message.incoming'));
    const ids = incoming.map(node => Number(node.id.replace('msg-', ''))).filter(Boolean);
    if (ids.length) {
      queueReceipt(ids, 'seen');
    }
  }

  async function sendMessage() {
    if (!state.currentConversation) return;
    const endpoint = isGroupChat()
      ? API.groupMessages(state.currentConversation.id)
      : API.messages;

    if (state.pendingAttachments.length) {
      if (state.uploading) return;
      if (state.pendingAttachments.some(att => !groupAllows(att.type))) {
        alert('ارسال این نوع پیام در گروه مجاز نیست.');
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
        const res = await apiFetch(endpoint, { method: 'POST', body: payload });
        if (res.data.ok) {
          messageInput.value = '';
          clearAttachments();
          clearReply();
          scheduleConversationsRefresh();
          if (!state.realtime.connected) {
            await loadMessages();
            await loadConversations();
          }
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
    const res = await apiFetch(endpoint, { method: 'POST', body: payload });
    if (res.data.ok) {
      messageInput.value = '';
      clearReply();
      scheduleConversationsRefresh();
      if (!state.realtime.connected) {
        await loadMessages();
        await loadConversations();
      }
    }
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
      alert('ارسال عکس در این گروه مجاز نیست.');
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
      alert('ارسال ویدیو در این گروه مجاز نیست.');
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
      alert('مرورگر شما از ضبط صدا پشتیبانی نمی‌کند.');
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
    if (!groupAllows('voice')) {
      alert('ارسال پیام صوتی در این گروه مجاز نیست.');
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
      const res = await apiFetch(API.usersSearch + '?query=' + encodeURIComponent(query));
      if (res.data.ok) {
        searchResults.innerHTML = '';
        res.data.data.forEach(user => {
          const item = document.createElement('div');
          item.className = 'search-item';
          item.textContent = `${user.full_name} (@${user.username})`;
          item.addEventListener('click', async () => {
            const convRes = await apiFetch(API.conversations, { method: 'POST', body: { user_id: user.id } });
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
    markSeenForVisible();
  }, { passive: true });

  async function initialize() {
    if (!state.token) {
      showAuth();
      return;
    }
    try {
      const meRes = await apiFetch(API.me);
      if (!meRes.data.ok) {
        throw new Error('ورود نامعتبر است');
      }
      state.me = meRes.data.data.user;
      state.profilePhotos = meRes.data.data.photos || [];
      showMain();
      syncUserSettingsUI();
      initEmojiPicker();
      connectSignaling();
      await loadConversations();
      await handleInviteLink();
      if (!state.currentConversation && state.conversations.length > 0) {
        await selectConversation(state.conversations[0]);
      }
      startRealtime();
      if (!conversationsAutoRefreshTimer) {
        conversationsAutoRefreshTimer = setInterval(() => {
          if (state.token) {
            loadConversations();
          }
        }, 20000);
      }
    } catch (err) {
      localStorage.removeItem('selo_token');
      state.token = null;
      stopRealtime();
      showAuth();
    }
  }

  initialize();
})();
