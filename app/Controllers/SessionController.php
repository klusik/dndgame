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
use App\Repositories\SessionRepository;
use App\Repositories\SettingRepository;
use App\Services\CampaignPromptSettingsService;
use App\Services\CampaignSidePanelService;
use App\Services\FileStorage;
use App\Services\MarkdownRenderer;
use App\Services\RollRequirementDetector;

/**
 * Session browsing controller.
 */
final class SessionController extends Controller
{
    /**
     * List sessions for a campaign.
     */
    public function index(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $repo = new SessionRepository($this->app->database());
        return $this->render('sessions/index', ['campaign' => $campaign, 'sessions' => $repo->listByCampaign((int) $campaign['id'])]);
    }

    /**
     * Show one imported session.
     */
    public function show(Request $request, string $id, string $sessionId): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $repo = new SessionRepository($this->app->database());
        $session = $repo->find((int) $campaign['id'], (int) $sessionId);
        if (!$session) {
            throw new \RuntimeException('Session not found.');
        }

        $storage = new FileStorage(APP_ROOT);
        $renderer = new MarkdownRenderer();
        $narrative = $storage->read((string) $session['narrative_file']);
        $latestSession = $repo->latest((int) $campaign['id']);
        $isLatestTurn = $latestSession !== null && (int) $latestSession['id'] === (int) $session['id'];
        $rollRequirement = $isLatestTurn ? (new RollRequirementDetector())->detect($narrative) : (new RollRequirementDetector())->detect('');
        $promptSettings = (new CampaignPromptSettingsService(new SettingRepository($this->app->database())))->getForCampaign((int) $campaign['id'], $this->app->translator()->locale());
        $rollContextKey = sprintf('campaign-%d-session-%d', (int) $campaign['id'], (int) $session['id']);
        $sidePanel = (new CampaignSidePanelService(
            new EntityRepository($this->app->database()),
            new FactRepository($this->app->database()),
            new MysteryRepository($this->app->database()),
            $repo,
            $storage
        ))->build((int) $campaign['id']);

        return $this->render('sessions/show', [
            'campaign' => $campaign,
            'session' => $session,
            'narrativeHtml' => $renderer->render($narrative),
            'rawText' => $storage->read((string) $session['raw_transcript_file']),
            'rollRequirement' => $rollRequirement,
            'promptSettings' => $promptSettings,
            'rollContextKey' => $rollContextKey,
            'latestSession' => $latestSession,
            'isLatestTurn' => $isLatestTurn,
            'sidePanel' => $sidePanel,
        ]);
    }

    /**
     * Load a campaign or fail.
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
}
