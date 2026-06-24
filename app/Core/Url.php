<?php

declare(strict_types=1);

namespace App\Core;

/**
 * URL generation helper for root or subdirectory hosting.
 */
final class Url
{
    /**
     * Convert an application path to a browser URL.
     */
    public static function to(string $path): string
    {
        $path = '/' . ltrim($path, '/');
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
        if ($scriptDir === '/' || $scriptDir === '.') {
            $scriptDir = '';
        }
        return $scriptDir . $path;
    }
}
