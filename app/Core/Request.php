<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable wrapper around the current HTTP request.
 */
final class Request
{
    /**
     * Create a request object from PHP superglobals.
     */
    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $scriptDir = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');

        if ($scriptDir !== '' && $scriptDir !== '/' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        $path = '/' . trim($path, '/');
        if ($path === '//') {
            $path = '/';
        }

        return new self($method, $path, $_GET, $_POST, $_FILES);
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, mixed> $files
     */
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $post,
        private array $files
    ) {
    }

    /**
     * Return the normalized request method.
     */
    public function method(): string
    {
        return $this->method;
    }

    /**
     * Return the normalized request path.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Return a submitted value.
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    /**
     * Return all submitted POST values.
     *
     * @return array<string, mixed>
     */
    public function post(): array
    {
        return $this->post;
    }

    /**
     * Return all query values.
     *
     * @return array<string, mixed>
     */
    public function query(): array
    {
        return $this->query;
    }
}
