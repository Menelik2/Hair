<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Database;
use App\Core\I18n;
use App\Services\QueueService;

$queueService = new QueueService();
$settings = $queueService->getSettings();
$isPaused = !empty($settings['is_queue_paused']);

// Fetch active services & stylists
$services = Database::fetchAll(
    "SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC"
);

$stylists = Database::fetchAll(
    "SELECT s.*, u.full_name, u.avatar_url 
     FROM stylists s 
     JOIN users u ON u.id = s.user_id 
     WHERE s.status IN ('active','break') 
     ORDER BY s.chair_number ASC"
);

// Handle form submission (AJAX-friendly)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    try {
        if ($isPaused) {
            throw new RuntimeException(I18n::t('queue.paused') ?: 'Queue is currently paused.');
        }

        $name       = trim($input['name'] ?? '');
        $phone      = trim($input['phone'] ?? '');
        $serviceIds = array_map('intval', $input['service_ids'] ?? []);
        $stylistId  = !empty($input['stylist_id']) ? (int)$input['stylist_id'] : null;

        if (strlen($name) < 2) {
            throw new RuntimeException('Please enter your name.');
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 9) {
            throw new RuntimeException('Please enter a valid phone number.');
        }
        if (empty($serviceIds)) {
            throw new RuntimeException('Please select at least one service.');
        }

        $ticket = $queueService->createTicket([
            'name'        => $name,
            'phone'       => $phone,
            'service_ids' => $serviceIds,
            'stylist_id'  => $stylistId,
        ]);

        echo json_encode([
            'success' => true,
            'ticket'  => $ticket,
            'redirect'=> '/ticket.php?code=' . urlencode($ticket['ticket_code']),
        ]);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$lang = I18n::getLocale();
$t = fn(string $key, array $r = []) => I18n::t($key, $r);
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $t('queue.title') ?> — <?= htmlspecialchars($settings['shop_name_en'] ?? 'Elite Cuts') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['\"Plus Jakarta Sans\"', 'system-ui', 'sans-serif'] },
                    colors: {
                        zinc: {
                            50: '#fafafa', 100: '#f4f4f5', 200: '#e4e4e7', 300: '#d4d4d8',
                            400: '#a1a1aa', 500: '#71717a', 600: '#52525b', 700: '#3f3f46',
                            800: '#27272a', 900: '#18181b', 950: '#09090b'
                        }
                    },
                    animation: {
                        'pulse-soft': 'pulse 2.5s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        .service-card { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .service-card.selected {
            border-color: #f59e0b;
            background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%);
            box-shadow: 0 0 0 1px #f59e0b, 0 4px 12px -2px rgba(245, 158, 11, 0.15);
        }
        .stylist-card.selected {
            border-color: #f59e0b;
            box-shadow: 0 0 0 2px #f59e0b;
        }
        .sticky-bar {
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
        }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="h-full bg-zinc-50 text-zinc-900 antialiased font-sans selection:bg-amber-100 selection:text-amber-900"
      x-data="queueApp()" x-cloak>

    <!-- Top Bar -->
    <header class="sticky top-0 z-40 bg-white/80 backdrop-blur-xl border-b border-zinc-200/80">
        <div class="max-w-2xl mx-auto px-4 h-14 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-zinc-950 flex items-center justify-center">
                    <i data-lucide="scissors" class="w-4 h-4 text-amber-400"></i>
                </div>
                <span class="font-bold text-[15px] tracking-tight"><?= htmlspecialchars($settings['shop_name_en'] ?? 'Elite Cuts') ?></span>
            </div>
            <div class="flex items-center gap-2">
                <button @click="toggleLang()" 
                        class="px-2.5 py-1 rounded-full text-xs font-semibold bg-zinc-100 hover:bg-zinc-200 transition-colors">
                    <span x-text="lang === 'en' ? 'አማ' : 'EN'"></span>
                </button>
            </div>
        </div>
    </header>

    <?php if ($isPaused): ?>
    <div class="bg-rose-500 text-white text-center py-3 px-4 text-sm font-medium">
        <?= $lang === 'am' ? 'ወረፋው ለጊዜው ቆሟል። እባክዎ ቆይተው ይሞክሩ።' : 'Walk-in queue is temporarily paused. Please try again later.' ?>
    </div>
    <?php endif; ?>

    <main class="max-w-2xl mx-auto px-4 pb-36 pt-6">

        <!-- Step Indicator -->
        <div class="flex items-center gap-2 mb-8">
            <template x-for="(step, i) in steps" :key="i">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-all duration-300"
                         :class="currentStep >= i 
                            ? 'bg-zinc-950 text-white' 
                            : 'bg-zinc-200 text-zinc-500'">
                        <span x-text="i + 1"></span>
                    </div>
                    <span class="text-sm font-medium hidden sm:inline"
                          :class="currentStep >= i ? 'text-zinc-900' : 'text-zinc-400'"
                          x-text="step"></span>
                    <div x-show="i < steps.length - 1" class="w-8 h-px bg-zinc-200"></div>
                </div>
            </template>
        </div>

        <!-- STEP 1: Services -->
        <section x-show="currentStep === 0" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0">
            <h1 class="text-2xl font-extrabold tracking-tight mb-1"><?= $t('queue.select_service') ?></h1>
            <p class="text-zinc-500 text-sm mb-6">Select one or more services</p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <?php foreach ($services as $svc): ?>
                <button type="button"
                        @click="toggleService(<?= (int)$svc['id'] ?>, <?= (float)$svc['price_etb'] ?>, <?= (int)$svc['duration_minutes'] ?>)"
                        class="service-card relative text-left p-4 rounded-2xl border border-zinc-200/80 bg-white shadow-sm hover:shadow-md active:scale-[0.98] transition-all"
                        :class="selectedServices.includes(<?= (int)$svc['id'] ?>) ? 'selected' : ''">
                    <div class="flex justify-between items-start gap-2">
                        <div>
                            <h3 class="font-semibold text-[15px] leading-snug">
                                <?= htmlspecialchars($lang === 'am' ? $svc['name_am'] : $svc['name_en']) ?>
                            </h3>
                            <p class="text-xs text-zinc-500 mt-1 line-clamp-1">
                                <?= htmlspecialchars($svc['description_en'] ?? '') ?>
                            </p>
                        </div>
                        <div class="flex-shrink-0 w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                             :class="selectedServices.includes(<?= (int)$svc['id'] ?>) 
                                ? 'bg-amber-500 border-amber-500' 
                                : 'border-zinc-300'">
                            <i data-lucide="check" class="w-3 h-3 text-white" x-show="selectedServices.includes(<?= (int)$svc['id'] ?>)"></i>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 mt-3">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-zinc-100 text-xs font-medium text-zinc-600">
                            <i data-lucide="clock" class="w-3 h-3"></i>
                            <?= (int)$svc['duration_minutes'] ?> min
                        </span>
                        <span class="text-sm font-bold text-zinc-900">
                            <?= number_format((float)$svc['price_etb'], 0) ?> ETB
                        </span>
                    </div>
                </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- STEP 2: Stylist -->
        <section x-show="currentStep === 1" x-transition x-cloak>
            <h1 class="text-2xl font-extrabold tracking-tight mb-1"><?= $t('queue.select_stylist') ?></h1>
            <p class="text-zinc-500 text-sm mb-6">Who would you like today?</p>

            <div class="space-y-3">
                <!-- First Available -->
                <button type="button"
                        @click="selectStylist(null)"
                        class="stylist-card w-full text-left p-4 rounded-2xl border border-zinc-200/80 bg-white shadow-sm hover:shadow-md active:scale-[0.98] transition-all flex items-center gap-4"
                        :class="selectedStylist === null ? 'selected' : ''">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center flex-shrink-0">
                        <i data-lucide="zap" class="w-6 h-6 text-white"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-[15px]"><?= $t('queue.first_available') ?></h3>
                        <p class="text-xs text-zinc-500 mt-0.5">Fastest option • Any available chair</p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Quickest
                        </span>
                    </div>
                </button>

                <?php foreach ($stylists as $st): ?>
                <button type="button"
                        @click="selectStylist(<?= (int)$st['id'] ?>)"
                        class="stylist-card w-full text-left p-4 rounded-2xl border border-zinc-200/80 bg-white shadow-sm hover:shadow-md active:scale-[0.98] transition-all flex items-center gap-4"
                        :class="selectedStylist === <?= (int)$st['id'] ?> ? 'selected' : ''">
                    <div class="w-14 h-14 rounded-xl bg-zinc-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                        <?php if ($st['avatar_url']): ?>
                            <img src="<?= htmlspecialchars($st['avatar_url']) ?>" alt="" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-lg font-bold text-zinc-500">
                                <?= strtoupper(mb_substr($st['full_name'], 0, 1)) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-[15px]"><?= htmlspecialchars($st['full_name']) ?></h3>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="text-amber-500 text-xs">★</span>
                            <span class="text-xs font-medium text-zinc-600"><?= number_format((float)$st['rating'], 1) ?></span>
                            <span class="text-zinc-300">·</span>
                            <span class="text-xs text-zinc-500">Chair <?= (int)$st['chair_number'] ?></span>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <?php if ($st['status'] === 'active'): ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Available
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                                On Break
                            </span>
                        <?php endif; ?>
                    </div>
                </button>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- STEP 3: Details -->
        <section x-show="currentStep === 2" x-transition x-cloak>
            <h1 class="text-2xl font-extrabold tracking-tight mb-1">Your Details</h1>
            <p class="text-zinc-500 text-sm mb-6">Almost done — we just need a few details</p>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1.5">Full Name</label>
                    <input type="text" x-model="name" placeholder="e.g. Yohannes M."
                           class="w-full px-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-zinc-700 mb-1.5"><?= $t('queue.your_phone') ?></label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 text-sm font-medium">+251</span>
                        <input type="tel" x-model="phone" @input="formatPhone"
                               placeholder="9 12 34 56 78"
                               class="w-full pl-16 pr-4 py-3 rounded-xl border border-zinc-200 bg-white text-[15px] placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-amber-500/30 focus:border-amber-500 transition-all">
                    </div>
                    <p class="text-xs text-zinc-400 mt-1.5">We'll text you when it's your turn</p>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="mt-8 p-5 rounded-2xl bg-zinc-950 text-white">
                <h3 class="text-sm font-semibold text-zinc-400 mb-3">Order Summary</h3>
                <div class="space-y-2 text-sm">
                    <template x-for="sid in selectedServices" :key="sid">
                        <div class="flex justify-between">
                            <span class="text-zinc-300" x-text="serviceMap[sid]?.name || 'Service'"></span>
                            <span class="font-medium" x-text="(serviceMap[sid]?.price || 0) + ' ETB'"></span>
                        </div>
                    </template>
                </div>
                <div class="border-t border-zinc-800 mt-3 pt-3 flex justify-between items-center">
                    <div>
                        <span class="text-zinc-400 text-sm"><?= $t('queue.total') ?></span>
                        <div class="text-2xl font-extrabold tracking-tight" x-text="totalPrice + ' ETB'"></div>
                    </div>
                    <div class="text-right">
                        <span class="text-zinc-400 text-sm"><?= $t('queue.duration') ?></span>
                        <div class="text-lg font-bold" x-text="totalDuration + ' min'"></div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Error Toast -->
        <div x-show="errorMsg" x-transition
             class="fixed top-20 left-1/2 -translate-x-1/2 z-50 px-4 py-3 rounded-xl bg-rose-500 text-white text-sm font-medium shadow-lg max-w-sm text-center"
             x-text="errorMsg"></div>
    </main>

    <!-- Sticky Bottom Bar -->
    <div class="fixed bottom-0 inset-x-0 z-40 sticky-bar bg-white/90 border-t border-zinc-200/80">
        <div class="max-w-2xl mx-auto px-4 py-3 flex items-center gap-3">
            <button x-show="currentStep > 0" @click="prevStep()"
                    class="px-4 py-3 rounded-xl border border-zinc-200 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 active:scale-95 transition-all">
                Back
            </button>

            <div class="flex-1 text-sm" x-show="selectedServices.length > 0 && currentStep < 2">
                <span class="font-bold" x-text="totalPrice + ' ETB'"></span>
                <span class="text-zinc-400 mx-1">·</span>
                <span class="text-zinc-500" x-text="totalDuration + ' min'"></span>
            </div>

            <button x-show="currentStep < 2"
                    @click="nextStep()"
                    :disabled="currentStep === 0 && selectedServices.length === 0"
                    class="flex-1 sm:flex-none px-6 py-3 rounded-xl bg-zinc-950 text-white text-sm font-semibold hover:bg-zinc-800 active:scale-95 transition-all disabled:opacity-40 disabled:pointer-events-none flex items-center justify-center gap-2">
                Continue
                <i data-lucide="arrow-right" class="w-4 h-4"></i>
            </button>

            <button x-show="currentStep === 2"
                    @click="submitTicket()"
                    :disabled="submitting || !name || !phone"
                    class="flex-1 px-6 py-3.5 rounded-xl bg-amber-500 text-zinc-950 text-sm font-bold hover:bg-amber-400 active:scale-95 transition-all disabled:opacity-50 disabled:pointer-events-none flex items-center justify-center gap-2 shadow-lg shadow-amber-500/25">
                <template x-if="!submitting">
                    <span class="flex items-center gap-2">
                        <?= $t('queue.join') ?>
                        <i data-lucide="ticket" class="w-4 h-4"></i>
                    </span>
                </template>
                <template x-if="submitting">
                    <span class="flex items-center gap-2">
                        <svg class="animate-spin w-4 h-4" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        Creating...
                    </span>
                </template>
            </button>
        </div>
    </div>

    <script src="/assets/js/audio.js"></script>
    <script>
        // Service lookup for Alpine
        const serviceMap = {
            <?php foreach ($services as $svc): ?>
            <?= (int)$svc['id'] ?>: {
                name: <?= json_encode($lang === 'am' ? $svc['name_am'] : $svc['name_en']) ?>,
                price: <?= (float)$svc['price_etb'] ?>,
                duration: <?= (int)$svc['duration_minutes'] ?>
            },
            <?php endforeach; ?>
        };

        function queueApp() {
            return {
                currentStep: 0,
                steps: ['Services', 'Barber', 'Details'],
                selectedServices: [],
                selectedStylist: null,
                name: '',
                phone: '',
                totalPrice: 0,
                totalDuration: 0,
                submitting: false,
                errorMsg: '',
                lang: '<?= $lang ?>',
                serviceMap,

                toggleService(id, price, duration) {
                    EliteAudio.select();
                    const idx = this.selectedServices.indexOf(id);
                    if (idx === -1) {
                        this.selectedServices.push(id);
                    } else {
                        this.selectedServices.splice(idx, 1);
                    }
                    this.recalc();
                },

                selectStylist(id) {
                    EliteAudio.select();
                    this.selectedStylist = id;
                },

                recalc() {
                    let price = 0, dur = 0;
                    this.selectedServices.forEach(id => {
                        if (this.serviceMap[id]) {
                            price += this.serviceMap[id].price;
                            dur += this.serviceMap[id].duration;
                        }
                    });
                    this.totalPrice = Math.round(price);
                    this.totalDuration = dur;
                },

                nextStep() {
                    if (this.currentStep === 0 && this.selectedServices.length === 0) return;
                    EliteAudio.click();
                    this.currentStep = Math.min(2, this.currentStep + 1);
                    this.errorMsg = '';
                },

                prevStep() {
                    EliteAudio.click();
                    this.currentStep = Math.max(0, this.currentStep - 1);
                    this.errorMsg = '';
                },

                formatPhone() {
                    let digits = this.phone.replace(/\D/g, '').slice(0, 9);
                    // Format as 9 XX XX XX XX
                    if (digits.length > 1) {
                        digits = digits.replace(/(\d{1})(\d{0,2})(\d{0,2})(\d{0,2})(\d{0,2})/, (_, a, b, c, d, e) => {
                            return [a, b, c, d, e].filter(Boolean).join(' ');
                        });
                    }
                    this.phone = digits;
                },

                async submitTicket() {
                    if (this.submitting) return;
                    this.submitting = true;
                    this.errorMsg = '';
                    EliteAudio.click();

                    try {
                        const res = await fetch('/queue.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                name: this.name.trim(),
                                phone: '+251' + this.phone.replace(/\s/g, ''),
                                service_ids: this.selectedServices,
                                stylist_id: this.selectedStylist
                            })
                        });
                        const data = await res.json();
                        if (!data.success) {
                            throw new Error(data.message || 'Something went wrong');
                        }
                        EliteAudio.success();
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 400);
                    } catch (err) {
                        EliteAudio.error();
                        this.errorMsg = err.message;
                        this.submitting = false;
                    }
                },

                toggleLang() {
                    const next = this.lang === 'en' ? 'am' : 'en';
                    window.location.href = '?lang=' + next;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    </script>
</body>
</html>
