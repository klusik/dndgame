<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\CampaignRepository;
use App\Repositories\FactRepository;
use App\Repositories\SessionRepository;

/**
 * Authenticated dashboard controller.
 */
final class DashboardController extends Controller
{
    /**
     * Show campaigns and current account overview.
     */
    public function index(Request $request): Response
    {
        $user = $this->app->auth()->user();
        $campaigns = new CampaignRepository($this->app->database());
        $sessionRepo = new SessionRepository($this->app->database());
        $factRepo = new FactRepository($this->app->database());
        $items = $campaigns->listForUser((int) $user['id']);

        $activeCampaignId = (int) $this->app->session()->get('active_campaign_id', 0);
        $activeCampaign = $activeCampaignId > 0 ? $campaigns->findForUser($activeCampaignId, (int) $user['id']) : null;

        return $this->render('dashboard/index', [
            'campaigns' => $items,
            'activeCampaign' => $activeCampaign,
            'sessionRepo' => $sessionRepo,
            'factRepo' => $factRepo,
        ]);
    }
}
