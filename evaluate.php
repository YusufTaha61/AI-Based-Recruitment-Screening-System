<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

$error = '';

// Load a job application (only the job owner's) when evaluating an applicant
function findApplication(PDO $db, int $id): ?array
{
    $stmt = $db->prepare(
        'SELECT a.*, j.title FROM applications a JOIN jobs j ON j.id = a.job_id
         WHERE a.id = ? AND j.user_id = ?'
    );
    $stmt->execute([$id, currentUser()['id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$application = null;
$applicationId = (int) ($_GET['application_id'] ?? $_POST['application_id'] ?? 0);
if ($applicationId) {
    $application = findApplication($db, $applicationId);
}

// Starting values of the form
$form = [
    'name' => '', 'position' => 'general', 'level' => 'mid', 'education_level' => 1,
    'years_experience' => 3, 'technical_skill' => 65, 'communication_skill' => 65, 'job_stability' => 3,
];

// Coming from an application: fill the form from the applicant's saved CV
if ($application && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $form['name'] = $application['name'];
    $form['position'] = guessPosition($application['title']);
    $profile = cvProfile(__DIR__ . '/uploads/' . $application['cv_path']);
    if (!isset($profile['error'])) {
        foreach (['level', 'education_level', 'years_experience', 'technical_skill', 'communication_skill', 'job_stability'] as $key) {
            $form[$key] = $profile[$key];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    // If something goes wrong, show the form again with what the user entered
    $form = array_merge($form, array_intersect_key($_POST, $form));
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
                'INSERT INTO candidates (user_id, application_id, name, position, level, education_level, years_experience,
                    technical_skill, communication_skill, job_stability, score, baseline, contributions, importance)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                currentUser()['id'], $application['id'] ?? null, $name, $position, $level,
                $values['education_level'], $values['years_experience'], $values['technical_skill'],
                $values['communication_skill'], $values['job_stability'],
                $result['score'], $result['baseline'],
                json_encode($result['contributions']), json_encode($result['importance']),
            ]);
            // Redirect so refreshing the page doesn't save the candidate twice
            header('Location: candidate.php?id=' . $db->lastInsertId());
            exit;
        }
    }
}

$pageTitle = 'Aday Değerlendir';
require __DIR__ . '/includes/header.php';
?>
<h1>Aday Değerlendir</h1>
<p class="muted">Adayın bilgilerini girin, yapay zeka modeli pozisyona uyum puanını hesaplasın.</p>

<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($application): ?>
    <p class="alert alert-success">
        <strong><?= htmlspecialchars($application['name']) ?></strong>, <strong><?= htmlspecialchars($application['title']) ?></strong>
        ilanına başvurdu. Form başvurudaki CV'ye, pozisyon ilan başlığına göre dolduruldu. Lütfen kontrol edin.
    </p>
<?php endif; ?>

<div class="form card">
    <h2>CV'den Otomatik Doldur <span class="hint">(isteğe bağlı)</span></h2>
    <p class="muted small">PDF veya TXT formatında CV seçin. Sistem bilgileri tahmin edip formu doldurur, siz kontrol edip düzeltirsiniz.</p>
    <div class="cv-row">
        <input type="file" id="cv-file" accept=".pdf,.txt">
        <button class="btn btn-outline" type="button" id="cv-read">CV'yi Oku</button>
    </div>
    <div id="cv-result" class="alert" hidden></div>
</div>

<form class="form card" method="post" action="evaluate.php">
    <?= csrfField() ?>
    <?php if ($application): ?>
        <input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>">
    <?php endif; ?>

    <p><label>Aday adı soyadı
        <input type="text" name="name" id="name" maxlength="60" placeholder="Örn: Ayşe Yılmaz" value="<?= htmlspecialchars($form['name']) ?>" required>
    </label></p>

    <p><label>Pozisyon
        <select name="position" id="position">
            <?php foreach (CANDIDATE_POSITIONS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $key === $form['position'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="hint">Her pozisyonun kendi modeli var. Örneğin satışta iletişim, yazılımda teknik beceri daha önemli.</span>
    </label></p>

    <p><label>Tecrübe seviyesi
        <select name="level" id="level">
            <?php foreach (CANDIDATE_LEVELS as $key => $label): ?>
                <option value="<?= $key ?>" <?= $key === $form['level'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <p><label>Eğitim
        <select name="education_level" id="education_level">
            <?php foreach ([1, 2, 3] as $value): ?>
                <option value="<?= $value ?>" <?= (int) $form['education_level'] === $value ? 'selected' : '' ?>><?= educationLabel($value) ?></option>
            <?php endforeach; ?>
        </select>
    </label></p>

    <p><label for="years_experience">Tecrübe (yıl) <span class="hint" id="years-range"></span></label>
        <span class="range-row">
            <input type="range" name="years_experience" id="years_experience" min="<?= CANDIDATE_LEVEL_YEARS[$form['level']][0] ?>" max="<?= CANDIDATE_LEVEL_YEARS[$form['level']][1] ?>" step="0.5" value="<?= (float) $form['years_experience'] ?>">
            <span class="range-value" data-for="years_experience">3</span>
        </span>
    </p>

    <p><label for="technical_skill">Teknik beceri (30-100)</label>
        <span class="range-row">
            <input type="range" name="technical_skill" id="technical_skill" min="30" max="100" step="1" value="<?= (float) $form['technical_skill'] ?>">
            <span class="range-value" data-for="technical_skill">65</span>
        </span>
    </p>

    <p><label for="communication_skill">İletişim becerisi (30-100)</label>
        <span class="range-row">
            <input type="range" name="communication_skill" id="communication_skill" min="30" max="100" step="1" value="<?= (float) $form['communication_skill'] ?>">
            <span class="range-value" data-for="communication_skill">65</span>
        </span>
    </p>

    <p id="stability-box"><label for="job_stability">İş istikrarı (yıl)</label>
        <span class="range-row">
            <input type="range" name="job_stability" id="job_stability" min="0.5" max="10" step="0.1" value="<?= (float) $form['job_stability'] ?>">
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
document.querySelectorAll('input[type=range]').forEach(showValue);
levelChanged();

// Read the chosen CV on the server and fill the form with the guesses
document.getElementById('cv-read').addEventListener('click', function () {
    var file = document.getElementById('cv-file').files[0];
    var box = document.getElementById('cv-result');
    box.hidden = false;
    if (!file) {
        box.className = 'alert alert-error';
        box.textContent = 'Önce bir CV dosyası seçin.';
        return;
    }
    box.className = 'alert';
    box.textContent = 'CV okunuyor...';

    var data = new FormData();
    data.append('cv', file);
    data.append('csrf_token', document.querySelector('input[name=csrf_token]').value);

    fetch('cv_profile.php', { method: 'POST', body: data })
        .then(function (response) { return response.text(); })
        .then(function (text) {
            var result;
            try { result = JSON.parse(text); } catch (e) { result = { error: text || 'Sunucudan cevap gelmedi.' }; }
            if (result.error) {
                box.className = 'alert alert-error';
                box.textContent = result.error;
                return;
            }

            if (result.name) document.getElementById('name').value = result.name;
            document.getElementById('education_level').value = result.education_level;
            document.getElementById('level').value = result.level;
            levelChanged(); // sets the experience range for the guessed level
            ['years_experience', 'technical_skill', 'communication_skill', 'job_stability'].forEach(function (id) {
                var slider = document.getElementById(id);
                slider.value = result[id];
                showValue(slider);
            });

            box.className = 'alert alert-success';
            box.textContent = 'Form CV\'ye göre dolduruldu. Değerler tahminidir, lütfen kontrol edin.';
            if (result.technical_found.length) {
                box.appendChild(document.createElement('br'));
                box.appendChild(document.createTextNode('Teknik beceriler: ' + result.technical_found.join(', ')));
            }
            if (result.communication_found.length) {
                box.appendChild(document.createElement('br'));
                box.appendChild(document.createTextNode('İletişim becerileri: ' + result.communication_found.join(', ')));
            }
        })
        .catch(function () {
            box.className = 'alert alert-error';
            box.textContent = 'CV okunamadı, sunucuya ulaşılamadı.';
        });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
