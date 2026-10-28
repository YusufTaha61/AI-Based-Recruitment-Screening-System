<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

// Ids of the ticked candidates (at most 6), as whole numbers
$ids = array_map('intval', (array) ($_GET['ids'] ?? []));
$ids = array_slice(array_unique(array_filter($ids)), 0, 6);

$candidates = [];
if ($ids) {
    // One "?" for each id: WHERE id IN (?, ?, ?)
    $marks = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $db->prepare("SELECT * FROM candidates WHERE id IN ($marks) AND team_id = ? ORDER BY score DESC");
    $stmt->execute([...$ids, currentTeamId($db)]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Aday Karşılaştırma';
require __DIR__ . '/includes/header.php';

if (count($candidates) < 2): ?>
    <h1>Aday Karşılaştırma</h1>
    <div class="card">
        <h2>Karşılaştırmak için en az 2 aday seçin</h2>
        <p class="muted">Aday Geçmişi sayfasında adayların yanındaki kutucukları işaretleyin.</p>
        <a class="btn" href="history.php">&larr; Aday Geçmişi</a>
    </div>
<?php
    require __DIR__ . '/includes/footer.php';
    exit;
endif;

// Rows of the table: label, column, how to show the value
$rows = [
    ['Eğitim', 'education_level', fn($v) => educationLabel((int) $v)],
    ['Tecrübe', 'years_experience', fn($v) => (float) $v . ' yıl'],
    ['Teknik beceri', 'technical_skill', fn($v) => (float) $v . ' / 100'],
    ['İletişim', 'communication_skill', fn($v) => (float) $v . ' / 100'],
    ['İş istikrarı', 'job_stability', fn($v) => (float) $v . ' yıl'],
];

// Scores come from different models when position or level differ
$mixed = count(array_unique(array_column($candidates, 'position'))) > 1
    || count(array_unique(array_column($candidates, 'level'))) > 1;
?>
<h1>Aday Karşılaştırma</h1>
<p class="muted">Adaylar uyum puanına göre sıralandı. Yeşil hücre, o satırdaki en yüksek değeri gösterir.</p>

<?php if ($mixed): ?>
    <p class="alert alert-warning">Seçilen adaylar farklı pozisyon veya seviyeden. Puanları farklı modellerden geldiği için doğrudan karşılaştırmak yanıltıcı olabilir.</p>
<?php endif; ?>

<div class="card">
    <h2>Uyum Puanı Sıralaması</h2>
    <?php foreach ($candidates as $i => $c): $score = (float) $c['score']; ?>
        <div class="bar-row">
            <div class="bar-label">
                <span><?= $i + 1 ?>. <b><?= htmlspecialchars($c['name']) ?></b> &middot; <?= decisionLabel($score) ?></span>
                <b>%<?= number_format($score, 1, ',', '') ?></b>
            </div>
            <div class="bar bar-<?= scoreLevel($score) ?>"><div style="width: <?= round($score) ?>%"></div></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="table-wrap">
    <table class="compare-table">
        <thead>
            <tr>
                <th>Kriter</th>
                <?php foreach ($candidates as $c): ?><th><?= htmlspecialchars($c['name']) ?></th><?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Pozisyon</td>
                <?php foreach ($candidates as $c): ?><td><?= htmlspecialchars(CANDIDATE_POSITIONS[$c['position']] ?? $c['position']) ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td>Seviye</td>
                <?php foreach ($candidates as $c): ?><td><?= htmlspecialchars(CANDIDATE_LEVELS[$c['level']] ?? $c['level']) ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td>Uyum puanı</td>
                <?php foreach ($candidates as $c): ?><td><?= scoreBadge((float) $c['score']) ?></td><?php endforeach; ?>
            </tr>
            <?php foreach ($rows as [$label, $column, $show]):
                // Interns don't have job stability, so they are left out of that row
                $values = [];
                foreach ($candidates as $c) {
                    $values[] = ($column === 'job_stability' && $c['level'] === 'intern') ? null : (float) $c[$column];
                }
                $known = array_filter($values, fn($v) => $v !== null);
                // Highlight only when the candidates are not all equal
                $best = ($known && max($known) > min($known)) ? max($known) : null;
            ?>
                <tr>
                    <td><?= $label ?></td>
                    <?php foreach ($values as $v): ?>
                        <td class="<?= $v !== null && $v === $best ? 'best' : '' ?>"><?= $v === null ? '-' : $show($v) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td>Süreç durumu</td>
                <?php foreach ($candidates as $c): ?><td><?= htmlspecialchars($c['status']) ?></td><?php endforeach; ?>
            </tr>
            <tr>
                <td></td>
                <?php foreach ($candidates as $c): ?><td><a href="candidate.php?id=<?= (int) $c['id'] ?>">Detay</a></td><?php endforeach; ?>
            </tr>
        </tbody>
    </table>
</div>

<p><a href="history.php">&larr; Aday Geçmişi</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
