<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
requireLogin();

// Only the user who posted the job can see its applicants
$jobId = (int) ($_GET['job_id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ? AND user_id = ?');
$stmt->execute([$jobId, currentUser()['id']]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    exit('Job not found.');
}

// Best candidates first, applications without a score at the end
$stmt = $db->prepare(
    'SELECT id, name, email, score, created_at FROM applications
     WHERE job_id = ? ORDER BY score IS NULL, score DESC, created_at'
);
$stmt->execute([$jobId]);
$applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Applicants - ' . $job['title'];
require __DIR__ . '/includes/header.php';
?>
<h1>Applicants: <?= htmlspecialchars($job['title']) ?></h1>
<p class="muted">Ranked by the model's match score.</p>

<?php if (!$applications): ?>
    <p>No applications yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>#</th><th>Name</th><th>Email</th><th>Score</th><th>Applied</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($applications as $i => $app): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($app['name']) ?></td>
                    <td><?= htmlspecialchars($app['email']) ?></td>
                    <td><?= scoreBadge($app['score'] === null ? null : (float) $app['score']) ?></td>
                    <td><?= htmlspecialchars(substr($app['created_at'], 0, 10)) ?></td>
                    <td><a href="application.php?id=<?= (int) $app['id'] ?>">Details</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<p><a href="index.php">&larr; Back to jobs</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
