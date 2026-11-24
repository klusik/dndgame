<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Small route matcher with parameter support.
 */
final class Router
{
    /** @var array<int, array<string, mixed>> */
    private array $routes = [];

    /**
     * Store the application reference used for controller creation.
     */
    public function __construct(private Application $application)
    {
    }

    /**
     * Register a GET route.
     */
    public function get(string $pattern, string $controller, string $method, bool $requiresAuth = false): void
    {
        $this->add('GET', $pattern, $controller, $method, $requiresAuth);
    }

    /**
     * Register a POST route.
     */
    public function post(string $pattern, string $controller, string $method, bool $requiresAuth = false): void
    {
        $this->add('POST', $pattern, $controller, $method, $requiresAuth);
    }

    /**
     * Dispatch a request to a matching controller method.
     */
    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $params = $this->match((string) $route['pattern'], $request->path());
            if ($params === null) {
                continue;
            }

            if ($route['requires_auth'] && !$this->application->auth()->check()) {
                $this->application->session()->set('intended_url', $request->path());
                return Response::redirect(Url::to('/login'));
            }

            $controller = $this->application->makeController((string) $route['controller']);
            return $controller->{$route['handler']}($request, ...array_values($params));
        }

        return Response::notFound();
    }

    /**
     * Store route metadata.
     */
    private function add(string $method, string $pattern, string $controller, string $handler, bool $requiresAuth): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'controller' => $controller,
            'handler' => $handler,
            'requires_auth' => $requiresAuth,
        ];
    }

    /**
     * Match a route pattern and return named parameters.
     *
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $names = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $matches) use (&$names): string {
            $names[] = $matches[1];
            return '([^/]+)';
        }, $pattern);

        $regex = '#^' . $regex . '$#';
        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        array_shift($matches);
        $params = [];
        foreach ($names as $index => $name) {
            $params[$name] = $matches[$index] ?? '';
        }

        return $params;
    }
}
