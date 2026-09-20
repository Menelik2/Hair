<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight bilingual (English / Amharic) internationalization helper.
 * Persists language preference via cookie + session.
 */
final class I18n
{
    private const SUPPORTED = ['en', 'am'];
    private const DEFAULT   = 'en';
    private const COOKIE    = 'hq_lang';
    private const COOKIE_TTL = 60 * 60 * 24 * 365; // 1 year

    private static string $locale = self::DEFAULT;

    /** @var array<string, array<string, string>> */
    private static array $messages = [];

    public static function boot(?string $forceLocale = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($forceLocale && in_array($forceLocale, self::SUPPORTED, true)) {
            self::setLocale($forceLocale);
            return;
        }

        // Priority: query param → session → cookie → default
        $fromQuery  = $_GET['lang'] ?? null;
        $fromSession = $_SESSION['lang'] ?? null;
        $fromCookie = $_COOKIE[self::COOKIE] ?? null;

        $candidate = $fromQuery ?? $fromSession ?? $fromCookie ?? self::DEFAULT;

        self::setLocale(in_array($candidate, self::SUPPORTED, true) ? $candidate : self::DEFAULT);
    }

    public static function setLocale(string $locale): void
    {
        if (!in_array($locale, self::SUPPORTED, true)) {
            $locale = self::DEFAULT;
        }

        self::$locale = $locale;
        $_SESSION['lang'] = $locale;

        setcookie(self::COOKIE, $locale, [
            'expires'  => time() + self::COOKIE_TTL,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function getLocale(): string
    {
        return self::$locale;
    }

    public static function isAmharic(): bool
    {
        return self::$locale === 'am';
    }

    /**
     * Translate a key. Supports simple :placeholder replacement.
     */
    public static function t(string $key, array $replace = []): string
    {
        self::loadMessages();

        $text = self::$messages[self::$locale][$key]
            ?? self::$messages[self::DEFAULT][$key]
            ?? $key;

        foreach ($replace as $search => $value) {
            $text = str_replace(':' . $search, (string)$value, $text);
        }

        return $text;
    }

    /**
     * Helper for bilingual column selection (name_en / name_am).
     */
    public static function col(string $base): string
    {
        return $base . '_' . self::$locale;
    }

    private static function loadMessages(): void
    {
        if (!empty(self::$messages)) {
            return;
        }

        self::$messages = [
            'en' => [
                'app.name'              => 'Elite Cuts',
                'queue.title'           => 'Join the Queue',
                'queue.select_service'  => 'Select Services',
                'queue.select_stylist'  => 'Choose Your Barber',
                'queue.first_available' => 'First Available',
                'queue.your_phone'      => 'Your Phone Number',
                'queue.join'            => 'Get My Ticket',
                'queue.total'           => 'Total',
                'queue.duration'        => 'Duration',
                'ticket.you_are'        => 'You are',
                'ticket.in_line'        => 'in line',
                'ticket.estimated_wait' => 'Estimated wait',
                'ticket.cancel'         => 'Cancel My Ticket',
                'ticket.called'         => 'Please proceed to your chair',
                'status.waiting'        => 'Waiting',
                'status.called'         => 'Called',
                'status.in_chair'       => 'In Chair',
                'status.completed'      => 'Completed',
                'status.cancelled'      => 'Cancelled',
                'min'                   => 'min',
                'etb'                   => 'ETB',
                'any_barber'            => 'Any Barber',
            ],
            'am' => [
                'app.name'              => 'ኤሊት ካትስ',
                'queue.title'           => 'ወረፋ ውስጥ ይግቡ',
                'queue.select_service'  => 'አገልግሎቶችን ይምረጡ',
                'queue.select_stylist'  => 'ባርበርዎን ይምረጡ',
                'queue.first_available' => 'መጀመሪያ የሚገኝ',
                'queue.your_phone'      => 'ስልክ ቁጥርዎ',
                'queue.join'            => 'ቲኬቴን አግኝ',
                'queue.total'           => 'ጠቅላላ',
                'queue.duration'        => 'ጊዜ',
                'ticket.you_are'        => 'እርስዎ',
                'ticket.in_line'        => 'ኛ በመስመር ላይ ነዎት',
                'ticket.estimated_wait' => 'የሚጠበቅ ጊዜ',
                'ticket.cancel'         => 'ቲኬቴን ሰርዝ',
                'ticket.called'         => 'እባክዎ ወደ ወንበርዎ ይሂዱ',
                'status.waiting'        => 'በመጠበቅ ላይ',
                'status.called'         => 'ተጠርቷል',
                'status.in_chair'       => 'በወንበር ላይ',
                'status.completed'      => 'ተጠናቋል',
                'status.cancelled'      => 'ተሰርዟል',
                'min'                   => 'ደቂቃ',
                'etb'                   => 'ብር',
                'any_barber'            => 'ማንኛውም ባርበር',
            ],
        ];
    }
}
