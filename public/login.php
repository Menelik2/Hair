<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\Database;
use App\Core\I18n;

Auth::startSession();

$next = $_GET['next'] ?? $_POST['next'] ?? '';
$allowedNext = ['admin', 'station', 'profile'];
if (!in_array($next, $allowedNext, true)) {
    $next = '';
}

if (Auth::check()) {
    $role = Auth::role();
    if ($role === 'admin') {
        header('Location: /admin/dashboard.php');
        exit;
    }
    if ($role === 'stylist') {
        $stylist = Database::fetch("SELECT id FROM stylists WHERE user_id = ?", [Auth::id()]);
        header('Location: /stylist/station.php?stylist_id=' . ($stylist['id'] ?? 1));
        exit;
    }
    if ($role === 'customer') {
        header('Location: /profile.php');
        exit;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $next = $_POST['next'] ?? $next;
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    $user = Auth::attempt($phone, $password);

    if ($user && $user['role'] === 'admin') {
        header('Location: /admin/dashboard.php');
        exit;
    }
    if ($user && $user['role'] === 'stylist') {
        $stylist = Database::fetch("SELECT id FROM stylists WHERE user_id = ?", [$user['id']]);
        header('Location: /stylist/station.php?stylist_id=' . ($stylist['id'] ?? 1));
        exit;
    }
    if ($user && $user['role'] === 'customer') {
        header('Location: /profile.php');
        exit;
    }

    $error = I18n::t('login.invalid');
}

$lang = I18n::getLocale();
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <link rel="stylesheet" href="/assets/css/apple.css">
    <title>Staff Login — Elite Cuts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['-apple-system', 'BlinkMacSystemFont', 'SF Pro Text', 'Helvetica Neue', 'sans-serif'] } } } }</script>
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
</head>
<body class="ios ios-dark h-full bg-black text-white antialiased font-sans flex items-center justify-center p-4">
    <div class="w-full max-w-sm">
        <div class="text-center mb-8">
            <div class="w-14 h-14 rounded-2xl bg-zinc-900 border border-zinc-700 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="scissors" class="w-7 h-7 text-amber-400"></i>
            </div>
            <h1 class="text-2xl font-bold">Staff Login</h1>
            <p class="text-zinc-500 text-sm mt-1">Admin · Stylist</p>
        </div>
        <?php if ($error): ?>
        <div class="mb-4 px-4 py-3 rounded-xl bg-rose-500/20 border border-rose-500/40 text-rose-300 text-sm"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="next" value="<?= htmlspecialchars($next ?? '') ?>">
            <div>
                <label class="text-xs font-semibold text-zinc-400 mb-1 block">Phone</label>
                <input type="tel" name="phone" required placeholder="+2519…"
                       class="w-full px-4 py-3 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:ring-2 focus:ring-amber-500">
            </div>
            <div>
                <label class="text-xs font-semibold text-zinc-400 mb-1 block">Password</label>
                <input type="password" name="password" required
                       class="w-full px-4 py-3 rounded-xl bg-zinc-900 border border-zinc-700 text-white focus:ring-2 focus:ring-amber-500">
            </div>
            <button type="submit" class="w-full py-3.5 rounded-xl bg-amber-500 text-black font-bold">Sign In</button>
        </form>
        <p class="text-center mt-6"><a href="/" class="text-zinc-500 text-sm">← Back to portal</a></p>
    </div>
    <script>document.addEventListener('DOMContentLoaded',()=>{ if(window.lucide) lucide.createIcons(); });</script>
</body>
</html>
