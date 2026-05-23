# SELO (سلو)

SELO is a messenger for shared hosting. It runs on PHP 8.2+ with Laravel for routing and a React + Vite frontend.

## At a Glance
- Private chats, group chats, replies, reactions, message deletion, and media messaging
- Shared-hosting friendly
- Installable from the latest GitHub Release ZIP

## Requirements
- PHP 8.2+
- MySQL 5.7+ or MariaDB 10.2+
- PHP extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `openssl`, `fileinfo`, `gd`
- Recommended PHP settings: `memory_limit=128M`, `upload_max_filesize=25M`, `post_max_size=25M`

## Quick Install
1. Upload the latest GitHub Release ZIP to cPanel and extract it.
2. Make sure your domain points to the `public/` directory if possible.
3. Open `/install` in your browser.
4. Enter your database details and finish the installer.

If your host cannot use `public/` as the document root, keep the app files outside web root and expose only `public/`.

## What the Installer Does
- Checks server requirements
- Connects to the database
- Creates or updates the schema
- Adds required indexes
- Writes `config/config.php`
- Finishes setup without manual steps

## Configuration
Main config file: `config/config.php`

Common values:
- `app.url`
- `app.jwt_secret`
- `uploads.*`
- `logging.level`

## Logs
- App log: `storage/logs/app.log`
- Error log: `storage/logs/error.log`

## Release Build
- Always use the latest GitHub Release for deployment
- Download the release ZIP from the release assets
- Upload and extract that ZIP on cPanel

## Troubleshooting
- `500 error / white page` usually means the wrong PHP version or a hosting filesystem issue
- `Upload failed` usually means `storage/uploads/` is not writable
- `Upload size exceeded` usually means PHP limits or `uploads.*` need to be increased
- `JWT unauthorized` usually means `app.jwt_secret` changed
