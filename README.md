# Elite Cuts — Barbershop Queue & Appointment Management

Production-ready **PHP 8.2+** system with native iOS-style mobile UI and PWA support.

## Features

| Screen | Path |
|--------|------|
| Customer Kiosk | `/queue.php` |
| Live Digital Ticket | `/ticket.php?code=A-01` |
| Appointments | `/appointments.php` |
| Profile | `/profile.php` |
| Barber Station | `/stylist/station.php?stylist_id=1` |
| Live TV Board | `/live-board.php` |
| Admin Dashboard | `/admin/dashboard.php` |
| Staff Login | `/login.php` |

## Mobile / PWA

- iOS-style bottom tab bar (Queue · Book · Profile)
- SF system fonts, glass nav, safe areas
- **Add to Home Screen** (manifest + service worker)
- Icons: `/assets/icons/icon-192.png`, `icon-512.png`

## Stack

- PHP 8.2+ (strict), PDO, MySQL
- Tailwind + Alpine.js + Chart.js
- SSE real-time updates
- Auth (Argon2id + RBAC)
- Bilingual EN / Amharic

## Quick Start

```bash
git clone https://github.com/Menelik2/Hair.git
cd Hair
cp .env.example .env
# set DB credentials

mysql -u root -p -e "CREATE DATABASE hair_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hair_queue < database/schema.sql

cd public && php -S localhost:8080
```

## Demo login

Phone: `+251911000001`  
Password: `password`

## License

Private — All rights reserved.
