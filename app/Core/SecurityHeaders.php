<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sends conservative HTTP security headers.
 */
final class SecurityHeaders
{
    /**
     * Send headers that are safe for this application.
     */
    public static function send(): void
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; img-src 'self' data:");
    }
}
