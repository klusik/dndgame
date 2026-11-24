<?php /** @var array<string, mixed> $stats */ ?>
<section class="hero">
    <div>
        <p class="eyebrow">Prompt compiler and continuity manager</p>
        <h1><?= e($app->translator()->get('home.title')) ?></h1>
        <p><?= e($app->translator()->get('home.subtitle')) ?></p>
        <a class="button button-primary" href="<?= e(url('/login')) ?>"><?= e($app->translator()->get('auth.login')) ?></a>
    </div>
</section>
<section class="card-grid stats-grid">
    <article class="card stat-card"><span><?= (int) $stats['campaigns'] ?></span><p>Campaigns</p></article>
    <article class="card stat-card"><span><?= (int) $stats['sessions'] ?></span><p>Sessions</p></article>
    <article class="card stat-card"><span><?= (int) $stats['facts'] ?></span><p>Indexed facts</p></article>
    <article class="card stat-card"><span><?= e((string) ($stats['last_activity'] ?? 'none')) ?></span><p>Last activity</p></article>
</section>
