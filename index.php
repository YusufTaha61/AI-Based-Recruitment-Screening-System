<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$jobs = $db->query(
    'SELECT jobs.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = jobs.id) AS applicant_count
     FROM jobs ORDER BY created_at DESC'
)->fetchAll(PDO::FETCH_ASSOC);

$user = currentUser();

$pageTitle = 'İş İlanları';
require __DIR__ . '/includes/header.php';
?>
<h1>İş İlanları</h1>
<?php if (!$jobs): ?>
    <p>Henüz ilan yok.</p>
<?php endif; ?>
<?php foreach ($jobs as $job): ?>
    <div class="card job">
        <h2><?= htmlspecialchars($job['title']) ?></h2>
        <p class="muted">Yayın tarihi: <?= date('d.m.Y', strtotime($job['created_at'])) ?></p>
        <p><?= nl2br(htmlspecialchars($job['description'])) ?></p>
        <p><strong>Aranan nitelikler:</strong> <?= nl2br(htmlspecialchars($job['requirements'])) ?></p>
        <div class="actions">
            <a class="btn" href="apply.php?job_id=<?= (int) $job['id'] ?>">Başvur</a>
            <?php if ($user && (int) $user['id'] === (int) $job['user_id']): ?>
                <a class="btn btn-outline" href="applicants.php?job_id=<?= (int) $job['id'] ?>">
                    Başvuranlar (<?= (int) $job['applicant_count'] ?>)
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
