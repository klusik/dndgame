<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, int> $counts */
?>
<section class="page-header">
    <div>
        <p class="eyebrow">Campaign</p>
        <h1><?= e($campaign['title']) ?></h1>
    </div>
    <div class="button-row">
        <a class="button button-primary" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Continue game</a>
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/prompt/new')) ?>">Generate prompt</a>
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/responses/new')) ?>">Import response</a>
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/export/html')) ?>">Export readable HTML</a>
    </div>
</section>
<section class="card-grid stats-grid">
    <article class="card stat-card"><span><?= (int) $counts['sessions'] ?></span><p>Story turns</p></article>
    <article class="card stat-card"><span><?= (int) $counts['facts'] ?></span><p>Facts</p></article>
    <article class="card stat-card"><span><?= (int) $counts['entities'] ?></span><p>Entities</p></article>
    <article class="card stat-card"><span><?= (int) $counts['mysteries'] ?></span><p>Mysteries</p></article>
</section>
<section class="card action-list">
    <a href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Guided play workflow</a>
    <a href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions')) ?>">Story turns</a>
    <a href="<?= e(url('/campaigns/' . $campaign['id'] . '/facts')) ?>">Facts</a>
    <a href="<?= e(url('/campaigns/' . $campaign['id'] . '/prompt/new')) ?>">Generate next prompt</a>
    <a href="<?= e(url('/campaigns/' . $campaign['id'] . '/responses/new')) ?>">Paste ChatGPT response</a>
    <a href="<?= e(url('/campaigns/' . $campaign['id'] . '/export/html')) ?>">Export complete campaign HTML</a>
</section>
