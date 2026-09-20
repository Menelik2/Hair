# Elite Cuts — Barbershop Queue & Appointment Management System

Production-ready, high-fidelity Barbershop Queue system built with **native PHP 8.2+** and MySQL 8.0+.

Designed for premium barbershops with:
- Live TV Waiting Lounge Display
- Barber Chair Station (mobile-first)
- Customer Self-Service Kiosk + Digital Ticket
- Admin Executive Dashboard

## Tech Stack

- **Backend**: Native PHP 8.2+ (strict types, modern features)
- **Database**: MySQL 8.0+ / MariaDB 10.6+
- **Frontend**: Tailwind CSS (CDN), Alpine.js, Lucide Icons, Web Audio API
- **Real-time**: Server-Sent Events (SSE)
- **i18n**: English + Amharic

## Quick Start

1. Clone the repo
   ```bash
   git clone https://github.com/Menelik2/Hair.git
   cd Hair
   cp .env.example .env
   # Edit DB credentials
   ```

2. Create database & import schema
   ```bash
   mysql -u root -p -e "CREATE DATABASE hair_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p hair_queue < database/schema.sql
   ```

3. Point web server document root to `/public`
   ```bash
   cd public
   php -S localhost:8080
   ```

4. Open http://localhost:8080/queue.php

## Phase Status

| Phase | Description                              | Status     |
|-------|------------------------------------------|------------|
| 1     | SQL Schema + Database.php                | ✅ Done    |
| 2     | QueueService, I18n, SSE                  | ✅ Done    |
| 3     | Customer Flow (queue.php + ticket.php)   | ✅ Done    |
| 4     | Barber Station (station.php)             | Next       |
| 5     | Live TV Board + Admin Dashboard          | Pending    |

## Phase 3 Highlights

### `/queue.php` — Self-Service Kiosk
- 3-step wizard (Services → Barber → Details)
- Multi-select service cards with live price & duration calculation
- Sticky summary bar
- Stylist carousel with ratings & availability
- Phone auto-formatting (+251)
- EN / አማ language toggle
- Tactile Web Audio feedback on every interaction

### `/ticket.php` — Live Digital Pass
- Boarding-pass style card with tear-line design
- Animated SVG radial progress ring
- Real-time position & status via SSE
- "It's your turn" banner with vibration + chime
- Cancel ticket with confirmation modal
- QR-style check-in code

## License

Private — All rights reserved.
