<?php
/**
 * @var array<string, mixed> $campaign
 * @var string $selectedLanguage
 * @var string $defaultNarrativeLength
 * @var string $defaultChallengeLevel
 * @var string $defaultDicePolicy
 * @var string $defaultAgencyMode
 * @var string $defaultDescriptionStyle
 * @var string $buttonLabel
 * @var string $placeholder
 */
$selectedLanguage = $selectedLanguage ?? $app->translator()->locale();
$defaultNarrativeLength = $defaultNarrativeLength ?? 'medium';
$defaultChallengeLevel = $defaultChallengeLevel ?? 'balanced';
$defaultDicePolicy = $defaultDicePolicy ?? 'occasional';
$defaultAgencyMode = $defaultAgencyMode ?? 'player_led';
$defaultDescriptionStyle = $defaultDescriptionStyle ?? 'direct';
$buttonLabel = $buttonLabel ?? 'Generate next prompt';
$placeholder = $placeholder ?? 'Describe only what your character attempts in this campaign. Example: I inspect the corridor carefully and listen for movement.';
?>
<form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/prompt')) ?>" class="next-action-form">
    <?= App\Core\Csrf::field($app->session()) ?>

    <label>Player action or request
        <textarea name="user_action" rows="7" required autofocus placeholder="<?= e($placeholder) ?>"><?= e($userAction ?? '') ?></textarea>
    </label>

    <details class="dice-helper-panel">
        <summary>Dice helper, use when ChatGPT asks for a check</summary>
        <div class="dice-helper-grid">
            <label>Check name or reason
                <input type="text" data-dice-reason placeholder="Technology check, Perception, Stealth">
            </label>
            <label>Dice expression
                <input type="text" data-dice-expression value="1d20" placeholder="1d20+4, 2d20kh1+2, 1d8+2">
            </label>
            <button class="button" type="button" data-roll-dice>Roll and add</button>
        </div>
        <label>Dice results for this turn
            <textarea name="dice_results" data-dice-results rows="4" placeholder="Dice rolls added by the helper will appear here."><?= e($diceResults ?? '') ?></textarea>
        </label>
        <p class="muted-text compact-help-text">Supported notation: NdM, numeric modifiers, advantage as 2d20kh1, disadvantage as 2d20kl1. The result is inserted into the next prompt so ChatGPT can resolve the requested check.</p>
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
    </div>

    <label>Extra operator notes
        <textarea name="extra_notes" rows="3" placeholder="Optional constraints for this one prompt."><?= e($extraNotes ?? '') ?></textarea>
    </label>

    <button class="button button-primary" type="submit"><?= e($buttonLabel) ?></button>
</form>
