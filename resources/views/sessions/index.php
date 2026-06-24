<?php /** @var array<int, array<string, mixed>> $sessions */ ?>
<section class="page-header"><div><p class="eyebrow">Story turns</p><h1><?= e($campaign['title']) ?></h1></div></section>
<div class="card table-card">
    <table>
        <thead><tr><th>No.</th><th>Title</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $session): ?>
            <tr>
                <td><?= (int) $session['sequence_number'] ?></td>
                <td><a href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions/' . $session['id'])) ?>"><?= e($session['title']) ?></a></td>
                <td><?= e($session['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
