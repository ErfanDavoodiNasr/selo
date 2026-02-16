# SELO (سلو) — PHP 7.4 Messenger

SELO is a Telegram‑like messenger for shared hosting. It supports private chats, group chats, replies, message deletion, reactions, and media messaging (text/emoji, photos, videos, voice, files). UI is Persian (RTL).

---

## Requirements (Short)
- **PHP 7.4.x**
- **MySQL 5.7+** or **MariaDB 10.2+**
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `gd`
- Recommended settings: `memory_limit=128M`, `upload_max_filesize=25M`, `post_max_size=25M`

---

## Install on cPanel (Step‑by‑Step)

### 1) Select PHP 7.4
- cPanel → **MultiPHP Manager** → select your domain → PHP **7.4** → Apply

### 2) Enable PHP extensions
- cPanel → **Select PHP Version** → enable: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `gd`

### 3) Upload project
- Example path: `/home/USERNAME/public_html/selo`
- Upload ZIP → Extract
- You should see: `app/`, `config/`, `database/`, `public/`, `storage/`

**Document root**
- Best: point domain to `/public`
- Simple deploy (no moving files): extract into `public_html` and open `/install` (requires `.htaccess` / mod_rewrite)
- If `.htaccess` is disabled: copy **contents of `public/`** into `public_html/` and keep the rest outside web root if you can

### 4) Set permissions
- Folders: **755**
- Files: **644**
- Writable folders: **775** (or **777** if 775 fails on shared hosting)
  - `config/`
  - `storage/`
  - `storage/uploads/`
  - `storage/uploads/media/`
  - `storage/logs/`

### 5) Create database
- cPanel → **MySQL Databases**
- Create DB + user, assign **ALL privileges**
- Keep: **DB name, user, password, host (usually localhost)**

### 6) Run installer
- If installed in root: `https://yourdomain.com/install` (or `https://yourdomain.com/install.php` if `/install` is blocked)
- If in subfolder: `https://yourdomain.com/selo/install` (or `https://yourdomain.com/selo/install.php` if `/install` is blocked)

Installer steps:
1. Requirements check
2. Database connection
3. Admin (optional)
4. Settings (app URL, JWT, uploads)
5. Config generation (`config/config.php`)
6. Finish

**After install**
- Delete or lock `/install`

---

## Configuration (Only what you usually need)
- File: `config/config.php`
- Change URL: `app.url`
- Change JWT secret: `app.jwt_secret` (changing it logs everyone out)
- Upload limits: `uploads.*`

### Offline Compliance Check
- Run: `php scripts/check-offline-compliance.php`
- The script scans `public/`, `app/`, `config/`, and `signaling/` for non-local `http(s)/ws(s)/stun/turn` URLs and fails when public-internet dependencies are detected.

---

## Logging (App + Error)
- Config: `logging.level` (DEBUG/INFO/WARNING/ERROR/CRITICAL)
  - Enable debug logs: set `logging.level` to **DEBUG**
  - Reduce noise: set it to **ERROR** or **CRITICAL**
- Log files:
  - App: `storage/logs/app.log`
  - Errors: `storage/logs/error.log`
- Rotation:
  - `logging.max_size_mb` (default 10MB)
  - `logging.max_files` (default 5)
- Permissions (cPanel):
  - `storage/logs/` → **775** (use **777** only if required by hosting)

---

## Troubleshooting (Quick)
- **500 error / white page** → wrong PHP version or permissions → set `storage/` to `775`
- **Upload failed** → make `storage/uploads/` writable
- **Upload size exceeded** → raise PHP limits and `uploads.*` in config
- **Emoji/reaction not saving** → ensure MySQL tables use **utf8mb4**
- **JWT unauthorized** → check `app.jwt_secret`

---

## Voice Calling (WebRTC) — Important Notes
- Voice calls are **audio‑only** and require **WebRTC** + a **signaling server** (WebSocket). Pure PHP/shared hosting alone is not enough.
- By default, `calls.enabled` is **false** in configuration templates and installer output (safe for cPanel shared hosting).
- If your cPanel plan does not support Node/WebSockets, keep calls disabled or host signaling externally and point `calls.signaling_url` to that external endpoint.
- For messenger realtime itself, use built-in **SSE + polling fallback** (`/api/stream` and `/api/poll`) which is PHP-compatible.
- Configure `calls.signaling_secret` (now in installer) and use the same value as `SIGNALING_SECRET` for the signaling server.
- No public STUN/TURN server is enabled by default (offline-safe). Add internal STUN/TURN servers to `calls.ice_servers` in `config/config.php` when needed.
- HTTPS requires **wss://** for signaling.
