const WebSocket = require('ws');
const crypto = require('crypto');
const http = require('http');
const fetch = require('node-fetch');

const PORT = parseInt(process.env.PORT || '3001', 10);
const HOST = process.env.HOST || '127.0.0.1';
const WS_PATH = normalizePath(process.env.WS_PATH || '/ws');
const SIGNALING_SECRET = process.env.SIGNALING_SECRET || '';
const API_BASE = (process.env.API_BASE || '').replace(/\/$/, '');
const CALL_VALIDATE_ENDPOINT = process.env.CALL_VALIDATE_ENDPOINT || (API_BASE ? `${API_BASE}/api/calls/validate` : '');
const CALL_LOG_ENDPOINT = process.env.CALL_LOG_ENDPOINT || (API_BASE ? `${API_BASE}/api/calls/event` : '');
const API_SECRET = process.env.API_SECRET || SIGNALING_SECRET;
const CALL_RING_TIMEOUT = parseInt(process.env.CALL_RING_TIMEOUT || '45', 10);
const CALL_RATE_MAX = parseInt(process.env.CALL_RATE_MAX || '6', 10);
const CALL_RATE_WINDOW = parseInt(process.env.CALL_RATE_WINDOW || '60', 10);

if (!SIGNALING_SECRET) {
  console.error('SIGNALING_SECRET is required.');
  process.exit(1);
}

function normalizePath(path) {
  const value = String(path || '').trim();
  if (!value) return '/ws';
  return value.startsWith('/') ? value : `/${value}`;
}

function isWsPath(pathname) {
  return pathname === WS_PATH || pathname === `${WS_PATH}/`;
}

const clientsByUser = new Map();
const sessions = new Map();
const userCalls = new Map();
const rateLimiter = new Map();

function generateId() {
  return crypto.randomBytes(16).toString('hex');
}

function base64UrlEncode(buffer) {
  return Buffer.from(buffer)
    .toString('base64')
    .replace(/\+/g, '-')
    .replace(/\//g, '_')
    .replace(/=+$/g, '');
}

function base64UrlDecode(str) {
  let input = str.replace(/-/g, '+').replace(/_/g, '/');
  const pad = input.length % 4;
  if (pad) {
    input += '='.repeat(4 - pad);
  }
  return Buffer.from(input, 'base64').toString('utf8');
}

function verifyToken(token) {
  const parts = String(token || '').split('.');
  if (parts.length !== 3) return null;
  const [header, payload, signature] = parts;
  const data = `${header}.${payload}`;
  const expected = base64UrlEncode(crypto.createHmac('sha256', SIGNALING_SECRET).update(data).digest());
  if (expected !== signature) return null;
  let decoded = null;
  try {
    decoded = JSON.parse(base64UrlDecode(payload));
  } catch (err) {
    return null;
  }
  if (!decoded || !decoded.exp || decoded.exp < Math.floor(Date.now() / 1000)) {
    return null;
  }
  return decoded;
}

function addClient(userId, ws) {
  const id = String(userId);
  if (!clientsByUser.has(id)) {
    clientsByUser.set(id, new Set());
  }
  clientsByUser.get(id).add(ws);
}

function removeClient(userId, ws) {
  const id = String(userId);
  const set = clientsByUser.get(id);
  if (!set) return;
  set.delete(ws);
  if (set.size === 0) {
    clientsByUser.delete(id);
  }
}

function send(ws, payload) {
  if (ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify(payload));
  }
}

function sendToUser(userId, payload) {
  const set = clientsByUser.get(String(userId));
  if (!set || set.size === 0) return false;
  set.forEach((ws) => send(ws, payload));
  return true;
}

function isRateLimited(userId) {
  const now = Date.now();
  const windowMs = CALL_RATE_WINDOW * 1000;
  const list = rateLimiter.get(userId) || [];
  const filtered = list.filter((ts) => now - ts < windowMs);
  if (filtered.length >= CALL_RATE_MAX) {
    rateLimiter.set(userId, filtered);
    return true;
  }
  filtered.push(now);
  rateLimiter.set(userId, filtered);
  return false;
}

async function validateCall(callerId, calleeId, conversationId) {
  if (!CALL_VALIDATE_ENDPOINT) {
    return { ok: false, error: 'validate_endpoint_missing' };
  }
  try {
    const res = await fetch(CALL_VALIDATE_ENDPOINT, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Signaling-Secret': API_SECRET
      },
      body: JSON.stringify({
        caller_id: callerId,
        callee_id: calleeId,
        conversation_id: conversationId
      })
    });
    const data = await res.json().catch(() => null);
    if (!data || !data.ok) {
      return { ok: false, error: data?.error || 'validate_failed', message: data?.message || '' };
    }
    return { ok: true, data: data.data };
  } catch (err) {
    return { ok: false, error: 'validate_error' };
  }
}

async function logEvent(event, payload) {
  if (!CALL_LOG_ENDPOINT) return null;
  try {
    const res = await fetch(CALL_LOG_ENDPOINT, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Signaling-Secret': API_SECRET
      },
      body: JSON.stringify({ event, ...payload })
    });
    const data = await res.json().catch(() => null);
    if (!data || !data.ok) return null;
    return data.data || null;
  } catch (err) {
    return null;
  }
}

async function endSession(callId, reason) {
  const session = sessions.get(callId);
  if (!session) return;
  if (session.ringTimer) {
    clearTimeout(session.ringTimer);
  }
  userCalls.delete(String(session.callerId));
  userCalls.delete(String(session.calleeId));
  sessions.delete(callId);
  if (session.logId) {
    await logEvent('end', { call_log_id: session.logId, end_reason: reason });
  }
}

async function handleMissed(callId) {
  const session = sessions.get(callId);
  if (!session || session.status !== 'ringing') return;
  sendToUser(session.callerId, { type: 'call_missed', call_id: callId });
  sendToUser(session.calleeId, { type: 'call_missed', call_id: callId });
  await endSession(callId, 'missed');
}

async function handleCallStart(ws, msg) {
  const callerId = ws.userId;
  const conversationId = parseInt(msg.conversation_id || 0, 10);
  const calleeId = parseInt(msg.to_user_id || 0, 10);

  if (!callerId || !conversationId || !calleeId || calleeId === callerId) {
    send(ws, { type: 'call_failed', message: 'invalid_payload' });
    return;
  }

  if (isRateLimited(callerId)) {
    send(ws, { type: 'call_failed', message: 'rate_limited' });
    return;
  }

  if (userCalls.has(String(callerId))) {
    send(ws, { type: 'call_busy', call_id: null });
    return;
  }

  const validation = await validateCall(callerId, calleeId, conversationId);
  if (!validation.ok) {
    if (validation.error === 'CALLS_DISABLED') {
      send(ws, { type: 'call_failed', code: 'CALLS_DISABLED', message: validation.message || 'calls_disabled' });
    } else {
      send(ws, { type: 'call_failed', message: 'not_allowed' });
    }
    return;
  }

  if (userCalls.has(String(calleeId))) {
    const logData = await logEvent('start', {
      conversation_id: conversationId,
      caller_id: callerId,
      callee_id: calleeId
    });
    if (logData?.call_log_id) {
      await logEvent('end', { call_log_id: logData.call_log_id, end_reason: 'busy' });
    }
    send(ws, { type: 'call_busy', call_id: null });
    return;
  }

  const callId = generateId();
  const session = {
    id: callId,
    conversationId,
    callerId,
    calleeId,
    status: 'ringing',
    logId: null,
    ringTimer: null
  };

  sessions.set(callId, session);
  userCalls.set(String(callerId), callId);
  userCalls.set(String(calleeId), callId);

  const logData = await logEvent('start', {
    conversation_id: conversationId,
    caller_id: callerId,
    callee_id: calleeId
  });
  if (logData?.call_log_id) {
    session.logId = logData.call_log_id;
  }

  send(ws, { type: 'call_ringing', call_id: callId });

  const caller = validation.data.caller;
  sendToUser(calleeId, {
    type: 'incoming_call',
    call_id: callId,
    conversation_id: conversationId,
    from_user_id: callerId,
    caller_name: caller.full_name,
    caller_username: caller.username,
    caller_photo_id: caller.photo_id
  });

  if (msg.sdp) {
    sendToUser(calleeId, {
      type: 'call_offer',
      call_id: callId,
      conversation_id: conversationId,
      from_user_id: callerId,
      sdp: msg.sdp
    });
  }

  session.ringTimer = setTimeout(() => {
    handleMissed(callId);
  }, CALL_RING_TIMEOUT * 1000);
}

async function handleCallOffer(ws, msg) {
  const callId = msg.call_id;
  const session = sessions.get(callId);
  if (!session) return;
  const target = ws.userId === session.callerId ? session.calleeId : session.callerId;
  sendToUser(target, {
    type: 'call_offer',
    call_id: callId,
    conversation_id: session.conversationId,
    from_user_id: ws.userId,
    sdp: msg.sdp,
    ice_restart: !!msg.ice_restart
  });
}

async function handleCallAnswer(ws, msg) {
  const callId = msg.call_id;
  const session = sessions.get(callId);
  if (!session || ws.userId !== session.calleeId) return;
  session.status = 'connected';
  if (session.ringTimer) {
    clearTimeout(session.ringTimer);
    session.ringTimer = null;
  }
  if (session.logId) {
    await logEvent('answer', { call_log_id: session.logId });
  }
  sendToUser(session.callerId, {
    type: 'call_answer',
    call_id: callId,
    from_user_id: ws.userId,
    sdp: msg.sdp
  });
}

async function handleIceCandidate(ws, msg) {
  const callId = msg.call_id;
  const session = sessions.get(callId);
  if (!session || !msg.candidate) return;
  const target = ws.userId === session.callerId ? session.calleeId : session.callerId;
  sendToUser(target, {
    type: 'ice_candidate',
    call_id: callId,
    from_user_id: ws.userId,
    candidate: msg.candidate
  });
}

async function handleCallDecline(ws, msg) {
  const callId = msg.call_id;
  const session = sessions.get(callId);
  if (!session || ws.userId !== session.calleeId) return;
  sendToUser(session.callerId, { type: 'call_declined', call_id: callId });
  await endSession(callId, 'declined');
}

async function handleCallCancel(ws, msg) {
  const callId = msg.call_id;
  const session = sessions.get(callId);
  if (!session || ws.userId !== session.callerId) return;
  sendToUser(session.calleeId, { type: 'call_canceled', call_id: callId });
  await endSession(callId, 'canceled');
}

async function handleCallEnd(ws, msg) {
  const callId = msg.call_id;
  const session = sessions.get(callId);
  if (!session) return;
  const reason = msg.reason && ['completed', 'canceled', 'failed'].includes(msg.reason)
    ? msg.reason
    : (session.status === 'connected' ? 'completed' : 'canceled');
  const target = ws.userId === session.callerId ? session.calleeId : session.callerId;
  sendToUser(target, { type: 'call_end', call_id: callId, reason });
  await endSession(callId, reason);
}

function handleDisconnect(ws) {
  if (!ws.userId) return;
  removeClient(ws.userId, ws);
  const remaining = clientsByUser.get(String(ws.userId));
  if (remaining && remaining.size > 0) {
    return;
  }
  const callId = userCalls.get(String(ws.userId));
  if (!callId) return;
  const session = sessions.get(callId);
  if (!session) return;
  const otherId = ws.userId === session.callerId ? session.calleeId : session.callerId;
  sendToUser(otherId, { type: 'call_end', call_id: callId, reason: 'failed' });
  endSession(callId, 'failed');
}

const server = http.createServer((req, res) => {
  if (req.url === '/' || req.url === '/health') {
    res.writeHead(200, { 'Content-Type': 'text/plain' });
    res.end('ok');
    return;
  }
  res.writeHead(404);
  res.end();
});

const wss = new WebSocket.Server({ noServer: true });

server.on('upgrade', (req, socket, head) => {
  let pathname = '/';
  try {
    const parsed = new URL(req.url, 'http://localhost');
    pathname = parsed.pathname || '/';
  } catch (err) {
    pathname = '/';
  }
  if (!isWsPath(pathname)) {
    console.warn('ws_upgrade_rejected', { path: pathname });
    socket.write('HTTP/1.1 404 Not Found\r\n\r\n');
    socket.destroy();
    return;
  }
  wss.handleUpgrade(req, socket, head, (ws) => {
    wss.emit('connection', ws, req);
  });
});

wss.on('connection', (ws, req) => {
  const xfwd = req.headers['x-forwarded-for'];
  const ip = Array.isArray(xfwd) ? xfwd[0] : (xfwd || req.socket.remoteAddress || '');
  console.info('ws_connected', { ip });
  ws.isAlive = true;

  ws.on('pong', () => {
    ws.isAlive = true;
  });

  ws.on('message', async (raw) => {
    let msg = null;
    try {
      msg = JSON.parse(raw);
    } catch (err) {
      return;
    }
    if (!msg || !msg.type) return;

    if (msg.type === 'join') {
      const payload = verifyToken(msg.token);
      if (!payload) {
        send(ws, { type: 'join_error' });
        ws.close();
        return;
      }
      ws.userId = payload.sub;
      addClient(ws.userId, ws);
      send(ws, { type: 'join_ok', user_id: ws.userId });
      return;
    }

    if (!ws.userId) return;

    switch (msg.type) {
      case 'call_start':
        await handleCallStart(ws, msg);
        break;
      case 'call_offer':
        await handleCallOffer(ws, msg);
        break;
      case 'call_answer':
        await handleCallAnswer(ws, msg);
        break;
      case 'ice_candidate':
        await handleIceCandidate(ws, msg);
        break;
      case 'call_decline':
        await handleCallDecline(ws, msg);
        break;
      case 'call_cancel':
        await handleCallCancel(ws, msg);
        break;
      case 'call_end':
        await handleCallEnd(ws, msg);
        break;
      default:
        break;
    }
  });

  ws.on('close', () => {
    handleDisconnect(ws);
  });

  ws.on('error', (err) => {
    console.warn('ws_error', { message: err?.message || 'unknown' });
  });
});

const interval = setInterval(() => {
  wss.clients.forEach((ws) => {
    if (ws.isAlive === false) {
      ws.terminate();
      return;
    }
    ws.isAlive = false;
    ws.ping();
  });
}, 30000);

wss.on('close', () => {
  clearInterval(interval);
});

server.listen(PORT, HOST, () => {
  console.log(`Signaling server listening on ws://${HOST}:${PORT}${WS_PATH}`);
});
