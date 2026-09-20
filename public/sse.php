<?php
declare(strict_types=1);

/**
 * Native PHP Server-Sent Events (SSE) endpoint.
 * Streams real-time queue updates to Live TV Board, Barber Stations, and Customer Tickets.
 *
 * Usage: new EventSource('/sse.php') or /sse.php?ticket_id=123
 */

// Long-lived connection setup
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

// Bootstrap application (Database + autoloader)
require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Services\QueueService;

$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;
$lastEventId = 0;

if (!empty($_SERVER['HTTP_LAST_EVENT_ID'])) {
    $lastEventId = (int)$_SERVER['HTTP_LAST_EVENT_ID'];
} elseif (isset($_GET['last_event_id'])) {
    $lastEventId = (int)$_GET['last_event_id'];
}

// Initial connection comment
echo ": connected\n\n";
if (function_exists('flush')) {
    flush();
}

$queueService = new QueueService();
$startTime = time();
$maxLifetime = 280; // ~4.5 min — clients should reconnect

while (true) {
    if (connection_aborted() || (time() - $startTime) > $maxLifetime) {
        break;
    }

    try {
        $events = Database::fetchAll(
            "SELECT id, event_type, payload, created_at 
             FROM events 
             WHERE id > ? 
             ORDER BY id ASC 
             LIMIT 30",
            [$lastEventId]
        );

        foreach ($events as $event) {
            $lastEventId = (int)$event['id'];
            $payload = json_decode($event['payload'] ?? '{}', true) ?? [];

            // Filter for ticket-specific subscribers
            if ($ticketId !== null) {
                $eventTicketId = isset($payload['ticket']['id']) ? (int)$payload['ticket']['id'] : null;
                $isRelevant = ($eventTicketId === $ticketId)
                    || in_array($event['event_type'], ['ticket_called', 'queue_paused'], true);

                if (!$isRelevant) {
                    continue;
                }
            }

            $data = [
                'type'      => $event['event_type'],
                'payload'   => $payload,
                'timestamp' => $event['created_at'],
            ];

            // Attach full board snapshot for TV on ticket_called
            if ($event['event_type'] === 'ticket_called') {
                try {
                    $data['board'] = $queueService->getLiveBoard();
                } catch (Throwable $e) {
                    // Non-fatal
                }
            }

            echo "id: {$lastEventId}\n";
            echo "event: {$event['event_type']}\n";
            echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";

            if (function_exists('flush')) {
                flush();
            }
        }

        // Heartbeat when idle
        if (empty($events)) {
            echo ": heartbeat " . time() . "\n\n";
            if (function_exists('flush')) {
                flush();
            }
        }
    } catch (Throwable $e) {
        error_log('SSE error: ' . $e->getMessage());
        echo "event: error\n";
        echo "data: " . json_encode(['message' => 'Internal error']) . "\n\n";
        if (function_exists('flush')) {
            flush();
        }
        usleep(500000);
    }

    usleep(700000); // ~0.7s polling interval
}

// Graceful close
echo "event: close\n";
echo "data: {\"reason\":\"timeout\"}\n\n";
if (function_exists('flush')) {
    flush();
}
