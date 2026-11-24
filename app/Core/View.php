<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple PHP view renderer.
 */
final class View
{
    /**
     * Render a view file inside the default layout.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = []): string
    {
        $viewFile = APP_ROOT . '/resources/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = (string) ob_get_clean();

        ob_start();
        require APP_ROOT . '/resources/views/layout.php';
        return (string) ob_get_clean();
    }

    /**
     * Render a partial view without the main layout.
     *
     * @param array<string, mixed> $data
     */
    public static function partial(string $view, array $data = []): string
    {
        $viewFile = APP_ROOT . '/resources/views/' . $view . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException('Partial not found: ' . $view);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewFile;
        return (string) ob_get_clean();
    }
}
