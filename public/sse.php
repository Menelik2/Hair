<?php
declare(strict_types=1);

/**
 * Native PHP Server-Sent Events (SSE) endpoint.
 * Streams real-time queue updates to Live TV Board, Barber Stations, and Customer Tickets.
 *
 * Usage: new EventSource('/sse.php') or /sse.php?ticket_id=123
 */

// Disable output buffering & time limits for long-lived connection
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    ob_end_flush();
}
set_time_limit(0);
ignore_user_abort(true);

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx
header('Access-Control-Allow-Origin: *');

// Fallback autoloader for pure native structure
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/src/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

use App\Core\Database;
use App\Services\QueueService;

// Optional filter by ticket
$ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : null;
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID'])
    ? (int)$_SERVER['HTTP_LAST_EVENT_ID']
    : (isset($_GET['last_event_id']) ? (int)$_GET['last_event_id'] : 0);

// Send a comment to keep connection alive
echo ": connected\n\n";
flush();

$queueService = new QueueService();
$startTime = time();
$maxLifetime = 300; // 5 minutes max connection lifetime (clients should reconnect)

while (true) {
    if (connection_aborted() || (time() - $startTime) > $maxLifetime) {
        break;
    }

    try {
        // Fetch new events since last ID
        $events = Database::fetchAll(
            "SELECT id, event_type, payload, created_at 
             FROM events 
             WHERE id > ? 
             ORDER BY id ASC 
             LIMIT 20",
            [$lastEventId]
        );

        foreach ($events as $event) {
            $lastEventId = (int)$event['id'];
            $payload = json_decode($event['payload'], true) ?? [];

            // If client is watching a specific ticket, only send relevant events
            if ($ticketId !== null) {
                $eventTicketId = $payload['ticket']['id'] ?? null;
                if ($eventTicketId && (int)$eventTicketId !== $ticketId) {
                    // Still send board-level events
                    if (!in_array($event['event_type'], ['ticket_called', 'queue_paused'], true)) {
                        continue;
                    }
                }
            }

            $data = [
                'type'      => $event['event_type'],
                'payload'   => $payload,
                'timestamp' => $event['created_at'],
            ];

            // For ticket_called we also attach a full board snapshot for TV
            if ($event['event_type'] === 'ticket_called') {
                $data['board'] = $queueService->getLiveBoard();
            }

            echo "id: {$lastEventId}\n";
            echo "event: {$event['event_type']}\n";
            echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        }

        // Heartbeat every ~15s if no events
        if (empty($events)) {
            echo ": heartbeat " . time() . "\n\n";
            flush();
        }

    } catch (Throwable $e) {
        error_log('SSE error: ' . $e->getMessage());
        echo "event: error\n";
        echo "data: " . json_encode(['message' => 'Internal error']) . "\n\n";
        flush();
    }

    // Sleep briefly to avoid tight loop
    usleep(800000); // 0.8 second
}

// Graceful close
echo "event: close\n";
echo "data: {\"reason\":\"timeout\"}\n\n";
flush();
