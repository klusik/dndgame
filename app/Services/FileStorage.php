<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Safe file storage helper for campaign text and JSON mirrors.
 */
final class FileStorage
{
    /**
     * Store the application root path.
     */
    public function __construct(private string $rootPath)
    {
    }

    /**
     * Write text to a relative path using an atomic replace.
     */
    public function write(string $relativePath, string $content): string
    {
        $relativePath = $this->normalizeRelativePath($relativePath);
        $absolutePath = $this->absolute($relativePath);
        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $temporary = $absolutePath . '.tmp-' . bin2hex(random_bytes(4));
        file_put_contents($temporary, $content, LOCK_EX);
        rename($temporary, $absolutePath);

        return $relativePath;
    }

    /**
     * Read a relative file path if it exists.
     */
    public function read(?string $relativePath): string
    {
        if ($relativePath === null || $relativePath === '') {
            return '';
        }

        $absolutePath = $this->absolute($this->normalizeRelativePath($relativePath));
        return is_file($absolutePath) ? (string) file_get_contents($absolutePath) : '';
    }

    /**
     * Write JSON with stable formatting.
     *
     * @param array<string, mixed>|array<int, mixed> $data
     */
    public function writeJson(string $relativePath, array $data): string
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new \RuntimeException('JSON encoding failed.');
        }

        return $this->write($relativePath, $json . PHP_EOL);
    }

    /**
     * Return an absolute path from a safe relative path.
     */
    public function absolute(string $relativePath): string
    {
        return rtrim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR . $this->normalizeRelativePath($relativePath);
    }

    /**
     * Ensure a directory exists.
     */
    public function ensureDirectory(string $relativePath): void
    {
        $absolute = $this->absolute($relativePath);
        if (!is_dir($absolute)) {
            mkdir($absolute, 0775, true);
        }
    }

    /**
     * Normalize and validate a relative path.
     */
    private function normalizeRelativePath(string $relativePath): string
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        $relativePath = ltrim($relativePath, '/');
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException('Unsafe storage path.');
        }
        return $relativePath;
    }
}
