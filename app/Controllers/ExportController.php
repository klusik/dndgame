<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CampaignRepository;
use App\Repositories\EntityRepository;
use App\Repositories\FactRepository;
use App\Repositories\MysteryRepository;
use App\Repositories\PromptRepository;
use App\Repositories\SessionRepository;
use App\Services\CampaignHtmlExportService;
use App\Services\FileStorage;
use App\Services\MarkdownRenderer;

/**
 * Handles downloadable campaign exports.
 */
final class ExportController extends Controller
{
    /**
     * Download a standalone readable HTML export of the whole campaign.
     */
    public function html(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $html = $this->exportService()->build((int) $campaign['id']);
        $filename = $this->safeFilename((string) $campaign['title']) . '-campaign-export.html';

        return (new Response($html))
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * Load a campaign owned by or shared with the current user.
     *
     * @return array<string, mixed>
     */
    private function requireCampaign(int $id): array
    {
        $user = $this->app->auth()->user();
        $campaign = (new CampaignRepository($this->app->database()))->findForUser($id, (int) $user['id']);
        if (!$campaign) {
            throw new \RuntimeException('Campaign not found.');
        }
        return $campaign;
    }

    /**
     * Create the HTML export service.
     */
    private function exportService(): CampaignHtmlExportService
    {
        return new CampaignHtmlExportService(
            new FileStorage(APP_ROOT),
            new CampaignRepository($this->app->database()),
            new SessionRepository($this->app->database()),
            new PromptRepository($this->app->database()),
            new EntityRepository($this->app->database()),
            new FactRepository($this->app->database()),
            new MysteryRepository($this->app->database()),
            new MarkdownRenderer()
        );
    }

    /**
     * Convert a campaign title into a conservative downloadable filename.
     */
    private function safeFilename(string $title): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title) ?: $title;
        $filename = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii));
        $filename = trim($filename, '-');
        return $filename !== '' ? $filename : 'campaign';
    }
}
