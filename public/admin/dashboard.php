<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;
use App\Services\QueueService;

$queueService = new QueueService();
$settings = $queueService->getSettings();

$todayRevenue = Database::fetch(
    "SELECT COALESCE(SUM(total_price_etb), 0) AS total
     FROM tickets WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
);
$yesterdayRevenue = Database::fetch(
    "SELECT COALESCE(SUM(total_price_etb), 0) AS total
     FROM tickets WHERE status = 'completed' AND DATE(completed_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
);
$servedToday = Database::fetch(
    "SELECT COUNT(*) AS cnt FROM tickets WHERE status = 'completed' AND DATE(completed_at) = CURDATE()"
);
$activeQueue = Database::fetch(
    "SELECT COUNT(*) AS cnt FROM tickets WHERE status IN ('waiting','called')"
);
$avgWait = Database::fetch(
    "SELECT AVG(estimated_wait_minutes) AS avg_w FROM tickets WHERE status = 'waiting'"
);

$revToday = (float)($todayRevenue['total'] ?? 0);
$revYest  = (float)($yesterdayRevenue['total'] ?? 0);
$revChange = $revYest > 0 ? round((($revToday - $revYest) / $revYest) * 100, 1) : 0;

$revenueTrend = Database::fetchAll(
    "SELECT DATE(completed_at) AS day, SUM(total_price_etb) AS revenue
     FROM tickets
     WHERE status = 'completed' AND completed_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
     GROUP BY DATE(completed_at)
     ORDER BY day ASC"
);

$hourlyVolume = Database::fetchAll(
    "SELECT HOUR(joined_at) AS hour, COUNT(*) AS cnt
     FROM tickets
     WHERE DATE(joined_at) = CURDATE()
     GROUP BY HOUR(joined_at)
     ORDER BY hour ASC"
);

$allTickets = Database::fetchAll(
    "SELECT t.*, s.chair_number, u.full_name AS stylist_name
     FROM tickets t
     LEFT JOIN stylists s ON s.id = t.stylist_id
     LEFT JOIN users u ON u.id = s.user_id
     WHERE t.status IN ('waiting','called','in_chair') OR DATE(t.joined_at) = CURDATE()
     ORDER BY FIELD(t.status, 'in_chair','called','waiting','completed','cancelled'), t.joined_at DESC
     LIMIT 50"
);

$stylists = Database::fetchAll(
    "SELECT s.*, u.full_name, u.phone
     FROM stylists s JOIN users u ON u.id = s.user_id
     ORDER BY s.chair_number"
);

$services = Database::fetchAll("SELECT * FROM services ORDER BY sort_order");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'pause_queue':
                $paused = !empty($input['paused']);
                $queueService->setQueuePaused($paused);
                echo json_encode(['success' => true, 'paused' => $paused]);
                break;
            case 'cancel_ticket':
                $queueService->cancelTicket((int)$input['ticket_id'], 'Cancelled by admin');
                echo json_encode(['success' => true]);
                break;
            case 'call_ticket':
                $ticket = $queueService->callNextTicket(isset($input['stylist_id']) ? (int)$input['stylist_id'] : null);
                echo json_encode(['success' => (bool)$ticket, 'ticket' => $ticket]);
                break;
            case 'update_stylist_status':
                Database::execute(
                    "UPDATE stylists SET status = ?, is_available = ? WHERE id = ?",
                    [$input['status'], $input['status'] === 'active' ? 1 : 0, (int)$input['stylist_id']]
                );
                echo json_encode(['success' => true]);
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

$isPaused = !empty($settings['is_queue_paused']);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard — <?= htmlspecialchars($settings['shop_name_en'] ?? 'Elite Cuts') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased font-sans"
      x-data="adminDash()" x-init="init()" x-cloak>

    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-zinc-200/80">
        <div class="max-w-7xl mx-auto px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-zinc-950 flex items-center justify-center">
                    <i data-lucide="scissors" class="w-4 h-4 text-amber-400"></i>
                </div>
                <span class="font-bold text-[15px]"><?= htmlspecialchars($settings['shop_name_en'] ?? 'Elite Cuts') ?></span>
                <span class="text-xs font-medium text-zinc-400 bg-zinc-100 px-2 py-0.5 rounded-full">Admin</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="/live-board.php" target="_blank" class="text-sm font-medium text-zinc-500 hover:text-zinc-900 flex items-center gap-1.5">
                    <i data-lucide="tv" class="w-4 h-4"></i> Live Board
                </a>
                <a href="/queue.php" class="text-sm font-medium text-zinc-500 hover:text-zinc-900">Kiosk</a>
            </div>
        </div>
    </header>

    <div x-show="isPaused" class="bg-rose-500 text-white text-center py-2.5 text-sm font-semibold">
        ⚠ Walk-in queue is currently PAUSED
        <button @click="togglePause()" class="ml-3 underline underline-offset-2">Resume now</button>
    </div>

    <main class="max-w-7xl mx-auto px-6 py-8 space-y-8">

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Daily Revenue</p>
                <p class="text-3xl font-extrabold tracking-tight"><?= number_format($revToday, 0) ?> <span class="text-lg text-zinc-400">ETB</span></p>
                <p class="text-xs mt-1 font-medium <?= $revChange >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                    <?= $revChange >= 0 ? '↑' : '↓' ?> <?= abs($revChange) ?>% vs yesterday
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Customers Served</p>
                <p class="text-3xl font-extrabold tracking-tight"><?= (int)($servedToday['cnt'] ?? 0) ?></p>
                <p class="text-xs mt-1 text-zinc-500">Completed today</p>
            </div>
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Active Queue</p>
                <p class="text-3xl font-extrabold tracking-tight"><?= (int)($activeQueue['cnt'] ?? 0) ?></p>
                <p class="text-xs mt-1 text-zinc-500">Waiting + Called</p>
            </div>
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Avg Wait Time</p>
                <p class="text-3xl font-extrabold tracking-tight"><?= round((float)($avgWait['avg_w'] ?? 0)) ?> <span class="text-lg text-zinc-400">min</span></p>
                <p class="text-xs mt-1 text-zinc-500">Current estimate</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <h3 class="text-sm font-bold mb-4">7-Day Revenue Trend</h3>
                <canvas id="revenueChart" height="180"></canvas>
            </div>
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <h3 class="text-sm font-bold mb-4">Hourly Walk-In Volume (Today)</h3>
                <canvas id="hourlyChart" height="180"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <h3 class="text-sm font-bold mb-4">Queue Controls</h3>
                <button @click="togglePause()"
                        class="w-full py-3 rounded-xl text-sm font-bold transition-all"
                        :class="isPaused ? 'bg-emerald-500 text-white hover:bg-emerald-600' : 'bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100'">
                    <span x-text="isPaused ? '▶ Resume Walk-In Queue' : '⏸ Pause Walk-In Queue'"></span>
                </button>
                <p class="text-xs text-zinc-500 mt-3">Pausing stops new tickets from being created at the kiosk.</p>
            </div>

            <div class="lg:col-span-2 bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
                <h3 class="text-sm font-bold mb-4">Staff Status</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <?php foreach ($stylists as $st): ?>
                    <div class="p-3 rounded-xl border border-zinc-100 bg-zinc-50">
                        <div class="flex items-center gap-2 mb-2">
                            <div class="w-8 h-8 rounded-lg bg-zinc-200 flex items-center justify-center text-xs font-bold text-zinc-600">
                                <?= strtoupper(mb_substr($st['full_name'], 0, 1)) ?>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold truncate"><?= htmlspecialchars($st['full_name']) ?></p>
                                <p class="text-[10px] text-zinc-400">Chair <?= (int)$st['chair_number'] ?></p>
                            </div>
                        </div>
                        <select @change="updateStylistStatus(<?= (int)$st['id'] ?>, $event.target.value)"
                                class="w-full text-xs font-medium rounded-lg border border-zinc-200 px-2 py-1.5 bg-white">
                            <option value="active"  <?= $st['status']==='active'  ? 'selected' : '' ?>>🟢 Active</option>
                            <option value="break"   <?= $st['status']==='break'   ? 'selected' : '' ?>>🟡 Break</option>
                            <option value="offline" <?= $st['status']==='offline' ? 'selected' : '' ?>>🔴 Offline</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-zinc-200/80 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-zinc-100 flex items-center justify-between">
                <h3 class="text-sm font-bold">Queue Management</h3>
                <div class="flex gap-2">
                    <button @click="filter = 'all'" :class="filter==='all' ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600'" class="px-3 py-1 rounded-lg text-xs font-semibold">All</button>
                    <button @click="filter = 'waiting'" :class="filter==='waiting' ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600'" class="px-3 py-1 rounded-lg text-xs font-semibold">Waiting</button>
                    <button @click="filter = 'in_chair'" :class="filter==='in_chair' ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-600'" class="px-3 py-1 rounded-lg text-xs font-semibold">In Chair</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-left text-xs font-semibold text-zinc-500 uppercase tracking-wider">
                        <tr>
                            <th class="px-5 py-3">Ticket</th>
                            <th class="px-5 py-3">Customer</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Barber / Chair</th>
                            <th class="px-5 py-3">Price</th>
                            <th class="px-5 py-3">Wait</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($allTickets as $t):
                            $statusClass = match($t['status']) {
                                'waiting'   => 'bg-amber-50 text-amber-700',
                                'called'    => 'bg-sky-50 text-sky-700',
                                'in_chair'  => 'bg-emerald-50 text-emerald-700',
                                'completed' => 'bg-zinc-100 text-zinc-600',
                                'cancelled' => 'bg-rose-50 text-rose-700',
                                default     => 'bg-zinc-100 text-zinc-600',
                            };
                        ?>
                        <tr class="hover:bg-zinc-50/80" data-status="<?= htmlspecialchars($t['status']) ?>">
                            <td class="px-5 py-3 font-mono font-bold"><?= htmlspecialchars($t['ticket_code']) ?></td>
                            <td class="px-5 py-3">
                                <p class="font-medium"><?= htmlspecialchars($t['customer_name']) ?></p>
                                <p class="text-xs text-zinc-400"><?= htmlspecialchars($t['customer_phone']) ?></p>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusClass ?>">
                                    <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-3 text-zinc-600">
                                <?= $t['stylist_name'] ? htmlspecialchars($t['stylist_name']) . ' (C' . (int)$t['chair_number'] . ')' : 'Any' ?>
                            </td>
                            <td class="px-5 py-3 font-medium"><?= number_format((float)$t['total_price_etb'], 0) ?> ETB</td>
                            <td class="px-5 py-3 text-zinc-500"><?= (int)($t['estimated_wait_minutes'] ?? 0) ?> min</td>
                            <td class="px-5 py-3 text-right">
                                <?php if (in_array($t['status'], ['waiting','called'])): ?>
                                <button @click="cancelTicket(<?= (int)$t['id'] ?>)"
                                        class="text-xs font-semibold text-rose-600 hover:text-rose-800">Cancel</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-zinc-200/80 p-5 shadow-sm">
            <h3 class="text-sm font-bold mb-4">Services & Pricing</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                <?php foreach ($services as $svc): ?>
                <div class="p-3 rounded-xl border border-zinc-100 bg-zinc-50">
                    <p class="text-sm font-semibold"><?= htmlspecialchars($svc['name_en']) ?></p>
                    <p class="text-xs text-zinc-500 mt-0.5"><?= (int)$svc['duration_minutes'] ?> min</p>
                    <p class="text-sm font-bold text-zinc-900 mt-1"><?= number_format((float)$svc['price_etb'], 0) ?> ETB</p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        const revenueLabels = <?= json_encode(array_column($revenueTrend, 'day')) ?>;
        const revenueData   = <?= json_encode(array_map('floatval', array_column($revenueTrend, 'revenue'))) ?>;
        const hourlyLabels  = <?= json_encode(array_map(fn($h) => $h['hour'] . ':00', $hourlyVolume)) ?>;
        const hourlyData    = <?= json_encode(array_map('intval', array_column($hourlyVolume, 'cnt'))) ?>;

        function adminDash() {
            return {
                isPaused: <?= $isPaused ? 'true' : 'false' ?>,
                filter: 'all',

                init() {
                    this.renderCharts();
                    lucide.createIcons();
                    this.$watch('filter', (val) => {
                        document.querySelectorAll('tbody tr').forEach(row => {
                            row.style.display = (val === 'all' || row.dataset.status === val) ? '' : 'none';
                        });
                    });
                },

                renderCharts() {
                    const gridColor = 'rgba(0,0,0,0.04)';
                    new Chart(document.getElementById('revenueChart'), {
                        type: 'line',
                        data: {
                            labels: revenueLabels,
                            datasets: [{
                                label: 'Revenue (ETB)',
                                data: revenueData,
                                borderColor: '#f59e0b',
                                backgroundColor: 'rgba(245,158,11,0.08)',
                                fill: true,
                                tension: 0.35,
                                pointRadius: 4,
                                pointBackgroundColor: '#f59e0b'
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, grid: { color: gridColor } },
                                x: { grid: { display: false } }
                            }
                        }
                    });

                    new Chart(document.getElementById('hourlyChart'), {
                        type: 'bar',
                        data: {
                            labels: hourlyLabels,
                            datasets: [{
                                label: 'Walk-ins',
                                data: hourlyData,
                                backgroundColor: 'rgba(16,185,129,0.7)',
                                borderRadius: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            plugins: { legend: { display: false } },
                            scales: {
                                y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                },

                async api(action, payload = {}) {
                    const res = await fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action, ...payload })
                    });
                    return res.json();
                },

                async togglePause() {
                    const next = !this.isPaused;
                    const data = await this.api('pause_queue', { paused: next });
                    if (data.success) this.isPaused = next;
                },

                async cancelTicket(id) {
                    if (!confirm('Cancel this ticket?')) return;
                    const data = await this.api('cancel_ticket', { ticket_id: id });
                    if (data.success) location.reload();
                },

                async updateStylistStatus(id, status) {
                    await this.api('update_stylist_status', { stylist_id: id, status });
                }
            }
        }
    </script>
</body>
</html>
