<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;

Auth::startSession();
$lang = I18n::getLocale();

$user = Auth::user();
$history = [];
$stats = ['cuts' => 0, 'spent' => 0, 'favorite' => null];

if ($user && $user['role'] === 'customer') {
    $history = Database::fetchAll(
        "SELECT t.*, 
                (SELECT GROUP_CONCAT(s.name_en SEPARATOR ', ')
                 FROM ticket_services ts JOIN services s ON s.id = ts.service_id
                 WHERE ts.ticket_id = t.id) AS services
         FROM tickets t
         WHERE t.customer_phone = ? AND t.status = 'completed'
         ORDER BY t.completed_at DESC
         LIMIT 20",
        [preg_replace('/\D+/', '', $user['phone'])]
    );

    $agg = Database::fetch(
        "SELECT COUNT(*) AS cuts, COALESCE(SUM(total_price_etb), 0) AS spent
         FROM tickets WHERE customer_phone = ? AND status = 'completed'",
        [preg_replace('/\D+/', '', $user['phone'])]
    );
    $stats['cuts']  = (int)($agg['cuts'] ?? 0);
    $stats['spent'] = (float)($agg['spent'] ?? 0);

    $fav = Database::fetch(
        "SELECT u.full_name, COUNT(*) AS cnt
         FROM tickets t
         JOIN stylists s ON s.id = t.stylist_id
         JOIN users u ON u.id = s.user_id
         WHERE t.customer_phone = ? AND t.status = 'completed'
         GROUP BY t.stylist_id
         ORDER BY cnt DESC LIMIT 1",
        [preg_replace('/\D+/', '', $user['phone'])]
    );
    $stats['favorite'] = $fav['full_name'] ?? null;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'login') {
            $u = Auth::attempt($_POST['phone'] ?? '', $_POST['password'] ?? '');
            if (!$u) throw new RuntimeException('Invalid phone or password.');
            header('Location: /profile.php');
            exit;
        }
        if ($action === 'register') {
            Auth::register($_POST['name'] ?? '', $_POST['phone'] ?? '', $_POST['password'] ?? '', $lang);
            header('Location: /profile.php');
            exit;
        }
        if ($action === 'logout') {
            Auth::logout();
            header('Location: /profile.php');
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Elite Cuts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['\"Plus Jakarta Sans\"','system-ui','sans-serif']}}}}</script>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased font-sans">
<header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-zinc-200/80">
    <div class="max-w-lg mx-auto px-4 h-14 flex items-center justify-between">
        <a href="/queue.php" class="flex items-center gap-2 text-zinc-500 hover:text-zinc-900">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span class="text-sm font-medium">Back</span>
        </a>
        <span class="font-bold text-sm">My Profile</span>
        <div class="w-16"></div>
    </div>
</header>
<main class="max-w-lg mx-auto px-4 py-8">
    <?php if ($error): ?>
    <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if (!$user || $user['role'] !== 'customer'): ?>
    <div class="space-y-6" x-data="{ tab: 'login' }">
        <div class="flex rounded-xl bg-zinc-100 p-1">
            <button type="button" @click="tab='login'" :class="tab==='login' ? 'bg-white shadow text-zinc-900' : 'text-zinc-500'" class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition">Sign In</button>
            <button type="button" @click="tab='register'" :class="tab==='register' ? 'bg-white shadow text-zinc-900' : 'text-zinc-500'" class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition">Register</button>
        </div>
        <form method="POST" x-show="tab==='login'" class="space-y-4 bg-white border border-zinc-200 rounded-2xl p-5">
            <input type="hidden" name="action" value="login">
            <div><label class="block text-sm font-medium text-zinc-700 mb-1.5">Phone</label><input type="tel" name="phone" required placeholder="+2519..." class="w-full px-4 py-3 rounded-xl border border-zinc-200 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none"></div>
            <div><label class="block text-sm font-medium text-zinc-700 mb-1.5">Password</label><input type="password" name="password" required class="w-full px-4 py-3 rounded-xl border border-zinc-200 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none"></div>
            <button type="submit" class="w-full py-3.5 rounded-xl bg-zinc-950 text-white font-bold text-sm">Sign In</button>
        </form>
        <form method="POST" x-show="tab==='register'" x-cloak class="space-y-4 bg-white border border-zinc-200 rounded-2xl p-5">
            <input type="hidden" name="action" value="register">
            <div><label class="block text-sm font-medium text-zinc-700 mb-1.5">Full Name</label><input type="text" name="name" required class="w-full px-4 py-3 rounded-xl border border-zinc-200 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none"></div>
            <div><label class="block text-sm font-medium text-zinc-700 mb-1.5">Phone</label><input type="tel" name="phone" required placeholder="+2519..." class="w-full px-4 py-3 rounded-xl border border-zinc-200 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none"></div>
            <div><label class="block text-sm font-medium text-zinc-700 mb-1.5">Password</label><input type="password" name="password" required minlength="6" class="w-full px-4 py-3 rounded-xl border border-zinc-200 focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 outline-none"></div>
            <button type="submit" class="w-full py-3.5 rounded-xl bg-amber-500 text-zinc-950 font-bold text-sm">Create Account</button>
        </form>
    </div>
    <?php else: ?>
    <div class="flex items-center gap-4 mb-8">
        <div class="w-16 h-16 rounded-2xl bg-zinc-900 text-amber-400 flex items-center justify-center text-2xl font-bold"><?= strtoupper(mb_substr($user['full_name'], 0, 1)) ?></div>
        <div>
            <h1 class="text-xl font-extrabold tracking-tight"><?= htmlspecialchars($user['full_name']) ?></h1>
            <p class="text-sm text-zinc-500"><?= htmlspecialchars($user['phone']) ?></p>
            <span class="inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-zinc-100 text-zinc-600"><?= htmlspecialchars(ucfirst($user['member_tier'] ?? 'regular')) ?></span>
        </div>
    </div>
    <div class="grid grid-cols-3 gap-3 mb-8">
        <div class="bg-white border border-zinc-200 rounded-2xl p-4 text-center"><div class="text-2xl font-extrabold"><?= $stats['cuts'] ?></div><div class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider mt-0.5">Cuts</div></div>
        <div class="bg-white border border-zinc-200 rounded-2xl p-4 text-center"><div class="text-2xl font-extrabold"><?= number_format($stats['spent'], 0) ?></div><div class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider mt-0.5">ETB Spent</div></div>
        <div class="bg-white border border-zinc-200 rounded-2xl p-4 text-center"><div class="text-sm font-bold truncate"><?= htmlspecialchars($stats['favorite'] ?? '—') ?></div><div class="text-[11px] font-medium text-zinc-500 uppercase tracking-wider mt-0.5">Favorite</div></div>
    </div>
    <h2 class="text-sm font-bold text-zinc-500 uppercase tracking-wider mb-3">Recent Visits</h2>
    <?php if (empty($history)): ?><p class="text-sm text-zinc-400 py-6 text-center">No completed visits yet.</p>
    <?php else: ?><div class="space-y-2 mb-8"><?php foreach ($history as $h): ?>
        <div class="bg-white border border-zinc-200 rounded-xl px-4 py-3 flex items-center justify-between">
            <div><div class="text-sm font-semibold"><?= htmlspecialchars($h['ticket_code']) ?></div><div class="text-xs text-zinc-500"><?= htmlspecialchars($h['services'] ?? 'Service') ?></div></div>
            <div class="text-right"><div class="text-sm font-bold"><?= number_format((float)$h['total_price_etb'], 0) ?> ETB</div><div class="text-[11px] text-zinc-400"><?= date('M j', strtotime($h['completed_at'])) ?></div></div>
        </div><?php endforeach; ?></div><?php endif; ?>
    <form method="POST"><input type="hidden" name="action" value="logout"><button type="submit" class="w-full py-3 rounded-xl border border-zinc-200 text-sm font-semibold text-zinc-600 hover:bg-zinc-50">Sign Out</button></form>
    <?php endif; ?>
</main>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>lucide.createIcons();</script>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
