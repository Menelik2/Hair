# Elite Cuts — Barbershop Queue & Appointment Management System

Production-ready, high-fidelity Barbershop Queue system built with **native PHP 8.2+** and MySQL 8.0+.

## Tech Stack
- Backend: Native PHP 8.2+
- Database: MySQL 8.0+
- Frontend: Tailwind CSS, Alpine.js, Lucide, Web Audio API
- Real-time: Server-Sent Events
- i18n: English + Amharic

## Quick Start
```bash
git clone https://github.com/Menelik2/Hair.git
cd Hair
cp .env.example .env
# edit DB credentials
mysql -u root -p -e "CREATE DATABASE hair_queue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p hair_queue < database/schema.sql
cd public && php -S localhost:8080
```
Open http://localhost:8080/queue.php

## Phase Status
| Phase | Description | Status |
|-------|-------------|--------|
| 1 | SQL Schema + Database.php | ✅ |
| 2 | QueueService, I18n, SSE | ✅ |
| 3 | Customer Flow (queue.php + ticket.php) | ✅ |
| 4 | Barber Station (station.php) | In Progress |
| 5 | Live TV Board + Admin Dashboard | Pending |

## License
Private
