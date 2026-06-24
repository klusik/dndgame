<?php /** @var array<string, string> $errors */ ?>
<section class="page-header">
    <div>
        <p class="eyebrow">New campaign</p>
        <h1>Create campaign</h1>
    </div>
</section>
<div class="card form-card wide">
    <?php if ($errors): ?><div class="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('/campaigns')) ?>">
        <?= App\Core\Csrf::field($app->session()) ?>
        <label>Title<input type="text" name="title" value="<?= e($title ?? '') ?>" required></label>
        <label>Default language
            <select name="default_language">
                <option value="en">English</option>
                <option value="cs">Čeština</option>
            </select>
        </label>
        <label>Description<textarea name="description" rows="8" placeholder="Tone, genre, rules, starting premise."><?= e($description ?? '') ?></textarea></label>
        <button class="button button-primary" type="submit">Create campaign</button>
    </form>
</div>
