<?php /** @var array<int, array<string, mixed>> $facts */ ?>
<section class="page-header"><div><p class="eyebrow">Facts</p><h1><?= e($campaign['title']) ?></h1></div></section>
<div class="card form-card wide compact-form">
    <h2>Add manual fact</h2>
    <form method="post" action="<?= e(url('/campaigns/' . $campaign['id'] . '/facts')) ?>">
        <?= App\Core\Csrf::field($app->session()) ?>
        <input name="subject" placeholder="Subject">
        <input name="predicate" placeholder="Predicate">
        <input name="value" placeholder="Value">
        <select name="certainty"><option>confirmed</option><option>inferred</option><option>suspected</option><option>false</option><option>dm_only</option></select>
        <button class="button" type="submit">Add</button>
    </form>
</div>
<div class="card table-card">
    <table>
        <thead><tr><th>Category</th><th>Subject</th><th>Predicate</th><th>Value</th><th>Certainty</th><th>Visibility</th></tr></thead>
        <tbody>
        <?php foreach ($facts as $fact): ?>
            <tr>
                <td><?= e($fact['category']) ?></td>
                <td><?= e($fact['entity_name'] ?: $fact['subject_text']) ?></td>
                <td><?= e($fact['predicate']) ?></td>
                <td><?= e($fact['value_text']) ?></td>
                <td><?= e($fact['certainty']) ?></td>
                <td><?= e($fact['visibility']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
