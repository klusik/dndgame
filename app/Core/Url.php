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
        return self::publicBasePath() . $path;
    }

    /**
     * Return the public URL base path used by the current request.
     *
     * Some shared hosts expose SCRIPT_NAME with a physical subdomain folder,
     * for example /subdom/example/index.php, while the public browser URL is
     * still rooted at /. The base path is therefore trusted only when it is
     * also present in REQUEST_URI.
     */
    public static function publicBasePath(): string
    {
        $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        $requestPath = is_string($requestPath) ? str_replace('\\', '/', $requestPath) : '';

        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $scriptBase = self::directoryBase($scriptName);
        if (self::requestUsesBasePath($requestPath, $scriptBase)) {
            return $scriptBase;
        }

        $phpSelf = str_replace('\\', '/', (string) ($_SERVER['PHP_SELF'] ?? ''));
        $phpSelfBase = self::directoryBase($phpSelf);
        if ($phpSelfBase !== $scriptBase && self::requestUsesBasePath($requestPath, $phpSelfBase)) {
            return $phpSelfBase;
        }

        return '';
    }

    /**
     * Return a normalized directory component suitable as a URL base path.
     */
    private static function directoryBase(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return ($base === '/' || $base === '.') ? '' : $base;
    }

    /**
     * Check whether the current request path really contains the base path.
     */
    private static function requestUsesBasePath(string $requestPath, string $basePath): bool
    {
        if ($basePath === '') {
            return false;
        }

        return $requestPath === $basePath || str_starts_with($requestPath, $basePath . '/');
    }
}
