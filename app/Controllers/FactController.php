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
use App\Services\CampaignFileService;
use App\Services\FileStorage;

/**
 * Fact browsing and manual fact creation controller.
 */
final class FactController extends Controller
{
    /**
     * Show indexed facts.
     */
    public function index(Request $request, string $id): Response
    {
        $campaign = $this->requireCampaign((int) $id);
        $facts = new FactRepository($this->app->database());
        return $this->render('facts/index', [
            'campaign' => $campaign,
            'facts' => $facts->listCurrentByCampaign((int) $campaign['id'], true),
        ]);
    }

    /**
     * Store a manual fact.
     */
    public function store(Request $request, string $id): Response
    {
        $this->requireCsrf($request);
        $campaign = $this->requireCampaign((int) $id);
        $subject = trim((string) $request->input('subject', ''));
        $predicate = trim((string) $request->input('predicate', ''));
        $value = trim((string) $request->input('value', ''));

        if ($subject !== '' && $predicate !== '' && $value !== '') {
            $entities = new EntityRepository($this->app->database());
            $entity = $entities->findByName((int) $campaign['id'], $subject);
            (new FactRepository($this->app->database()))->create(
                (int) $campaign['id'],
                $entity ? (int) $entity['id'] : null,
                (string) $request->input('category', 'other'),
                $subject,
                $predicate,
                $value,
                (string) $request->input('certainty', 'confirmed'),
                (string) $request->input('visibility', 'player'),
                null
            );
            $this->fileService()->refreshMirrors((int) $campaign['id']);
            $this->app->session()->flash('success', 'Fact saved.');
        }

        return $this->redirect('/campaigns/' . $campaign['id'] . '/facts');
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
     * Create campaign file service.
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
