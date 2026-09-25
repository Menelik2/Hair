<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Services\QueueService;
use App\Services\AnalyticsService;

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
Auth::requireRole('admin', $isPost);

$queueService = new QueueService();
$analytics    = new AnalyticsService();
$settings     = $queueService->getSettings();

if ($isPost) {
    header('Content-Type: application/json; charset=utf-8');
    $input  = json_decode(file_get_contents('php://input') ?: '[]', true) ?? [];
    if (!is_array($input)) {
        $input = $_POST;
    }
    $action = (string)($input['action'] ?? '');

    try {
        switch ($action) {
            case 'pause_queue':
                $paused = filter_var($input['paused'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $queueService->setQueuePaused($paused);
                echo json_encode(['success' => true, 'paused' => $paused]);
                break;

            case 'cancel_ticket':
                $ticketId = (int)($input['ticket_id'] ?? 0);
                if ($ticketId < 1) {
                    throw new RuntimeException('Invalid ticket id.');
                }
                $queueService->cancelTicket($ticketId, 'Cancelled by admin');
                echo json_encode(['success' => true]);
                break;

            case 'complete_ticket':
                $ticketId = (int)($input['ticket_id'] ?? 0);
                if ($ticketId < 1) {
                    throw new RuntimeException('Invalid ticket id.');
                }
                $ticket = $queueService->completeTicket($ticketId);
                echo json_encode(['success' => true, 'ticket' => $ticket]);
                break;

            case 'call_next':
                $stylistId = isset($input['stylist_id']) ? (int)$input['stylist_id'] : null;
                if ($stylistId !== null && $stylistId < 1) {
                    $stylistId = null;
                }
                $ticket = $queueService->callNextTicket($stylistId);
                echo json_encode([
                    'success' => (bool)$ticket,
                    'ticket'  => $ticket,
                    'message' => $ticket ? 'Ticket called' : 'No waiting tickets',
                ]);
                break;

            case 'update_stylist_status':
                $stylistId = (int)($input['stylist_id'] ?? 0);
                $status    = (string)($input['status'] ?? '');
                $allowed   = ['active', 'break', 'offline'];
                if ($stylistId < 1 || !in_array($status, $allowed, true)) {
                    throw new RuntimeException('Invalid stylist or status.');
                }
                Database::execute(
                    "UPDATE stylists SET status = ?, is_available = ? WHERE id = ?",
                    [$status, $status === 'active' ? 1 : 0, $stylistId]
                );
                echo json_encode(['success' => true, 'status' => $status]);
                break;

            case 'logout':
                Auth::logout();
                echo json_encode(['success' => true, 'redirect' => '/login.php']);
                break;

            default:
                throw new RuntimeException('Unknown action: ' . $action);
        }
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$kpis         = $analytics->getTodayKpis();
$revenueTrend = $analytics->getSevenDayRevenue();
$hourlyVolume = $analytics->getHourlyVolume();

$allTickets = Database::fetchAll(
    "SELECT t.*, s.chair_number, u.full_name AS stylist_name
     FROM tickets t
     LEFT JOIN stylists s ON s.id = t.stylist_id
     LEFT JOIN users u ON u.id = s.user_id
     WHERE t.status IN ('waiting','called','in_chair')
        OR DATE(t.joined_at) = CURDATE()
     ORDER BY FIELD(t.status, 'in_chair','called','waiting','completed','cancelled'),
              t.joined_at DESC
     LIMIT 50"
);

$stylists = Database::fetchAll(
    "SELECT s.*, u.full_name, u.phone
     FROM stylists s
     JOIN users u ON u.id = s.user_id
     ORDER BY s.chair_number"
);

$services = Database::fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order");
if (!$services) {
    $services = Database::fetchAll("SELECT * FROM services ORDER BY sort_order");
}

$isPaused = !empty($settings['is_queue_paused']);
$user     = Auth::user();
$shopName = $settings['shop_name_en'] ?? 'Elite Cuts';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#f2f2f7">
    <link rel="stylesheet" href="/assets/css/apple.css">
    <title>Admin — <?= htmlspecialchars($shopName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['-apple-system', 'BlinkMacSystemFont', 'SF Pro Text', 'Helvetica Neue', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="ios h-full bg-[#f2f2f7] text-black antialiased font-sans"
      x-data="adminDash()" x-init="init()" x-cloak>

    <header class="ios-nav sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 h-14 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-[9px] bg-black flex items-center justify-center">
                    <i data-lucide="scissors" class="w-4 h-4 text-amber-400"></i>
                </div>
                <span class="font-bold text-[15px]"><?= htmlspecialchars($shopName) ?></span>
                <span class="text-xs font-medium text-zinc-400 bg-zinc-100 px-2 py-0.5 rounded-full">Admin</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <span class="hidden sm:inline text-zinc-500"><?= htmlspecialchars($user['full_name'] ?? '') ?></span>
                <a href="/live-board.php" target="_blank" class="font-medium text-zinc-500 hover:text-zinc-900">Board</a>
                <a href="/queue.php" class="font-medium text-zinc-500 hover:text-zinc-900">Kiosk</a>
                <button @click="logout()" class="font-medium text-rose-600 hover:text-rose-800">Logout</button>
            </div>
        </div>
    </header>

    <div x-show="isPaused" x-cloak class="bg-rose-500 text-white text-center py-2.5 text-sm font-semibold">
        Queue PAUSED
        <button type="button" @click="togglePause()" class="ml-3 underline">Resume</button>
    </div>

    <div x-show="toast" x-transition x-cloak
         class="fixed top-16 left-1/2 -translate-x-1/2 z-50 px-4 py-2 rounded-xl bg-zinc-900 text-white text-sm font-medium shadow-lg"
         x-text="toast"></div>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 space-y-8">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white rounded-[16px] border border-black/[0.04] p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Daily Revenue</p>
                <p class="text-3xl font-extrabold"><?= number_format($kpis['revenue_today'], 0) ?> <span class="text-lg text-zinc-400">ETB</span></p>
                <p class="text-xs mt-1 font-medium <?= $kpis['revenue_delta_pct'] >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                    <?= $kpis['revenue_delta_pct'] >= 0 ? '↑' : '↓' ?> <?= abs($kpis['revenue_delta_pct']) ?>% vs yesterday
                </p>
            </div>
            <div class="bg-white rounded-[16px] border border-black/[0.04] p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Served</p>
                <p class="text-3xl font-extrabold"><?= (int)$kpis['completed_today'] ?></p>
            </div>
            <div class="bg-white rounded-[16px] border border-black/[0.04] p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Active Queue</p>
                <p class="text-3xl font-extrabold"><?= (int)$kpis['waiting'] ?></p>
                <p class="text-xs mt-1 text-zinc-500"><?= (int)$kpis['in_chair'] ?> in chair</p>
            </div>
            <div class="bg-white rounded-[16px] border border-black/[0.04] p-5 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-1">Avg Wait</p>
                <p class="text-3xl font-extrabold"><?= (int)$kpis['avg_wait_minutes'] ?> <span class="text-lg text-zinc-400">min</span></p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-[16px] p-5 border border-black/[0.04] shadow-sm">
                <h3 class="text-sm font-bold mb-4">7-Day Revenue</h3>
                <canvas id="revenueChart" height="180"></canvas>
            </div>
            <div class="bg-white rounded-[16px] p-5 border border-black/[0.04] shadow-sm">
                <h3 class="text-sm font-bold mb-4">Hourly Completions</h3>
                <canvas id="hourlyChart" height="180"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white rounded-[16px] p-5 border border-black/[0.04] shadow-sm space-y-3">
                <h3 class="text-sm font-bold">Queue Controls</h3>
                <button type="button" @click="togglePause()" class="w-full py-3 rounded-xl text-sm font-bold"
                        :class="isPaused ? 'bg-emerald-500 text-white' : 'bg-rose-50 text-rose-700 border border-rose-200'" :disabled="busy">
                    <span x-text="isPaused ? 'Resume Queue' : 'Pause Queue'"></span>
                </button>
                <button type="button" @click="callNext()" class="w-full py-3 rounded-xl text-sm font-bold bg-black text-white" :disabled="busy">Call Next</button>
            </div>
            <div class="lg:col-span-2 bg-white rounded-[16px] p-5 border border-black/[0.04] shadow-sm">
                <h3 class="text-sm font-bold mb-4">Staff Status</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <?php foreach ($stylists as $st): ?>
                    <div class="p-3 rounded-xl border border-zinc-100 bg-zinc-50">
                        <p class="text-sm font-semibold truncate"><?= htmlspecialchars($st['full_name']) ?></p>
                        <p class="text-[10px] text-zinc-400 mb-2">Chair <?= (int)$st['chair_number'] ?></p>
                        <select @change="updateStylistStatus(<?= (int)$st['id'] ?>, $event.target.value)" class="w-full text-xs rounded-lg border px-2 py-1.5">
                            <option value="active" <?= $st['status']==='active'?'selected':'' ?>>Active</option>
                            <option value="break" <?= $st['status']==='break'?'selected':'' ?>>Break</option>
                            <option value="offline" <?= $st['status']==='offline'?'selected':'' ?>>Offline</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-[16px] border border-black/[0.04] shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b flex justify-between items-center">
                <h3 class="text-sm font-bold">Queue</h3>
                <div class="flex gap-2">
                    <button type="button" @click="filter='all'" :class="filter==='all'?'bg-zinc-900 text-white':'bg-zinc-100'" class="px-3 py-1 rounded-lg text-xs font-semibold">All</button>
                    <button type="button" @click="filter='waiting'" :class="filter==='waiting'?'bg-zinc-900 text-white':'bg-zinc-100'" class="px-3 py-1 rounded-lg text-xs font-semibold">Waiting</button>
                    <button type="button" @click="filter='in_chair'" :class="filter==='in_chair'?'bg-zinc-900 text-white':'bg-zinc-100'" class="px-3 py-1 rounded-lg text-xs font-semibold">In Chair</button>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-zinc-50 text-xs font-semibold text-zinc-500 uppercase">
                        <tr>
                            <th class="px-5 py-3 text-left">Ticket</th>
                            <th class="px-5 py-3 text-left">Customer</th>
                            <th class="px-5 py-3 text-left">Status</th>
                            <th class="px-5 py-3 text-left">Barber</th>
                            <th class="px-5 py-3 text-left">Price</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        <?php foreach ($allTickets as $t): ?>
                        <tr data-status="<?= htmlspecialchars($t['status']) ?>">
                            <td class="px-5 py-3 font-mono font-bold"><?= htmlspecialchars($t['ticket_code']) ?></td>
                            <td class="px-5 py-3">
                                <p class="font-medium"><?= htmlspecialchars($t['customer_name']) ?></p>
                                <p class="text-xs text-zinc-400"><?= htmlspecialchars($t['customer_phone']) ?></p>
                            </td>
                            <td class="px-5 py-3"><?= htmlspecialchars($t['status']) ?></td>
                            <td class="px-5 py-3"><?= $t['stylist_name'] ? htmlspecialchars($t['stylist_name']) : 'Any' ?></td>
                            <td class="px-5 py-3"><?= number_format((float)$t['total_price_etb'], 0) ?> ETB</td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <?php if (in_array($t['status'], ['waiting','called'], true)): ?>
                                <button type="button" @click="cancelTicket(<?= (int)$t['id'] ?>)" class="text-xs font-semibold text-rose-600">Cancel</button>
                                <?php endif; ?>
                                <?php if ($t['status'] === 'in_chair'): ?>
                                <button type="button" @click="completeTicket(<?= (int)$t['id'] ?>)" class="text-xs font-semibold text-emerald-600">Complete</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        const revenueLabels = <?= json_encode(array_column($revenueTrend, 'label')) ?>;
        const revenueData   = <?= json_encode(array_map('floatval', array_column($revenueTrend, 'revenue'))) ?>;
        const hourlyLabels  = <?= json_encode(array_column($hourlyVolume, 'label')) ?>;
        const hourlyData    = <?= json_encode(array_map('intval', array_column($hourlyVolume, 'cuts'))) ?>;

        function adminDash() {
            return {
                isPaused: <?= $isPaused ? 'true' : 'false' ?>,
                filter: 'all',
                busy: false,
                toast: '',
                init() {
                    this.renderCharts();
                    if (window.lucide) lucide.createIcons();
                    this.$watch('filter', (val) => {
                        document.querySelectorAll('tbody tr[data-status]').forEach(row => {
                            row.style.display = (val === 'all' || row.dataset.status === val) ? '' : 'none';
                        });
                    });
                },
                showToast(msg) { this.toast = msg; setTimeout(() => this.toast = '', 2800); },
                renderCharts() {
                    if (typeof Chart === 'undefined') return;
                    const g = 'rgba(0,0,0,0.04)';
                    new Chart(document.getElementById('revenueChart'), {
                        type: 'line',
                        data: { labels: revenueLabels.length ? revenueLabels : ['—'], datasets: [{ data: revenueData.length ? revenueData : [0], borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.08)', fill: true, tension: 0.35 }] },
                        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: g } }, x: { grid: { display: false } } } }
                    });
                    new Chart(document.getElementById('hourlyChart'), {
                        type: 'bar',
                        data: { labels: hourlyLabels.length ? hourlyLabels : ['—'], datasets: [{ data: hourlyData.length ? hourlyData : [0], backgroundColor: 'rgba(16,185,129,0.7)', borderRadius: 6 }] },
                        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: g } }, x: { grid: { display: false } } } }
                    });
                },
                async api(action, payload = {}) {
                    const res = await fetch(window.location.pathname, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ action, ...payload })
                    });
                    const text = await res.text();
                    let data;
                    try { data = JSON.parse(text); } catch {
                        if (res.status === 401 || text.includes('login')) { location.href = '/login.php'; return { success: false }; }
                        throw new Error('Invalid response');
                    }
                    if (res.status === 401) location.href = '/login.php';
                    return data;
                },
                async togglePause() {
                    if (this.busy) return; this.busy = true;
                    try {
                        const next = !this.isPaused;
                        const data = await this.api('pause_queue', { paused: next });
                        if (data.success) { this.isPaused = next; this.showToast(next ? 'Paused' : 'Resumed'); }
                        else this.showToast(data.message || 'Failed');
                    } catch (e) { this.showToast(e.message); }
                    finally { this.busy = false; }
                },
                async callNext() {
                    if (this.busy) return; this.busy = true;
                    try {
                        const data = await this.api('call_next');
                        this.showToast(data.message || 'Done');
                        if (data.success) setTimeout(() => location.reload(), 600);
                    } catch (e) { this.showToast(e.message); }
                    finally { this.busy = false; }
                },
                async cancelTicket(id) {
                    if (!confirm('Cancel ticket?')) return;
                    const data = await this.api('cancel_ticket', { ticket_id: id });
                    if (data.success) location.reload(); else this.showToast(data.message || 'Failed');
                },
                async completeTicket(id) {
                    if (!confirm('Complete service?')) return;
                    const data = await this.api('complete_ticket', { ticket_id: id });
                    if (data.success) location.reload(); else this.showToast(data.message || 'Failed');
                },
                async updateStylistStatus(id, status) {
                    const data = await this.api('update_stylist_status', { stylist_id: id, status });
                    this.showToast(data.success ? 'Updated' : (data.message || 'Failed'));
                },
                async logout() {
                    await this.api('logout');
                    location.href = '/login.php';
                }
            }
        }
    </script>
</body>
</html>
