<?php
/** @var array<string, mixed> $campaign */
/** @var int|null $promptId */
$promptId = isset($promptId) && (int) $promptId > 0 ? (int) $promptId : null;
?>
<section class="page-header compact-page-header">
    <div>
        <p class="eyebrow">Response import</p>
        <h1>Paste ChatGPT response</h1>
    </div>
    <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Back to play workflow</a>
</section>
<div class="card form-card wide">
    <p class="muted-text">Paste the complete ChatGPT answer. The application will save the narrative, apply a valid state delta when present, and then open the full saved DM response before the next action box.</p>
    <?php if (!empty($errors)): ?><div class="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/responses')) ?>" class="guided-response-form">
        <?= App\Core\Csrf::field($app->session()) ?>
        <?php if ($promptId): ?>
            <input type="hidden" name="prompt_id" value="<?= $promptId ?>">
            <div class="info-box">This response will be linked to generated prompt #<?= $promptId ?>.</div>
        <?php else: ?>
            <label>Generated prompt ID, optional<input type="number" min="1" name="prompt_id"></label>
        <?php endif; ?>
        <label>ChatGPT response<textarea name="response_text" rows="18" required autofocus><?= e($responseText ?? '') ?></textarea></label>
        <details class="advanced-options">
            <summary>Advanced</summary>
            <label class="checkbox-label"><input type="checkbox" name="review_first" value="1"> Review state delta before applying it</label>
        </details>
        <button class="button button-primary" type="submit">Save and show response</button>
    </form>
</div>
