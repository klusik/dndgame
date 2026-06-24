<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\PublicStatsRepository;

/**
 * Public landing page controller.
 */
final class HomeController extends Controller
{
    /**
     * Show public statistics or redirect authenticated users.
     */
    public function index(Request $request): Response
    {
        if ($this->app->auth()->check()) {
            return $this->redirect('/dashboard');
        }

        $stats = (new PublicStatsRepository($this->app->database()))->getStats();
        return $this->render('home/index', ['stats' => $stats]);
    }
}
