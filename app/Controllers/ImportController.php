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
use App\Services\CampaignFileService;
use App\Services\ChatResponseParser;
use App\Services\ContextPackService;
use App\Services\DeltaApplier;
use App\Services\FileStorage;
use App\Services\PlayerInputExtractor;

/**
 * Handles pasted ChatGPT responses and state delta processing.
 */
final class ImportController extends Controller
{
    /**
     * Show response import form.
     */
    public function create(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $promptId = (int) $request->input('prompt_id', 0);

        return $this->render('responses/create', [
            'campaign' => $campaign,
            'errors' => [],
            'promptId' => $promptId > 0 ? $promptId : null,
        ]);
    }

    /**
     * Store a pasted response and keep the play flow moving forward.
     *
     * Valid structured deltas are applied immediately by default. Responses
     * without valid delta JSON are still stored as narrative-only story turns so
     * the player is not sent back to a stale waiting screen.
     */
    public function store(Request $request, string $id): Response
    {
        $this->requireCsrf($request);
        $campaign = $this->requireCampaign((int) $id);
        $responseText = trim((string) $request->input('response_text', ''));
        $promptId = (int) $request->input('prompt_id', 0);
        $promptId = $promptId > 0 ? $promptId : null;
        $reviewFirst = (string) $request->input('review_first', '') === '1';

        if ($responseText === '') {
            return $this->render('responses/create', [
                'campaign' => $campaign,
                'errors' => ['response_text' => 'Response text is required.'],
                'promptId' => $promptId,
            ]);
        }

        $storage = new FileStorage(APP_ROOT);
        $parser = new ChatResponseParser();
        $parsed = $parser->parse($responseText);
        $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $base = (string) $campaign['storage_path'];
        $responseFile = $storage->write($base . '/responses/response-' . $stamp . '.md', $responseText . "\n");
        $narrativeFile = $storage->write($base . '/responses/narrative-' . $stamp . '.md', $parsed['narrative'] . "\n");
        $rawDeltaFile = $this->writeRawDeltaFile($storage, $base, $stamp, $parsed['delta_raw']);

        $repo = new PromptRepository($this->app->database());
        $responseId = $repo->createResponse(
            (int) $campaign['id'],
            $promptId,
            $responseFile,
            $narrativeFile,
            $rawDeltaFile,
            $parsed['ok'] ? 'parsed' : 'stored_without_valid_delta'
        );

        if (!$parsed['ok'] || $rawDeltaFile === null) {
            $sessionId = $this->storeNarrativeOnlySession(
                $campaign,
                $responseId,
                $promptId,
                $responseFile,
                $narrativeFile,
                $parsed['errors']
            );
            $this->app->session()->flash(
                'warning',
                'The response was saved as story turn #' . $sessionId . ', but no valid state delta was applied. Continue playing or regenerate a repair prompt later.'
            );
            return $this->redirect('/campaigns/' . $campaign['id'] . '/sessions/' . $sessionId);
        }

        $deltaId = $repo->createDelta((int) $campaign['id'], $responseId, $rawDeltaFile);

        if ($reviewFirst) {
            return $this->redirect('/campaigns/' . $campaign['id'] . '/deltas/' . $deltaId . '/review');
        }

        $sessionId = $this->deltaApplier()->apply((int) $campaign['id'], $deltaId);
        $this->app->session()->flash('success', 'Response saved as story turn #' . $sessionId . '. Read it below, then continue with the next player action.');
        return $this->redirect('/campaigns/' . $campaign['id'] . '/sessions/' . $sessionId);
    }

    /**
     * Review a pending state delta before applying it.
     */
    public function review(Request $request, string $id, string $deltaId): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $repo = new PromptRepository($this->app->database());
        $delta = $repo->findDelta((int) $campaign['id'], (int) $deltaId);
        if (!$delta) {
            throw new \RuntimeException('State delta not found.');
        }
        $storage = new FileStorage(APP_ROOT);
        $deltaData = json_decode($storage->read((string) $delta['delta_file']), true);
        return $this->render('responses/review', [
            'campaign' => $campaign,
            'delta' => $delta,
            'deltaData' => is_array($deltaData) ? $deltaData : [],
            'narrative' => $storage->read((string) $delta['narrative_file']),
        ]);
    }

    /**
     * Apply a reviewed state delta.
     */
    public function apply(Request $request, string $id, string $deltaId): Response
    {
        $this->requireCsrf($request);
        $campaign = $this->requireCampaign((int) $id);
        $sessionId = $this->deltaApplier()->apply((int) $campaign['id'], (int) $deltaId);
        $this->app->session()->flash('success', 'State delta applied as story turn #' . $sessionId . '. Read it below, then continue with the next player action.');
        return $this->redirect('/campaigns/' . $campaign['id'] . '/sessions/' . $sessionId);
    }

    /**
     * Store raw delta JSON when ChatGPT supplied a delta section.
     */
    private function writeRawDeltaFile(FileStorage $storage, string $base, string $stamp, string $deltaRaw): ?string
    {
        if (trim($deltaRaw) === '') {
            return null;
        }

        return $storage->write($base . '/deltas/delta-' . $stamp . '.json', $deltaRaw . "\n");
    }

    /**
     * Store an unstructured response as a narrative-only story turn.
     *
     * @param array<string, mixed> $campaign
     * @param array<int, string> $errors
     */
    private function storeNarrativeOnlySession(array $campaign, int $responseId, ?int $promptId, string $responseFile, string $narrativeFile, array $errors): int
    {
        $storage = new FileStorage(APP_ROOT);
        $repo = new PromptRepository($this->app->database());
        $sessions = new SessionRepository($this->app->database());
        $base = (string) $campaign['storage_path'];
        $stamp = gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3));
        $deltaFile = $storage->write(
            $base . '/deltas/narrative-only-' . $stamp . '.json',
            json_encode($this->narrativeOnlyDelta($errors), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
        );

        $deltaId = $repo->createDelta((int) $campaign['id'], $responseId, $deltaFile);
        $sessionId = $sessions->create(
            (int) $campaign['id'],
            'Unstructured ChatGPT response',
            $responseFile,
            $narrativeFile,
            $deltaFile
        );
        $repo->markDeltaApplied($deltaId, $sessionId);
        $this->storeNarrativeOnlyPlayerInput($campaign, $sessionId, $promptId);

        $campaigns = new CampaignRepository($this->app->database());
        $campaigns->touch((int) $campaign['id']);
        $this->campaignFileService()->refreshMirrors((int) $campaign['id']);
        $this->contextPackService()->build((int) $campaign['id']);

        return $sessionId;
    }

    /**
     * Store player input for a narrative-only response when it is linked to a prompt.
     *
     * @param array<string, mixed> $campaign
     */
    private function storeNarrativeOnlyPlayerInput(array $campaign, int $sessionId, ?int $promptId): void
    {
        if ($promptId === null || $promptId <= 0) {
            return;
        }

        $repo = new PromptRepository($this->app->database());
        $prompt = $repo->findPrompt((int) $campaign['id'], $promptId);
        if (!$prompt || empty($prompt['prompt_file'])) {
            return;
        }

        $storage = new FileStorage(APP_ROOT);
        $input = (new PlayerInputExtractor())->extractFromPrompt($storage->read((string) $prompt['prompt_file']));
        $sessions = new SessionRepository($this->app->database());
        $base = (string) $campaign['storage_path'];
        $action = trim($input['action']);
        $dice = trim($input['dice']);

        if ($action !== '') {
            $path = $storage->write(sprintf('%s/player-input/session-%04d-action.md', $base, $sessionId), $action . "\n");
            $sessions->createEvent((int) $campaign['id'], $sessionId, 'player_action', 'Player action', $path, 'player');
        }

        if ($dice !== '') {
            $path = $storage->write(sprintf('%s/player-input/session-%04d-dice.md', $base, $sessionId), $dice . "\n");
            $sessions->createEvent((int) $campaign['id'], $sessionId, 'dice_result', 'Dice result', $path, 'player');
        }
    }

    /**
     * Create a valid minimal delta for an unstructured narrative-only response.
     *
     * @param array<int, string> $errors
     * @return array<string, mixed>
     */
    private function narrativeOnlyDelta(array $errors): array
    {
        return [
            'turn_title' => 'Unstructured ChatGPT response',
            'turn_summary' => 'The response was stored as narrative because it did not contain a valid state delta.',
            'events' => [],
            'entities' => [],
            'facts' => [],
            'mysteries' => [],
            'warnings' => array_values($errors),
        ];
    }

    /**
     * Create the delta applier with all required repositories and services.
     */
    private function deltaApplier(): DeltaApplier
    {
        return new DeltaApplier(
            new FileStorage(APP_ROOT),
            new CampaignRepository($this->app->database()),
            new SessionRepository($this->app->database()),
            new EntityRepository($this->app->database()),
            new FactRepository($this->app->database()),
            new MysteryRepository($this->app->database()),
            new PromptRepository($this->app->database()),
            $this->campaignFileService(),
            $this->contextPackService()
        );
    }

    /**
     * Create campaign file service.
     */
    private function campaignFileService(): CampaignFileService
    {
        return new CampaignFileService(
            new FileStorage(APP_ROOT),
            new CampaignRepository($this->app->database()),
            new EntityRepository($this->app->database()),
            new FactRepository($this->app->database()),
            new MysteryRepository($this->app->database())
        );
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
