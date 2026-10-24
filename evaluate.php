<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

$error = '';
$saved = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 60);
    $position = $_POST['position'] ?? '';
    $level = $_POST['level'] ?? '';

    if ($name === '') {
        $error = 'Lütfen adayın adını yazın.';
    } elseif (!isset(CANDIDATE_POSITIONS[$position], CANDIDATE_LEVELS[$level])) {
        $error = 'Geçersiz pozisyon veya seviye.';
    } else {
        // Ask the model for this position + level
        $values = candidateValuesFromForm($_POST, $level);
        $result = evaluateCandidate($position, $level, $values);

        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $stmt = $db->prepare(
                'INSERT INTO candidates (user_id, name, position, level, education_level, years_experience,
                    technical_skill, communication_skill, job_stability, score, baseline, contributions, importance)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                currentUser()['id'], $name, $position, $level,
                $values['education_level'], $values['years_experience'], $values['technical_skill'],
                $values['communication_skill'], $values['job_stability'],
                $result['score'], $result['baseline'],
                json_encode($result['contributions']), json_encode($result['importance']),
            ]);
            // Redirect so refreshing the page doesn't save the candidate twice
            header('Location: evaluate.php?saved=' . $db->lastInsertId());
            exit;
        }
    }
}

// Show the result of the candidate that was just saved
if (isset($_GET['saved'])) {
    $stmt = $db->prepare('SELECT name, score FROM candidates WHERE id = ? AND user_id = ?');
    $stmt->execute([(int) $_GET['saved'], currentUser()['id']]);
    $saved = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$pageTitle = 'Aday Değerlendir';
require __DIR__ . '/includes/header.php';
?>
<h1>Aday Değerlendir</h1>
<p class="muted">Adayın bilgilerini girin, yapay zeka modeli pozisyona uyum puanını hesaplasın.</p>

<?php if ($saved): ?>
    <p class="alert alert-success">
        <strong><?= htmlspecialchars($saved['name']) ?></strong> kaydedildi. Uyum puanı: <?= scoreBadge((float) $saved['score']) ?>
    </p>
<?php endif; ?>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<form class="form card" method="post" action="evaluate.php">
    <?= csrfField() ?>

    <p><label>Aday adı soyadı
        <input type="text" name="name" id="name" maxlength="60" placeholder="Örn: Ayşe Yılmaz" required>
    </label></p>

    <p><label>Pozisyon
        <select name="position" id="position">
            <?php foreach (CANDIDATE_POSITIONS as $key => $label): ?>
                <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="hint">Her pozisyonun kendi modeli var. Örneğin satışta iletişim, yazılımda teknik beceri daha önemli.</span>
    </label></p>

    <p><label>Tecrübe seviyesi
        <select name="level" id="level">
            <?php foreach (CANDIDATE_LEVELS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $key === 'mid' ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <p><label>Eğitim
        <select name="education_level" id="education_level">
            <option value="1">Lisans</option>
            <option value="2">Yüksek Lisans</option>
            <option value="3">Doktora</option>
        </select>
    </label></p>

    <p><label for="years_experience">Tecrübe (yıl) <span class="hint" id="years-range"></span></label>
        <span class="range-row">
            <input type="range" name="years_experience" id="years_experience" min="2" max="8" step="0.5" value="3">
            <span class="range-value" data-for="years_experience">3</span>
        </span>
    </p>

    <p><label for="technical_skill">Teknik beceri (30-100)</label>
        <span class="range-row">
            <input type="range" name="technical_skill" id="technical_skill" min="30" max="100" step="1" value="65">
            <span class="range-value" data-for="technical_skill">65</span>
        </span>
    </p>

    <p><label for="communication_skill">İletişim becerisi (30-100)</label>
        <span class="range-row">
            <input type="range" name="communication_skill" id="communication_skill" min="30" max="100" step="1" value="65">
            <span class="range-value" data-for="communication_skill">65</span>
        </span>
    </p>

    <p id="stability-box"><label for="job_stability">İş istikrarı (yıl)</label>
        <span class="range-row">
            <input type="range" name="job_stability" id="job_stability" min="0.5" max="10" step="0.1" value="3">
            <span class="range-value" data-for="job_stability">3.0</span>
        </span>
        <span class="hint">Bir işte ortalama kaç yıl kaldığı. Stajyerlerde dikkate alınmaz.</span>
    </p>

    <button class="btn" type="submit">Yapay Zeka ile Değerlendir</button>
</form>

<script>
var LEVEL_YEARS = <?= json_encode(CANDIDATE_LEVEL_YEARS) ?>;

// Show the slider's number next to it
function showValue(slider) {
    var box = document.querySelector('.range-value[data-for="' + slider.id + '"]');
    box.textContent = slider.step.indexOf('.') > -1 ? parseFloat(slider.value).toFixed(1) : slider.value;
}

// Each level has its own experience range; interns don't use job stability
function levelChanged() {
    var level = document.getElementById('level').value;
    var years = document.getElementById('years_experience');
    var range = LEVEL_YEARS[level];
    years.min = range[0];
    years.max = range[1];
    if (years.value < range[0] || years.value > range[1]) {
        years.value = range[0];
    }
    showValue(years);
    document.getElementById('years-range').textContent = '(' + range[0] + '-' + range[1] + ' yıl)';
    document.getElementById('stability-box').hidden = level === 'intern';
}

document.querySelectorAll('input[type=range]').forEach(function (slider) {
    slider.addEventListener('input', function () { showValue(slider); });
});
document.getElementById('level').addEventListener('change', levelChanged);
levelChanged();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
