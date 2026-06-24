<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CampaignRepository;
use App\Repositories\PromptRepository;
use App\Repositories\SessionRepository;
use App\Repositories\SettingRepository;
use App\Services\CampaignPromptSettingsService;
use App\Services\FileStorage;
use App\Services\MarkdownRenderer;
use App\Services\RollRequirementDetector;

/**
 * Campaign play workflow controller.
 */
final class PlayController extends Controller
{
    /**
     * Show the guided play workflow for one campaign.
     */
    public function show(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $storage = new FileStorage(APP_ROOT);
        $promptRepository = new PromptRepository($this->app->database());
        $sessionRepository = new SessionRepository($this->app->database());

        $latestSession = $sessionRepository->latest((int) $campaign['id']);
        $latestNarrative = $latestSession ? $storage->read((string) $latestSession['narrative_file']) : '';
        $latestNarrativeHtml = $latestNarrative !== '' ? (new MarkdownRenderer())->render($latestNarrative) : '';
        $rollRequirement = (new RollRequirementDetector())->detect($latestNarrative);
        $promptSettings = (new CampaignPromptSettingsService(new SettingRepository($this->app->database())))->getForCampaign((int) $campaign['id'], $this->app->translator()->locale());
        $rollContextKey = $latestSession ? sprintf('campaign-%d-session-%d', (int) $campaign['id'], (int) $latestSession['id']) : sprintf('campaign-%d-empty', (int) $campaign['id']);

        $awaitingPrompt = $promptRepository->findLatestAwaitingResponse((int) $campaign['id']);
        $pendingDelta = $promptRepository->findLatestPendingDelta((int) $campaign['id']);
        $timeline = $this->hydrateTimeline($promptRepository->listGenerationTimeline((int) $campaign['id']), $storage);

        return $this->render('play/show', [
            'campaign' => $campaign,
            'latestSession' => $latestSession,
            'latestNarrative' => $latestNarrative,
            'latestNarrativeHtml' => $latestNarrativeHtml,
            'awaitingPrompt' => $awaitingPrompt,
            'pendingDelta' => $pendingDelta,
            'timeline' => $timeline,
            'selectedLanguage' => $promptSettings['response_language'],
            'promptSettings' => $promptSettings,
            'rollRequirement' => $rollRequirement,
            'rollContextKey' => $rollContextKey,
        ]);
    }

    /**
     * Add readable excerpts to timeline rows without storing duplicated text in the database.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function hydrateTimeline(array $rows, FileStorage $storage): array
    {
        $hydrated = [];
        foreach ($rows as $row) {
            $row['prompt_excerpt'] = $this->excerpt($storage->read((string) ($row['prompt_file'] ?? '')));
            $row['response_excerpt'] = $this->excerpt($storage->read((string) ($row['response_file'] ?? '')));
            $row['narrative_excerpt'] = $this->excerpt($storage->read((string) ($row['narrative_file'] ?? '')));
            $hydrated[] = $row;
        }

        return $hydrated;
    }

    /**
     * Return a compact excerpt for collapsed history details.
     */
    private function excerpt(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return mb_strlen($text) > 1800 ? mb_substr($text, 0, 1800) . "\n[excerpt truncated]" : $text;
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
