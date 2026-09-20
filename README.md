# Elite Cuts — Barbershop Queue & Appointment Management System

Production-ready native **PHP 8.2+** system for premium barbershops.

## Live URLs

| Feature | Path |
|---------|------|
| Customer Kiosk | `/queue.php` |
| Live Digital Ticket | `/ticket.php?code=A-01` |
| Book Appointment | `/appointments.php` |
| Customer Profile | `/profile.php` |
| Barber Station | `/stylist/station.php?stylist_id=1` |
| Live TV Board | `/live-board.php` |
| Admin Dashboard | `/admin/dashboard.php` |
| Staff Login | `/login.php` |

## Tech Stack

- PHP 8.2+ (strict types, PDO)
- MySQL 8.0+
- Tailwind CSS + Alpine.js + Lucide + Chart.js
- Server-Sent Events (real-time)
- Web Audio API (tactile sounds)
- Bilingual: English + Amharic
- Auth: Argon2id + RBAC (admin / stylist / customer)

## Quick Start

```bash
git clone https://github.com/Menelik2/Hair.git
cd Hair
cp .env.example .env
# Edit DB credentials in .env

mysql -u root -p -e "CREATE DATABASE hair_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hair_queue < database/schema.sql

cd public
php -S localhost:8080
```

## Demo Credentials

- **Admin / Stylist:** phone `+251911000001` / password `password`

## Project Structure

```
├── config/database.php
├── database/schema.sql
├── public/
│   ├── queue.php
│   ├── ticket.php
│   ├── appointments.php
│   ├── profile.php
│   ├── live-board.php
│   ├── login.php
│   ├── admin/dashboard.php
│   ├── stylist/station.php
│   ├── sse.php
│   └── assets/js/audio.js
├── src/
│   ├── bootstrap.php
│   ├── Core/
│   │   ├── Auth.php
│   │   ├── Database.php
│   │   └── I18n.php
│   └── Services/
│       ├── QueueService.php
│       └── AnalyticsService.php
└── .env.example
```

## License

Private — All rights reserved.
