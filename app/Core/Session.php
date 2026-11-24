<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Session helper with flash message support.
 */
final class Session
{
    /**
     * Store the configured session name.
     */
    public function __construct(private string $name)
    {
    }

    /**
     * Start the PHP session if needed.
     */
    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name($this->name);
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }

    /**
     * Regenerate the session identifier.
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Get a session value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Store a session value.
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a session value.
     */
    public function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Store a one-time flash message.
     */
    public function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /**
     * Return and clear flash messages.
     *
     * @return array<int, array{type:string,message:string}>
     */
    public function consumeFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }
}
