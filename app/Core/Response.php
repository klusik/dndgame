<?php

declare(strict_types=1);

namespace App\Core;

/**
 * HTTP response value object.
 */
final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /**
     * Create a response with a status code and body.
     */
    public function __construct(private string $body = '', private int $status = 200)
    {
    }

    /**
     * Create a redirect response.
     */
    public static function redirect(string $location): self
    {
        $response = new self('', 302);
        $response->headers['Location'] = $location;
        return $response;
    }

    /**
     * Create a simple not found response.
     */
    public static function notFound(): self
    {
        return new self('Not found', 404);
    }

    /**
     * Add or replace a response header.
     */
    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Send headers and body to the client.
     */
    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }
}
