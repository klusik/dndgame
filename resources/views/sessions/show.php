<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, mixed> $session */
/** @var string $narrativeHtml */
/** @var string $rawText */
?>
<section class="page-header compact-page-header session-reader-header">
    <div>
        <p class="eyebrow">Session <?= (int) $session['sequence_number'] ?></p>
        <h1><?= e($session['title']) ?></h1>
    </div>
    <div class="button-row">
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Play workflow</a>
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions')) ?>">All sessions</a>
    </div>
</section>

<section class="card dm-response-card">
    <div class="section-title-row">
        <div>
            <p class="eyebrow">DM response</p>
            <h2>Read this before choosing the next action</h2>
        </div>
    </div>
    <div class="rendered-markdown narrative-reading-panel"><?= $narrativeHtml ?></div>
</section>

<section class="card workflow-card next-turn-card">
    <div class="step-label">Next turn</div>
    <h2>What do you do next?</h2>
    <p class="muted-text">Write your next action. If the DM asked for a check, use the dice helper and the roll will be included in the next generated prompt.</p>
    <?= App\Core\View::partial('partials/next_action_form', [
        'app' => $app,
        'campaign' => $campaign,
        'selectedLanguage' => $app->translator()->locale(),
        'defaultNarrativeLength' => 'medium',
        'defaultChallengeLevel' => 'balanced',
        'defaultDicePolicy' => 'occasional',
        'buttonLabel' => 'Generate next prompt',
    ]) ?>
</section>

<details class="card raw-response-card">
    <summary>Raw pasted response</summary>
    <pre class="plain-pre"><?= e($rawText) ?></pre>
</details>
