# Elite Cuts — Barbershop Queue System

Native PHP 8.2+ Barbershop Queue & Appointment Management.

## Status

- Phase 1: Schema + Database core ✅
- Phase 2: QueueService + I18n + SSE ✅
- Phase 3: Customer Kiosk (queue.php) + Live Ticket (ticket.php) ✅

## Quick Start

```bash
git clone https://github.com/Menelik2/Hair.git
cd Hair
cp .env.example .env
# configure DB
mysql -u root -p -e "CREATE DATABASE hair_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hair_queue < database/schema.sql
cd public && php -S localhost:8080
```

Open http://localhost:8080/queue.php
