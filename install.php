<?php

declare(strict_types=1);

const APP_ROOT = __DIR__;

require APP_ROOT . '/app/Autoloader.php';

App\Autoloader::register(APP_ROOT . '/app');
require APP_ROOT . '/app/Core/helpers.php';

$controller = new App\Controllers\InstallController();
$controller->handle();
