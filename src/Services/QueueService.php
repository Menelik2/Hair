<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Core queue engine for the barbershop system.
 *
 * - Atomic ticket calling with SELECT ... FOR UPDATE
 * - Intelligent wait-time estimation
 * - Race-condition free operations
 * - Event emission for SSE consumers
 */
final class QueueService
{
    private const BUFFER_MINUTES = 3;
    private const TICKET_PREFIX  = 'A';

    /**
     * Create a new walk-in ticket.
     *
     * @param array{name:string, phone:string, service_ids:int[], stylist_id?:int|null} $data
     */
    public function createTicket(array $data): array
    {
        if (empty($data['name']) || empty($data['phone']) || empty($data['service_ids'])) {
            throw new RuntimeException('Name, phone and at least one service are required.');
        }

        $settings = $this->getSettings();
        if (!empty($settings['is_queue_paused'])) {
            throw new RuntimeException('The walk-in queue is currently paused. Please try again later.');
        }

        // Enforce max queue size
        $maxSize = (int)($settings['max_queue_size'] ?? 50);
        $currentSize = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM tickets WHERE status IN ('waiting','called')"
        );
        if ((int)($currentSize['cnt'] ?? 0) >= $maxSize) {
            throw new RuntimeException('Queue is full. Please try again later.');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $placeholders = implode(',', array_fill(0, count($data['service_ids']), '?'));
            $services = Database::fetchAll(
                "SELECT id, duration_minutes, price_etb FROM services WHERE id IN ($placeholders) AND is_active = 1",
                $data['service_ids']
            );

            if (count($services) !== count(array_unique($data['service_ids']))) {
                throw new RuntimeException('One or more selected services are invalid.');
            }

            $totalPrice    = (float)array_sum(array_column($services, 'price_etb'));
            $totalDuration = (int)array_sum(array_column($services, 'duration_minutes'));
            $stylistId     = !empty($data['stylist_id']) ? (int)$data['stylist_id'] : null;
            $estimatedWait = $this->estimateWaitTime($stylistId, $totalDuration);
            $ticketCode    = $this->generateTicketCode();

            $ticketId = Database::insert(
                "INSERT INTO tickets 
                    (ticket_code, customer_name, customer_phone, stylist_id, status, 
                     total_price_etb, estimated_wait_minutes, joined_at)
                 VALUES (?, ?, ?, ?, 'waiting', ?, ?, NOW())",
                [
                    $ticketCode,
                    trim($data['name']),
                    preg_replace('/\D+/', '', $data['phone']),
                    $stylistId,
                    $totalPrice,
                    $estimatedWait,
                ]
            );

            foreach ($services as $svc) {
                Database::execute(
                    "INSERT INTO ticket_services (ticket_id, service_id, price_etb, duration_minutes)
                     VALUES (?, ?, ?, ?)",
                    [$ticketId, $svc['id'], $svc['price_etb'], $svc['duration_minutes']]
                );
            }

            $ticket = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);

            $this->emitEvent('ticket_created', [
                'ticket'   => $ticket,
                'services' => $services,
            ]);

            $pdo->commit();
            return $ticket;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Atomically call the next ticket for a given stylist.
     * Prefers tickets specifically assigned to this stylist, then falls back to "Any Barber".
     */
    public function callNextTicket(?int $stylistId = null): ?array
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $ticket = null;

            if ($stylistId) {
                // 1) Prefer tickets specifically requested for this stylist
                $ticket = Database::fetch(
                    "SELECT * FROM tickets 
                     WHERE status = 'waiting' AND stylist_id = ?
                     ORDER BY priority DESC, joined_at ASC
                     LIMIT 1
                     FOR UPDATE",
                    [$stylistId]
                );

                // 2) Fall back to "Any Barber" tickets
                if (!$ticket) {
                    $ticket = Database::fetch(
                        "SELECT * FROM tickets 
                         WHERE status = 'waiting' AND stylist_id IS NULL
                         ORDER BY priority DESC, joined_at ASC
                         LIMIT 1
                         FOR UPDATE"
                    );
                }
            } else {
                $ticket = Database::fetch(
                    "SELECT * FROM tickets 
                     WHERE status = 'waiting'
                     ORDER BY priority DESC, joined_at ASC
                     LIMIT 1
                     FOR UPDATE"
                );
            }

            if (!$ticket) {
                $pdo->commit();
                return null;
            }

            $assignStylistId = $ticket['stylist_id'] ?? $stylistId;

            Database::execute(
                "UPDATE tickets 
                 SET status = 'called', 
                     called_at = NOW(),
                     stylist_id = COALESCE(stylist_id, ?)
                 WHERE id = ? AND status = 'waiting'",
                [$assignStylistId, $ticket['id']]
            );

            $updated = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticket['id']]);

            $this->emitEvent('ticket_called', [
                'ticket'     => $updated,
                'stylist_id' => $assignStylistId,
            ]);

            $pdo->commit();
            return $updated;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Start a cut (called → in_chair).
     */
    public function startTicket(int $ticketId, int $stylistId): array
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $ticket = Database::fetch(
                "SELECT * FROM tickets WHERE id = ? AND status = 'called' FOR UPDATE",
                [$ticketId]
            );

            if (!$ticket) {
                throw new RuntimeException('Ticket is not in called state.');
            }

            Database::execute(
                "UPDATE tickets 
                 SET status = 'in_chair', started_at = NOW(), stylist_id = ?
                 WHERE id = ?",
                [$stylistId, $ticketId]
            );

            Database::execute(
                "UPDATE stylists SET status = 'active', is_available = 1 WHERE id = ?",
                [$stylistId]
            );

            $updated = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);

            $this->emitEvent('ticket_started', [
                'ticket'     => $updated,
                'stylist_id' => $stylistId,
            ]);

            $pdo->commit();
            return $updated;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Finish a ticket (in_chair → completed).
     */
    public function completeTicket(int $ticketId): array
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $ticket = Database::fetch(
                "SELECT * FROM tickets WHERE id = ? AND status = 'in_chair' FOR UPDATE",
                [$ticketId]
            );

            if (!$ticket) {
                throw new RuntimeException('Ticket is not currently in chair.');
            }

            Database::execute(
                "UPDATE tickets SET status = 'completed', completed_at = NOW() WHERE id = ?",
                [$ticketId]
            );

            $updated = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);

            $this->emitEvent('ticket_completed', [
                'ticket' => $updated,
            ]);

            $pdo->commit();
            return $updated;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cancel a ticket (only waiting or called).
     */
    public function cancelTicket(int $ticketId, ?string $reason = null): array
    {
        $pdo = Database::getInstance();
        $pdo->beginTransaction();

        try {
            $ticket = Database::fetch(
                "SELECT * FROM tickets WHERE id = ? AND status IN ('waiting','called') FOR UPDATE",
                [$ticketId]
            );

            if (!$ticket) {
                throw new RuntimeException('Ticket cannot be cancelled in its current state.');
            }

            Database::execute(
                "UPDATE tickets SET status = 'cancelled', notes = COALESCE(?, notes) WHERE id = ?",
                [$reason, $ticketId]
            );

            $updated = Database::fetch("SELECT * FROM tickets WHERE id = ?", [$ticketId]);

            $this->emitEvent('ticket_cancelled', [
                'ticket' => $updated,
            ]);

            $pdo->commit();
            return $updated;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Intelligent wait-time estimation (minutes).
     */
    public function estimateWaitTime(?int $stylistId = null, int $ownDuration = 30): int
    {
        $settings = $this->getSettings();
        $buffer   = (int)($settings['buffer_time_minutes'] ?? self::BUFFER_MINUTES);

        // People ahead in relevant queue
        if ($stylistId) {
            $ahead = Database::fetch(
                "SELECT COUNT(*) AS cnt FROM tickets 
                 WHERE status IN ('waiting','called')
                   AND (stylist_id = ? OR stylist_id IS NULL)",
                [$stylistId]
            );
        } else {
            $ahead = Database::fetch(
                "SELECT COUNT(*) AS cnt FROM tickets WHERE status IN ('waiting','called')"
            );
        }
        $peopleAhead = (int)($ahead['cnt'] ?? 0);

        // Active chairs
        $activeChairs = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM stylists WHERE status = 'active' AND is_available = 1"
        );
        $numActive = max(1, (int)($activeChairs['cnt'] ?? 1));

        // Average service duration currently in system
        $avgDuration = Database::fetch(
            "SELECT AVG(ts.duration_minutes) AS avg_dur
             FROM ticket_services ts
             JOIN tickets t ON t.id = ts.ticket_id
             WHERE t.status IN ('waiting','called','in_chair')"
        );
        $avgService = (int)round((float)($avgDuration['avg_dur'] ?? 35));
        if ($avgService < 10) {
            $avgService = 30;
        }

        // Average remaining time on current cuts
        $remaining = Database::fetch(
            "SELECT AVG(
                GREATEST(0, 
                    COALESCE((SELECT SUM(duration_minutes) FROM ticket_services WHERE ticket_id = t.id), 30)
                    - TIMESTAMPDIFF(MINUTE, t.started_at, NOW())
                )
             ) AS avg_remaining
             FROM tickets t
             WHERE t.status = 'in_chair' AND t.started_at IS NOT NULL"
        );
        $avgRemaining = (int)round((float)($remaining['avg_remaining'] ?? 12));

        $wait = (int)ceil(($peopleAhead * $avgService) / $numActive) + $avgRemaining + $buffer;

        return max(0, min(180, $wait));
    }

    /**
     * 1-based position of a ticket in the waiting line.
     */
    public function getPosition(int $ticketId): int
    {
        $ticket = Database::fetch(
            "SELECT id, status, joined_at, stylist_id, priority FROM tickets WHERE id = ?",
            [$ticketId]
        );

        if (!$ticket || !in_array($ticket['status'], ['waiting', 'called'], true)) {
            return 0;
        }

        if ($ticket['stylist_id']) {
            $row = Database::fetch(
                "SELECT COUNT(*) AS cnt FROM tickets
                 WHERE status IN ('waiting','called')
                   AND (stylist_id = ? OR stylist_id IS NULL)
                   AND (
                       priority > ?
                       OR (priority = ? AND joined_at < ?)
                       OR (priority = ? AND joined_at = ? AND id < ?)
                   )",
                [
                    $ticket['stylist_id'],
                    $ticket['priority'],
                    $ticket['priority'], $ticket['joined_at'],
                    $ticket['priority'], $ticket['joined_at'], $ticket['id'],
                ]
            );
        } else {
            $row = Database::fetch(
                "SELECT COUNT(*) AS cnt FROM tickets
                 WHERE status IN ('waiting','called')
                   AND (
                       priority > ?
                       OR (priority = ? AND joined_at < ?)
                       OR (priority = ? AND joined_at = ? AND id < ?)
                   )",
                [
                    $ticket['priority'],
                    $ticket['priority'], $ticket['joined_at'],
                    $ticket['priority'], $ticket['joined_at'], $ticket['id'],
                ]
            );
        }

        return (int)($row['cnt'] ?? 0) + 1;
    }

    /**
     * Full live board snapshot for TV display & SSE.
     */
    public function getLiveBoard(): array
    {
        $nowServing = Database::fetchAll(
            "SELECT t.*, s.chair_number, u.full_name AS stylist_name, u.avatar_url,
                    (SELECT GROUP_CONCAT(sv.name_en SEPARATOR ' + ')
                     FROM ticket_services ts
                     JOIN services sv ON sv.id = ts.service_id
                     WHERE ts.ticket_id = t.id) AS services_en
             FROM tickets t
             LEFT JOIN stylists s ON s.id = t.stylist_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE t.status = 'in_chair'
             ORDER BY s.chair_number ASC"
        );

        $upNext = Database::fetchAll(
            "SELECT t.*,
                    (SELECT GROUP_CONCAT(sv.name_en SEPARATOR ' + ')
                     FROM ticket_services ts
                     JOIN services sv ON sv.id = ts.service_id
                     WHERE ts.ticket_id = t.id) AS services_en
             FROM tickets t
             WHERE t.status IN ('waiting','called')
             ORDER BY t.priority DESC, t.joined_at ASC
             LIMIT 12"
        );

        $chairs = Database::fetchAll(
            "SELECT s.*, u.full_name, u.avatar_url
             FROM stylists s
             JOIN users u ON u.id = s.user_id
             ORDER BY s.chair_number ASC"
        );

        return [
            'now_serving' => $nowServing,
            'up_next'     => $upNext,
            'chairs'      => $chairs,
            'settings'    => $this->getSettings(),
            'timestamp'   => time(),
        ];
    }

    public function getSettings(): array
    {
        return Database::fetch("SELECT * FROM shop_settings WHERE id = 1") ?? [];
    }

    public function setQueuePaused(bool $paused): void
    {
        Database::execute(
            "UPDATE shop_settings SET is_queue_paused = ? WHERE id = 1",
            [$paused ? 1 : 0]
        );
        $this->emitEvent('queue_paused', ['paused' => $paused]);
    }

    private function emitEvent(string $type, array $payload): void
    {
        Database::execute(
            "INSERT INTO events (event_type, payload) VALUES (?, ?)",
            [$type, json_encode($payload, JSON_UNESCAPED_UNICODE)]
        );

        // Keep only the most recent 500 events
        Database::execute(
            "DELETE FROM events WHERE id NOT IN (
                SELECT id FROM (
                    SELECT id FROM events ORDER BY id DESC LIMIT 500
                ) AS keep_ids
            )"
        );
    }

    /**
     * Generate a unique ticket code for today (race-safe via unique constraint + retry).
     */
    private function generateTicketCode(): string
    {
        $todayCount = Database::fetch(
            "SELECT COUNT(*) AS cnt FROM tickets WHERE DATE(joined_at) = CURDATE()"
        );
        $seq = ((int)($todayCount['cnt'] ?? 0)) + 1;

        // Guard against collisions under concurrency
        for ($i = 0; $i < 5; $i++) {
            $code = self::TICKET_PREFIX . '-' . str_pad((string)($seq + $i), 2, '0', STR_PAD_LEFT);
            $exists = Database::fetch("SELECT id FROM tickets WHERE ticket_code = ?", [$code]);
            if (!$exists) {
                return $code;
            }
        }

        // Fallback: use timestamp suffix
        return self::TICKET_PREFIX . '-' . date('His');
    }
}
