<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller with common rendering and redirect helpers.
 */
abstract class Controller
{
    /**
     * Store the application reference.
     */
    public function __construct(protected Application $app)
    {
    }

    /**
     * Render a view inside the main layout.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $view, array $data = []): Response
    {
        $body = View::render($view, $data + ['app' => $this->app]);
        return new Response($body);
    }

    /**
     * Return a redirect response.
     */
    protected function redirect(string $path): Response
    {
        return Response::redirect(Url::to($path));
    }

    /**
     * Validate CSRF token from a POST request.
     */
    protected function requireCsrf(Request $request): void
    {
        $token = (string) $request->input('_csrf', '');
        if (!Csrf::check($this->app->session(), $token)) {
            throw new \RuntimeException('Invalid CSRF token.');
        }
    }
}
