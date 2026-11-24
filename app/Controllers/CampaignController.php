<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Repositories\CampaignRepository;
use App\Repositories\EntityRepository;
use App\Repositories\FactRepository;
use App\Repositories\MysteryRepository;
use App\Repositories\SessionRepository;
use App\Services\CampaignFileService;
use App\Services\FileStorage;

/**
 * Campaign management controller.
 */
final class CampaignController extends Controller
{
    /**
     * List campaigns.
     */
    public function index(Request $request): Response
    {
        $user = $this->app->auth()->user();
        $repo = new CampaignRepository($this->app->database());
        return $this->render('campaigns/index', ['campaigns' => $repo->listForUser((int) $user['id'])]);
    }

    /**
     * Show campaign creation form.
     */
    public function create(Request $request): Response
    {
        return $this->render('campaigns/create', ['errors' => []]);
    }

    /**
     * Store a new campaign.
     */
    public function store(Request $request): Response
    {
        $this->requireCsrf($request);
        $title = trim((string) $request->input('title', ''));
        $description = trim((string) $request->input('description', ''));
        $language = (string) $request->input('default_language', 'en');

        $validator = new Validator();
        $validator->required('title', $title, 'Campaign title is required.');
        if (!$validator->passes()) {
            return $this->render('campaigns/create', [
                'errors' => $validator->errors(),
                'title' => $title,
                'description' => $description,
                'defaultLanguage' => $language,
            ]);
        }

        $user = $this->app->auth()->user();
        $campaigns = new CampaignRepository($this->app->database());
        $campaignId = $campaigns->create((int) $user['id'], $title, '', $language === 'cs' ? 'cs' : 'en');
        $campaign = $campaigns->find($campaignId);
        if (!$campaign) {
            throw new \RuntimeException('Campaign was not created.');
        }

        $descriptionFile = (string) $campaign['storage_path'] . '/README.md';
        $this->app->database()->pdo()->prepare('UPDATE campaigns SET description_file = :description_file WHERE id = :id')
            ->execute(['description_file' => $descriptionFile, 'id' => $campaignId]);
        $campaign['description_file'] = $descriptionFile;

        $files = $this->fileService();
        $files->initialize($campaign, $description !== '' ? $description : 'No description yet.');

        $this->app->session()->set('active_campaign_id', $campaignId);
        $this->app->session()->flash('success', 'Campaign created.');
        return $this->redirect('/campaigns/' . $campaignId);
    }

    /**
     * Show one campaign.
     */
    public function show(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $sessionRepo = new SessionRepository($this->app->database());
        $factRepo = new FactRepository($this->app->database());
        $entityRepo = new EntityRepository($this->app->database());
        $mysteryRepo = new MysteryRepository($this->app->database());

        return $this->render('campaigns/show', [
            'campaign' => $campaign,
            'recentSessions' => $sessionRepo->recent((int) $campaign['id'], 5),
            'counts' => [
                'sessions' => $sessionRepo->countByCampaign((int) $campaign['id']),
                'facts' => $factRepo->countByCampaign((int) $campaign['id']),
                'entities' => $entityRepo->countByCampaign((int) $campaign['id']),
                'mysteries' => $mysteryRepo->countByCampaign((int) $campaign['id']),
            ],
        ]);
    }

    /**
     * Set a campaign as active for the current session.
     */
    public function activate(Request $request, string $id): Response
    {
        $this->requireCsrf($request);
        $campaign = $this->requireCampaign((int) $id);
        $this->app->session()->set('active_campaign_id', (int) $campaign['id']);
        $this->app->session()->flash('success', 'Active campaign changed.');
        return $this->redirect('/campaigns/' . $campaign['id']);
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

    /**
     * Create the campaign file service.
     */
    private function fileService(): CampaignFileService
    {
        return new CampaignFileService(
            new FileStorage(APP_ROOT),
            new CampaignRepository($this->app->database()),
            new EntityRepository($this->app->database()),
            new FactRepository($this->app->database()),
            new MysteryRepository($this->app->database())
        );
    }
}
