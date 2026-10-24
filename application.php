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
    exit('Başvuru bulunamadı.');
}

// Ask the model again to see which skills matched and why
$result = $app['cv_text'] ? scoreCv($app['cv_text'], $app['requirements']) : null;
$required = parseSkills($app['requirements']);
$matched = $result['matched_skills'] ?? [];
$missing = array_values(array_diff($required, $matched));
$features = $result['features'] ?? null;
$score = $app['score'] === null ? null : (float) $app['score'];

// Evaluations made for this application
$stmt = $db->prepare('SELECT id, position, level, score, status, created_at FROM candidates WHERE application_id = ? ORDER BY id DESC');
$stmt->execute([$app['id']]);
$evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Başvuru - ' . $app['name'];
require __DIR__ . '/includes/header.php';
?>
<h1><?= htmlspecialchars($app['name']) ?></h1>
<p class="muted">
    <?= htmlspecialchars($app['email']) ?> &middot;
    <strong><?= htmlspecialchars($app['title']) ?></strong> ilanına başvurdu
</p>

<div class="card">
    <h2>Uyum puanı <?= scoreBadge($score) ?></h2>
    <?php if ($features): ?>
        <ul class="facts">
            <li><span>Eşleşen nitelik</span><?= count($matched) ?> / <?= count($required) ?></li>
            <li><span>Deneyim</span><?= (int) $features['years_experience'] ?> yıl</li>
            <li><span>Eğitim</span><?= educationLabel((int) $features['education_level']) ?></li>
        </ul>
    <?php else: ?>
        <p class="muted">Detaylar gösterilemiyor (CV metni boş ya da model çalıştırılamadı).</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Nitelikler</h2>
    <p>
        <?php foreach ($matched as $skill): ?>
            <span class="tag tag-ok"><?= htmlspecialchars($skill) ?></span>
        <?php endforeach; ?>
        <?php foreach ($missing as $skill): ?>
            <span class="tag tag-missing"><?= htmlspecialchars($skill) ?></span>
        <?php endforeach; ?>
    </p>
    <p class="muted">Yeşil: CV'de bulundu. Kırmızı: eksik.</p>
</div>

<div class="card">
    <h2>Aday Değerlendirmesi</h2>
    <p class="muted">CV'deki eğitim, tecrübe ve becerilere göre adayı pozisyon modeliyle değerlendirin.</p>
    <?php if ($evaluations): ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Pozisyon</th><th>Seviye</th><th>Puan</th><th>Durum</th><th>Tarih</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($evaluations as $evaluation): ?>
                    <tr>
                        <td><?= htmlspecialchars(CANDIDATE_POSITIONS[$evaluation['position']] ?? '-') ?></td>
                        <td><?= htmlspecialchars(CANDIDATE_LEVELS[$evaluation['level']] ?? '-') ?></td>
                        <td><?= scoreBadge((float) $evaluation['score']) ?></td>
                        <td><?= htmlspecialchars($evaluation['status']) ?></td>
                        <td><?= localDateTime($evaluation['created_at']) ?></td>
                        <td><a href="candidate.php?id=<?= (int) $evaluation['id'] ?>">Sonuç</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    <div class="actions">
        <a class="btn" href="evaluate.php?application_id=<?= (int) $app['id'] ?>">Bu Adayı Değerlendir</a>
    </div>
</div>

<div class="card">
    <h2>CV metni</h2>
    <pre class="cv-text"><?= htmlspecialchars($app['cv_text'] ?: 'Bu CV\'den metin okunamadı.') ?></pre>
</div>

<p><a href="applicants.php?job_id=<?= (int) $app['job_id'] ?>">&larr; Başvuranlara dön</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
