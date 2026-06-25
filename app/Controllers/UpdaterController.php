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
        $tokenInput = (string) $request->input('github_token', '');
        $clearToken = (string) $request->input('clear_github_token', '') === '1';

        $service = new GitHubUpdateService(APP_ROOT);
        $normalizedRepository = $service->normalizeRepository($repository);
        if ($normalizedRepository === '') {
            $this->app->session()->flash('error', 'Use GitHub repository format owner/repository.');
            return $this->redirect('/settings');
        }

        $settings = new SettingRepository($this->app->database());
        $settings->set('updater.github_repository', $normalizedRepository);

        if ($clearToken) {
            $settings->set('updater.github_token', '');
        } elseif (trim($tokenInput) !== '') {
            $normalizedToken = $service->normalizeToken($tokenInput);
            if ($normalizedToken === '') {
                $this->app->session()->flash('error', 'GitHub token is invalid. Paste the token without spaces or line breaks.');
                return $this->redirect('/settings');
            }
            $settings->set('updater.github_token', $normalizedToken);
        }

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
        $token = (string) $settings->get('updater.github_token', '');
        $confirm = (string) $request->input('confirm_update', '') === '1';

        if (!$confirm) {
            $this->app->session()->flash('error', 'Confirm the update before applying it.');
            return $this->redirect('/settings');
        }

        try {
            $service = new GitHubUpdateService(APP_ROOT);
            $result = $service->update($repository, $ref, $token);
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
