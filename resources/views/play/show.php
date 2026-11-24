<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, mixed>|null $latestSession */
/** @var array<string, mixed>|null $awaitingPrompt */
/** @var array<string, mixed>|null $pendingDelta */
/** @var array<int, array<string, mixed>> $timeline */
/** @var string $latestNarrativeHtml */
$selectedLanguage = $selectedLanguage ?? $app->translator()->locale();
?>
<section class="page-header compact-page-header">
    <div>
        <p class="eyebrow">Play workflow</p>
        <h1><?= e($campaign['title']) ?></h1>
    </div>
    <div class="button-row">
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'])) ?>">Campaign overview</a>
        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions')) ?>">Sessions</a>
    </div>
</section>

<section class="workflow-strip card">
    <div class="workflow-step <?= !$awaitingPrompt && !$pendingDelta ? 'active' : '' ?>">
        <span>1</span>
        <strong>Write action</strong>
        <small>Generate a prompt.</small>
    </div>
    <div class="workflow-step <?= $awaitingPrompt ? 'active' : '' ?>">
        <span>2</span>
        <strong>Paste response</strong>
        <small>Save ChatGPT answer.</small>
    </div>
    <div class="workflow-step <?= $latestSession ? 'active' : '' ?>">
        <span>3</span>
        <strong>Read and continue</strong>
        <small>Answer stays visible.</small>
    </div>
</section>

<section class="workflow-grid">
    <div class="workflow-main-stack">
        <?php if ($pendingDelta): ?>
            <article class="card workflow-card primary-workflow-card">
                <div class="step-label">One click needed</div>
                <h2>Finish the previous turn</h2>
                <p class="muted-text">A parsed state delta is waiting from the previous workflow version. Apply it and the full resulting session will open immediately.</p>
                <form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/deltas/' . $pendingDelta['id'] . '/apply')) ?>" class="inline-form spacious-inline-form">
                    <?= App\Core\Csrf::field($app->session()) ?>
                    <button class="button button-primary" type="submit">Apply and show response</button>
                    <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/deltas/' . $pendingDelta['id'] . '/review')) ?>">Review details</a>
                </form>
            </article>
        <?php elseif ($awaitingPrompt): ?>
            <article class="card workflow-card primary-workflow-card">
                <div class="step-label">Paste ChatGPT response</div>
                <h2>Continue from prompt #<?= (int) $awaitingPrompt['id'] ?></h2>
                <p class="muted-text">Paste the full ChatGPT answer. After saving, the app opens the created session so you can read the whole response before deciding what to do next.</p>
                <form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/responses')) ?>" class="guided-response-form">
                    <?= App\Core\Csrf::field($app->session()) ?>
                    <input type="hidden" name="prompt_id" value="<?= (int) $awaitingPrompt['id'] ?>">
                    <label>ChatGPT response<textarea name="response_text" rows="14" required autofocus placeholder="Paste the complete ChatGPT answer here. The next page will show the full saved response."></textarea></label>
                    <details class="advanced-options">
                        <summary>Advanced</summary>
                        <label class="checkbox-label"><input type="checkbox" name="review_first" value="1"> Review state delta before applying it</label>
                    </details>
                    <div class="button-row">
                        <button class="button button-primary" type="submit">Save and show response</button>
                        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/prompt/' . $awaitingPrompt['id'])) ?>">Open generated prompt</a>
                    </div>
                </form>
            </article>
        <?php else: ?>
            <?php if ($latestSession && $latestNarrativeHtml !== ''): ?>
                <article class="card latest-response-card">
                    <div class="section-title-row">
                        <div>
                            <p class="eyebrow">Latest DM response</p>
                            <h2><?= e($latestSession['title']) ?></h2>
                        </div>
                        <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions/' . $latestSession['id'])) ?>">Open session #<?= (int) $latestSession['sequence_number'] ?></a>
                    </div>
                    <div class="rendered-markdown narrative-reading-panel"><?= $latestNarrativeHtml ?></div>
                </article>
            <?php endif; ?>

            <article class="card workflow-card primary-workflow-card next-turn-card">
                <div class="step-label">Next turn</div>
                <h2>What do you do next?</h2>
                <p class="muted-text">Write the next player action or request. If the previous response asked for a roll, use the dice helper before generating the prompt.</p>
                <?= App\Core\View::partial('partials/next_action_form', [
                    'app' => $app,
                    'campaign' => $campaign,
                    'selectedLanguage' => $selectedLanguage,
                    'defaultNarrativeLength' => 'medium',
                    'defaultChallengeLevel' => 'balanced',
                    'defaultDicePolicy' => 'occasional',
                    'buttonLabel' => 'Generate next prompt',
                ]) ?>
            </article>
        <?php endif; ?>
    </div>

    <aside class="card workflow-card compact-side-card">
        <div class="step-label">Current state</div>
        <?php if ($latestSession): ?>
            <h2>Latest session #<?= (int) $latestSession['sequence_number'] ?></h2>
            <p><a href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions/' . $latestSession['id'])) ?>"><?= e($latestSession['title']) ?></a></p>
            <p class="muted-text">The full answer is shown in the main column when no prompt is waiting.</p>
        <?php else: ?>
            <h2>No imported sessions yet</h2>
            <p class="muted-text">Start by writing the first player action. The generated prompt will contain the campaign description and empty state indexes.</p>
        <?php endif; ?>
    </aside>
</section>

<section class="card history-card">
    <div class="section-title-row">
        <div>
            <p class="eyebrow">Generation history</p>
            <h2>Prompts, responses, deltas, and sessions</h2>
        </div>
    </div>

    <?php if (!$timeline): ?>
        <p class="muted-text">No generated prompts yet.</p>
    <?php else: ?>
        <div class="timeline-list">
            <?php foreach ($timeline as $row): ?>
                <details class="timeline-item">
                    <summary>
                        <span class="timeline-main">
                            <?php if (!empty($row['prompt_id'])): ?>
                                Prompt #<?= (int) $row['prompt_id'] ?>: <?= e($row['prompt_title'] ?? 'Untitled prompt') ?>
                            <?php else: ?>
                                Imported response #<?= (int) $row['response_id'] ?> without linked prompt
                            <?php endif; ?>
                        </span>
                        <span class="timeline-statuses">
                            <?php if (!empty($row['response_id'])): ?><span class="pill">response: <?= e($row['parse_status']) ?></span><?php else: ?><span class="pill pill-warning">awaiting response</span><?php endif; ?>
                            <?php if (!empty($row['delta_id'])): ?><span class="pill">delta: <?= e($row['delta_status']) ?></span><?php endif; ?>
                            <?php if (!empty($row['session_id'])): ?><span class="pill pill-success">session #<?= (int) $row['session_sequence_number'] ?></span><?php endif; ?>
                        </span>
                    </summary>
                    <div class="timeline-details">
                        <div class="metadata-grid">
                            <div><strong>Prompt created</strong><br><?= e($row['prompt_created_at'] ?? 'n/a') ?></div>
                            <div><strong>Response created</strong><br><?= e($row['response_created_at'] ?? 'n/a') ?></div>
                            <div><strong>Estimated prompt length</strong><br><?= e($row['estimated_length'] ?? 'n/a') ?></div>
                        </div>
                        <div class="button-row small-button-row">
                            <?php if (!empty($row['prompt_id'])): ?>
                                <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/prompt/' . $row['prompt_id'])) ?>">Open prompt</a>
                            <?php endif; ?>
                            <?php if (!empty($row['delta_id']) && ($row['delta_status'] ?? '') === 'pending_review'): ?>
                                <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/deltas/' . $row['delta_id'] . '/review')) ?>">Review delta</a>
                            <?php endif; ?>
                            <?php if (!empty($row['session_id'])): ?>
                                <a class="button" href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions/' . $row['session_id'])) ?>">Open session</a>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($row['narrative_excerpt'])): ?>
                            <details class="nested-details" open>
                                <summary>Narrative excerpt</summary>
                                <pre class="plain-pre compact-pre"><?= e($row['narrative_excerpt']) ?></pre>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($row['prompt_excerpt'])): ?>
                            <details class="nested-details">
                                <summary>Prompt excerpt</summary>
                                <pre class="plain-pre compact-pre"><?= e($row['prompt_excerpt']) ?></pre>
                            </details>
                        <?php endif; ?>
                        <?php if (!empty($row['response_excerpt'])): ?>
                            <details class="nested-details">
                                <summary>Raw response excerpt</summary>
                                <pre class="plain-pre compact-pre"><?= e($row['response_excerpt']) ?></pre>
                            </details>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
