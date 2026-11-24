<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install DnD ChatGPT Campaign</title>
    <link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<main class="page install-page">
    <section class="center-panel">
        <div class="card form-card wide">
            <p class="eyebrow">First run</p>
            <h1>Install DnD ChatGPT Campaign</h1>
            <?php if ($done): ?>
                <div class="flash flash-success">Installation completed.</div>
                <a class="button button-primary" href="index.php">Go to login</a>
            <?php else: ?>
                <?php if ($errors): ?><div class="alert"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
                <form method="post">
                    <div class="form-section"><h2>Database</h2>
                        <label>Host<input name="db_host" required value="<?= e($_POST['db_host'] ?? '') ?>"></label>
                        <label>Port<input name="db_port" value="<?= e($_POST['db_port'] ?? '3306') ?>"></label>
                        <label>Database<input name="db_name" required value="<?= e($_POST['db_name'] ?? '') ?>"></label>
                        <label>User<input name="db_user" required value="<?= e($_POST['db_user'] ?? '') ?>"></label>
                        <label>Password<input type="password" name="db_password" value="<?= e($_POST['db_password'] ?? '') ?>"></label>
                    </div>
                    <div class="form-section"><h2>Admin</h2>
                        <label>Email<input type="email" name="admin_email" required value="<?= e($_POST['admin_email'] ?? '') ?>"></label>
                        <label>Password<input type="password" name="admin_password" required minlength="10"></label>
                    </div>
                    <label>Default language
                        <select name="default_locale"><option value="en">English</option><option value="cs">Čeština</option></select>
                    </label>
                    <button class="button button-primary" type="submit">Install</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
</body>
</html>
