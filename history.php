<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
require __DIR__ . '/includes/candidate_filters.php';
require __DIR__ . '/includes/csrf.php';
requireLogin();

// Delete a candidate (only HR managers can do this)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verifyCsrf();
    requireHr();
    $stmt = $db->prepare('DELETE FROM candidates WHERE id = ?');
    $stmt->execute([(int) $_POST['delete']]);
    header('Location: history.php');
    exit;
}

// Every evaluated candidate (used for the summary boxes)
$all = $db->query('SELECT score, status FROM candidates')->fetchAll(PDO::FETCH_ASSOC);

// Small summary boxes at the top of the page
$total = count($all);
$shortlisted = count(array_filter($all, fn($c) => (float) $c['score'] >= 70));
$hired = count(array_filter($all, fn($c) => $c['status'] === 'İşe Alındı'));
$average = $total ? array_sum(array_column($all, 'score')) / $total : 0;

// Candidates that match the filters (the same code is used by the CSV export)
[$candidates, $filters] = filteredCandidates($db, $_GET);
['q' => $search, 'decision' => $decision, 'position' => $position,
 'level' => $level, 'status' => $status, 'sort' => $sort] = $filters;
$sortOptions = CANDIDATE_SORTS;

// Helper: "selected" for the option that is currently chosen
$sel = fn($current, $value) => (string) $current === (string) $value ? 'selected' : '';

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

<form class="form filters card" method="get">
    <label>Ada göre ara
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Aday adı...">
    </label>
    <label>Karar
        <select name="decision">
            <option value="">Tümü</option>
            <option value="high" <?= $sel($decision, 'high') ?>>Kısa Listeye Alındı</option>
            <option value="medium" <?= $sel($decision, 'medium') ?>>Değerlendirilebilir</option>
            <option value="low" <?= $sel($decision, 'low') ?>>Elendi</option>
        </select>
    </label>
    <label>Pozisyon
        <select name="position">
            <option value="">Tümü</option>
            <?php foreach (CANDIDATE_POSITIONS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $sel($position, $key) ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Seviye
        <select name="level">
            <option value="">Tümü</option>
            <?php foreach (CANDIDATE_LEVELS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $sel($level, $key) ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Durum
        <select name="status">
            <option value="">Tümü</option>
            <?php foreach (CANDIDATE_STATUSES as $s): ?>
                <option <?= $sel($status, $s) ?>><?= htmlspecialchars($s) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Sıralama
        <select name="sort">
            <?php foreach ($sortOptions as $key => [$label]): ?>
                <option value="<?= $key ?>" <?= $sel($sort, $key) ?>><?= $label ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="filter-buttons">
        <button class="btn" type="submit">Uygula</button>
        <a class="btn btn-outline" href="history.php">Temizle</a>
    </div>
</form>

<?php if (!$candidates && $total): ?>
    <div class="card"><p>Filtreye uyan aday bulunamadı.</p></div>
<?php elseif (!$candidates): ?>
    <div class="card">
        <p>Henüz değerlendirilmiş aday yok.</p>
        <a class="btn" href="evaluate.php">İlk adayı değerlendir</a>
    </div>
<?php else: ?>
    <!-- The "Sil" buttons send this hidden form (a form can't be inside another form) -->
    <form id="delete-form" method="post" action="history.php"><?= csrfField() ?></form>

    <!-- Ticked candidates are sent to the comparison page -->
    <form method="get" action="compare.php">
    <div class="compare-bar">
        <span class="muted small"><?= count($candidates) ?> aday gösteriliyor. Karşılaştırmak için 2 ile 6 aday seçin.</span>
        <span class="filter-buttons">
            <button class="btn btn-small" type="submit">Seçilenleri Karşılaştır</button>
            <?php if (isHr()): ?>
                <a class="btn btn-small btn-outline" href="export.php?<?= htmlspecialchars(http_build_query($filters)) ?>">Excel'e Aktar (CSV)</a>
            <?php endif; ?>
        </span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th></th><th>Aday</th><th>Pozisyon</th><th>Seviye</th><th>Puan</th><th>Karar</th><th>Durum</th><th>Değerlendiren</th><th>Tarih</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($candidates as $c): $score = (float) $c['score']; ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?= (int) $c['id'] ?>" aria-label="Seç"></td>
                    <td><b><?= htmlspecialchars($c['name']) ?></b></td>
                    <td><?= htmlspecialchars(CANDIDATE_POSITIONS[$c['position']] ?? $c['position']) ?></td>
                    <td><?= htmlspecialchars(CANDIDATE_LEVELS[$c['level']] ?? $c['level']) ?></td>
                    <td><?= scoreBadge($score) ?></td>
                    <td><?= decisionLabel($score) ?></td>
                    <td><?= htmlspecialchars($c['status']) ?></td>
                    <td><?= htmlspecialchars($c['evaluator']) ?></td>
                    <td><?= localDateTime($c['created_at']) ?></td>
                    <td>
                        <a href="candidate.php?id=<?= (int) $c['id'] ?>">Detay</a>
                        <?php if (isHr()): ?>
                            <button class="btn btn-danger btn-small" type="submit" form="delete-form"
                                    name="delete" value="<?= (int) $c['id'] ?>"
                                    onclick="return confirm(<?= htmlspecialchars(json_encode($c['name'] . ' silinsin mi?', JSON_UNESCAPED_UNICODE)) ?>)">Sil</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
