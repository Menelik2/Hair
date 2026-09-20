# Elite Cuts — Barbershop Queue & Appointment Management System

Production-ready native PHP 8.2+ system for premium barbershops.

## Features

- **Customer Kiosk** (`/queue.php`) — Multi-step service picker, stylist selection, live digital ticket
- **Live Digital Ticket** (`/ticket.php`) — Boarding-pass UI, SSE position updates, cancel
- **Barber Station** (`/stylist/station.php`) — Mobile-first chair app with timer, Call Next, Walk-in
- **Live TV Board** (`/live-board.php`) — Fullscreen 16:9 dark display, now serving + up next, audio chime
- **Admin Dashboard** (`/admin/dashboard.php`) — KPIs, charts, queue table, staff controls, pause queue

## Tech
- PHP 8.2+ (strict), MySQL 8, Tailwind, Alpine.js, Lucide, Chart.js, Web Audio API, SSE
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

## URLs
| Page | URL |
|------|-----|
| Customer Kiosk | http://localhost:8080/queue.php |
| Live Ticket | http://localhost:8080/ticket.php?code=A-01 |
| Barber Station | http://localhost:8080/stylist/station.php?stylist_id=1 |
| Live TV Board | http://localhost:8080/live-board.php |
| Admin Dashboard | http://localhost:8080/admin/dashboard.php |

## Phase Status
All 5 phases complete ✅

## License
Private
