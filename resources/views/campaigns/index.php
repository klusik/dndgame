<?php /** @var array<int, array<string, mixed>> $campaigns */ ?>
<section class="page-header">
    <div>
        <p class="eyebrow">Campaigns</p>
        <h1>Your campaigns</h1>
    </div>
    <a class="button button-primary" href="<?= e(url('/campaigns/create')) ?>">Create campaign</a>
</section>
<section class="card-grid">
    <?php foreach ($campaigns as $campaign): ?>
        <article class="card campaign-card">
            <h2><a href="<?= e(url('/campaigns/' . $campaign['id'])) ?>"><?= e($campaign['title']) ?></a></h2>
            <p><?= e($campaign['status']) ?></p>
        </article>
    <?php endforeach; ?>
</section>
