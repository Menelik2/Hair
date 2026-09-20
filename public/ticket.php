<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;
use App\Services\QueueService;

$code = trim($_GET['code'] ?? '');
if ($code === '') {
    header('Location: /queue.php');
    exit;
}

$queueService = new QueueService();
$ticket = Database::fetch(
    "SELECT t.*, 
            s.chair_number,
            u.full_name AS stylist_name
     FROM tickets t
     LEFT JOIN stylists s ON s.id = t.stylist_id
     LEFT JOIN users u ON u.id = s.user_id
     WHERE t.ticket_code = ?",
    [$code]
);

if (!$ticket) {
    http_response_code(404);
    echo 'Ticket not found.';
    exit;
}

// Services for this ticket
$services = Database::fetchAll(
    "SELECT sv.name_en, sv.name_am, ts.price_etb, ts.duration_minutes
     FROM ticket_services ts
     JOIN services sv ON sv.id = ts.service_id
     WHERE ts.ticket_id = ?",
    [$ticket['id']]
);

$position = $queueService->getPosition((int)$ticket['id']);
$settings = $queueService->getSettings();
$lang = I18n::getLocale();
$t = fn(string $key, array $r = []) => I18n::t($key, $r);

// Handle cancel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    header('Content-Type: application/json');
    try {
        $queueService->cancelTicket((int)$ticket['id'], 'Cancelled by customer');
        echo json_encode(['success' => true]);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$statusColors = [
    'waiting'   => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'dot' => 'bg-amber-500', 'label' => $t('status.waiting')],
    'called'    => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => $t('status.called')],
    'in_chair'  => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => $t('status.in_chair')],
    'completed' => ['bg' => 'bg-zinc-100', 'text' => 'text-zinc-600', 'dot' => 'bg-zinc-400', 'label' => $t('status.completed')],
    'cancelled' => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'dot' => 'bg-rose-500', 'label' => $t('status.cancelled')],
];
$st = $statusColors[$ticket['status']] ?? $statusColors['waiting'];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Ticket <?= htmlspecialchars($ticket['ticket_code']) ?> — <?= htmlspecialchars($settings['shop_name_en'] ?? 'Elite Cuts') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['\"Plus Jakarta Sans\"', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .ticket-card {
            background: linear-gradient(180deg, #ffffff 0%, #fafafa 100%);
        }
        .tear-line {
            background-image: linear-gradient(90deg, #e4e4e7 50%, transparent 50%);
            background-size: 12px 1px;
            background-repeat: repeat-x;
            height: 1px;
        }
        .radial-progress {
            transition: stroke-dashoffset 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.7; }
            50% { transform: scale(1.05); opacity: 0.3; }
            100% { transform: scale(0.95); opacity: 0.7; }
        }
        .pulse-ring { animation: pulse-ring 2s ease-in-out infinite; }
    </style>
</head>
<body class="h-full bg-zinc-100 text-zinc-900 antialiased font-sans"
      x-data="ticketApp()" x-init="init()" x-cloak>

    <!-- Top Bar -->
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-zinc-200/80">
        <div class="max-w-md mx-auto px-4 h-14 flex items-center justify-between">
            <a href="/queue.php" class="flex items-center gap-2 text-zinc-500 hover:text-zinc-900 transition-colors">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span class="text-sm font-medium">Back</span>
            </a>
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-lg bg-zinc-950 flex items-center justify-center">
                    <i data-lucide="scissors" class="w-3.5 h-3.5 text-amber-400"></i>
                </div>
                <span class="font-bold text-sm"><?= htmlspecialchars($settings['shop_name_en'] ?? 'Elite Cuts') ?></span>
            </div>
            <button @click="toggleLang()" class="px-2 py-1 rounded-full text-xs font-semibold bg-zinc-100 hover:bg-zinc-200">
                <span x-text="lang === 'en' ? 'አማ' : 'EN'"></span>
            </button>
        </div>
    </header>

    <main class="max-w-md mx-auto px-4 py-6">

        <!-- Status Banner (Called / In Chair) -->
        <div x-show="status === 'called' || status === 'in_chair'" 
             x-transition
             class="mb-5 p-4 rounded-2xl bg-emerald-500 text-white text-center shadow-lg shadow-emerald-500/25">
            <div class="flex items-center justify-center gap-2 mb-1">
                <span class="relative flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-white opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-white"></span>
                </span>
                <span class="font-bold text-sm uppercase tracking-wide" x-text="status === 'called' ? 'It\'s your turn!' : 'You\'re in the chair'"></span>
            </div>
            <p class="text-emerald-100 text-sm" x-text="status === 'called' ? 'Please proceed to Chair ' + (chairNumber || '—') : 'Enjoy your cut'"></p>
        </div>

        <!-- Boarding Pass Style Ticket -->
        <div class="ticket-card rounded-3xl border border-zinc-200/80 shadow-xl overflow-hidden">
            
            <!-- Top Section -->
            <div class="px-6 pt-6 pb-5">
                <div class="flex items-start justify-between mb-5">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-400 mb-1">Ticket</p>
                        <h1 class="text-4xl font-extrabold tracking-tight text-zinc-950" x-text="ticketCode"><?= htmlspecialchars($ticket['ticket_code']) ?></h1>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $st['bg'] ?> <?= $st['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $st['dot'] ?>" :class="status === 'called' || status === 'in_chair' ? 'animate-pulse' : ''"></span>
                            <span x-text="statusLabel"><?= $st['label'] ?></span>
                        </span>
                    </div>
                </div>

                <!-- Radial Progress + Position -->
                <div class="flex items-center gap-5" x-show="status === 'waiting' || status === 'called'">
                    <div class="relative w-24 h-24 flex-shrink-0">
                        <svg class="w-24 h-24 -rotate-90" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="42" fill="none" stroke="#e4e4e7" stroke-width="8"/>
                            <circle cx="50" cy="50" r="42" fill="none" stroke="#f59e0b" stroke-width="8"
                                    stroke-linecap="round"
                                    class="radial-progress"
                                    :stroke-dasharray="264"
                                    :stroke-dashoffset="264 - (264 * progressPercent / 100)"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-extrabold tracking-tight" x-text="position > 0 ? position : '—'"><?= $position ?: '—' ?></span>
                            <span class="text-[10px] font-semibold uppercase text-zinc-400 tracking-wide">in line</span>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-zinc-500 mb-0.5"><?= $t('ticket.you_are') ?></p>
                        <p class="text-xl font-bold tracking-tight">
                            <span x-text="position > 0 ? position + getOrdinal(position) : '—'"><?= $position ? $position . ($position == 1 ? 'st' : ($position == 2 ? 'nd' : ($position == 3 ? 'rd' : 'th'))) : '—' ?></span>
                            <span class="text-zinc-400 font-medium text-base"><?= $t('ticket.in_line') ?></span>
                        </p>
                        <div class="mt-2 flex items-center gap-1.5 text-sm">
                            <i data-lucide="clock" class="w-3.5 h-3.5 text-zinc-400"></i>
                            <span class="text-zinc-500"><?= $t('ticket.estimated_wait') ?>:</span>
                            <span class="font-bold text-zinc-900" x-text="estWait + ' min'"><?= (int)($ticket['estimated_wait_minutes'] ?? 0) ?> min</span>
                        </div>
                    </div>
                </div>

                <!-- Completed / Cancelled state -->
                <div x-show="status === 'completed' || status === 'cancelled'" class="text-center py-4">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center"
                         :class="status === 'completed' ? 'bg-emerald-100' : 'bg-rose-100'">
                        <i :data-lucide="status === 'completed' ? 'check' : 'x'" class="w-8 h-8"
                           :class="status === 'completed' ? 'text-emerald-600' : 'text-rose-600'"></i>
                    </div>
                    <p class="font-semibold" x-text="status === 'completed' ? 'Service completed. Thank you!' : 'Ticket cancelled'"></p>
                </div>
            </div>

            <!-- Tear Line -->
            <div class="relative px-2">
                <div class="tear-line w-full"></div>
                <div class="absolute -left-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-zinc-100"></div>
                <div class="absolute -right-3 top-1/2 -translate-y-1/2 w-6 h-6 rounded-full bg-zinc-100"></div>
            </div>

            <!-- Bottom Section: Details -->
            <div class="px-6 py-5 space-y-4">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-0.5">Customer</p>
                        <p class="font-semibold"><?= htmlspecialchars($ticket['customer_name']) ?></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-0.5">Phone</p>
                        <p class="font-semibold"><?= htmlspecialchars($ticket['customer_phone']) ?></p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-0.5">Barber</p>
                        <p class="font-semibold" x-text="stylistName || 'Any Barber'">
                            <?= htmlspecialchars($ticket['stylist_name'] ?? ($lang === 'am' ? 'ማንኛውም ባርበር' : 'Any Barber')) ?>
                        </p>
                    </div>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-0.5">Chair</p>
                        <p class="font-semibold" x-text="chairNumber ? 'Chair ' + chairNumber : '—'">
                            <?= $ticket['chair_number'] ? 'Chair ' . (int)$ticket['chair_number'] : '—' ?>
                        </p>
                    </div>
                </div>

                <!-- Services -->
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-2">Services</p>
                    <div class="space-y-1.5">
                        <?php foreach ($services as $svc): ?>
                        <div class="flex justify-between text-sm">
                            <span><?= htmlspecialchars($lang === 'am' ? $svc['name_am'] : $svc['name_en']) ?></span>
                            <span class="font-medium text-zinc-600"><?= number_format((float)$svc['price_etb'], 0) ?> ETB</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="border-t border-zinc-100 mt-2 pt-2 flex justify-between">
                        <span class="font-semibold">Total</span>
                        <span class="font-extrabold text-lg"><?= number_format((float)$ticket['total_price_etb'], 0) ?> ETB</span>
                    </div>
                </div>

                <!-- QR Code Placeholder -->
                <div class="flex flex-col items-center pt-2">
                    <div class="w-32 h-32 rounded-xl bg-white border border-zinc-200 flex items-center justify-center p-2 shadow-sm">
                        <div class="w-full h-full bg-zinc-950 rounded-lg flex items-center justify-center">
                            <div class="text-center">
                                <i data-lucide="qr-code" class="w-10 h-10 text-white mx-auto mb-1"></i>
                                <p class="text-[9px] text-zinc-400 font-mono tracking-wider"><?= htmlspecialchars($ticket['ticket_code']) ?></p>
                            </div>
                        </div>
                    </div>
                    <p class="text-[11px] text-zinc-400 mt-2">Show this at the shop for check-in</p>
                </div>
            </div>
        </div>

        <!-- Cancel Button -->
        <div class="mt-6" x-show="status === 'waiting' || status === 'called'">
            <button @click="showCancel = true"
                    class="w-full py-3.5 rounded-xl border border-zinc-200 bg-white text-sm font-semibold text-zinc-600 hover:bg-zinc-50 hover:text-rose-600 hover:border-rose-200 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                <i data-lucide="x-circle" class="w-4 h-4"></i>
                <?= $t('ticket.cancel') ?>
            </button>
        </div>

        <!-- Live connection indicator -->
        <div class="mt-8 flex items-center justify-center gap-2 text-xs text-zinc-400">
            <span class="w-1.5 h-1.5 rounded-full" :class="connected ? 'bg-emerald-500' : 'bg-zinc-300'"></span>
            <span x-text="connected ? 'Live updates active' : 'Connecting...'"></span>
        </div>
    </main>

    <!-- Cancel Confirmation Modal -->
    <div x-show="showCancel" x-transition.opacity
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
         @click.self="showCancel = false">
        <div x-show="showCancel" x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="w-full max-w-sm bg-white rounded-2xl shadow-2xl p-6">
            <div class="text-center">
                <div class="w-12 h-12 rounded-full bg-rose-100 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="alert-triangle" class="w-6 h-6 text-rose-600"></i>
                </div>
                <h3 class="text-lg font-bold mb-1">Cancel your ticket?</h3>
                <p class="text-sm text-zinc-500 mb-6">This cannot be undone. You will lose your place in line.</p>
                <div class="flex gap-3">
                    <button @click="showCancel = false"
                            class="flex-1 py-3 rounded-xl border border-zinc-200 text-sm font-semibold hover:bg-zinc-50 transition-colors">
                        Keep Ticket
                    </button>
                    <button @click="cancelTicket()"
                            :disabled="cancelling"
                            class="flex-1 py-3 rounded-xl bg-rose-500 text-white text-sm font-semibold hover:bg-rose-600 active:scale-95 transition-all disabled:opacity-50">
                        <span x-text="cancelling ? 'Cancelling...' : 'Yes, Cancel'"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/audio.js"></script>
    <script>
        function ticketApp() {
            return {
                ticketCode: '<?= htmlspecialchars($ticket['ticket_code']) ?>',
                ticketId: <?= (int)$ticket['id'] ?>,
                status: '<?= htmlspecialchars($ticket['status']) ?>',
                statusLabel: '<?= $st['label'] ?>',
                position: <?= (int)$position ?>,
                estWait: <?= (int)($ticket['estimated_wait_minutes'] ?? 0) ?>,
                chairNumber: <?= $ticket['chair_number'] ? (int)$ticket['chair_number'] : 'null' ?>,
                stylistName: <?= json_encode($ticket['stylist_name'] ?? null) ?>,
                progressPercent: <?= max(5, min(95, 100 - ($position * 12))) ?>,
                connected: false,
                showCancel: false,
                cancelling: false,
                lang: '<?= $lang ?>',
                eventSource: null,

                init() {
                    this.connectSSE();
                    lucide.createIcons();
                },

                connectSSE() {
                    if (this.eventSource) this.eventSource.close();

                    this.eventSource = new EventSource('/sse.php?ticket_id=' + this.ticketId);

                    this.eventSource.onopen = () => {
                        this.connected = true;
                    };

                    this.eventSource.onerror = () => {
                        this.connected = false;
                        setTimeout(() => this.connectSSE(), 3000);
                    };

                    this.eventSource.onmessage = (e) => {
                        try {
                            const data = JSON.parse(e.data);
                            this.handleEvent(data);
                        } catch {}
                    };

                    ['ticket_called', 'ticket_started', 'ticket_completed', 'ticket_cancelled', 'ticket_created'].forEach(evt => {
                        this.eventSource.addEventListener(evt, (e) => {
                            try {
                                const data = JSON.parse(e.data);
                                this.handleEvent(data);
                            } catch {}
                        });
                    });
                },

                handleEvent(data) {
                    const type = data.type || data.event;
                    const payload = data.payload || {};

                    if (payload.ticket && payload.ticket.id == this.ticketId) {
                        const t = payload.ticket;
                        this.status = t.status;
                        this.chairNumber = t.chair_number || this.chairNumber;
                        this.stylistName = t.stylist_name || this.stylistName;

                        if (type === 'ticket_called') {
                            EliteAudio.ticketCalled();
                            this.statusLabel = '<?= $t('status.called') ?>';
                            if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                        } else if (type === 'ticket_started') {
                            this.statusLabel = '<?= $t('status.in_chair') ?>';
                        } else if (type === 'ticket_completed') {
                            this.statusLabel = '<?= $t('status.completed') ?>';
                            EliteAudio.success();
                        } else if (type === 'ticket_cancelled') {
                            this.statusLabel = '<?= $t('status.cancelled') ?>';
                        }
                    }

                    if (data.board && data.board.up_next) {
                        const idx = data.board.up_next.findIndex(t => t.id == this.ticketId);
                        if (idx >= 0) {
                            this.position = idx + 1;
                            this.progressPercent = Math.max(5, Math.min(95, 100 - (this.position * 12)));
                        }
                    }
                },

                getOrdinal(n) {
                    const s = ['th', 'st', 'nd', 'rd'];
                    const v = n % 100;
                    return s[(v - 20) % 10] || s[v] || s[0];
                },

                async cancelTicket() {
                    this.cancelling = true;
                    EliteAudio.click();
                    try {
                        const form = new FormData();
                        form.append('action', 'cancel');
                        const res = await fetch(window.location.href, { method: 'POST', body: form });
                        const data = await res.json();
                        if (data.success) {
                            this.status = 'cancelled';
                            this.statusLabel = '<?= $t('status.cancelled') ?>';
                            this.showCancel = false;
                            EliteAudio.error();
                        } else {
                            alert(data.message || 'Could not cancel');
                        }
                    } catch {
                        alert('Network error');
                    }
                    this.cancelling = false;
                },

                toggleLang() {
                    const next = this.lang === 'en' ? 'am' : 'en';
                    window.location.href = '?code=<?= urlencode($code) ?>&lang=' + next;
                }
            }
        }
    </script>
</body>
</html>
