<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$jobs = $db->query(
    'SELECT jobs.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = jobs.id) AS applicant_count
     FROM jobs ORDER BY created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$user = currentUser();

$pageTitle = 'Job Listings';
require __DIR__ . '/includes/header.php';
?>
<h1>Job Listings</h1>
<?php if (!$jobs): ?>
    <p>No job postings yet.</p>
<?php endif; ?>
<?php foreach ($jobs as $job): ?>
    <div class="card job">
        <h2><?= htmlspecialchars($job['title']) ?></h2>
        <p class="muted">Posted on <?= htmlspecialchars(substr($job['created_at'], 0, 10)) ?></p>
        <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
        <p><strong>Requirements:</strong> <?= nl2br(htmlspecialchars($job['requirements'])) ?></p>
        <div class="actions">
            <a class="btn" href="apply.php?job_id=<?= (int) $job['id'] ?>">Apply</a>
            <?php if ($user && (int) $user['id'] === (int) $job['user_id']): ?>
                <a class="btn btn-outline" href="applicants.php?job_id=<?= (int) $job['id'] ?>">
                    Applicants (<?= (int) $job['applicant_count'] ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
