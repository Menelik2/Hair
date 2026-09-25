<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/bootstrap.php';

use App\Core\Auth;
use App\Core\I18n;
use App\Services\QueueService;

Auth::startSession();
$lang = I18n::getLocale();
$settings = (new QueueService())->getSettings();
$shopEn = $settings['shop_name_en'] ?? 'Elite Cuts';
$shopAm = $settings['shop_name_am'] ?? 'ኤሊት ካትስ';
$shop = $lang === 'am' ? $shopAm : $shopEn;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#000000">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/assets/icons/icon-192.png">
    <title><?= htmlspecialchars($shop) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { height: 100%; background: #000; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Display", "SF Pro Text",
                "Noto Sans Ethiopic", "Helvetica Neue", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .portal-card {
            background: #1c1c1e;
            border-radius: 16px;
            transition: transform 0.15s ease, background 0.15s ease;
        }
        .portal-card:active { transform: scale(0.98); background: #2c2c2e; }
        .icon-box {
            width: 44px; height: 44px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .lang-pill {
            background: rgba(255,255,255,0.08);
            border-radius: 999px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
        }
    </style>
</head>
<body class="h-full text-white flex flex-col items-center justify-center px-6 py-10 select-none">

    <div class="absolute top-4 right-4" style="padding-top: env(safe-area-inset-top)">
        <a href="?lang=<?= $lang === 'am' ? 'en' : 'am' ?>" class="lang-pill">
            <?= $lang === 'am' ? 'English' : 'አማርኛ' ?>
        </a>
    </div>

    <div class="w-full max-w-sm mx-auto text-center">
        <div class="mx-auto mb-6 w-[72px] h-[72px] rounded-[18px] bg-[#1c1c1e] flex items-center justify-center"
             style="box-shadow: 0 8px 32px rgba(245,158,11,0.15);">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none">
                <path d="M14.5 6.5L17.5 3.5M9.5 6.5L6.5 3.5M14.5 17.5L17.5 20.5M9.5 17.5L6.5 20.5"
                      stroke="#f59e0b" stroke-width="1.75" stroke-linecap="round"/>
                <circle cx="12" cy="12" r="3.5" stroke="#f59e0b" stroke-width="1.75"/>
                <path d="M12 8.5V4M12 20v-4.5" stroke="#ef4444" stroke-width="1.75" stroke-linecap="round"/>
            </svg>
        </div>

        <h1 class="text-[34px] font-bold tracking-tight leading-none mb-2">
            <?= htmlspecialchars(strtoupper($shopEn)) ?>
        </h1>
        <p class="text-[13px] font-semibold tracking-[0.2em] text-amber-500 uppercase mb-10">
            <?= $lang === 'am' ? 'መግቢያ ይምረጡ' : 'Choose your portal' ?>
        </p>

        <div class="space-y-3 text-left">
            <a href="/queue.php" class="portal-card flex items-center gap-4 px-4 py-4">
                <div class="icon-box bg-[#1d4ed8]">
                    <svg width="22" height="22" fill="none" stroke="#fff" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.5 20.118a7.5 7.5 0 0114.998 0"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-[17px]"><?= $lang === 'am' ? 'የደንበኛ መተግበሪያ' : 'Customer App' ?></p>
                    <p class="text-[13px] text-white/40"><?= $lang === 'am' ? 'ወረፋ · ቀጠሮ · ቲኬት' : 'Queue · Book · Ticket' ?></p>
                </div>
                <svg width="18" height="18" fill="none" stroke="#666" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/login.php?next=station" class="portal-card flex items-center gap-4 px-4 py-4">
                <div class="icon-box bg-[#14532d]">
                    <svg width="22" height="22" fill="none" stroke="#f59e0b" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 6.5L17.5 3.5M9.5 6.5L6.5 3.5M12 12v8"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-[17px]"><?= $lang === 'am' ? 'የሰራተኛ ዳሽቦርድ' : 'Stylist Dashboard' ?></p>
                    <p class="text-[13px] text-white/40"><?= $lang === 'am' ? 'ወንበር · ጥሪ · ጨርስ' : 'Chair · Call · Finish' ?></p>
                </div>
                <svg width="18" height="18" fill="none" stroke="#666" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>

            <a href="/login.php?next=admin" class="portal-card flex items-center gap-4 px-4 py-4">
                <div class="icon-box bg-[#713f12]">
                    <svg width="22" height="22" fill="none" stroke="#fbbf24" stroke-width="1.75" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-[17px]"><?= $lang === 'am' ? 'አስተዳዳሪ' : 'Admin Portal' ?></p>
                    <p class="text-[13px] text-white/40"><?= $lang === 'am' ? 'ገቢ · ወረፋ · ሰራተኞች' : 'Revenue · Queue · Staff' ?></p>
                </div>
                <svg width="18" height="18" fill="none" stroke="#666" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <p class="mt-10 text-[12px] text-white/30">
            <?= $lang === 'am' ? 'ወደ መነሻ ስክሪን ጨምረው እንደ መተግበሪያ ይክፈቱ' : 'Add to Home Screen for the full app experience' ?>
        </p>
    </div>

<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  });
}
</script>
</body>
</html>
