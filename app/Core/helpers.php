<?php

declare(strict_types=1);

/**
 * Escape a value for HTML output.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Return a translated message.
 *
 * @param array<string, scalar> $replace
 */
function t(string $key, array $replace = []): string
{
    global $app;
    return $app instanceof App\Core\Application ? $app->translator()->get($key, $replace) : $key;
}

/**
 * Return a generated URL.
 */
function url(string $path): string
{
    return App\Core\Url::to($path);
}
