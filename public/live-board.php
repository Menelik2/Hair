<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Services\QueueService;

$queueService = new QueueService();
$board = $queueService->getLiveBoard();
$settings = $board['settings'] ?? [];
$shopName = $settings['shop_name_en'] ?? 'Elite Cuts';
?>
<!DOCTYPE html>
<html lang="en" class="h-full overflow-hidden">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Board — <?= htmlspecialchars($shopName) ?></title>
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
        html, body { height: 100%; overflow: hidden; }
        .chair-card { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .chair-card.called {
            animation: callFlash 1.2s ease-out;
            box-shadow: 0 0 0 3px #f59e0b, 0 0 40px -5px rgba(245, 158, 11, 0.5);
        }
        @keyframes callFlash {
            0%, 100% { box-shadow: 0 0 0 3px #f59e0b, 0 0 40px -5px rgba(245, 158, 11, 0.5); }
            50% { box-shadow: 0 0 0 8px #fbbf24, 0 0 60px -2px rgba(251, 191, 36, 0.7); }
        }
        .border-flash { animation: borderPulse 1.5s ease-out; }
        @keyframes borderPulse {
            0% { box-shadow: inset 0 0 0 0 transparent; }
            30% { box-shadow: inset 0 0 0 6px #f59e0b; }
            100% { box-shadow: inset 0 0 0 0 transparent; }
        }
        .ticket-row { transition: background 0.3s ease; }
        .ticket-row.highlight { background: rgba(245, 158, 11, 0.12); }
    </style>
</head>
<body class="h-full bg-zinc-950 text-white antialiased font-sans select-none"
      x-data="liveBoard()" x-init="init()" x-cloak
      :class="{ 'border-flash': flashing }">

    <header class="h-16 px-8 flex items-center justify-between border-b border-zinc-800/80 bg-zinc-950/80 backdrop-blur">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-xl bg-zinc-900 border border-zinc-700 flex items-center justify-center">
                <i data-lucide="scissors" class="w-5 h-5 text-amber-400"></i>
            </div>
            <div>
                <h1 class="text-xl font-extrabold tracking-tight"><?= htmlspecialchars($shopName) ?></h1>
                <p class="text-xs text-zinc-500 font-medium">Live Queue Board</p>
            </div>
        </div>
        <div class="flex items-center gap-6">
            <div class="text-right">
                <p class="text-2xl font-bold tabular-nums" x-text="clock"><?= date('H:i') ?></p>
                <p class="text-xs text-zinc-500" x-text="dateStr"><?= date('D, M j') ?></p>
            </div>
            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-zinc-900 border border-zinc-800">
                <span class="w-2 h-2 rounded-full" :class="connected ? 'bg-emerald-400 animate-pulse' : 'bg-zinc-600'"></span>
                <span class="text-xs font-medium text-zinc-400" x-text="connected ? 'Live' : 'Reconnecting…'"></span>
            </div>
        </div>
    </header>

    <div class="flex h-[calc(100%-4rem)]">
        <section class="w-[60%] border-r border-zinc-800/80 p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-1.5 h-6 rounded-full bg-emerald-500"></div>
                <h2 class="text-sm font-bold uppercase tracking-[0.2em] text-zinc-400">Now Serving</h2>
            </div>

            <div class="grid grid-cols-2 gap-4 flex-1 content-start">
                <template x-for="chair in chairs" :key="chair.id">
                    <div class="chair-card relative rounded-2xl bg-zinc-900 border border-zinc-800 p-5 flex flex-col"
                         :class="{ 'called': highlightedChair === chair.chair_number }">
                        <div class="flex items-center justify-between mb-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-zinc-800 text-xs font-bold tracking-wide text-zinc-300">
                                CHAIR <span x-text="chair.chair_number"></span>
                            </span>
                            <template x-if="getServing(chair.id)">
                                <span class="relative flex h-3 w-3">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-60"></span>
                                    <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                                </span>
                            </template>
                            <template x-if="!getServing(chair.id)">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-zinc-600"
                                      x-text="chair.status === 'break' ? 'Break' : chair.status === 'offline' ? 'Off' : 'Free'"></span>
                            </template>
                        </div>

                        <template x-if="getServing(chair.id)">
                            <div class="flex-1 flex flex-col justify-center">
                                <p class="text-4xl font-extrabold tracking-tight text-white mb-1"
                                   x-text="'#' + getServing(chair.id).ticket_code"></p>
                                <p class="text-lg font-semibold text-zinc-200 truncate"
                                   x-text="getServing(chair.id).customer_name"></p>
                                <p class="text-sm text-zinc-500 mt-2 truncate"
                                   x-text="getServing(chair.id).services_en || 'In service'"></p>
                                <div class="mt-4 flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-zinc-800 flex items-center justify-center text-xs font-bold text-amber-400"
                                         x-text="(getServing(chair.id).stylist_name || '?')[0]"></div>
                                    <span class="text-sm text-zinc-400" x-text="getServing(chair.id).stylist_name || 'Barber'"></span>
                                </div>
                            </div>
                        </template>

                        <template x-if="!getServing(chair.id)">
                            <div class="flex-1 flex flex-col items-center justify-center text-zinc-600">
                                <i data-lucide="armchair" class="w-10 h-10 mb-2 opacity-40"></i>
                                <p class="text-sm font-medium">Available</p>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </section>

        <section class="w-[40%] p-6 flex flex-col bg-zinc-950">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-1.5 h-6 rounded-full bg-amber-500"></div>
                <h2 class="text-sm font-bold uppercase tracking-[0.2em] text-zinc-400">Up Next In Line</h2>
            </div>

            <div class="flex-1 overflow-hidden space-y-2">
                <template x-for="(ticket, idx) in upNext" :key="ticket.id">
                    <div class="ticket-row flex items-center gap-4 px-4 py-3.5 rounded-xl border border-zinc-800/80 bg-zinc-900/50"
                         :class="{ 'highlight': idx === 0 }">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-extrabold"
                             :class="idx === 0 ? 'bg-amber-500 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                             x-text="idx + 1"></div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-baseline gap-2">
                                <span class="text-xl font-extrabold tracking-tight" x-text="'#' + ticket.ticket_code"></span>
                                <span class="text-sm text-zinc-400 truncate" x-text="ticket.customer_name"></span>
                            </div>
                            <p class="text-xs text-zinc-500 mt-0.5 truncate"
                               x-text="ticket.services_en || (ticket.stylist_id ? 'Specific barber' : 'Any Barber')"></p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-800 text-xs font-semibold text-zinc-300"
                                  x-text="'~' + (ticket.estimated_wait_minutes || '—') + ' min'"></span>
                        </div>
                    </div>
                </template>

                <template x-if="upNext.length === 0">
                    <div class="h-full flex flex-col items-center justify-center text-zinc-600">
                        <i data-lucide="check-circle-2" class="w-12 h-12 mb-3 opacity-40"></i>
                        <p class="font-medium">Queue is clear</p>
                    </div>
                </template>
            </div>

            <div class="mt-4 pt-4 border-t border-zinc-800 grid grid-cols-3 gap-3 text-center">
                <div>
                    <p class="text-2xl font-extrabold tabular-nums" x-text="upNext.length">0</p>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">Waiting</p>
                </div>
                <div>
                    <p class="text-2xl font-extrabold tabular-nums text-emerald-400" x-text="nowServing.length">0</p>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">In Chair</p>
                </div>
                <div>
                    <p class="text-2xl font-extrabold tabular-nums text-amber-400" x-text="activeChairs">0</p>
                    <p class="text-[10px] uppercase tracking-wider text-zinc-500 font-semibold">Active</p>
                </div>
            </div>
        </section>
    </div>

    <script src="/assets/js/audio.js"></script>
    <script>
        function liveBoard() {
            return {
                nowServing: <?= json_encode($board['now_serving'] ?? []) ?>,
                upNext: <?= json_encode($board['up_next'] ?? []) ?>,
                chairs: <?= json_encode($board['chairs'] ?? []) ?>,
                connected: false,
                flashing: false,
                highlightedChair: null,
                clock: '',
                dateStr: '',
                eventSource: null,

                get activeChairs() {
                    return this.chairs.filter(c => c.status === 'active').length;
                },

                getServing(stylistId) {
                    return this.nowServing.find(t => t.stylist_id == stylistId) || null;
                },

                init() {
                    this.updateClock();
                    setInterval(() => this.updateClock(), 1000);
                    this.connectSSE();
                    lucide.createIcons();
                },

                updateClock() {
                    const now = new Date();
                    this.clock = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
                    this.dateStr = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
                },

                connectSSE() {
                    if (this.eventSource) this.eventSource.close();
                    this.eventSource = new EventSource('/sse.php');

                    this.eventSource.onopen = () => { this.connected = true; };
                    this.eventSource.onerror = () => {
                        this.connected = false;
                        setTimeout(() => this.connectSSE(), 3000);
                    };

                    this.eventSource.addEventListener('ticket_called', (e) => {
                        try {
                            const data = JSON.parse(e.data);
                            this.handleTicketCalled(data);
                        } catch {}
                    });

                    ['ticket_created', 'ticket_started', 'ticket_completed', 'ticket_cancelled'].forEach(evt => {
                        this.eventSource.addEventListener(evt, () => this.refreshBoard());
                    });
                },

                handleTicketCalled(data) {
                    this.flashing = true;
                    setTimeout(() => this.flashing = false, 1500);

                    const ticket = data.payload?.ticket;
                    if (ticket?.stylist_id) {
                        const chair = this.chairs.find(c => c.id == ticket.stylist_id);
                        if (chair) {
                            this.highlightedChair = chair.chair_number;
                            setTimeout(() => this.highlightedChair = null, 3000);
                        }
                    }

                    if (window.EliteAudio) EliteAudio.ticketCalled();
                    this.refreshBoard(data.board);
                },

                refreshBoard(boardData) {
                    if (boardData) {
                        this.nowServing = boardData.now_serving || [];
                        this.upNext = boardData.up_next || [];
                        this.chairs = boardData.chairs || this.chairs;
                    } else {
                        setTimeout(() => location.reload(), 800);
                    }
                }
            }
        }
    </script>
</body>
</html>
