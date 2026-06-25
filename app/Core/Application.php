<?php

declare(strict_types=1);

namespace App\Core;

use App\Controllers\AuthController;
use App\Controllers\CampaignController;
use App\Controllers\DashboardController;
use App\Controllers\ExportController;
use App\Controllers\FactController;
use App\Controllers\HomeController;
use App\Controllers\ImportController;
use App\Controllers\PlayController;
use App\Controllers\PromptController;
use App\Controllers\SessionController;
use App\Controllers\SettingsController;
use App\Controllers\UpdaterController;
use App\Repositories\UserRepository;

/**
 * Main application object that owns shared services and route registration.
 */
final class Application
{
    private Config $config;
    private Database $database;
    private Session $session;
    private Translator $translator;
    private Auth $auth;
    private Router $router;

    /**
     * Create a new application instance from the loaded configuration.
     */
    public function __construct(array $config)
    {
        $this->config = new Config($config);
        $this->session = new Session($this->config->get('security.session_name', 'dnd_chatgpt_campaign'));
        $this->session->start();

        $locale = $this->session->get('locale', (string) $this->config->get('app.default_locale', 'en'));
        $this->translator = new Translator(APP_ROOT . '/lang', $locale);

        $this->database = new Database($this->config);
        $this->auth = new Auth($this->session, new UserRepository($this->database));
        $this->router = new Router($this);
        $this->registerRoutes();
    }

    /**
     * Run request dispatching and send the generated response.
     */
    public function run(): void
    {
        SecurityHeaders::send();

        $request = Request::capture();
        $response = $this->router->dispatch($request);
        $response->send();
    }

    /**
     * Return the application configuration wrapper.
     */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Return the database service.
     */
    public function database(): Database
    {
        return $this->database;
    }

    /**
     * Return the session wrapper.
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * Return the translator service.
     */
    public function translator(): Translator
    {
        return $this->translator;
    }

    /**
     * Return the authentication service.
     */
    public function auth(): Auth
    {
        return $this->auth;
    }

    /**
     * Build a controller with this application injected.
     */
    public function makeController(string $controllerClass): Controller
    {
        return new $controllerClass($this);
    }

    /**
     * Register all HTTP routes.
     */
    private function registerRoutes(): void
    {
        $this->router->get('/', HomeController::class, 'index');
        $this->router->get('/login', AuthController::class, 'showLogin');
        $this->router->post('/login', AuthController::class, 'login');
        $this->router->post('/logout', AuthController::class, 'logout', true);

        $this->router->get('/dashboard', DashboardController::class, 'index', true);
        $this->router->get('/settings', SettingsController::class, 'index', true);
        $this->router->post('/settings/locale', SettingsController::class, 'setLocale', true);
        $this->router->post('/settings/updater', UpdaterController::class, 'saveSettings', true);
        $this->router->post('/settings/updater/run', UpdaterController::class, 'run', true);

        $this->router->get('/campaigns', CampaignController::class, 'index', true);
        $this->router->get('/campaigns/create', CampaignController::class, 'create', true);
        $this->router->post('/campaigns', CampaignController::class, 'store', true);
        $this->router->get('/campaigns/{id}', CampaignController::class, 'show', true);
        $this->router->get('/campaigns/{id}/play', PlayController::class, 'show', true);
        $this->router->post('/campaigns/{id}/activate', CampaignController::class, 'activate', true);
        $this->router->get('/campaigns/{id}/export/html', ExportController::class, 'html', true);

        $this->router->get('/campaigns/{id}/sessions', SessionController::class, 'index', true);
        $this->router->get('/campaigns/{id}/sessions/{sessionId}', SessionController::class, 'show', true);

        $this->router->get('/campaigns/{id}/facts', FactController::class, 'index', true);
        $this->router->post('/campaigns/{id}/facts', FactController::class, 'store', true);

        $this->router->get('/campaigns/{id}/prompt/new', PromptController::class, 'create', true);
        $this->router->post('/campaigns/{id}/prompt', PromptController::class, 'store', true);
        $this->router->get('/campaigns/{id}/prompt/{promptId}', PromptController::class, 'show', true);

        $this->router->get('/campaigns/{id}/responses/new', ImportController::class, 'create', true);
        $this->router->post('/campaigns/{id}/responses', ImportController::class, 'store', true);
        $this->router->get('/campaigns/{id}/deltas/{deltaId}/review', ImportController::class, 'review', true);
        $this->router->post('/campaigns/{id}/deltas/{deltaId}/apply', ImportController::class, 'apply', true);
    }
}
