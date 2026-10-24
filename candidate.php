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

// How many points each feature added (+) or removed (-), biggest effect first
$labels = array_values(CANDIDATE_FEATURES);
$contributions = [];
foreach (json_decode($candidate['contributions'] ?? '[]', true) ?: [] as $i => $points) {
    $contributions[$labels[$i]] = (float) $points;
}
uasort($contributions, fn($a, $b) => abs($b) <=> abs($a));
$maxEffect = max(array_map('abs', $contributions) ?: [1]) ?: 1;
$raised = array_filter($contributions, fn($p) => $p >= 1);
$lowered = array_filter($contributions, fn($p) => $p <= -1);
arsort($raised);
asort($lowered);

// How much the model cares about each feature in general (same for every candidate)
$importance = [];
foreach (json_decode($candidate['importance'] ?? '[]', true) ?: [] as $i => $share) {
    $importance[$labels[$i]] = $share * 100;
}
arsort($importance);
$maxImportance = max($importance ?: [1]) ?: 1;

[$strong, $weak] = candidateStrengths($candidate);

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

        <?php if ($contributions): ?>
        <div class="card">
            <h2>Bu Adayın Puanını Ne Etkiledi?</h2>
            <p class="muted">
                Bu pozisyon ve seviyedeki ortalama bir adayın puanı <strong>%<?= round((float) $candidate['baseline']) ?></strong>.
                <?php if ($raised): ?>
                    Puanı en çok <strong><?= array_key_first($raised) ?></strong> yükseltti
                    (+<?= round(reset($raised)) ?> puan)<?= $lowered ? ',' : '.' ?>
                <?php endif; ?>
                <?php if ($lowered): ?>
                    en çok <strong><?= array_key_first($lowered) ?></strong> düşürdü
                    (<?= round(reset($lowered)) ?> puan).
                <?php endif; ?>
            </p>
            <?php foreach ($contributions as $label => $points): ?>
                <div class="bar-row">
                    <div class="bar-label">
                        <span><?= $label ?></span>
                        <b class="<?= $points >= 0 ? 'text-up' : 'text-down' ?>"><?= $points >= 0 ? '+' : '' ?><?= number_format($points, 1, ',', '') ?> puan</b>
                    </div>
                    <div class="effect-bar">
                        <div class="<?= $points >= 0 ? 'up' : 'down' ?>" style="width: <?= round(abs($points) / $maxEffect * 50) ?>%"></div>
                    </div>
                </div>
            <?php endforeach; ?>
            <p class="muted small">Yeşil: puanı artıran, kırmızı: puanı düşüren özellik. (Shapley değeri yöntemiyle hesaplanır.)</p>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>Güçlü ve Zayıf Yönler</h2>
            <?php if (!$strong && !$weak): ?>
                <p class="muted">Aday tüm alanlarda orta düzeyde.</p>
            <?php endif; ?>
            <?php if ($strong): ?>
                <p><strong>Güçlü yönler</strong><br>
                    <?php foreach ($strong as $label): ?><span class="tag tag-ok">▲ <?= $label ?></span><?php endforeach; ?>
                </p>
            <?php endif; ?>
            <?php if ($weak): ?>
                <p><strong>Geliştirilmesi gereken yönler</strong><br>
                    <?php foreach ($weak as $label): ?><span class="tag tag-missing">▼ <?= $label ?></span><?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($importance): ?>
        <div class="card">
            <h2>Modelin Genel Olarak Önem Verdiği Özellikler</h2>
            <p class="muted">Bu pozisyon ve seviyedeki model, bütün adaylarda hangi özelliğe ne kadar ağırlık veriyor?</p>
            <?php foreach ($importance as $label => $percent): ?>
                <div class="bar-row">
                    <div class="bar-label"><span><?= $label ?></span><b>%<?= number_format($percent, 1, ',', '') ?></b></div>
                    <div class="bar"><div style="width: <?= round($percent / $maxImportance * 100) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="actions">
            <a class="btn" href="evaluate.php">+ Yeni Aday</a>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
