<?php /** @var array<int, array<string, mixed>> $sessions */
$latestId = $sessions !== [] ? (int) $sessions[0]['id'] : 0;
?>
<section class="page-header"><div><p class="eyebrow">Story turns</p><h1><?= e($campaign['title']) ?></h1></div></section>
<div class="card table-card">
    <table>
        <thead><tr><th>No.</th><th>Title</th><th>Status</th><th>Created</th></tr></thead>
        <tbody>
        <?php foreach ($sessions as $session): ?>
            <tr>
                <td><?= (int) $session['sequence_number'] ?></td>
                <td><a href="<?= e(url('/campaigns/' . $campaign['id'] . '/sessions/' . $session['id'])) ?>"><?= e($session['title']) ?></a></td>
                <td><?php if ((int) $session['id'] === $latestId): ?><span class="pill pill-success">Story head</span><?php else: ?><span class="pill">Read-only</span><?php endif; ?></td>
                <td><?= e($session['created_at']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
