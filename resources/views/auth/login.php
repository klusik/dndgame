<?php /** @var array<string, string> $errors */ ?>
<section class="center-panel">
    <div class="card form-card">
        <h1><?= e($app->translator()->get('auth.login')) ?></h1>
        <?php if ($errors): ?>
            <div class="alert"><?= e(implode(' ', $errors)) ?></div>
        <?php endif; ?>
        <form method="post" action="<?= e(url('/login')) ?>">
            <?= App\Core\Csrf::field($app->session()) ?>
            <label><?= e($app->translator()->get('auth.email')) ?><input type="email" name="email" value="<?= e($email ?? '') ?>" required></label>
            <label><?= e($app->translator()->get('auth.password')) ?><input type="password" name="password" required></label>
            <button class="button button-primary" type="submit"><?= e($app->translator()->get('auth.login')) ?></button>
        </form>
    </div>
</section>
