<?php /** @var array<string, mixed> $deltaData */ ?>
<section class="page-header compact-page-header">
    <div>
        <p class="eyebrow">Review</p>
        <h1>State delta review</h1>
    </div>
    <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Back to play workflow</a>
</section>
<section class="card split-card">
    <div>
        <h2>Narrative</h2>
        <pre class="plain-pre"><?= e($narrative) ?></pre>
    </div>
    <div>
        <h2>Parsed delta</h2>
        <pre class="plain-pre"><?= e(json_encode($deltaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}') ?></pre>
    </div>
</section>
<form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/deltas/' . $delta['id'] . '/apply')) ?>" class="card review-actions">
    <?= App\Core\Csrf::field($app->session()) ?>
    <p>Apply this delta, create a new story turn, and return directly to the next player action box?</p>
    <button class="button button-primary" type="submit">Apply and continue</button>
</form>
