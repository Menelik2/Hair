<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Auth
{
    private const SESSION_KEY = 'auth_user';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 86400 * 7,
                'path'     => '/',
                'httponly'  => true,
                'samesite'  => 'Lax',
            ]);
            session_start();
        }
    }

    /** @return list<string> */
    public static function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return [];
        }
        return array_values(array_unique(array_filter([
            $phone,
            $digits,
            '+' . $digits,
            (str_starts_with($digits, '251') ? '0' . substr($digits, 3) : null),
            (str_starts_with($digits, '0') ? '251' . substr($digits, 1) : null),
            (str_starts_with($digits, '0') ? '+251' . substr($digits, 1) : null),
            (str_starts_with($digits, '251') ? '+251' . substr($digits, 3) : null),
        ])));
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public static function attempt(string $phone, string $password): ?array
    {
        $candidates = self::phoneVariants($phone);
        if ($candidates === []) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($candidates), '?'));
        $user = Database::fetch(
            "SELECT * FROM users WHERE phone IN ($placeholders) LIMIT 1",
            $candidates
        );

        if (!$user || empty($user['password_hash'])) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        self::login($user);
        return $user;
    }

    public static function login(array $user): void
    {
        self::startSession();
        $_SESSION[self::SESSION_KEY] = [
            'id'         => (int)$user['id'],
            'full_name'  => $user['full_name'],
            'phone'      => $user['phone'],
            'role'       => $user['role'],
            'lang'       => $user['preferred_lang'] ?? 'en',
            'member_tier'=> $user['member_tier'] ?? 'regular',
        ];
    }

    public static function logout(): void
    {
        self::startSession();
        unset($_SESSION[self::SESSION_KEY]);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();
        return !empty($_SESSION[self::SESSION_KEY]['id']);
    }

    public static function user(): ?array
    {
        self::startSession();
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function role(): ?string
    {
        $u = self::user();
        return $u['role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function isStylist(): bool
    {
        return self::role() === 'stylist';
    }

    public static function requireRole(string|array $roles, bool $json = false): void
    {
        $roles = (array)$roles;
        if (!self::check() || !in_array(self::role(), $roles, true)) {
            if ($json) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }
            header('Location: /login.php');
            exit;
        }
    }

    public static function register(string $name, string $phone, string $password, string $lang = 'en'): array
    {
        $phone = self::normalizePhone($phone);
        if (strlen($phone) < 9) {
            throw new RuntimeException('Invalid phone number.');
        }
        if (strlen($password) < 6) {
            throw new RuntimeException('Password must be at least 6 characters.');
        }

        $variants = self::phoneVariants($phone);
        $placeholders = implode(',', array_fill(0, count($variants), '?'));
        $exists = Database::fetch("SELECT id FROM users WHERE phone IN ($placeholders)", $variants);
        if ($exists) {
            throw new RuntimeException('Phone number already registered.');
        }

        $hash = password_hash($password, PASSWORD_ARGON2ID);

        $id = Database::insert(
            "INSERT INTO users (full_name, phone, password_hash, role, preferred_lang, member_tier, created_at)
             VALUES (?, ?, ?, 'customer', ?, 'regular', NOW())",
            [trim($name), $phone, $hash, $lang]
        );

        $user = Database::fetch("SELECT * FROM users WHERE id = ?", [$id]);
        self::login($user);
        return $user;
    }
}
