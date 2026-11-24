<?php

/**
 * Sample configuration file.
 *
 * The installer writes config.php automatically. Keep this file in version
 * control as documentation for the required runtime settings.
 */
return [
    'app' => [
        'name' => 'DnD ChatGPT Campaign',
        'base_url' => '',
        'default_locale' => 'en',
        'installed' => false,
    ],
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'port' => 3306,
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'security' => [
        'secret_key' => 'replace-me',
        'session_name' => 'dnd_chatgpt_campaign',
    ],
];
