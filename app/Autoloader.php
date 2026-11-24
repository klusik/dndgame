<?php

declare(strict_types=1);

namespace App;

/**
 * Minimal PSR-4 style autoloader for the App namespace.
 */
final class Autoloader
{
    /**
     * Register the autoloader for application classes.
     */
    public static function register(string $basePath): void
    {
        spl_autoload_register(static function (string $class) use ($basePath): void {
            $prefix = 'App\\';
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                return;
            }

            $relative = substr($class, strlen($prefix));
            $file = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }
}
