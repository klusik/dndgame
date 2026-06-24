<?php
/**
 * @var array<string, mixed> $campaign
 * @var string $selectedLanguage
 * @var string $defaultNarrativeLength
 * @var string $defaultChallengeLevel
 * @var string $defaultDicePolicy
 * @var string $defaultAgencyMode
 * @var string $defaultDescriptionStyle
 * @var string $defaultDmStance
 * @var string $buttonLabel
 * @var string $placeholder
 * @var bool $rollRequired
 * @var string $rollRequirementSummary
 * @var string $rollExpression
 * @var string $rollReason
 * @var string $rollContextKey
 */
$selectedLanguage = $selectedLanguage ?? $app->translator()->locale();
$defaultNarrativeLength = $defaultNarrativeLength ?? 'medium';
$defaultChallengeLevel = $defaultChallengeLevel ?? 'balanced';
$defaultDicePolicy = $defaultDicePolicy ?? 'occasional';
$defaultAgencyMode = $defaultAgencyMode ?? 'player_led';
$defaultDescriptionStyle = $defaultDescriptionStyle ?? 'direct';
$defaultDmStance = $defaultDmStance ?? 'guarded';
$buttonLabel = $buttonLabel ?? 'Generate next prompt';
$placeholder = $placeholder ?? 'Describe only what your character attempts in this campaign. Example: I inspect the corridor carefully and listen for movement.';
$rollRequired = (bool) ($rollRequired ?? false);
$rollRequirementSummary = $rollRequirementSummary ?? '';
$rollExpression = $rollExpression ?? '1d20';
$rollReason = $rollReason ?? 'Requested check';
$rollContextKey = $rollContextKey ?? ('campaign-' . $campaign['id'] . '-manual');
$diceResults = trim((string) ($diceResults ?? ''));
?>
<form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/prompt')) ?>" class="next-action-form" data-roll-required="<?= $rollRequired ? '1' : '0' ?>">
    <?= App\Core\Csrf::field($app->session()) ?>

    <label>Player action or request
        <textarea name="user_action" rows="7" required autofocus placeholder="<?= e($placeholder) ?>"><?= e($userAction ?? '') ?></textarea>
    </label>

    <details class="dice-helper-panel" data-dice-helper-panel data-roll-context-key="<?= e($rollContextKey) ?>" <?= $rollRequired || $diceResults !== '' ? 'open' : '' ?>>
        <summary><?= $rollRequired ? 'Required dice roll' : 'Dice helper, use only when ChatGPT asks for a check' ?></summary>
        <?php if ($rollRequired): ?>
            <div class="roll-required-note">
                <strong>Roll is required before continuing.</strong>
                <?php if ($rollRequirementSummary !== ''): ?><span><?= e($rollRequirementSummary) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="dice-helper-grid">
            <label>Check name or reason
                <input type="text" data-dice-reason value="<?= e($rollReason) ?>" placeholder="Technology check, Perception, Stealth">
            </label>
            <label>Dice expression
                <input type="text" data-dice-expression value="<?= e($rollExpression) ?>" placeholder="1d20+4, 2d20kh1+2, 1d8+2">
            </label>
            <button class="button" type="button" data-roll-dice <?= $diceResults !== '' ? 'disabled' : '' ?>>Roll once</button>
        </div>
        <input type="hidden" name="dice_results" data-dice-results value="<?= e($diceResults) ?>">
        <div class="dice-result-display <?= $diceResults === '' ? 'is-empty' : '' ?>" data-dice-result-display aria-live="polite">
            <?= $diceResults === '' ? 'No roll recorded yet.' : e($diceResults) ?>
        </div>
        <p class="muted-text compact-help-text">The roll result is locked after the first roll and is inserted into the next prompt. Supported notation: NdM, numeric modifiers, advantage as 2d20kh1, disadvantage as 2d20kl1.</p>
    </details>

    <div class="inline-fields prompt-control-grid">
        <label>Response language
            <select name="response_language">
                <option value="en" <?= $selectedLanguage === 'en' ? 'selected' : '' ?>>English</option>
                <option value="cs" <?= $selectedLanguage === 'cs' ? 'selected' : '' ?>>Čeština</option>
            </select>
        </label>
        <label>Narrative length
            <select name="narrative_length">
                <option value="compact" <?= $defaultNarrativeLength === 'compact' ? 'selected' : '' ?>>Compact</option>
                <option value="medium" <?= $defaultNarrativeLength === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="rich" <?= $defaultNarrativeLength === 'rich' ? 'selected' : '' ?>>Rich</option>
            </select>
        </label>
        <label>Challenge
            <select name="challenge_level">
                <option value="gentle" <?= $defaultChallengeLevel === 'gentle' ? 'selected' : '' ?>>Gentle</option>
                <option value="balanced" <?= $defaultChallengeLevel === 'balanced' ? 'selected' : '' ?>>Balanced</option>
                <option value="demanding" <?= $defaultChallengeLevel === 'demanding' ? 'selected' : '' ?>>Demanding</option>
            </select>
        </label>
        <label>Checks and dice
            <select name="dice_policy">
                <option value="rare" <?= $defaultDicePolicy === 'rare' ? 'selected' : '' ?>>Rare</option>
                <option value="occasional" <?= $defaultDicePolicy === 'occasional' ? 'selected' : '' ?>>Occasional</option>
                <option value="frequent" <?= $defaultDicePolicy === 'frequent' ? 'selected' : '' ?>>Frequent</option>
            </select>
        </label>
        <label>Player agency
            <select name="agency_mode">
                <option value="guided" <?= $defaultAgencyMode === 'guided' ? 'selected' : '' ?>>Guided</option>
                <option value="player_led" <?= $defaultAgencyMode === 'player_led' ? 'selected' : '' ?>>Player-led</option>
                <option value="sandbox" <?= $defaultAgencyMode === 'sandbox' ? 'selected' : '' ?>>Open sandbox</option>
            </select>
        </label>
        <label>Description style
            <select name="description_style">
                <option value="direct" <?= $defaultDescriptionStyle === 'direct' ? 'selected' : '' ?>>Direct</option>
                <option value="plain" <?= $defaultDescriptionStyle === 'plain' ? 'selected' : '' ?>>Plain</option>
                <option value="atmospheric" <?= $defaultDescriptionStyle === 'atmospheric' ? 'selected' : '' ?>>Atmospheric</option>
            </select>
        </label>
        <label>DM stance
            <select name="dm_stance">
                <option value="straight" <?= $defaultDmStance === 'straight' ? 'selected' : '' ?>>Straight</option>
                <option value="guarded" <?= $defaultDmStance === 'guarded' ? 'selected' : '' ?>>Guarded</option>
                <option value="shady" <?= $defaultDmStance === 'shady' ? 'selected' : '' ?>>Shady</option>
            </select>
        </label>
    </div>

    <label>Extra operator notes
        <textarea name="extra_notes" rows="3" placeholder="Optional constraints for this one prompt."><?= e($extraNotes ?? '') ?></textarea>
    </label>

    <button class="button button-primary" type="submit"><?= e($buttonLabel) ?></button>
</form>
