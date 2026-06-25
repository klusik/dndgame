<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\SettingRepository;
use App\Services\GitHubUpdateService;

/**
 * Admin controller for updating the application from a GitHub archive.
 */
final class UpdaterController extends Controller
{
    /**
     * Save updater settings.
     */
    public function saveSettings(Request $request): Response
    {
        $this->requireCsrf($request);
        $this->requireAdmin();

        $repository = (string) $request->input('github_repository', '');
        $service = new GitHubUpdateService(APP_ROOT);
        $normalized = $service->normalizeRepository($repository);
        if ($normalized === '') {
            $this->app->session()->flash('error', 'Use GitHub repository format owner/repository.');
            return $this->redirect('/settings');
        }

        $settings = new SettingRepository($this->app->database());
        $settings->set('updater.github_repository', $normalized);
        $this->app->session()->flash('success', 'Updater settings saved.');
        return $this->redirect('/settings');
    }

    /**
     * Download and apply a GitHub update.
     */
    public function run(Request $request): Response
    {
        $this->requireCsrf($request);
        $this->requireAdmin();

        $settings = new SettingRepository($this->app->database());
        $repository = (string) $request->input('github_repository', (string) $settings->get('updater.github_repository', ''));
        $ref = (string) $request->input('github_ref', '');
        $confirm = (string) $request->input('confirm_update', '') === '1';

        if (!$confirm) {
            $this->app->session()->flash('error', 'Confirm the update before applying it.');
            return $this->redirect('/settings');
        }

        try {
            $service = new GitHubUpdateService(APP_ROOT);
            $result = $service->update($repository, $ref);
            $settings->set('updater.github_repository', (string) $result['repository']);
            $settings->set('updater.last_ref', (string) $result['ref']);
            $settings->set('updater.last_result_json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
            $this->app->session()->flash('success', sprintf('Update applied. %d files copied. Backup saved to %s.', (int) $result['files_copied'], (string) $result['backup_archive']));
        } catch (\Throwable $exception) {
            $this->app->session()->flash('error', 'Update failed: ' . $exception->getMessage());
        }

        return $this->redirect('/settings');
    }

    /**
     * Ensure the current user has admin rights.
     */
    private function requireAdmin(): void
    {
        $user = $this->app->auth()->user();
        if (!$user || (string) ($user['role'] ?? '') !== 'admin') {
            throw new \RuntimeException('Administrator access is required.');
        }
    }
}
