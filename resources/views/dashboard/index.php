<?php
/** @var array<int, array<string, mixed>> $campaigns */
/** @var array<string, mixed>|null $activeCampaign */
?>
<section class="page-header">
    <div>
        <p class="eyebrow">Workspace</p>
        <h1>Dashboard</h1>
    </div>
    <a class="button button-primary" href="<?= e(url('/campaigns/create')) ?>"><?= e($app->translator()->get('campaign.create')) ?></a>
</section>

<?php if (!$campaigns): ?>
    <section class="empty-state card">
        <h2><?= e($app->translator()->get('campaign.none')) ?></h2>
        <p>Create the first campaign to start generating structured prompts.</p>
        <a class="button button-primary" href="<?= e(url('/campaigns/create')) ?>"><?= e($app->translator()->get('campaign.create')) ?></a>
    </section>
<?php else: ?>
    <?php if ($activeCampaign): ?>
        <section class="card active-card">
            <p class="eyebrow"><?= e($app->translator()->get('campaign.active')) ?></p>
            <h2><?= e($activeCampaign['title']) ?></h2>
            <div class="button-row">
                <a class="button button-primary" href="<?= e(url('/campaigns/' . $activeCampaign['id'] . '/play')) ?>">Continue game</a>
                <a class="button" href="<?= e(url('/campaigns/' . $activeCampaign['id'] . '/prompt/new')) ?>">Generate prompt</a>
                <a class="button" href="<?= e(url('/campaigns/' . $activeCampaign['id'])) ?>">Open campaign</a>
            </div>
        </section>
    <?php endif; ?>
    <section class="card-grid">
        <?php foreach ($campaigns as $campaign): ?>
            <article class="card campaign-card">
                <h2><a href="<?= e(url('/campaigns/' . $campaign['id'])) ?>"><?= e($campaign['title']) ?></a></h2>
                <p>Status: <?= e($campaign['status']) ?></p>
                <form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/activate')) ?>">
                    <?= App\Core\Csrf::field($app->session()) ?>
                    <button class="button" type="submit">Set active</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
