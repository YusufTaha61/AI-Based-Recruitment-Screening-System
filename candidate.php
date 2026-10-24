<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

// Load the candidate together with the name of the person who evaluated it
$stmt = $db->prepare(
    'SELECT c.*, u.name AS evaluator FROM candidates c
     JOIN users u ON u.id = c.user_id
     WHERE c.id = ?'
);
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$candidate = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$candidate) {
    http_response_code(404);
    exit('Aday bulunamadı.');
}

$score = (float) $candidate['score'];
$level = scoreLevel($score);

$pageTitle = 'Aday - ' . $candidate['name'];
require __DIR__ . '/includes/header.php';
?>
<h1><?= htmlspecialchars($candidate['name']) ?></h1>
<p class="muted">
    <?= htmlspecialchars(CANDIDATE_POSITIONS[$candidate['position']] ?? $candidate['position']) ?> &middot;
    <?= htmlspecialchars(CANDIDATE_LEVELS[$candidate['level']] ?? $candidate['level']) ?> &middot;
    <?= localDateTime($candidate['created_at']) ?> &middot;
    Değerlendiren: <?= htmlspecialchars($candidate['evaluator']) ?>
</p>

<div class="result-grid">
    <div>
        <div class="card score-card">
            <h2>Uyum Puanı</h2>
            <?= scoreGauge($score) ?>
            <span class="decision decision-<?= $level ?>"><?= decisionLabel($score) ?></span>
            <p class="muted score-limits">
                %70 ve üstü: Kısa liste<br>
                %40 – %70: Değerlendirilebilir<br>
                %40 altı: Elendi
            </p>
        </div>
    </div>

    <div>
        <div class="card">
            <h2>Aday Bilgileri</h2>
            <ul class="facts">
                <li><span>Pozisyon</span><?= htmlspecialchars(CANDIDATE_POSITIONS[$candidate['position']] ?? '-') ?></li>
                <li><span>Seviye</span><?= htmlspecialchars(CANDIDATE_LEVELS[$candidate['level']] ?? '-') ?></li>
                <li><span>Eğitim</span><?= educationLabel((int) $candidate['education_level']) ?></li>
                <li><span>Tecrübe</span><?= (float) $candidate['years_experience'] ?> yıl</li>
                <li><span>Teknik beceri</span><?= (float) $candidate['technical_skill'] ?> / 100</li>
                <li><span>İletişim</span><?= (float) $candidate['communication_skill'] ?> / 100</li>
                <?php if ($candidate['level'] !== 'intern'): ?>
                    <li><span>İş istikrarı</span><?= (float) $candidate['job_stability'] ?> yıl</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="actions">
            <a class="btn" href="evaluate.php">+ Yeni Aday</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
