<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\InstallerService;

/**
 * Standalone installer controller.
 */
final class InstallController
{
    /**
     * Handle installer GET and POST requests.
     */
    public function handle(): void
    {
        $installer = new InstallerService(APP_ROOT);
        if ($installer->isInstalled()) {
            header('Location: index.php');
            exit;
        }

        $errors = [];
        $done = false;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = $installer->install([
                'db_host' => (string) ($_POST['db_host'] ?? ''),
                'db_port' => (string) ($_POST['db_port'] ?? '3306'),
                'db_name' => (string) ($_POST['db_name'] ?? ''),
                'db_user' => (string) ($_POST['db_user'] ?? ''),
                'db_password' => (string) ($_POST['db_password'] ?? ''),
                'admin_email' => (string) ($_POST['admin_email'] ?? ''),
                'admin_password' => (string) ($_POST['admin_password'] ?? ''),
                'default_locale' => (string) ($_POST['default_locale'] ?? 'en'),
            ]);
            $errors = $result['errors'];
            $done = $result['ok'];
        }

        require APP_ROOT . '/resources/views/install.php';
    }
}
