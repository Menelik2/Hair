# Deploy Elite Cuts on AeonFree

Step-by-step guide to host this app on **AeonFree** free hosting (PHP 8.2 + MySQL 8).

---

## 1. Create hosting account

1. Sign up / log in at [https://aeonfree.com](https://aeonfree.com)
2. Create a new **Hosting Account**
3. Choose a free subdomain (`.hstn.me`, `.zya.me`, or `.iceiy.com`) or your own domain
4. Wait until status is **Active**

---

## 2. Create MySQL database

1. Open **Control Panel** → **MySQL Databases**
2. Create a database (e.g. `hair_queue`)
3. Create a user and grant it **all privileges** on that database
4. Note: host (usually `localhost`), database name, username, password

---

## 3. Set PHP version

1. Go to **More Account Settings** → **PHP Settings**
2. Select **PHP 8.2**
3. Recommended options:
   - `memory_limit` = `256M`
   - `max_execution_time` = `120`
   - `upload_max_filesize` = `16M`
   - `post_max_size` = `20M`
   - `display_errors` = `Off`

---

## 4. Upload files (recommended structure)

AeonFree web root is usually `htdocs`.

**Option A – Clean (preferred if you can put files outside htdocs)**

```
/home/youruser/
├── htdocs/          ← only the contents of /public go here
│   ├── index.php
│   ├── queue.php
│   ├── .htaccess
│   ├── assets/
│   └── ...
├── src/
├── config/
├── database/
├── storage/
│   └── logs/
└── .env
```

Then every `require_once dirname(__DIR__) . '/src/bootstrap.php'` still works because `__DIR__` of PHP files in `htdocs` points to `htdocs`, and `dirname(__DIR__)` is the parent folder.

**Option B – Everything inside htdocs (simplest)**

1. Upload the whole project into `htdocs` so you have:
   ```
   htdocs/
   ├── public/
   ├── src/
   ├── config/
   ├── database/
   ├── storage/
   └── .env
   ```
2. Create (or edit) `htdocs/.htaccess`:
   ```apache
   RewriteEngine On
   RewriteRule ^(.*)$ public/$1 [L]
   ```
   Or move everything from `public/` up into `htdocs/` and keep `src/`, `config/`, etc. as siblings of the PHP files.

**Recommended for most users (Option B simplified):**

1. Download the repo ZIP from GitHub
2. Extract
3. Using File Manager or FileZilla:
   - Upload **all files and folders from `public/`** into `htdocs/`
   - Upload folders `src/`, `config/`, `database/` into the **parent** of `htdocs` (if the panel allows) **OR** into `htdocs/` itself
4. If `src` ends up inside `htdocs`, the paths still work only if you keep the relative structure (`htdocs/src`, `htdocs/config` next to the PHP files that call `dirname(__DIR__)`).

**Simplest working layout for AeonFree File Manager:**

```
htdocs/
├── index.php          ← from public/
├── queue.php
├── ticket.php
├── login.php
├── ... (all public PHP + assets)
├── .htaccess
├── src/               ← from project root
├── config/
├── database/
├── storage/
│   └── logs/
└── .env
```

With this layout, `dirname(__DIR__)` from a file in `htdocs` correctly points to `htdocs` itself, so `htdocs/src/bootstrap.php` is found.

---

## 5. Create `.env`

In the same folder as `src/` and `config/` create `.env`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=your_real_database_name
DB_USER=your_real_database_user
DB_PASS=your_real_database_password
APP_ENV=production
```

---

## 6. Import database

1. Open **phpMyAdmin** from the Control Panel
2. Select your database
3. Import → choose `database/schema.sql` → Go

This creates tables + demo data (admin login ready).

---

## 7. Create storage folder

Make sure this folder exists and is writable:

```
storage/logs/
```

(The app will try to create it automatically, but creating it manually is safer.)

---

## 8. Enable free SSL

Use the SSL section in the AeonFree dashboard (or ZeroSSL / Cloudflare method they document).

---

## 9. Test

1. Visit your subdomain
2. You should see the portal page
3. Demo login:
   - Phone: `+251911000001`
   - Password: `password`

---

## Troubleshooting

| Problem | Solution |
|---------|----------|
| Blank page / 500 | Check `storage/logs/php-error.log` and PHP error log in panel |
| Database connection failed | Double-check `.env` host/name/user/pass. Host is usually `localhost` |
| CSS/JS not loading | Confirm assets were uploaded under `/assets/` |
| SSE not updating | Normal on free hosting after ~4–5 min; clients reconnect automatically |
| Argon2 / password issues | App now falls back to bcrypt automatically |

---

## Quick checklist

- [ ] PHP 8.2 selected
- [ ] MySQL database created + schema imported
- [ ] `.env` filled with real credentials
- [ ] Files in correct structure under `htdocs`
- [ ] `storage/logs` exists
- [ ] SSL enabled
- [ ] Demo login works
