<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

/**
 * User-facing settings controller.
 */
final class SettingsController extends Controller
{
    /**
     * Show settings page.
     */
    public function index(Request $request): Response
    {
        return $this->render('settings/index');
    }

    /**
     * Change UI locale for the current session.
     */
    public function setLocale(Request $request): Response
    {
        $this->requireCsrf($request);
        $locale = (string) $request->input('locale', 'en');
        $this->app->session()->set('locale', $locale === 'cs' ? 'cs' : 'en');
        $this->app->session()->flash('success', 'Language changed.');
        return $this->redirect('/settings');
    }
}
