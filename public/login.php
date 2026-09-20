<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;

Auth::startSession();

if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'admin') { header('Location: /admin/dashboard.php'); exit; }
    if ($role === 'stylist') {
        $stylist = Database::fetch("SELECT id FROM stylists WHERE user_id = ?", [Auth::id()]);
        header('Location: /stylist/station.php?stylist_id=' . ($stylist['id'] ?? 1)); exit;
    }
    header('Location: /profile.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = Auth::attempt(trim($_POST['phone'] ?? ''), $_POST['password'] ?? '');
    if ($user && in_array($user['role'], ['admin', 'stylist'], true)) {
        if ($user['role'] === 'admin') { header('Location: /admin/dashboard.php'); }
        else {
            $stylist = Database::fetch("SELECT id FROM stylists WHERE user_id = ?", [$user['id']]);
            header('Location: /stylist/station.php?stylist_id=' . ($stylist['id'] ?? 1));
        }
        exit;
    }
    if ($user && $user['role'] === 'customer') { header('Location: /profile.php'); exit; }
    $error = 'Invalid phone or password';
}
$lang = I18n::getLocale();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login — Elite Cuts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['\"Plus Jakarta Sans\"', 'system-ui', 'sans-serif'] } } } }</script>
</head>
<body class="h-full bg-zinc-950 text-white antialiased font-sans flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="w-14 h-14 rounded-2xl bg-zinc-900 border border-zinc-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="scissors" class="w-7 h-7 text-amber-400"></i>
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight">Elite Cuts</h1>
            <p class="text-sm text-zinc-400 mt-1">Staff & Admin Login</p>
        </div>
        <form method="POST" class="bg-zinc-900 border border-zinc-800 rounded-2xl p-6 space-y-4">
            <?php if ($error): ?><div class="px-3 py-2 rounded-lg bg-rose-500/15 text-rose-400 text-sm font-medium text-center"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <div><label class="block text-xs font-semibold text-zinc-400 mb-1.5 uppercase tracking-wider">Phone</label>
                <input type="tel" name="phone" required placeholder="+251911000001" class="w-full px-4 py-3 rounded-xl bg-zinc-800 border border-zinc-700 text-sm placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500"></div>
            <div><label class="block text-xs font-semibold text-zinc-400 mb-1.5 uppercase tracking-wider">Password</label>
                <input type="password" name="password" required placeholder="••••••••" class="w-full px-4 py-3 rounded-xl bg-zinc-800 border border-zinc-700 text-sm placeholder:text-zinc-500 focus:outline-none focus:ring-2 focus:ring-amber-500/40 focus:border-amber-500"></div>
            <button type="submit" class="w-full py-3.5 rounded-xl bg-amber-500 text-zinc-950 font-bold text-sm hover:bg-amber-400 active:scale-[0.98] transition-all">Sign In</button>
        </form>
        <p class="text-center text-xs text-zinc-500 mt-6">Demo: <code class="text-zinc-400">+251911000001</code> / <code class="text-zinc-400">password</code></p>
        <p class="text-center mt-4 space-x-4">
            <a href="/queue.php" class="text-sm text-zinc-400 hover:text-white transition-colors">Customer Kiosk</a>
            <a href="/profile.php" class="text-sm text-zinc-400 hover:text-white transition-colors">My Profile</a>
        </p>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
