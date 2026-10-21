<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

// Load the application together with its job, only for the job owner
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare(
    'SELECT a.*, j.title, j.requirements FROM applications a
     JOIN jobs j ON j.id = a.job_id
     WHERE a.id = ? AND j.user_id = ?'
);
$stmt->execute([$id, currentUser()['id']]);
$app = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$app) {
    http_response_code(404);
    exit('Application not found.');
}

// Ask the model again to see which skills matched and why
$result = $app['cv_text'] ? scoreCv($app['cv_text'], $app['requirements']) : null;
$required = parseSkills($app['requirements']);
$matched = $result['matched_skills'] ?? [];
$missing = array_values(array_diff($required, $matched));
$features = $result['features'] ?? null;
$score = $app['score'] === null ? null : (float) $app['score'];

$pageTitle = 'Application - ' . $app['name'];
require __DIR__ . '/includes/header.php';
?>
<h1><?= htmlspecialchars($app['name']) ?></h1>
<p class="muted">
    <?= htmlspecialchars($app['email']) ?> &middot; applied for
    <strong><?= htmlspecialchars($app['title']) ?></strong>
</p>

<div class="card">
    <h2>Match score <?= scoreBadge($score) ?></h2>
    <?php if ($features): ?>
        <ul class="facts">
            <li><span>Skills matched</span><?= count($matched) ?> / <?= count($required) ?></li>
            <li><span>Experience</span><?= (int) $features['years_experience'] ?> years</li>
            <li><span>Education</span><?= educationLabel((int) $features['education_level']) ?></li>
        </ul>
    <?php else: ?>
        <p class="muted">Details are not available (CV text is empty or the model could not run).</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Skills</h2>
    <p>
        <?php foreach ($matched as $skill): ?>
            <span class="tag tag-ok"><?= htmlspecialchars($skill) ?></span>
        <?php endforeach; ?>
        <?php foreach ($missing as $skill): ?>
            <span class="tag tag-missing"><?= htmlspecialchars($skill) ?></span>
        <?php endforeach; ?>
    </p>
    <p class="muted">Green: found in the CV. Red: missing.</p>
</div>

<div class="card">
    <h2>CV text</h2>
    <pre class="cv-text"><?= htmlspecialchars($app['cv_text'] ?: 'No text could be read from this CV.') ?></pre>
</div>

<p><a href="applicants.php?job_id=<?= (int) $app['job_id'] ?>">&larr; Back to applicants</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
