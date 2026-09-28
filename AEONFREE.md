# Deploy Elite Cuts on AeonFree

## 1. Create account
1. Sign up: https://aeonfree.com
2. Verify email → Dashboard → **+ New Account**
3. Pick free subdomain (`.zya.me` / `.hstn.me` / `.iceiy.com`)
4. Set control-panel password

## 2. Create MySQL database
1. Open **Control Panel** → **MySQL Databases**
2. Create database + user (note host, name, user, password)
3. Open **phpMyAdmin** → Import `database/schema.sql`
4. Optional: run `database/optimize_indexes.sql`

## 3. Upload files
Zip the project → File Manager → `htdocs`/`public_html` → Upload → Extract.
Ensure `index.php`, `public/`, `src/`, `config/` are at web root.

## 4. Configure database
Create `.env` next to `index.php`:

```
DB_HOST=sqlXXX.aeonfree.com
DB_PORT=3306
DB_NAME=your_db
DB_USER=your_user
DB_PASS=your_password
```

## 5. PHP version
Select **PHP 8.0+** (8.1 or 8.2 preferred).

## 6. Test
- Portal: `https://yoursite.zya.me/`
- Admin: `+251911000001` / `password`
- Stylist: `+251911000002` / `password`
