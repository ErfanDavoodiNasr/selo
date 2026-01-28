(function () {
  var overlay = document.getElementById('call-overlay');
  if (!overlay) return;

  var nameEl = document.getElementById('call-name');
  var statusEl = document.getElementById('call-status');
  var topStatusEl = document.getElementById('call-top-status');
  var timerEl = document.getElementById('call-timer');
  var avatarEl = document.getElementById('call-avatar');
  var initialEl = document.getElementById('call-initial');

  var acceptBtn = document.getElementById('call-accept-btn');
  var declineBtn = document.getElementById('call-decline-btn');
  var hangupBtn = document.getElementById('call-hangup-btn');
  var muteBtn = document.getElementById('call-mute-btn');
  var speakerBtn = document.getElementById('call-speaker-btn');
  var closeBtn = document.getElementById('call-close-btn');

  var state = 'idle';
  var timerId = null;
  var startAt = 0;
  var hideStatusTimer = null;
  var handlers = {};
  var baseUrl = (window.SELO_CONFIG && window.SELO_CONFIG.baseUrl) ? window.SELO_CONFIG.baseUrl.replace(/\/$/, '') : '';

  var stateMap = {
    calling: { ui: 'outgoing_ringing', label: 'در حال تماس…' },
    ringing: { ui: 'outgoing_ringing', label: 'در حال زنگ…' },
    incoming: { ui: 'incoming_ringing', label: 'تماس ورودی' },
    connecting: { ui: 'connecting', label: 'در حال اتصال…' },
    reconnecting: { ui: 'reconnecting', label: 'در حال اتصال مجدد…' },
    connected: { ui: 'connected', label: 'تماس برقرار شد' },
    ended: { ui: 'ended', label: 'تماس پایان یافت' },
    failed: { ui: 'failed', label: 'تماس ناموفق بود' },
    declined: { ui: 'ended', label: 'تماس رد شد' },
    busy: { ui: 'ended', label: 'کاربر مشغول است' },
    missed: { ui: 'ended', label: 'تماس بی‌پاسخ' },
    canceled: { ui: 'ended', label: 'تماس لغو شد' }
  };

  function emit(event, payload) {
    var list = handlers[event] || [];
    list.forEach(function (fn) { fn(payload); });
  }

  function on(event, fn) {
    if (!handlers[event]) handlers[event] = [];
    handlers[event].push(fn);
  }

  function open() {
    overlay.classList.add('is-visible');
    overlay.setAttribute('aria-hidden', 'false');
    document.body.classList.add('call-open');
  }

  function openOutgoing(options) {
    if (options && options.peer) {
      setPeer(options.peer);
    }
    open();
    setState('calling');
  }

  function openIncoming(options) {
    if (options && options.peer) {
      setPeer(options.peer);
    }
    open();
    setState('incoming');
  }

  function close() {
    overlay.classList.remove('is-visible');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('call-open');
    overlay.dataset.state = 'idle';
    overlay.classList.remove('is-status-hidden');
  }

  function setPeer(peer) {
    if (!peer) return;
    var name = peer.full_name || peer.name || peer.username || 'تماس';
    if (nameEl) nameEl.textContent = name;
    if (peer.photo_id) {
      var photoPath = '/photo.php?id=' + peer.photo_id;
      var url = baseUrl ? (baseUrl + photoPath) : photoPath;
      avatarEl.style.backgroundImage = "url('" + url + "')";
      if (initialEl) initialEl.textContent = '';
    } else if (peer.photo_url) {
      avatarEl.style.backgroundImage = "url('" + peer.photo_url + "')";
      if (initialEl) initialEl.textContent = '';
    } else {
      avatarEl.style.backgroundImage = '';
      var initial = name.trim().charAt(0) || '?';
      if (initialEl) initialEl.textContent = initial;
    }
  }

  function setState(nextState) {
    state = nextState;
    var meta = stateMap[nextState] || { ui: nextState, label: '' };
    overlay.dataset.state = meta.ui;

    var label = meta.label || '';
    if (topStatusEl) topStatusEl.textContent = label;
    if (statusEl) statusEl.textContent = label;

    overlay.classList.remove('is-status-hidden');
    if (hideStatusTimer) {
      clearTimeout(hideStatusTimer);
      hideStatusTimer = null;
    }

    if (nextState === 'connected') {
      hideStatusTimer = setTimeout(function () {
        overlay.classList.add('is-status-hidden');
      }, 1400);
    }

    if (nextState === 'ended' || nextState === 'failed') {
      stopTimer();
    }
  }

  function setMuted(isMuted) {
    overlay.dataset.muted = isMuted ? 'true' : 'false';
  }

  function setSpeaker(isOn) {
    overlay.dataset.speaker = isOn ? 'true' : 'false';
  }

  function formatDuration(seconds) {
    var total = Math.max(0, Math.floor(seconds));
    var h = Math.floor(total / 3600);
    var m = Math.floor((total % 3600) / 60);
    var s = total % 60;
    var out = [m, s].map(function (v) { return String(v).padStart(2, '0'); }).join(':');
    if (h > 0) {
      out = String(h).padStart(2, '0') + ':' + out;
    }
    return out;
  }

  function startTimer(startTs) {
    if (timerId) return;
    startAt = startTs || Date.now();
    if (timerEl) timerEl.textContent = '00:00';
    timerId = setInterval(function () {
      var seconds = Math.floor((Date.now() - startAt) / 1000);
      if (timerEl) timerEl.textContent = formatDuration(seconds);
    }, 1000);
  }

  function stopTimer() {
    if (timerId) {
      clearInterval(timerId);
      timerId = null;
    }
    if (timerEl) timerEl.textContent = '00:00';
  }

  function handleEsc(e) {
    if (e.key !== 'Escape') return;
    if (state === 'incoming') {
      emit('decline');
    } else if (state !== 'idle') {
      emit('hangup');
    }
  }

  if (acceptBtn) acceptBtn.addEventListener('click', function () { emit('accept'); });
  if (declineBtn) declineBtn.addEventListener('click', function () { emit('decline'); });
  if (hangupBtn) hangupBtn.addEventListener('click', function () { emit('hangup'); });
  if (muteBtn) muteBtn.addEventListener('click', function () { emit('mute'); });
  if (speakerBtn) speakerBtn.addEventListener('click', function () { emit('speaker'); });
  if (closeBtn) closeBtn.addEventListener('click', function () { emit('close'); });

  document.addEventListener('keydown', handleEsc);

  window.CallUI = {
    open: open,
    openOutgoing: openOutgoing,
    openIncoming: openIncoming,
    close: close,
    setPeer: setPeer,
    setState: setState,
    setMuted: setMuted,
    setSpeaker: setSpeaker,
    startTimer: startTimer,
    stopTimer: stopTimer,
    on: on
  };
})();
