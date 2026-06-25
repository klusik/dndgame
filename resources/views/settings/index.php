<?php
/** @var string $githubRepository */
/** @var bool $githubTokenConfigured */
/** @var string $lastUpdateRef */
/** @var string $lastUpdateResult */
$user = $app->auth()->user();
$isAdmin = $user && (string) ($user['role'] ?? '') === 'admin';
?>
<section class="page-header">
    <div>
        <p class="eyebrow">Preferences</p>
        <h1>Settings</h1>
    </div>
</section>

<div class="settings-grid">
    <section class="card form-card">
        <h2>Language</h2>
        <form method="post" action="<?= e(url('/settings/locale')) ?>">
            <?= App\Core\Csrf::field($app->session()) ?>
            <label>Interface language
                <select name="locale">
                    <option value="en" <?= $app->translator()->locale() === 'en' ? 'selected' : '' ?>>English</option>
                    <option value="cs" <?= $app->translator()->locale() === 'cs' ? 'selected' : '' ?>>Čeština</option>
                </select>
            </label>
            <button class="button button-primary" type="submit">Save language</button>
        </form>
    </section>

    <?php if ($isAdmin): ?>
        <section class="card form-card updater-card">
            <div class="section-title-row">
                <div>
                    <p class="eyebrow">Admin tool</p>
                    <h2>GitHub updater</h2>
                </div>
            </div>
            <p class="muted-text">Downloads a GitHub ZIP archive and copies code files over the current installation. It does not use git, shell commands, Composer, or external libraries.</p>
            <form method="post" action="<?= e(url('/settings/updater')) ?>" class="stacked-form">
                <?= App\Core\Csrf::field($app->session()) ?>
                <label>GitHub repository
                    <input type="text" name="github_repository" value="<?= e($githubRepository) ?>" placeholder="owner/repository" autocomplete="off">
                </label>
                <label>GitHub token for private repositories
                    <input type="password" name="github_token" value="" placeholder="Leave empty to keep current token" autocomplete="new-password">
                </label>
                <p class="muted-text compact-help-text">
                    Use a fine-grained GitHub token with read-only Contents access for this repository. The token is stored server-side and is never printed back into the page.
                </p>
                <div class="updater-token-status">
                    <span class="status-pill <?= $githubTokenConfigured ? 'status-pill-ok' : 'status-pill-muted' ?>">
                        <?= $githubTokenConfigured ? 'Private token saved' : 'No private token saved' ?>
                    </span>
                </div>
                <?php if ($githubTokenConfigured): ?>
                    <label class="checkbox-label updater-confirm-row"><input type="checkbox" name="clear_github_token" value="1"> Clear saved token</label>
                <?php endif; ?>
                <button class="button" type="submit">Save updater settings</button>
            </form>

            <form method="post" action="<?= e(url('/settings/updater/run')) ?>" class="stacked-form updater-run-form">
                <?= App\Core\Csrf::field($app->session()) ?>
                <input type="hidden" name="github_repository" value="<?= e($githubRepository) ?>">
                <label>Git ref, tag, branch, or commit ID
                    <input type="text" name="github_ref" value="<?= e($lastUpdateRef ?: 'main') ?>" placeholder="main, v0.2.0, or commit SHA" autocomplete="off" required>
                </label>
                <label class="checkbox-label updater-confirm-row"><input type="checkbox" name="confirm_update" value="1" required> I understand this will overwrite application code files.</label>
                <div class="alert alert-warning">
                    <strong>Excluded from update:</strong> config.php, storage, deploy, and .git. A backup ZIP of overwritten files is created under storage/updates/backups.
                </div>
                <button class="button button-primary" type="submit" <?= trim($githubRepository) === '' ? 'disabled' : '' ?>>Download and apply update</button>
            </form>

            <?php if (trim($lastUpdateResult) !== ''): ?>
                <details class="nested-details updater-result-details">
                    <summary>Last update result</summary>
                    <pre class="plain-pre compact-pre"><?= e($lastUpdateResult) ?></pre>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>
