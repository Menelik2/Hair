# Elite Cuts — Barbershop Queue & Appointment Management System

Production-ready, high-fidelity Barbershop Queue system built with **native PHP 8.2+** and MySQL 8.0+.

Designed for premium barbershops with:
- Live TV Waiting Lounge Display
- Barber Chair Station (mobile-first)
- Customer Self-Service Kiosk + Digital Ticket
- Admin Executive Dashboard

## Tech Stack

- **Backend**: Native PHP 8.2+ (strict types, modern features)
- **Database**: MySQL 8.0+ / MariaDB 10.6+ (PostgreSQL compatible schema)
- **Frontend**: Tailwind CSS (CDN), Alpine.js, Lucide Icons, Chart.js, Web Audio API
- **Real-time**: Server-Sent Events (SSE)
- **i18n**: English + Amharic

## Project Structure

```
hair-queue/
├── config/
│   └── database.php
├── database/
│   └── schema.sql          # Full DDL + seed data
├── public/                 # Web root
│   ├── sse.php
│   ├── queue.php           # (Phase 3)
│   ├── ticket.php          # (Phase 3)
│   ├── live-board.php      # (Phase 5)
│   └── stylist/
│       └── station.php     # (Phase 4)
├── src/
│   ├── bootstrap.php
│   ├── Core/
│   │   ├── Database.php
│   │   └── I18n.php
│   └── Services/
│       └── QueueService.php
├── assets/
│   └── js/
│       └── audio.js        # (Phase 3+)
├── storage/logs/
├── .env.example
└── README.md
```

## Quick Start

1. **Clone & configure**
   ```bash
   git clone https://github.com/Menelik2/Hair.git
   cd Hair
   cp .env.example .env
   # Edit .env with your DB credentials
   ```

2. **Create database & import schema**
   ```bash
   mysql -u root -p -e "CREATE DATABASE hair_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p hair_queue < database/schema.sql
   ```

3. **Point web server to `/public`**
   - Apache / Nginx document root → `public/`
   - Or use PHP built-in server for development:
     ```bash
     cd public
     php -S localhost:8080
     ```

4. **Default credentials**
   - Admin: phone `+251911000001` / password `password`
   - Stylists: same password pattern

## Phase Status

| Phase | Description                              | Status     |
|-------|------------------------------------------|------------|
| 1     | SQL Schema + Database.php                | ✅ Done    |
| 2     | QueueService, I18n, SSE                  | ✅ Done    |
| 3     | Customer Flow (queue.php + ticket.php)   | Next       |
| 4     | Barber Station (station.php)             | Pending    |
| 5     | Live TV Board + Admin Dashboard          | Pending    |

## Key Features Implemented (Phase 1–2)

- Complete relational schema with seed data (4 chairs, 10 services, sample tickets)
- Atomic ticket calling with `SELECT ... FOR UPDATE`
- Smart wait-time estimation algorithm
- Bilingual (EN / AM) support with cookie + session persistence
- Native Server-Sent Events stream for real-time updates
- Production PDO wrapper with transaction helpers

## License

Private — All rights reserved.
