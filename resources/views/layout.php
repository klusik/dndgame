<?php
/** @var App\Core\Application $app */
$tr = $app->translator();
$user = $app->auth()->user();
$flashMessages = $app->session()->consumeFlash();
?>
<!doctype html>
<html lang="<?= e($tr->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($tr->get('app.title')) ?></title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body>
<header class="topbar">
    <a class="brand" href="<?= e(url('/dashboard')) ?>"><?= e($tr->get('app.title')) ?></a>
    <?php if ($user): ?>
        <nav class="nav">
            <a href="<?= e(url('/dashboard')) ?>"><?= e($tr->get('nav.dashboard')) ?></a>
            <a href="<?= e(url('/campaigns')) ?>"><?= e($tr->get('nav.campaigns')) ?></a>
            <a href="<?= e(url('/settings')) ?>"><?= e($tr->get('nav.settings')) ?></a>
            <form action="<?= e(url('/logout')) ?>" method="post" class="inline-form">
                <?= App\Core\Csrf::field($app->session()) ?>
                <button class="link-button" type="submit"><?= e($tr->get('nav.logout')) ?></button>
            </form>
        </nav>
    <?php else: ?>
        <nav class="nav">
            <a href="<?= e(url('/login')) ?>"><?= e($tr->get('auth.login')) ?></a>
        </nav>
    <?php endif; ?>
</header>
<main class="page">
    <?php foreach ($flashMessages as $message): ?>
        <div class="flash flash-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<script src="<?= e(url('/assets/js/app.js')) ?>"></script>
</body>
</html>
