<?php
require __DIR__ . '/includes/db.php';

$jobs = $db->query('SELECT * FROM jobs ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Job Listings';
require __DIR__ . '/includes/header.php';
?>
<h1>Job Listings</h1>
<?php if (!$jobs): ?>
    <p>No job postings yet.</p>
<?php endif; ?>
<?php foreach ($jobs as $job): ?>
    <div>
        <h2><?= htmlspecialchars($job['title']) ?></h2>
        <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
        <p><strong>Requirements:</strong> <?= nl2br(htmlspecialchars($job['requirements'])) ?></p>
        <a href="apply.php?job_id=<?= (int) $job['id'] ?>">Apply</a>
    </div>
    <hr>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
