<?php

declare(strict_types=1);

namespace Blueprint;

/**
 * The panel session. Without a session cookie, no session is started (anonymous visitors).
 */
class Session
{
    public const CSRF_KEY = "blueprint.csrf";

    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_NONE || headers_sent()) {
            return;
        }

        session_set_cookie_params([
            "httponly" => true,
            "samesite" => "Lax",
            "secure" => ($_SERVER["HTTPS"] ?? "off") !== "off",
            "path" => "/",
        ]);
        session_start();
    }

    /**
     * Resumes an existing session, but never creates a new one.
     */
    public static function resume(): void
    {
        if (isset($_COOKIE[session_name()])) {
            self::start();
        }
    }

    public static function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public static function login(User $user): void
    {
        self::start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION[User::SESSION_KEY] = $user->uid();
        $_SESSION[self::CSRF_KEY] = bin2hex(random_bytes(32));
    }

    public static function logout(): void
    {
        $_SESSION = [];
        User::setCurrent(null);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function csrfToken(): ?string
    {
        return self::get(self::CSRF_KEY);
    }

    public static function isValidCsrfToken(?string $token): bool
    {
        $expected = self::csrfToken();

        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
