<?php
/** @var array<string, mixed> $campaign */
/** @var array<string, mixed> $sidePanel */
$characters = $sidePanel['characters'] ?? [];
$skills = $sidePanel['skills'] ?? [];
$resources = $sidePanel['resources'] ?? [];
$objectives = $sidePanel['objectives'] ?? [];
$recentTurns = $sidePanel['recentTurns'] ?? [];
?>
<aside class="game-side-panel" data-side-panel>
    <div class="game-side-panel-header">
        <div>
            <p class="eyebrow">Campaign panel</p>
            <h2><?= e($campaign['title']) ?></h2>
        </div>
        <button class="side-panel-toggle" type="button" data-side-panel-collapse aria-label="Toggle campaign panel">›</button>
    </div>

    <div class="side-tabs" role="tablist" aria-label="Campaign information tabs">
        <button class="side-tab active" type="button" data-side-tab="character">Character</button>
        <button class="side-tab" type="button" data-side-tab="journal">Journal</button>
        <button class="side-tab" type="button" data-side-tab="map">Map</button>
    </div>

    <div class="side-tab-panel active" data-side-tab-panel="character">
        <section class="side-section">
            <h3>Characters</h3>
            <?php if (!$characters): ?>
                <p class="muted-text compact-muted">No character facts are indexed yet.</p>
            <?php else: ?>
                <?php foreach ($characters as $character): ?>
                    <details class="side-details" open>
                        <summary>
                            <span><?= e($character['name']) ?></span>
                            <small><?= e($character['type']) ?>, <?= e($character['status']) ?></small>
                        </summary>
                        <?php if (empty($character['facts'])): ?>
                            <p class="muted-text compact-muted">No detailed facts yet.</p>
                        <?php else: ?>
                            <table class="side-table">
                                <tbody>
                                <?php foreach ($character['facts'] as $fact): ?>
                                    <tr>
                                        <th><?= e($fact['label']) ?></th>
                                        <td><?= e($fact['value']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="side-section">
            <h3>Skills and stats</h3>
            <?php if (!$skills): ?>
                <p class="muted-text compact-muted">No indexed skills yet.</p>
            <?php else: ?>
                <table class="side-table">
                    <tbody>
                    <?php foreach ($skills as $skill): ?>
                        <tr>
                            <th><?= e($skill['subject']) ?><br><small><?= e($skill['label']) ?></small></th>
                            <td><?= e($skill['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>

        <section class="side-section">
            <h3>Resources</h3>
            <?php if (!$resources): ?>
                <p class="muted-text compact-muted">No tracked resources yet.</p>
            <?php else: ?>
                <table class="side-table">
                    <tbody>
                    <?php foreach ($resources as $resource): ?>
                        <tr>
                            <th><?= e($resource['subject']) ?><br><small><?= e($resource['label']) ?></small></th>
                            <td><?= e($resource['value']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </div>

    <div class="side-tab-panel" data-side-tab-panel="journal">
        <section class="side-section">
            <h3>Jobs and plotpoints</h3>
            <?php if (!$objectives): ?>
                <p class="muted-text compact-muted">No journal objectives are indexed yet.</p>
            <?php else: ?>
                <div class="journal-list">
                    <?php foreach ($objectives as $objective): ?>
                        <article class="journal-item">
                            <div class="journal-item-title-row">
                                <strong><?= e($objective['title']) ?></strong>
                                <span class="pill"><?= e($objective['status']) ?></span>
                            </div>
                            <?php if (!empty($objective['summary'])): ?>
                                <p><?= e($objective['summary']) ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="side-section">
            <h3>Recent turns</h3>
            <?php if (!$recentTurns): ?>
                <p class="muted-text compact-muted">No story turns yet.</p>
            <?php else: ?>
                <div class="recent-turn-list">
                    <?php foreach ($recentTurns as $turn): ?>
                        <a class="recent-turn-link" href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions/' . $turn['id'])) ?>">
                            <strong>#<?= (int) $turn['sequence'] ?> <?= e($turn['title']) ?></strong>
                            <?php if (!empty($turn['excerpt'])): ?><span><?= e($turn['excerpt']) ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="side-tab-panel" data-side-tab-panel="map">
        <section class="side-section">
            <h3>Map</h3>
            <p class="muted-text compact-muted">Map support is reserved for a future version.</p>
        </section>
    </div>
</aside>
