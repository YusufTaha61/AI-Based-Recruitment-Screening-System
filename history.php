<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

// Every evaluated candidate (used for the summary boxes)
$all = $db->query('SELECT score, status FROM candidates')->fetchAll(PDO::FETCH_ASSOC);

// Small summary boxes at the top of the page
$total = count($all);
$shortlisted = count(array_filter($all, fn($c) => (float) $c['score'] >= 70));
$hired = count(array_filter($all, fn($c) => $c['status'] === 'İşe Alındı'));
$average = $total ? array_sum(array_column($all, 'score')) / $total : 0;

// Filters chosen in the form (empty = show all)
$search = trim($_GET['q'] ?? '');
$decision = $_GET['decision'] ?? '';
$position = $_GET['position'] ?? '';
$level = $_GET['level'] ?? '';
$status = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';

// Build the WHERE part step by step; values go in $params, never into the SQL text
$where = [];
$params = [];
if ($search !== '') {
    $where[] = 'c.name LIKE ?';
    $params[] = '%' . $search . '%';
}
// Decision uses the same score limits as decisionLabel()
$decisionRules = [
    'high' => 'c.score >= 70',
    'medium' => 'c.score >= 40 AND c.score < 70',
    'low' => 'c.score < 40',
];
if (isset($decisionRules[$decision])) {
    $where[] = $decisionRules[$decision];
}
if (isset(CANDIDATE_POSITIONS[$position])) {
    $where[] = 'c.position = ?';
    $params[] = $position;
}
if (isset(CANDIDATE_LEVELS[$level])) {
    $where[] = 'c.level = ?';
    $params[] = $level;
}
if (in_array($status, CANDIDATE_STATUSES, true)) {
    $where[] = 'c.status = ?';
    $params[] = $status;
}

// Only these sort options are allowed
$sortOptions = [
    'newest' => ['En yeni önce', 'c.created_at DESC, c.id DESC'],
    'score' => ['Puana göre (yüksekten)', 'c.score DESC'],
    'name' => ['Ada göre (A-Z)', 'c.name COLLATE NOCASE'],
];
if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}

$sql = "SELECT c.id, c.name, c.position, c.level, c.score, c.status, c.created_at,
               COALESCE(u.name, 'Silinmiş kullanıcı') AS evaluator
        FROM candidates c
        LEFT JOIN users u ON u.id = c.user_id"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . ' ORDER BY ' . $sortOptions[$sort][1];
$stmt = $db->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <p class="muted small"><?= count($candidates) ?> aday gösteriliyor.</p>
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
