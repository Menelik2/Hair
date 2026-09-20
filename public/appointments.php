<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;
use App\Services\QueueService;

$queueService = new QueueService();
$settings = $queueService->getSettings();
$lang = I18n::getLocale();

$services = Database::fetchAll("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order");
$stylists = Database::fetchAll(
    "SELECT s.*, u.full_name FROM stylists s JOIN users u ON u.id = s.user_id WHERE s.status != 'offline' ORDER BY s.chair_number"
);

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $serviceId = (int)($_POST['service_id'] ?? 0);
    $stylistId = !empty($_POST['stylist_id']) ? (int)$_POST['stylist_id'] : null;
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? '';

    if (strlen($name) < 2 || strlen($phone) < 9 || !$serviceId || !$date || !$time) {
        $error = 'Please fill all required fields.';
    } else {
        try {
            Database::insert(
                "INSERT INTO appointments (customer_name, customer_phone, stylist_id, service_id, appointment_date, start_time, status)
                 VALUES (?, ?, ?, ?, ?, ?, 'scheduled')",
                [$name, $phone, $stylistId, $serviceId, $date, $time]
            );
            $success = true;
        } catch (Throwable $e) {
            $error = 'Could not book appointment. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — Elite Cuts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['\"Plus Jakarta Sans\"', 'system-ui', 'sans-serif'] } } } }</script>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased font-sans">
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-zinc-200/80">
        <div class="max-w-lg mx-auto px-4 h-14 flex items-center justify-between">
            <a href="/queue.php" class="flex items-center gap-2 text-zinc-500 hover:text-zinc-900">
                <i data-lucide="arrow-left" class="w-4 h-4"></i>
                <span class="text-sm font-medium">Back</span>
            </a>
            <span class="font-bold text-sm">Elite Cuts</span>
            <div class="w-16"></div>
        </div>
    </header>

    <main class="max-w-lg mx-auto px-4 py-8">
        <h1 class="text-2xl font-extrabold tracking-tight mb-1">Book an Appointment</h1>
        <p class="text-zinc-500 text-sm mb-8">Reserve your preferred time and barber</p>

        <?php if ($success): ?>
        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-6 text-center">
            <div class="w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                <i data-lucide="check" class="w-7 h-7 text-emerald-600"></i>
            </div>
            <h2 class="text-lg font-bold text-emerald-900 mb-1">Appointment Booked!</h2>
            <p class="text-sm text-emerald-700 mb-4">We'll see you then.</p>
            <a href="/queue.php" class="inline-flex px-5 py-2.5 rounded-xl bg-emerald-600 text-white text-sm font-semibold">Back to Kiosk</a>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="mb-5 px-4 py-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm font-medium"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1.5">Full Name *</label>
                <input type="text" name="name" required class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1.5">Phone *</label>
                <input type="tel" name="phone" required placeholder="+2519..." class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1.5">Service *</label>
                <select name="service_id" required class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                    <option value="">Select a service</option>
                    <?php foreach ($services as $svc): ?>
                    <option value="<?= (int)$svc['id'] ?>"><?= htmlspecialchars($lang === 'am' ? $svc['name_am'] : $svc['name_en']) ?> — <?= number_format((float)$svc['price_etb'], 0) ?> ETB</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-zinc-700 mb-1.5">Preferred Barber</label>
                <select name="stylist_id" class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                    <option value="">Any Available</option>
                    <?php foreach ($stylists as $st): ?>
                    <option value="<?= (int)$st['id'] ?>"><?= htmlspecialchars($st['full_name']) ?> (Chair <?= (int)$st['chair_number'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1.5">Date *</label>
                    <input type="date" name="date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1.5">Time *</label>
                    <input type="time" name="time" required value="10:00" class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500">
                </div>
            </div>
            <button type="submit" class="w-full py-3.5 rounded-xl bg-zinc-950 text-white font-bold text-sm hover:bg-zinc-800 active:scale-[0.98] transition-all flex items-center justify-center gap-2">
                <i data-lucide="calendar-check" class="w-4 h-4"></i>
                Confirm Appointment
            </button>
        </form>
        <p class="text-center text-sm text-zinc-500 mt-6">
            Prefer to walk in? <a href="/queue.php" class="font-semibold text-amber-600 hover:text-amber-700">Join the queue instead</a>
        </p>
        <?php endif; ?>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>
