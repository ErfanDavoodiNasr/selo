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

Automatic fix (recommended after each upload/extract):
- `bash scripts/fix-permissions.sh`
- Optional owner/group on VPS: `bash scripts/fix-permissions.sh /home/USER/public_html/selo www-data www-data`

If your cPanel has no terminal/SSH:
1. In File Manager create: `storage/.permfix.key` and put a long random string in it.
2. Open in browser: `https://yourdomain.com/fix-permissions.php?key=YOUR_STRING`
3. After success, delete `storage/.permfix.key`.
4. (Optional, recommended) delete `public/fix-permissions.php` too.

### 5) Create database
- cPanel → **MySQL Databases**
- Create DB + user, assign **ALL privileges**
- Keep: **DB name, user, password, host (usually localhost)**

### 6) Run installer
- If installed in root: `https://yourdomain.com/install` (or `https://yourdomain.com/install.php` if `/install` is blocked)
- If in subfolder: `https://yourdomain.com/selo/install` (or `https://yourdomain.com/selo/install.php` if `/install` is blocked)

Installer is locked by default for non-local IPs.
- Preferred: set env var `SELO_INSTALL_TOKEN` and open installer with `?install_token=...` (or `X-Install-Token` header).
- Alternative: create file `storage/.install_unlock` before first run; it is removed automatically after successful install.

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
- Filesystem permission policy (runtime): `filesystem.dir_mode`, `filesystem.file_mode`, `filesystem.umask`

## Upgrade / Migration
- Runtime schema auto-create has been removed from request paths.
- Before upgrading code, apply schema updates from `database/schema.sql` (same DB prefix as your install).
- If schema is incomplete, APIs now return a clear migration-required error instead of trying runtime DDL.

### Offline Compliance Check
- Run: `php scripts/check-offline-compliance.php`
- The script scans `public/`, `app/`, and `config/` for non-local `http(s)/ws(s)/stun/turn` URLs and fails when public-internet dependencies are detected.

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

## Realtime Notes
- For messenger realtime, use built-in **SSE + polling fallback** (`/api/stream` and `/api/poll`) which is PHP-compatible.
