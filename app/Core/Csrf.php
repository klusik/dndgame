<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token generation and verification.
 */
final class Csrf
{
    /**
     * Return the current token or create a new one.
     */
    public static function token(Session $session): string
    {
        $token = $session->get('_csrf_token');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $session->set('_csrf_token', $token);
        }

        return $token;
    }

    /**
     * Verify a submitted token.
     */
    public static function check(Session $session, string $token): bool
    {
        $known = $session->get('_csrf_token');
        return is_string($known) && hash_equals($known, $token);
    }

    /**
     * Return a hidden CSRF input field.
     */
    public static function field(Session $session): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token($session)) . '">';
    }
}
