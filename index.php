<?php

declare(strict_types=1);

const APP_ROOT = __DIR__;

require APP_ROOT . '/app/Autoloader.php';

App\Autoloader::register(APP_ROOT . '/app');
require APP_ROOT . '/app/Core/helpers.php';

if (!is_file(APP_ROOT . '/config.php')) {
    header('Location: install.php');
    exit;
}

$config = require APP_ROOT . '/config.php';

$application = new App\Core\Application($config);
$GLOBALS['app'] = $application;
$application->run();
