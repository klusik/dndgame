<?php

declare(strict_types=1);

namespace App\Services;

use ZipArchive;

/**
 * Downloads and applies application updates from a GitHub ZIP archive.
 *
 * The service intentionally avoids shell commands, git binaries, Composer, and
 * external libraries so it can run on ordinary PHP shared hosting.
 */
final class GitHubUpdateService
{
    /** @var array<int, string> */
    private array $excludedTopLevel = ['config.php', 'storage', 'deploy', '.git'];

    /**
     * Store the application root path.
     */
    public function __construct(private string $rootPath)
    {
    }

    /**
     * Download and apply a GitHub archive.
     *
     * @return array<string, mixed>
     */
    public function update(string $repository, string $ref, string $token = ''): array
    {
        $repository = $this->normalizeRepository($repository);
        $ref = $this->normalizeRef($ref);
        $token = $this->normalizeToken($token);

        if ($repository === '') {
            throw new \RuntimeException('GitHub repository must use owner/repository format.');
        }
        if ($ref === '') {
            throw new \RuntimeException('Git ref cannot be empty.');
        }
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('The PHP ZipArchive extension is required for the updater.');
        }

        $timestamp = date('Ymd-His');
        $workDir = $this->rootPath . '/storage/updates/update-' . $timestamp;
        $archivePath = $workDir . '/source.zip';
        $extractDir = $workDir . '/extract';
        $backupPath = $this->rootPath . '/storage/updates/backups/backup-' . $timestamp . '.zip';

        $this->ensureDirectory($workDir);
        $this->ensureDirectory($extractDir);
        $this->ensureDirectory(dirname($backupPath));

        $url = 'https://api.github.com/repos/' . $repository . '/zipball/' . rawurlencode($ref);
        $this->download($url, $archivePath, $token);
        $sourceRoot = $this->extractArchive($archivePath, $extractDir);

        $sourceFiles = $this->listFiles($sourceRoot);
        $copyableFiles = array_values(array_filter($sourceFiles, fn (string $path): bool => $this->isAllowedRelativePath($path)));
        $this->createBackup($copyableFiles, $backupPath);

        $copied = 0;
        foreach ($copyableFiles as $relativePath) {
            $source = $sourceRoot . '/' . $relativePath;
            $target = $this->rootPath . '/' . $relativePath;
            $this->ensureDirectory(dirname($target));
            if (!copy($source, $target)) {
                throw new \RuntimeException('Failed to copy updated file: ' . $relativePath);
            }
            $copied++;
        }

        return [
            'repository' => $repository,
            'ref' => $ref,
            'private_token_used' => $token !== '',
            'files_copied' => $copied,
            'downloaded_archive' => $this->relativeToRoot($archivePath),
            'backup_archive' => $this->relativeToRoot($backupPath),
            'work_directory' => $this->relativeToRoot($workDir),
        ];
    }

    /**
     * Normalize and validate owner/repository input.
     */
    public function normalizeRepository(string $repository): string
    {
        $repository = trim($repository);
        $repository = preg_replace('#^https?://github\.com/#i', '', $repository) ?? $repository;
        $repository = trim($repository, " \t\n\r\0\x0B/");
        $repository = preg_replace('#\.git$#i', '', $repository) ?? $repository;

        return preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository) ? $repository : '';
    }

    /**
     * Normalize a git ref, tag, branch, or commit SHA.
     */
    public function normalizeRef(string $ref): string
    {
        $ref = trim($ref);
        if ($ref === '') {
            return '';
        }

        return preg_match('#^[A-Za-z0-9._/\-]+$#', $ref) ? $ref : '';
    }

    /**
     * Normalize a GitHub access token without exposing it anywhere.
     */
    public function normalizeToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (strlen($token) > 512 || preg_match('/\s/', $token)) {
            return '';
        }

        return $token;
    }

    /**
     * Download a remote archive using curl when available, otherwise streams.
     */
    private function download(string $url, string $targetPath, string $token): void
    {
        $headers = [
            'User-Agent: DnD-ChatGPT-Campaign-Updater',
            'Accept: application/vnd.github+json, application/zip',
            'X-GitHub-Api-Version: 2022-11-28',
        ];
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        if (function_exists('curl_init')) {
            $handle = curl_init($url);
            $file = fopen($targetPath, 'wb');
            if (!$handle || !$file) {
                throw new \RuntimeException('Failed to initialize download.');
            }
            curl_setopt_array($handle, [
                CURLOPT_FILE => $file,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FAILONERROR => true,
                CURLOPT_TIMEOUT => 120,
            ]);
            $ok = curl_exec($handle);
            $error = curl_error($handle);
            $httpCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            curl_close($handle);
            fclose($file);

            if (!$ok) {
                @unlink($targetPath);
                $detail = $error !== '' ? $error : 'HTTP ' . $httpCode;
                throw new \RuntimeException('GitHub download failed: ' . $detail);
            }
            return;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'follow_location' => 1,
                'timeout' => 120,
            ],
        ]);
        $data = @file_get_contents($url, false, $context);
        if (!is_string($data) || $data === '') {
            throw new \RuntimeException('GitHub download failed. Enable curl or allow_url_fopen. For private repositories, save a token with read access.');
        }
        file_put_contents($targetPath, $data);
    }

    /**
     * Extract an archive and return its single top-level source directory.
     */
    private function extractArchive(string $archivePath, string $extractDir): string
    {
        $zip = new ZipArchive();
        if ($zip->open($archivePath) !== true) {
            throw new \RuntimeException('Downloaded archive is not a valid ZIP file. Check the repository, ref, and token permissions.');
        }
        if (!$zip->extractTo($extractDir)) {
            $zip->close();
            throw new \RuntimeException('Failed to extract downloaded archive.');
        }
        $zip->close();

        $items = array_values(array_filter(scandir($extractDir) ?: [], static fn (string $item): bool => $item !== '.' && $item !== '..'));
        foreach ($items as $item) {
            $path = $extractDir . '/' . $item;
            if (is_dir($path)) {
                return $path;
            }
        }

        throw new \RuntimeException('Extracted archive does not contain a source directory.');
    }

    /**
     * Create a backup archive with files that will be overwritten.
     *
     * @param array<int, string> $relativeFiles
     */
    private function createBackup(array $relativeFiles, string $backupPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create backup ZIP.');
        }

        $manifest = [];
        foreach ($relativeFiles as $relativePath) {
            $target = $this->rootPath . '/' . $relativePath;
            if (!is_file($target)) {
                continue;
            }
            $zip->addFile($target, $relativePath);
            $manifest[] = $relativePath;
        }

        $zip->addFromString('backup_manifest.json', json_encode([
            'created_at' => date('c'),
            'application_root' => $this->rootPath,
            'files' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        $zip->close();
    }

    /**
     * List all files under a directory as relative paths.
     *
     * @return array<int, string>
     */
    private function listFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }
            $absolute = str_replace('\\', '/', $fileInfo->getPathname());
            $relative = ltrim(substr($absolute, strlen(str_replace('\\', '/', $root))), '/');
            if ($relative !== '') {
                $files[] = $relative;
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Check whether a relative archive path is safe to apply.
     */
    private function isAllowedRelativePath(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '../') || str_starts_with($relativePath, '/')) {
            return false;
        }

        $topLevel = explode('/', $relativePath, 2)[0];
        if (in_array($topLevel, $this->excludedTopLevel, true)) {
            return false;
        }

        $basename = basename($relativePath);
        if (in_array($basename, ['.env', 'install.lock'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Ensure a directory exists.
     */
    private function ensureDirectory(string $path): void
    {
        if (is_dir($path)) {
            return;
        }
        if (!mkdir($path, 0775, true) && !is_dir($path)) {
            throw new \RuntimeException('Failed to create directory: ' . $path);
        }
    }

    /**
     * Convert an absolute path to an application-relative path.
     */
    private function relativeToRoot(string $path): string
    {
        $root = rtrim(str_replace('\\', '/', $this->rootPath), '/') . '/';
        $normalized = str_replace('\\', '/', $path);
        return str_starts_with($normalized, $root) ? substr($normalized, strlen($root)) : $normalized;
    }
}
