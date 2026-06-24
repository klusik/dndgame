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
use App\Repositories\SettingRepository;
use App\Services\CampaignPromptSettingsService;
use App\Services\ContextPackService;
use App\Services\FileStorage;
use App\Services\PromptCompiler;
use App\Services\RollRequirementDetector;

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
        return $this->render('prompts/create', $this->promptFormData($campaign, ['errors' => []]));
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
        $promptSettings = $this->promptSettings()->fromInput([
            'response_language' => $request->input('response_language', $this->app->translator()->locale()),
            'narrative_length' => $request->input('narrative_length', 'medium'),
            'challenge_level' => $request->input('challenge_level', 'balanced'),
            'dice_policy' => $request->input('dice_policy', 'occasional'),
            'agency_mode' => $request->input('agency_mode', 'player_led'),
            'description_style' => $request->input('description_style', 'direct'),
            'dm_stance' => $request->input('dm_stance', 'guarded'),
        ], $this->app->translator()->locale());
        $responseLanguage = $promptSettings['response_language'];
        $narrativeLength = $promptSettings['narrative_length'];
        $challengeLevel = $promptSettings['challenge_level'];
        $dicePolicy = $promptSettings['dice_policy'];
        $agencyMode = $promptSettings['agency_mode'];
        $descriptionStyle = $promptSettings['description_style'];
        $dmStance = $promptSettings['dm_stance'];
        $rollRequirement = $this->latestRollRequirement((int) $campaign['id']);
        $errors = [];
        if ($userAction === '') {
            $errors['user_action'] = 'Player action is required.';
        }
        if (($rollRequirement['required'] ?? false) && $diceResults === '') {
            $errors['dice_results'] = 'The previous DM response requires a dice roll before the next prompt can be generated.';
        }

        if ($errors !== []) {
            return $this->render('prompts/create', $this->promptFormData($campaign, [
                'errors' => $errors,
                'userAction' => $userAction,
                'extraNotes' => $extraNotes,
                'diceResults' => $diceResults,
                'responseLanguage' => $responseLanguage,
                'defaultNarrativeLength' => $narrativeLength,
                'defaultChallengeLevel' => $challengeLevel,
                'defaultDicePolicy' => $dicePolicy,
                'defaultAgencyMode' => $agencyMode,
                'defaultDescriptionStyle' => $descriptionStyle,
                'defaultDmStance' => $dmStance,
                'promptSettings' => $promptSettings,
                'rollRequirement' => $rollRequirement,
                'rollContextKey' => $this->latestRollContextKey((int) $campaign['id']),
            ]));
        }

        $this->promptSettings()->saveForCampaign((int) $campaign['id'], $promptSettings, $this->app->translator()->locale());

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
            $dmStance,
            $diceResults
        );
        $storage = new FileStorage(APP_ROOT);
        $promptPath = sprintf(
            '%s/prompts/prompt-%s-%s.md',
            $campaign['storage_path'],
            gmdate('Ymd-His'),
            bin2hex(random_bytes(3))
        );
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
     * Build default data for the prompt creation form.
     *
     * @param array<string, mixed> $campaign
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function promptFormData(array $campaign, array $overrides = []): array
    {
        $campaignId = (int) $campaign['id'];
        $settings = $this->promptSettings()->getForCampaign($campaignId, $this->app->translator()->locale());
        return $overrides + [
            'campaign' => $campaign,
            'errors' => [],
            'promptSettings' => $settings,
            'responseLanguage' => $settings['response_language'],
            'defaultNarrativeLength' => $settings['narrative_length'],
            'defaultChallengeLevel' => $settings['challenge_level'],
            'defaultDicePolicy' => $settings['dice_policy'],
            'defaultAgencyMode' => $settings['agency_mode'],
            'defaultDescriptionStyle' => $settings['description_style'],
            'defaultDmStance' => $settings['dm_stance'],
            'rollRequirement' => $this->latestRollRequirement($campaignId),
            'rollContextKey' => $this->latestRollContextKey($campaignId),
        ];
    }

    /**
     * Return the latest roll requirement for the campaign.
     *
     * @return array{required:bool, expression:string, reason:string, summary:string}
     */
    private function latestRollRequirement(int $campaignId): array
    {
        $sessions = (new SessionRepository($this->app->database()))->recent($campaignId, 1);
        $latestSession = $sessions[0] ?? null;
        if (!$latestSession) {
            return (new RollRequirementDetector())->detect('');
        }

        $storage = new FileStorage(APP_ROOT);
        $narrative = $storage->read((string) $latestSession['narrative_file']);
        return (new RollRequirementDetector())->detect($narrative);
    }

    /**
     * Return the browser-side roll lock key for the latest campaign turn.
     */
    private function latestRollContextKey(int $campaignId): string
    {
        $sessions = (new SessionRepository($this->app->database()))->recent($campaignId, 1);
        $latestSession = $sessions[0] ?? null;
        if (!$latestSession) {
            return sprintf('campaign-%d-empty', $campaignId);
        }

        return sprintf('campaign-%d-session-%d', $campaignId, (int) $latestSession['id']);
    }

    /**
     * Create campaign prompt settings service.
     */
    private function promptSettings(): CampaignPromptSettingsService
    {
        return new CampaignPromptSettingsService(new SettingRepository($this->app->database()));
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
