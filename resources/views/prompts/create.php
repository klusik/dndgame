<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, mixed> $rollRequirement */
$selectedLanguage = $responseLanguage ?? $app->translator()->locale();
?>
<section class="page-header compact-page-header">
    <div>
        <p class="eyebrow">Prompt</p>
        <h1>Generate prompt</h1>
    </div>
    <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Back to play workflow</a>
</section>
<div class="card form-card wide next-turn-card">
    <?php if (!empty($errors)): ?><div class="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <?= App\Core\View::partial('partials/next_action_form', [
        'app' => $app,
        'campaign' => $campaign,
        'selectedLanguage' => $selectedLanguage,
        'defaultNarrativeLength' => $defaultNarrativeLength ?? 'medium',
        'defaultChallengeLevel' => $defaultChallengeLevel ?? 'balanced',
        'defaultDicePolicy' => $defaultDicePolicy ?? 'occasional',
        'defaultAgencyMode' => $defaultAgencyMode ?? 'player_led',
        'defaultDescriptionStyle' => $defaultDescriptionStyle ?? 'direct',
        'defaultDmStance' => $defaultDmStance ?? 'guarded',
        'buttonLabel' => 'Generate copy-ready prompt',
        'userAction' => $userAction ?? '',
        'extraNotes' => $extraNotes ?? '',
        'diceResults' => $diceResults ?? '',
        'rollRequired' => (bool) (($rollRequirement['required'] ?? false)),
        'rollRequirementSummary' => (string) (($rollRequirement['summary'] ?? '')),
        'rollExpression' => (string) (($rollRequirement['expression'] ?? '1d20')),
        'rollReason' => (string) (($rollRequirement['reason'] ?? 'Requested check')),
        'rollContextKey' => $rollContextKey ?? ('campaign-' . $campaign['id'] . '-prompt-create'),
        'sourceSessionId' => (int) ($sourceSessionId ?? 0),
    ]) ?>
</div>
