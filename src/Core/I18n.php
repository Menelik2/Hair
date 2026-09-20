<?php
declare(strict_types=1);

namespace App\Core;

final class I18n
{
    private const SUPPORTED = ['en', 'am'];
    private const DEFAULT   = 'en';
    private const COOKIE    = 'hq_lang';
    private const COOKIE_TTL = 60 * 60 * 24 * 365;

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
        $candidate = $_GET['lang'] ?? $_SESSION['lang'] ?? $_COOKIE[self::COOKIE] ?? self::DEFAULT;
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
            'expires' => time() + self::COOKIE_TTL,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public static function getLocale(): string { return self::$locale; }
    public static function isAmharic(): bool { return self::$locale === 'am'; }

    public static function t(string $key, array $replace = []): string
    {
        self::loadMessages();
        $text = self::$messages[self::$locale][$key] ?? self::$messages[self::DEFAULT][$key] ?? $key;
        foreach ($replace as $search => $value) {
            $text = str_replace(':' . $search, (string)$value, $text);
        }
        return $text;
    }

    public static function col(string $base): string
    {
        return $base . '_' . self::$locale;
    }

    private static function loadMessages(): void
    {
        if (!empty(self::$messages)) return;

        self::$messages = [
            'en' => [
                'app.name' => 'Elite Cuts', 'app.tagline' => 'Premium barbershop',
                'nav.queue' => 'Queue', 'nav.book' => 'Book', 'nav.profile' => 'Profile',
                'nav.back' => 'Back', 'nav.login' => 'Sign In', 'nav.logout' => 'Sign Out', 'nav.kiosk' => 'Customer Kiosk',
                'common.continue' => 'Continue', 'common.cancel' => 'Cancel', 'common.confirm' => 'Confirm',
                'common.save' => 'Save', 'common.loading' => 'Loading…', 'common.error' => 'Something went wrong',
                'common.min' => 'min', 'common.etb' => 'ETB', 'common.any_barber' => 'Any Barber',
                'common.name' => 'Full Name', 'common.phone' => 'Phone', 'common.password' => 'Password',
                'common.date' => 'Date', 'common.time' => 'Time', 'common.service' => 'Service', 'common.optional' => 'Optional',
                'queue.title' => 'Join the Queue', 'queue.select_service' => 'Select Services',
                'queue.select_stylist' => 'Choose Your Barber', 'queue.first_available' => 'First Available',
                'queue.fastest' => 'Fastest option', 'queue.your_name' => 'Your Name', 'queue.your_phone' => 'Your Phone Number',
                'queue.join' => 'Get My Ticket', 'queue.total' => 'Total', 'queue.duration' => 'Duration',
                'queue.paused' => 'Walk-in queue is temporarily paused. Please try again later.',
                'queue.next' => 'Next', 'queue.step_services' => 'Services', 'queue.step_barber' => 'Barber', 'queue.step_details' => 'Details',
                'ticket.title' => 'Your Ticket', 'ticket.you_are' => 'You are', 'ticket.in_line' => 'in line',
                'ticket.estimated_wait' => 'Estimated wait', 'ticket.cancel' => 'Cancel My Ticket',
                'ticket.cancel_confirm' => 'Cancel your ticket?', 'ticket.cancel_yes' => 'Yes, Cancel',
                'ticket.called' => "It's your turn!", 'ticket.called_go' => 'Please proceed to Chair',
                'ticket.in_chair_msg' => "You're in the chair", 'ticket.enjoy' => 'Enjoy your cut',
                'ticket.completed' => 'Service completed. Thank you!', 'ticket.cancelled' => 'Ticket cancelled',
                'ticket.customer' => 'Customer', 'ticket.phone' => 'Phone', 'ticket.services' => 'Services', 'ticket.code' => 'Ticket',
                'status.waiting' => 'Waiting', 'status.called' => 'Called', 'status.in_chair' => 'In Chair',
                'status.completed' => 'Completed', 'status.cancelled' => 'Cancelled',
                'appt.title' => 'Book an Appointment', 'appt.subtitle' => 'Reserve your preferred time and barber',
                'appt.confirm' => 'Confirm Appointment', 'appt.success' => 'Appointment Booked!',
                'appt.success_msg' => "We'll see you then.", 'appt.walkin' => 'Prefer to walk in?',
                'appt.join_queue' => 'Join the queue instead', 'appt.preferred_barber' => 'Preferred Barber',
                'appt.select_service' => 'Select a service', 'appt.any' => 'Any Available',
                'profile.title' => 'My Profile', 'profile.sign_in' => 'Sign In', 'profile.register' => 'Register',
                'profile.create' => 'Create Account', 'profile.cuts' => 'Cuts', 'profile.spent' => 'ETB Spent',
                'profile.favorite' => 'Favorite', 'profile.history' => 'Recent Visits',
                'profile.no_history' => 'No completed visits yet.', 'profile.logout' => 'Sign Out',
                'login.title' => 'Staff & Admin Login', 'login.submit' => 'Sign In', 'login.invalid' => 'Invalid phone or password',
                'station.call_next' => 'Call Next', 'station.finish' => 'Finish & Pay', 'station.start' => 'Start',
                'station.walk_in' => 'Walk-In', 'station.up_next' => 'Up Next', 'station.active' => 'Active',
                'station.break' => 'Break', 'station.offline' => 'Off Duty', 'station.no_waiting' => 'No customers waiting', 'station.timer' => 'Elapsed',
                'board.now_serving' => 'Now Serving', 'board.up_next' => 'Up Next', 'board.live' => 'Live', 'board.chair' => 'Chair',
                'admin.dashboard' => 'Admin', 'admin.revenue' => 'Daily Revenue', 'admin.served' => 'Customers Served',
                'admin.queue' => 'Active Queue', 'admin.avg_wait' => 'Avg Wait Time',
                'admin.pause' => 'Pause Walk-In Queue', 'admin.resume' => 'Resume Walk-In Queue',
            ],
            'am' => [
                'app.name' => 'ኤሊት ካትስ', 'app.tagline' => 'የላቀ የጸጉር ቤት',
                'nav.queue' => 'ወረፋ', 'nav.book' => 'ቀጠሮ', 'nav.profile' => 'መገለጫ',
                'nav.back' => 'ተመለስ', 'nav.login' => 'ግባ', 'nav.logout' => 'ውጣ', 'nav.kiosk' => 'የደንበኛ አገልግሎት',
                'common.continue' => 'ቀጥል', 'common.cancel' => 'ሰርዝ', 'common.confirm' => 'አረጋግጥ',
                'common.save' => 'አስቀምጥ', 'common.loading' => 'በመጫን ላይ…', 'common.error' => 'ችግር ተፈጥሯል',
                'common.min' => 'ደቂቃ', 'common.etb' => 'ብር', 'common.any_barber' => 'ማንኛውም ሰራተኛ',
                'common.name' => 'ሙሉ ስም', 'common.phone' => 'ስልክ ቁጥር', 'common.password' => 'የይለፍ ቃል',
                'common.date' => 'ቀን', 'common.time' => 'ሰዓት', 'common.service' => 'አገልግሎት', 'common.optional' => 'አማራጭ',
                'queue.title' => 'ወደ ወረፋ ይግቡ', 'queue.select_service' => 'አገልግሎት ይምረጡ',
                'queue.select_stylist' => 'ሰራተኛ ይምረጡ', 'queue.first_available' => 'በፍጥነት የሚገኝ',
                'queue.fastest' => 'ፈጣኑ አማራጭ', 'queue.your_name' => 'ስምዎ', 'queue.your_phone' => 'ስልክ ቁጥርዎ',
                'queue.join' => 'ቁጥር ይውሰዱ', 'queue.total' => 'ድምር', 'queue.duration' => 'ጊዜ',
                'queue.paused' => 'ወረፋው ለጊዜው ቆሟል። እባክዎ ትንሽ ቆይተው ይሞክሩ።',
                'queue.next' => 'ቀጣይ', 'queue.step_services' => 'አገልግሎቶች', 'queue.step_barber' => 'ሰራተኛ', 'queue.step_details' => 'መረጃ',
                'ticket.title' => 'የእርስዎ ቁጥር', 'ticket.you_are' => 'እርስዎ', 'ticket.in_line' => 'ኛ በተራ ነዎት',
                'ticket.estimated_wait' => 'የሚጠበቅ ጊዜ', 'ticket.cancel' => 'ቁጥሬን ሰርዝ',
                'ticket.cancel_confirm' => 'ቁጥርዎን መሰረዝ ይፈልጋሉ?', 'ticket.cancel_yes' => 'አዎ፣ ሰርዝ',
                'ticket.called' => 'ተራዎ ደርሷል!', 'ticket.called_go' => 'እባክዎ ወደ ወንበር',
                'ticket.in_chair_msg' => 'አሁን በአገልግሎት ላይ ነዎት', 'ticket.enjoy' => 'ጥሩ አገልግሎት!',
                'ticket.completed' => 'አገልግሎቱ ተጠናቋል። እናመሰግናለን!', 'ticket.cancelled' => 'ቁጥሩ ተሰርዟል',
                'ticket.customer' => 'ደንበኛ', 'ticket.phone' => 'ስልክ', 'ticket.services' => 'አገልግሎቶች', 'ticket.code' => 'ቁጥር',
                'status.waiting' => 'በመጠበቅ ላይ', 'status.called' => 'ተጠርቷል', 'status.in_chair' => 'በአገልግሎት ላይ',
                'status.completed' => 'ተጠናቋል', 'status.cancelled' => 'ተሰርዟል',
                'appt.title' => 'ቀጠሮ ይያዙ', 'appt.subtitle' => 'የሚፈልጉትን ቀን፣ ሰዓትና ሰራተኛ ይምረጡ',
                'appt.confirm' => 'ቀጠሮውን አረጋግጥ', 'appt.success' => 'ቀጠሮዎ ተይዟል!',
                'appt.success_msg' => 'በተያዘው ሰዓት እንጠብቅዎታለን።', 'appt.walkin' => 'ያለ ቀጠሮ መምጣት ይፈልጋሉ?',
                'appt.join_queue' => 'ወደ ወረፋ ይግቡ', 'appt.preferred_barber' => 'የሚመርጡት ሰራተኛ',
                'appt.select_service' => 'አገልግሎት ይምረጡ', 'appt.any' => 'ማንኛውም የሚገኝ',
                'profile.title' => 'የእኔ መገለጫ', 'profile.sign_in' => 'ግባ', 'profile.register' => 'ተመዝገብ',
                'profile.create' => 'መለያ ፍጠር', 'profile.cuts' => 'ጸጉር ቁረጦች', 'profile.spent' => 'ያወጡት ብር',
                'profile.favorite' => 'ተወዳጅ ሰራተኛ', 'profile.history' => 'የቅርብ ጊዜ ጉብኝቶች',
                'profile.no_history' => 'እስካሁን የተጠናቀቀ ጉብኝት የለም።', 'profile.logout' => 'ውጣ',
                'login.title' => 'የሰራተኛና አስተዳዳሪ መግቢያ', 'login.submit' => 'ግባ',
                'login.invalid' => 'ስልክ ቁጥር ወይም የይለፍ ቃል ትክክል አይደለም',
                'station.call_next' => 'ቀጣዩን ጥራ', 'station.finish' => 'ጨርስ', 'station.start' => 'ጀምር',
                'station.walk_in' => 'ያለ ቀጠሮ', 'station.up_next' => 'ቀጣይ', 'station.active' => 'በስራ ላይ',
                'station.break' => 'እረፍት', 'station.offline' => 'ከስራ ውጭ',
                'station.no_waiting' => 'በመጠበቅ ላይ ያለ ደንበኛ የለም', 'station.timer' => 'ያለፈ ጊዜ',
                'board.now_serving' => 'አሁን በአገልግሎት', 'board.up_next' => 'ቀጣይ ተራ', 'board.live' => 'ቀጥታ', 'board.chair' => 'ወንበር',
                'admin.dashboard' => 'አስተዳዳሪ', 'admin.revenue' => 'የዕለቱ ገቢ', 'admin.served' => 'ያገለገሉ ደንበኞች',
                'admin.queue' => 'ንቁ ወረፋ', 'admin.avg_wait' => 'አማካይ የመጠበቅ ጊዜ',
                'admin.pause' => 'ወረፋውን አቁም', 'admin.resume' => 'ወረፋውን ቀጥል',
            ],
        ];
    }
}
