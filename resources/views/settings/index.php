<section class="page-header"><div><p class="eyebrow">Preferences</p><h1>Settings</h1></div></section>
<div class="card form-card">
    <form method="post" action="<?= e(url('/settings/locale')) ?>">
        <?= App\Core\Csrf::field($app->session()) ?>
        <label>Language
            <select name="locale">
                <option value="en" <?= $app->translator()->locale() === 'en' ? 'selected' : '' ?>>English</option>
                <option value="cs" <?= $app->translator()->locale() === 'cs' ? 'selected' : '' ?>>Čeština</option>
            </select>
        </label>
        <button class="button button-primary" type="submit">Save</button>
    </form>
</div>
