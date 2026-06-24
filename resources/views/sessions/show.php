<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, mixed> $session */
/** @var string $narrativeHtml */
/** @var string $rawText */
/** @var array<string, mixed> $rollRequirement */
/** @var string $rollContextKey */
/** @var array<string, string> $promptSettings */
?>
<section class="page-header compact-page-header session-reader-header">
    <div>
        <p class="eyebrow">Story turn <?= (int) $session['sequence_number'] ?></p>
        <h1><?= e($session['title']) ?></h1>
    </div>
    <div class="button-row">
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Play workflow</a>
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions')) ?>">All story turns</a>
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
    <?php if (!empty($rollRequirement['required'])): ?>
        <div class="alert alert-warning"><strong>Roll required.</strong> <?= e((string) $rollRequirement['summary']) ?></div>
    <?php else: ?>
        <p class="muted-text">Write your next action. Use the dice helper only when the DM asked for a check.</p>
    <?php endif; ?>
    <?= App\Core\View::partial('partials/next_action_form', [
        'app' => $app,
        'campaign' => $campaign,
        'selectedLanguage' => $promptSettings['response_language'] ?? $app->translator()->locale(),
        'defaultNarrativeLength' => $promptSettings['narrative_length'] ?? 'medium',
        'defaultChallengeLevel' => $promptSettings['challenge_level'] ?? 'balanced',
        'defaultDicePolicy' => $promptSettings['dice_policy'] ?? 'occasional',
        'defaultAgencyMode' => $promptSettings['agency_mode'] ?? 'player_led',
        'defaultDescriptionStyle' => $promptSettings['description_style'] ?? 'direct',
        'defaultDmStance' => $promptSettings['dm_stance'] ?? 'guarded',
        'rollRequired' => (bool) ($rollRequirement['required'] ?? false),
        'rollRequirementSummary' => (string) ($rollRequirement['summary'] ?? ''),
        'rollExpression' => (string) ($rollRequirement['expression'] ?? '1d20'),
        'rollReason' => (string) ($rollRequirement['reason'] ?? 'Requested check'),
        'rollContextKey' => $rollContextKey,
        'buttonLabel' => 'Generate next prompt',
    ]) ?>
</section>

<details class="card raw-response-card">
    <summary>Raw pasted response</summary>
    <pre class="plain-pre"><?= e($rawText) ?></pre>
</details>
