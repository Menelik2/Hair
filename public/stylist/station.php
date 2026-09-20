<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;
use App\Services\QueueService;

$queueService = new QueueService();

// Simple stylist identification via query param (production would use auth)
$stylistId = isset($_GET['stylist_id']) ? (int)$_GET['stylist_id'] : 1;

$stylist = Database::fetch(
    "SELECT s.*, u.full_name, u.avatar_url, u.phone
     FROM stylists s
     JOIN users u ON u.id = s.user_id
     WHERE s.id = ?",
    [$stylistId]
);

if (!$stylist) {
    http_response_code(404);
    echo 'Stylist not found. Use ?stylist_id=1';
    exit;
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'set_status':
                $status = $input['status'] ?? 'active';
                if (!in_array($status, ['active', 'break', 'offline'], true)) {
                    throw new RuntimeException('Invalid status');
                }
                Database::execute(
                    "UPDATE stylists SET status = ?, is_available = ? WHERE id = ?",
                    [$status, $status === 'active' ? 1 : 0, $stylistId]
                );
                echo json_encode(['success' => true, 'status' => $status]);
                break;

            case 'call_next':
                $ticket = $queueService->callNextTicket($stylistId);
                if (!$ticket) {
                    echo json_encode(['success' => false, 'message' => 'No customers waiting']);
                } else {
                    echo json_encode(['success' => true, 'ticket' => $ticket]);
                }
                break;

            case 'start_ticket':
                $ticketId = (int)($input['ticket_id'] ?? 0);
                $ticket = $queueService->startTicket($ticketId, $stylistId);
                echo json_encode(['success' => true, 'ticket' => $ticket]);
                break;

            case 'finish_ticket':
                $ticketId = (int)($input['ticket_id'] ?? 0);
                $ticket = $queueService->completeTicket($ticketId);
                echo json_encode(['success' => true, 'ticket' => $ticket]);
                break;

            case 'walk_in':
                $name = trim($input['name'] ?? 'Walk-in');
                $phone = trim($input['phone'] ?? '');
                $serviceIds = array_map('intval', $input['service_ids'] ?? [1]);
                $ticket = $queueService->createTicket([
                    'name' => $name,
                    'phone' => $phone ?: '+251900000000',
                    'service_ids' => $serviceIds,
                    'stylist_id' => $stylistId,
                ]);
                $queueService->callNextTicket($stylistId);
                $started = $queueService->startTicket((int)$ticket['id'], $stylistId);
                echo json_encode(['success' => true, 'ticket' => $started]);
                break;

            default:
                throw new RuntimeException('Unknown action');
        }
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Current in-chair ticket
$currentTicket = Database::fetch(
    "SELECT t.*, 
            (SELECT GROUP_CONCAT(sv.name_en SEPARATOR ' + ')
             FROM ticket_services ts JOIN services sv ON sv.id = ts.service_id
             WHERE ts.ticket_id = t.id) AS services_list
     FROM tickets t
     WHERE t.stylist_id = ? AND t.status = 'in_chair'
     ORDER BY t.started_at DESC LIMIT 1",
    [$stylistId]
);

// Called ticket waiting to start
$calledTicket = Database::fetch(
    "SELECT t.*, 
            (SELECT GROUP_CONCAT(sv.name_en SEPARATOR ' + ')
             FROM ticket_services ts JOIN services sv ON sv.id = ts.service_id
             WHERE ts.ticket_id = t.id) AS services_list
     FROM tickets t
     WHERE t.stylist_id = ? AND t.status = 'called'
     ORDER BY t.called_at DESC LIMIT 1",
    [$stylistId]
);

// Waiting for this stylist (or any)
$waitingList = Database::fetchAll(
    "SELECT t.*, 
            (SELECT GROUP_CONCAT(sv.name_en SEPARATOR ' + ')
             FROM ticket_services ts JOIN services sv ON sv.id = ts.service_id
             WHERE ts.ticket_id = t.id) AS services_list
     FROM tickets t
     WHERE t.status = 'waiting'
       AND (t.stylist_id = ? OR t.stylist_id IS NULL)
     ORDER BY t.priority DESC, t.joined_at ASC
     LIMIT 8",
    [$stylistId]
);

$services = Database::fetchAll("SELECT id, name_en, duration_minutes, price_etb FROM services WHERE is_active = 1 ORDER BY sort_order");
$settings = $queueService->getSettings();
$lang = I18n::getLocale();
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#09090b">
    <title>Chair <?= (int)$stylist['chair_number'] ?> — <?= htmlspecialchars($stylist['full_name']) ?></title>
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
        .safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0); }
        .timer-display { font-variant-numeric: tabular-nums; letter-spacing: -0.02em; }
        .status-active { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .status-break  { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
        .status-offline{ background: linear-gradient(135deg, #71717a 0%, #52525b 100%); }
        .glow-emerald { box-shadow: 0 0 24px -4px rgba(16, 185, 129, 0.4); }
        .glow-amber   { box-shadow: 0 0 24px -4px rgba(245, 158, 11, 0.35); }
    </style>
</head>
<body class="h-full bg-zinc-950 text-white antialiased font-sans select-none"
      x-data="stationApp()" x-init="init()" x-cloak>

    <!-- Header -->
    <header class="sticky top-0 z-40 bg-zinc-950/90 backdrop-blur-xl border-b border-zinc-800">
        <div class="px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-zinc-800 flex items-center justify-center overflow-hidden">
                    <?php if ($stylist['avatar_url']): ?>
                        <img src="<?= htmlspecialchars($stylist['avatar_url']) ?>" class="w-full h-full object-cover" alt="">
                    <?php else: ?>
                        <span class="text-lg font-bold text-amber-400"><?= strtoupper(mb_substr($stylist['full_name'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div>
                    <p class="font-semibold text-sm leading-tight"><?= htmlspecialchars($stylist['full_name']) ?></p>
                    <p class="text-xs text-zinc-400">Chair <?= (int)$stylist['chair_number'] ?></p>
                </div>
            </div>

            <!-- Status Switcher -->
            <div class="flex rounded-full bg-zinc-900 p-1 border border-zinc-800">
                <button @click="setStatus('active')"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all"
                        :class="status === 'active' ? 'status-active text-white shadow' : 'text-zinc-400 hover:text-white'">
                    🟢 Active
                </button>
                <button @click="setStatus('break')"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all"
                        :class="status === 'break' ? 'status-break text-white shadow' : 'text-zinc-400 hover:text-white'">
                    🟡 Break
                </button>
                <button @click="setStatus('offline')"
                        class="px-3 py-1.5 rounded-full text-xs font-semibold transition-all"
                        :class="status === 'offline' ? 'status-offline text-white shadow' : 'text-zinc-400 hover:text-white'">
                    🔴 Off
                </button>
            </div>
        </div>
    </header>

    <main class="px-4 pt-5 pb-32 space-y-5">

        <!-- Active / Called Hero Card -->
        <template x-if="currentTicket">
            <div class="rounded-3xl bg-zinc-900 border border-zinc-800 overflow-hidden glow-emerald">
                <div class="px-5 pt-5 pb-4">
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 text-xs font-bold uppercase tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            In Progress
                        </span>
                        <span class="text-xs text-zinc-500 font-mono" x-text="currentTicket.ticket_code"></span>
                    </div>

                    <!-- Big Timer -->
                    <div class="text-center mb-5">
                        <p class="text-[11px] font-semibold uppercase tracking-widest text-zinc-500 mb-1">Elapsed</p>
                        <div class="timer-display text-5xl font-extrabold tracking-tight text-white" x-text="elapsedDisplay">00:00:00</div>
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="text-2xl font-bold tracking-tight" x-text="currentTicket.customer_name"></p>
                            <p class="text-sm text-zinc-400 mt-0.5" x-text="currentTicket.customer_phone"></p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span class="px-2.5 py-1 rounded-lg bg-zinc-800 text-xs font-medium text-zinc-300" x-text="currentTicket.services_list || 'Service'"></span>
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-5 flex gap-3">
                    <button @click="finishTicket()"
                            class="flex-1 py-3.5 rounded-2xl bg-emerald-500 text-zinc-950 font-bold text-sm flex items-center justify-center gap-2 active:scale-[0.98] transition-transform shadow-lg shadow-emerald-500/25">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        Finish & Pay
                    </button>
                    <button @click="showAddService = true"
                            class="px-4 py-3.5 rounded-2xl bg-zinc-800 border border-zinc-700 text-sm font-semibold active:scale-[0.98] transition-transform">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                    </button>
                </div>
            </div>
        </template>

        <!-- Called Ticket (waiting to start) -->
        <template x-if="!currentTicket && calledTicket">
            <div class="rounded-3xl bg-zinc-900 border border-amber-500/40 overflow-hidden glow-amber">
                <div class="px-5 pt-5 pb-4">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-400 text-xs font-bold uppercase tracking-wide">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                            Called — Ready
                        </span>
                        <span class="text-xs text-zinc-500 font-mono" x-text="calledTicket.ticket_code"></span>
                    </div>
                    <p class="text-2xl font-bold" x-text="calledTicket.customer_name"></p>
                    <p class="text-sm text-zinc-400 mt-1" x-text="calledTicket.services_list"></p>
                </div>
                <div class="px-5 pb-5">
                    <button @click="startTicket(calledTicket.id)"
                            class="w-full py-3.5 rounded-2xl bg-amber-500 text-zinc-950 font-bold text-sm flex items-center justify-center gap-2 active:scale-[0.98] transition-transform">
                        <i data-lucide="play" class="w-4 h-4"></i>
                        Start Cut
                    </button>
                </div>
            </div>
        </template>

        <!-- Empty State -->
        <template x-if="!currentTicket && !calledTicket">
            <div class="rounded-3xl bg-zinc-900/60 border border-zinc-800 border-dashed p-8 text-center">
                <div class="w-16 h-16 rounded-2xl bg-zinc-800 flex items-center justify-center mx-auto mb-4">
                    <i data-lucide="armchair" class="w-8 h-8 text-zinc-500"></i>
                </div>
                <p class="font-semibold text-zinc-300">Chair is free</p>
                <p class="text-sm text-zinc-500 mt-1">Call the next customer when ready</p>
            </div>
        </template>

        <!-- Call Next + Walk-in -->
        <div class="grid grid-cols-2 gap-3">
            <button @click="callNext()"
                    :disabled="status !== 'active' || loading"
                    class="py-4 rounded-2xl bg-white text-zinc-950 font-bold text-sm flex flex-col items-center justify-center gap-1.5 active:scale-[0.98] transition-all disabled:opacity-40 disabled:pointer-events-none shadow-lg">
                <i data-lucide="megaphone" class="w-5 h-5"></i>
                Call Next
            </button>
            <button @click="showWalkIn = true"
                    :disabled="status !== 'active'"
                    class="py-4 rounded-2xl bg-zinc-800 border border-zinc-700 font-semibold text-sm flex flex-col items-center justify-center gap-1.5 active:scale-[0.98] transition-all disabled:opacity-40">
                <i data-lucide="user-plus" class="w-5 h-5 text-amber-400"></i>
                Walk-In
            </button>
        </div>

        <!-- Up Next List -->
        <section>
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-bold uppercase tracking-wider text-zinc-400">Up Next</h2>
                <span class="text-xs text-zinc-500" x-text="waitingList.length + ' waiting'"></span>
            </div>

            <div class="space-y-2">
                <template x-for="(t, idx) in waitingList" :key="t.id">
                    <div class="flex items-center gap-3 p-3 rounded-2xl bg-zinc-900 border border-zinc-800">
                        <div class="w-8 h-8 rounded-lg bg-zinc-800 flex items-center justify-center text-xs font-bold text-zinc-400" x-text="idx + 1"></div>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate" x-text="t.customer_name"></p>
                            <p class="text-xs text-zinc-500 truncate" x-text="t.services_list || t.ticket_code"></p>
                        </div>
                        <div class="text-right">
                            <span class="text-xs font-mono text-zinc-400" x-text="t.ticket_code"></span>
                            <p class="text-[10px] text-zinc-500" x-text="(t.estimated_wait_minutes || '—') + ' min'"></p>
                        </div>
                    </div>
                </template>

                <template x-if="waitingList.length === 0">
                    <div class="py-8 text-center text-sm text-zinc-500">
                        No one waiting for you right now
                    </div>
                </template>
            </div>
        </section>
    </main>

    <!-- Walk-In Modal -->
    <div x-show="showWalkIn" x-transition.opacity
         class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm"
         @click.self="showWalkIn = false">
        <div x-show="showWalkIn"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             class="w-full max-w-md bg-zinc-900 rounded-t-3xl border-t border-zinc-700 p-5 safe-bottom">
            <div class="w-10 h-1 rounded-full bg-zinc-700 mx-auto mb-5"></div>
            <h3 class="text-lg font-bold mb-4">Quick Walk-In</h3>
            <div class="space-y-3">
                <input type="text" x-model="walkInName" placeholder="Customer name"
                       class="w-full px-4 py-3 rounded-xl bg-zinc-800 border border-zinc-700 text-sm placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-amber-500/40">
                <input type="tel" x-model="walkInPhone" placeholder="Phone (optional)"
                       class="w-full px-4 py-3 rounded-xl bg-zinc-800 border border-zinc-700 text-sm placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-amber-500/40">
                <select x-model="walkInService"
                        class="w-full px-4 py-3 rounded-xl bg-zinc-800 border border-zinc-700 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/40">
                    <?php foreach ($services as $svc): ?>
                    <option value="<?= (int)$svc['id'] ?>"><?= htmlspecialchars($svc['name_en']) ?> — <?= (int)$svc['price_etb'] ?> ETB</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex gap-3 mt-5">
                <button @click="showWalkIn = false" class="flex-1 py-3 rounded-xl border border-zinc-700 text-sm font-semibold">Cancel</button>
                <button @click="submitWalkIn()" :disabled="!walkInName || loading"
                        class="flex-1 py-3 rounded-xl bg-amber-500 text-zinc-950 text-sm font-bold disabled:opacity-50">
                    Seat Now
                </button>
            </div>
        </div>
    </div>

    <!-- Toast -->
    <div x-show="toast" x-transition
         class="fixed top-20 left-1/2 -translate-x-1/2 z-50 px-4 py-2.5 rounded-xl bg-zinc-800 border border-zinc-700 text-sm font-medium shadow-xl"
         x-text="toast"></div>

    <script src="/assets/js/audio.js"></script>
    <script>
        function stationApp() {
            return {
                stylistId: <?= (int)$stylistId ?>,
                status: '<?= htmlspecialchars($stylist['status']) ?>',
                currentTicket: <?= $currentTicket ? json_encode($currentTicket) : 'null' ?>,
                calledTicket: <?= $calledTicket ? json_encode($calledTicket) : 'null' ?>,
                waitingList: <?= json_encode($waitingList) ?>,
                elapsedSeconds: 0,
                elapsedDisplay: '00:00:00',
                timerInterval: null,
                loading: false,
                showWalkIn: false,
                showAddService: false,
                walkInName: '',
                walkInPhone: '',
                walkInService: '<?= (int)($services[0]['id'] ?? 1) ?>',
                toast: '',
                eventSource: null,

                init() {
                    if (this.currentTicket && this.currentTicket.started_at) {
                        this.startTimer(this.currentTicket.started_at);
                    }
                    this.connectSSE();
                    lucide.createIcons();
                },

                startTimer(startedAt) {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                    const start = new Date(startedAt).getTime();
                    const tick = () => {
                        this.elapsedSeconds = Math.max(0, Math.floor((Date.now() - start) / 1000));
                        const h = Math.floor(this.elapsedSeconds / 3600);
                        const m = Math.floor((this.elapsedSeconds % 3600) / 60);
                        const s = this.elapsedSeconds % 60;
                        this.elapsedDisplay = [h, m, s].map(v => String(v).padStart(2, '0')).join(':');
                    };
                    tick();
                    this.timerInterval = setInterval(tick, 1000);
                },

                async api(action, payload = {}) {
                    this.loading = true;
                    try {
                        const res = await fetch(window.location.href, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ action, ...payload })
                        });
                        return await res.json();
                    } finally {
                        this.loading = false;
                    }
                },

                async setStatus(status) {
                    EliteAudio.click();
                    const data = await this.api('set_status', { status });
                    if (data.success) {
                        this.status = status;
                        this.showToast(status === 'active' ? 'You are now Active' : status === 'break' ? 'On Break' : 'Off Duty');
                    }
                },

                async callNext() {
                    EliteAudio.click();
                    const data = await this.api('call_next');
                    if (data.success && data.ticket) {
                        this.calledTicket = data.ticket;
                        this.currentTicket = null;
                        EliteAudio.ticketCalled();
                        this.showToast('Called ' + data.ticket.customer_name);
                        this.refreshWaiting();
                    } else {
                        this.showToast(data.message || 'No one waiting');
                        EliteAudio.error();
                    }
                },

                async startTicket(ticketId) {
                    EliteAudio.click();
                    const data = await this.api('start_ticket', { ticket_id: ticketId });
                    if (data.success) {
                        this.currentTicket = data.ticket;
                        this.calledTicket = null;
                        this.startTimer(data.ticket.started_at);
                        EliteAudio.success();
                    }
                },

                async finishTicket() {
                    if (!this.currentTicket) return;
                    EliteAudio.click();
                    const data = await this.api('finish_ticket', { ticket_id: this.currentTicket.id });
                    if (data.success) {
                        if (this.timerInterval) clearInterval(this.timerInterval);
                        this.currentTicket = null;
                        this.elapsedDisplay = '00:00:00';
                        EliteAudio.success();
                        this.showToast('Service completed');
                        this.refreshWaiting();
                    }
                },

                async submitWalkIn() {
                    EliteAudio.click();
                    const data = await this.api('walk_in', {
                        name: this.walkInName,
                        phone: this.walkInPhone,
                        service_ids: [parseInt(this.walkInService)]
                    });
                    if (data.success) {
                        this.currentTicket = data.ticket;
                        this.calledTicket = null;
                        this.showWalkIn = false;
                        this.walkInName = '';
                        this.walkInPhone = '';
                        this.startTimer(data.ticket.started_at);
                        EliteAudio.success();
                    } else {
                        this.showToast(data.message || 'Failed');
                        EliteAudio.error();
                    }
                },

                async refreshWaiting() {
                    // rely on SSE
                },

                connectSSE() {
                    if (this.eventSource) this.eventSource.close();
                    this.eventSource = new EventSource('/sse.php');
                    this.eventSource.addEventListener('ticket_created', () => this.softRefresh());
                    this.eventSource.addEventListener('ticket_called', () => this.softRefresh());
                    this.eventSource.addEventListener('ticket_completed', () => this.softRefresh());
                },

                softRefresh() {
                    setTimeout(() => location.reload(), 600);
                },

                showToast(msg) {
                    this.toast = msg;
                    setTimeout(() => this.toast = '', 2500);
                }
            }
        }
    </script>
</body>
</html>
