<?php
// Printable candidate report. Use the browser's "Save as PDF" to get a PDF file.
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

$stmt = $db->prepare(
    "SELECT c.*, COALESCE(u.name, 'Silinmiş kullanıcı') AS evaluator FROM candidates c
     LEFT JOIN users u ON u.id = c.user_id
     WHERE c.id = ? AND c.team_id = ?"
);
$stmt->execute([(int) ($_GET['id'] ?? 0), currentTeamId($db)]);
$c = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$c) {
    http_response_code(404);
    exit('Aday bulunamadı.');
}

$score = (float) $c['score'];
$isIntern = $c['level'] === 'intern';
[$strong, $weak] = candidateStrengths($c);

// Points each feature added (+) or removed (-); interns skip job stability
$labels = array_values(CANDIDATE_FEATURES);
$contributions = [];
foreach (json_decode($c['contributions'] ?? '[]', true) ?: [] as $i => $points) {
    if (!($isIntern && $i === 4)) {
        $contributions[$labels[$i]] = (float) $points;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aday Raporu - <?= htmlspecialchars($c['name']) ?></title>
    <link rel="stylesheet" href="assets/style.css?v=<?= filemtime(__DIR__ . '/assets/style.css') ?>">
</head>
<body class="report-body">
<!-- This bar is hidden when printing -->
<div class="report-tools">
    <button class="btn" type="button" onclick="window.print()">Yazdır / PDF Olarak Kaydet</button>
    <a class="btn btn-outline" href="candidate.php?id=<?= (int) $c['id'] ?>">&larr; Adaya dön</a>
    <span class="muted small">Yazdırma penceresinde hedef olarak "PDF olarak kaydet" seçin.</span>
</div>

<div class="report-page">
    <div class="report-head">
        <div>
            <div class="muted small">Akıllı İK · Aday Değerlendirme Raporu</div>
            <h1><?= htmlspecialchars($c['name']) ?></h1>
            <div>
                <?= htmlspecialchars(CANDIDATE_POSITIONS[$c['position']] ?? $c['position']) ?> &middot;
                <?= htmlspecialchars(CANDIDATE_LEVELS[$c['level']] ?? $c['level']) ?> &middot;
                <?= localDateTime($c['created_at']) ?>
            </div>
        </div>
        <div class="report-score decision-<?= scoreLevel($score) ?>">
            <b>%<?= round($score) ?></b>
            <span><?= decisionLabel($score) ?></span>
        </div>
    </div>

    <h2>Aday Bilgileri</h2>
    <table class="report-table">
        <tr><td>Eğitim</td><td><?= educationLabel((int) $c['education_level']) ?></td></tr>
        <tr><td>Tecrübe</td><td><?= (float) $c['years_experience'] ?> yıl</td></tr>
        <tr><td>Teknik beceri</td><td><?= (float) $c['technical_skill'] ?> / 100</td></tr>
        <tr><td>İletişim</td><td><?= (float) $c['communication_skill'] ?> / 100</td></tr>
        <?php if (!$isIntern): ?>
            <tr><td>İş istikrarı</td><td><?= (float) $c['job_stability'] ?> yıl</td></tr>
        <?php endif; ?>
        <tr><td>Süreç durumu</td><td><?= htmlspecialchars($c['status']) ?></td></tr>
        <tr><td>Değerlendiren</td><td><?= htmlspecialchars($c['evaluator']) ?></td></tr>
    </table>

    <?php if ($contributions): ?>
        <h2>Puanı Etkileyen Özellikler</h2>
        <?php if ($c['baseline'] !== null): ?>
            <p>Bu pozisyon ve seviyedeki ortalama bir adayın puanı %<?= round((float) $c['baseline']) ?>. Her özelliğin bu adayın puanına etkisi:</p>
        <?php endif; ?>
        <table class="report-table">
            <?php foreach ($contributions as $label => $points): ?>
                <tr>
                    <td><?= $label ?></td>
                    <td class="<?= $points >= 0 ? 'text-up' : 'text-down' ?>"><?= $points >= 0 ? '+' : '' ?><?= number_format($points, 1, ',', '') ?> puan</td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>

    <h2>Güçlü ve Zayıf Yönler</h2>
    <p><b>Güçlü yönler:</b> <?= $strong ? implode(', ', $strong) : '-' ?></p>
    <p><b>Geliştirilmesi gereken yönler:</b> <?= $weak ? implode(', ', $weak) : '-' ?></p>

    <?php if ($c['notes'] !== ''): ?>
        <h2>Notlar</h2>
        <p class="notes"><?= nl2br(htmlspecialchars($c['notes'])) ?></p>
    <?php endif; ?>

    <p class="report-note">
        Bu rapor bir yapay zeka modeli (Random Forest) tarafından üretilmiştir ve yalnızca karar destek amaçlıdır.
        Son işe alım kararı insan kaynaklarına aittir. Rapor tarihi: <?= localDateTime('now') ?>
    </p>
</div>
</body>
</html>
