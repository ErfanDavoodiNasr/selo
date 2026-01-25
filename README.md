# SELO (سلو) — PHP 7.4 Messenger

## Project Structure
```
app/
  bootstrap.php
  core/
    Auth.php
    Database.php
    RateLimiter.php
    Request.php
    Response.php
    Router.php
    Utils.php
    Validator.php
  controllers/
    AuthController.php
    ConversationController.php
    MessageController.php
    ProfileController.php
    UserController.php
  routes.php
config/
  config.dist.php
  config.php (generated)
  .htaccess
database/
  schema.sql
public/
  .htaccess
  index.php
  photo.php
  install/index.php
  assets/
    app.js
    emoji-picker.js
    style.css
storage/
  .htaccess
  uploads/
    .htaccess
  logs/
```

## Deployment on cPanel (Apache)
1. Upload the project.
2. Point the domain document root to the `public` folder.
   - If you cannot change document root, copy the contents of `public` into `public_html` and keep the rest outside webroot, or ensure `.htaccess` denies access to `app/`, `config/`, `storage/`, `database/`.
3. Create a MySQL database and user in cPanel.
4. Ensure these folders are writable:
   - `config/`
   - `storage/`
   - `storage/uploads/`
5. Visit `https://your-domain.com/install/` and follow the wizard.

## .htaccess (Apache)
- `public/.htaccess` includes rewrite rules and access blocks for sensitive directories.

## Security Notes
- JWT authentication (Authorization: Bearer <token>).
- API calls use Authorization headers (no cookies), so CSRF tokens are not required for API. Installer form uses a CSRF token.
- Uploads stored in `storage/uploads` and served via `public/photo.php`.
- Login rate limiting stored in `login_attempts`.
- Prepared statements for DB access.
- Messages are escaped in the frontend.

## Manual Test Checklist
- Install wizard completes and creates config + tables.
- Register with Gmail-only validation.
- Username normalization (uppercase becomes lowercase).
- Strong password validation.
- Login with username or email.
- User search by username.
- Start conversation, send message with emoji.
- Reply to message and navigate to replied message.
- Delete for me (message hidden only for current user).
- Delete for everyone (message removed for both).
- Upload profile photo and set active.
- Light/Dark theme toggle saved.
- RTL rendering and mixed Persian/English.
- Pagination by scrolling to top loads older messages.
