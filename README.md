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
- If not possible: copy **contents of `public/`** into `public_html/` and keep the rest outside web root if you can

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
- If installed in root: `https://yourdomain.com/install`
- If in subfolder: `https://yourdomain.com/selo/install`

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

---

## Troubleshooting (Quick)
- **500 error / white page** → wrong PHP version or permissions → set `storage/` to `775`
- **Upload failed** → make `storage/uploads/` writable
- **Upload size exceeded** → raise PHP limits and `uploads.*` in config
- **Emoji/reaction not saving** → ensure MySQL tables use **utf8mb4**
- **JWT unauthorized** → check `app.jwt_secret`
