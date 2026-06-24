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
use App\Services\ContextPackService;
use App\Services\FileStorage;
use App\Services\PromptCompiler;

/**
 * Prompt generation controller.
 */
final class PromptController extends Controller
{
    /**
     * Show prompt creation form.
     */
    public function create(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        return $this->render('prompts/create', ['campaign' => $campaign, 'errors' => []]);
    }

    /**
     * Generate and store a copy-ready prompt.
     */
    public function store(Request $request, string $id): Response
    {
        $this->requireCsrf($request);
        $campaign = $this->requireCampaign((int) $id);
        $userAction = trim((string) $request->input('user_action', ''));
        $extraNotes = trim((string) $request->input('extra_notes', ''));
        $diceResults = trim((string) $request->input('dice_results', ''));
        $responseLanguage = $this->normalizeLanguage((string) $request->input('response_language', $this->app->translator()->locale()));
        $narrativeLength = $this->normalizeNarrativeLength((string) $request->input('narrative_length', 'medium'));
        $challengeLevel = $this->normalizeChallengeLevel((string) $request->input('challenge_level', 'balanced'));
        $dicePolicy = $this->normalizeDicePolicy((string) $request->input('dice_policy', 'occasional'));
        $agencyMode = $this->normalizeAgencyMode((string) $request->input('agency_mode', 'player_led'));
        $descriptionStyle = $this->normalizeDescriptionStyle((string) $request->input('description_style', 'direct'));
        if ($userAction === '') {
            return $this->render('prompts/create', [
                'campaign' => $campaign,
                'errors' => ['user_action' => 'Player action is required.'],
                'userAction' => $userAction,
                'extraNotes' => $extraNotes,
                'diceResults' => $diceResults,
                'responseLanguage' => $responseLanguage,
                'defaultNarrativeLength' => $narrativeLength,
                'defaultChallengeLevel' => $challengeLevel,
                'defaultDicePolicy' => $dicePolicy,
                'defaultAgencyMode' => $agencyMode,
                'defaultDescriptionStyle' => $descriptionStyle,
            ]);
        }

        $compiler = new PromptCompiler($this->contextPackService());
        $prompt = $compiler->compile(
            (int) $campaign['id'],
            $userAction,
            $extraNotes,
            $responseLanguage,
            $narrativeLength,
            $challengeLevel,
            $dicePolicy,
            $agencyMode,
            $descriptionStyle,
            $diceResults
        );
        $storage = new FileStorage(APP_ROOT);
        $promptPath = sprintf('%s/prompts/prompt-%s.md', $campaign['storage_path'], gmdate('Ymd-His'));
        $storage->write($promptPath, $prompt . "\n");

        $repo = new PromptRepository($this->app->database());
        $promptId = $repo->createPrompt(
            (int) $campaign['id'],
            null,
            mb_substr($userAction, 0, 120),
            $promptPath,
            hash('sha256', $prompt),
            mb_strlen($prompt)
        );

        return $this->redirect('/campaigns/' . $campaign['id'] . '/prompt/' . $promptId);
    }

    /**
     * Show a stored prompt.
     */
    public function show(Request $request, string $id, string $promptId): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $repo = new PromptRepository($this->app->database());
        $prompt = $repo->findPrompt((int) $campaign['id'], (int) $promptId);
        if (!$prompt) {
            throw new \RuntimeException('Prompt not found.');
        }
        $storage = new FileStorage(APP_ROOT);
        return $this->render('prompts/show', [
            'campaign' => $campaign,
            'prompt' => $prompt,
            'promptText' => $storage->read((string) $prompt['prompt_file']),
        ]);
    }

    /**
     * Normalize a supported response language code.
     */
    private function normalizeLanguage(string $language): string
    {
        return $language === 'cs' ? 'cs' : 'en';
    }


    /**
     * Normalize a supported narrative length option.
     */
    private function normalizeNarrativeLength(string $length): string
    {
        return in_array($length, ['compact', 'medium', 'rich'], true) ? $length : 'medium';
    }

    /**
     * Normalize a supported challenge level option.
     */
    private function normalizeChallengeLevel(string $level): string
    {
        return in_array($level, ['gentle', 'balanced', 'demanding'], true) ? $level : 'balanced';
    }

    /**
     * Normalize a supported dice policy option.
     */
    private function normalizeDicePolicy(string $policy): string
    {
        return in_array($policy, ['rare', 'occasional', 'frequent'], true) ? $policy : 'occasional';
    }

    /**
     * Normalize a supported player agency mode.
     */
    private function normalizeAgencyMode(string $mode): string
    {
        return in_array($mode, ['guided', 'player_led', 'sandbox'], true) ? $mode : 'player_led';
    }

    /**
     * Normalize a supported description style option.
     */
    private function normalizeDescriptionStyle(string $style): string
    {
        return in_array($style, ['direct', 'plain', 'atmospheric'], true) ? $style : 'direct';
    }

    /**
     * Create context pack service.
     */
    private function contextPackService(): ContextPackService
    {
        return new ContextPackService(
            new FileStorage(APP_ROOT),
            new CampaignRepository($this->app->database()),
            new EntityRepository($this->app->database()),
            new FactRepository($this->app->database()),
            new MysteryRepository($this->app->database()),
            new SessionRepository($this->app->database())
        );
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
