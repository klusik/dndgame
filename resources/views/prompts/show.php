<?php /** @var string $promptText */ ?>
<section class="page-header compact-page-header">
    <div>
        <p class="eyebrow">Copy to ChatGPT</p>
        <h1>Generated prompt</h1>
    </div>
    <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/play')) ?>">Back to play workflow</a>
</section>
<section class="workflow-strip card">
    <div class="workflow-step active"><span>1</span><strong>Copy prompt</strong><small>Paste it into ChatGPT.</small></div>
    <div class="workflow-step active"><span>2</span><strong>Paste response</strong><small>Save and continue.</small></div>
    <div class="workflow-step"><span>3</span><strong>Next action</strong><small>Read the answer first.</small></div>
</section>
<div class="card workflow-card">
    <div class="step-label">Step 1</div>
    <h2>Copy this prompt into ChatGPT</h2>
    <p class="muted-text">After ChatGPT replies, paste the full answer into the response box below. A valid state delta is applied automatically, then the full saved DM response opens before the next action box.</p>
    <div class="copy-toolbar"><button class="button button-primary" data-copy-target="prompt-text">Copy prompt</button></div>
    <textarea id="prompt-text" class="large-textarea" readonly><?= e($promptText) ?></textarea>
    <div class="copy-toolbar copy-toolbar-bottom">
        <button class="button button-primary" data-copy-target="prompt-text">Copy prompt</button>
    </div>
</div>
<div class="card workflow-card">
    <div class="step-label">Step 2</div>
    <h2>Paste ChatGPT response</h2>
    <form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/responses')) ?>" class="guided-response-form">
        <?= App\Core\Csrf::field($app->session()) ?>
        <input type="hidden" name="prompt_id" value="<?= (int) $prompt['id'] ?>">
        <label>ChatGPT response<textarea name="response_text" rows="14" required autofocus placeholder="Paste the complete ChatGPT answer here."></textarea></label>
        <details class="advanced-options">
            <summary>Advanced</summary>
            <label class="checkbox-label"><input type="checkbox" name="review_first" value="1"> Review state delta before applying it</label>
        </details>
        <button class="button button-primary" type="submit">Save and show response</button>
    </form>
</div>
