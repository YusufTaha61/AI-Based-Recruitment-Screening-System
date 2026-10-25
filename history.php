<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

// Every evaluated candidate, newest first, with the evaluator's name
$candidates = $db->query(
    "SELECT c.id, c.name, c.position, c.level, c.score, c.status, c.created_at,
            COALESCE(u.name, 'Silinmiş kullanıcı') AS evaluator
     FROM candidates c
     LEFT JOIN users u ON u.id = c.user_id
     ORDER BY c.created_at DESC, c.id DESC"
)->fetchAll(PDO::FETCH_ASSOC);

// Small summary boxes at the top of the page
$total = count($candidates);
$shortlisted = count(array_filter($candidates, fn($c) => (float) $c['score'] >= 70));
$hired = count(array_filter($candidates, fn($c) => $c['status'] === 'İşe Alındı'));
$average = $total ? array_sum(array_column($candidates, 'score')) / $total : 0;

$pageTitle = 'Aday Geçmişi';
require __DIR__ . '/includes/header.php';
?>
<h1>Aday Geçmişi</h1>
<p class="muted">Değerlendirilen bütün adaylar burada listelenir.</p>

<div class="stats">
    <div class="stat"><b><?= $total ?></b><span>Toplam aday</span></div>
    <div class="stat"><b><?= $shortlisted ?></b><span>Kısa listedeki aday</span></div>
    <div class="stat"><b>%<?= round($average) ?></b><span>Ortalama uyum puanı</span></div>
    <div class="stat"><b><?= $hired ?></b><span>İşe alınan</span></div>
</div>

<?php if (!$candidates): ?>
    <div class="card">
        <p>Henüz değerlendirilmiş aday yok.</p>
        <a class="btn" href="evaluate.php">İlk adayı değerlendir</a>
    </div>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Aday</th><th>Pozisyon</th><th>Seviye</th><th>Puan</th><th>Karar</th><th>Durum</th><th>Değerlendiren</th><th>Tarih</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $c): $score = (float) $c['score']; ?>
                <tr>
                    <td><b><?= htmlspecialchars($c['name']) ?></b></td>
                    <td><?= htmlspecialchars(CANDIDATE_POSITIONS[$c['position']] ?? $c['position']) ?></td>
                    <td><?= htmlspecialchars(CANDIDATE_LEVELS[$c['level']] ?? $c['level']) ?></td>
                    <td><?= scoreBadge($score) ?></td>
                    <td><?= decisionLabel($score) ?></td>
                    <td><?= htmlspecialchars($c['status']) ?></td>
                    <td><?= htmlspecialchars($c['evaluator']) ?></td>
                    <td><?= localDateTime($c['created_at']) ?></td>
                    <td><a href="candidate.php?id=<?= (int) $c['id'] ?>">Detay</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
