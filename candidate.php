<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
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
$isIntern = $candidate['level'] === 'intern'; // interns don't use job stability (last feature)
$contributions = [];
foreach (json_decode($candidate['contributions'] ?? '[]', true) ?: [] as $i => $points) {
    if ($isIntern && $i === 4) {
        continue;
    }
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
    if ($isIntern && $i === 4) {
        continue;
    }
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

        <div class="card">
            <h2>"Ne Olursa?" Simülasyonu</h2>
            <p class="muted">Değerleri değiştirin, puan anında yeniden hesaplansın. Kayıt değişmez.</p>
            <div class="sim-score">
                <span>Simülasyon puanı:</span>
                <b id="sim-score">%<?= round($score) ?></b>
                <span id="sim-decision" class="decision decision-<?= $level ?>"><?= decisionLabel($score) ?></span>
            </div>
            <form class="form" id="sim-form">
                <?= csrfField() ?>
                <input type="hidden" name="position" value="<?= htmlspecialchars($candidate['position']) ?>">
                <p><label>Tecrübe seviyesi
                    <select name="level" id="sim-level">
                        <?php foreach (CANDIDATE_LEVELS as $key => $label): ?>
                            <option value="<?= $key ?>" <?= $key === $candidate['level'] ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="hint">Örneğin: "Bu aday kıdemli kadroya başvursaydı puanı ne olurdu?"</span>
                </label></p>
                <p><label>Eğitim
                    <select name="education_level">
                        <?php foreach ([1, 2, 3] as $value): ?>
                            <option value="<?= $value ?>" <?= (int) $candidate['education_level'] === $value ? 'selected' : '' ?>><?= educationLabel($value) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label></p>
                <?php
                // [field, label, min, max, step]
                $sliders = [
                    ['years_experience', 'Tecrübe (yıl)', 0, 20, 0.5],
                    ['technical_skill', 'Teknik beceri', 30, 100, 1],
                    ['communication_skill', 'İletişim', 30, 100, 1],
                    ['job_stability', 'İş istikrarı (yıl)', 0.5, 10, 0.1],
                ];
                foreach ($sliders as [$field, $label, $min, $max, $step]):
                    $value = (float) $candidate[$field]; ?>
                    <p id="sim-box-<?= $field ?>"><label for="sim-<?= $field ?>"><?= $label ?></label>
                        <span class="range-row">
                            <input type="range" name="<?= $field ?>" id="sim-<?= $field ?>" min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= $value ?>">
                            <span class="range-value" data-for="sim-<?= $field ?>"><?= $value ?></span>
                        </span>
                    </p>
                <?php endforeach; ?>
            </form>
        </div>

        <div class="actions">
            <a class="btn" href="evaluate.php">+ Yeni Aday</a>
        </div>
    </div>
</div>
<script>
(function () {
    var LEVEL_YEARS = <?= json_encode(CANDIDATE_LEVEL_YEARS) ?>;
    var form = document.getElementById('sim-form');
    var scoreBox = document.getElementById('sim-score');
    var decisionBox = document.getElementById('sim-decision');
    var timer = null;

    function showValue(slider) {
        var box = form.querySelector('.range-value[data-for="' + slider.id + '"]');
        box.textContent = slider.step.indexOf('.') > -1 ? parseFloat(slider.value).toFixed(1) : slider.value;
    }

    // Same rules as the evaluation form: level sets the experience range
    function levelChanged() {
        var level = document.getElementById('sim-level').value;
        var years = document.getElementById('sim-years_experience');
        var range = LEVEL_YEARS[level];
        years.min = range[0];
        years.max = range[1];
        if (years.value < range[0] || years.value > range[1]) {
            years.value = range[0];
        }
        showValue(years);
        document.getElementById('sim-box-job_stability').hidden = level === 'intern';
    }

    // Ask the model for a new score (only for the screen, nothing is saved)
    function simulate() {
        scoreBox.style.opacity = 0.4;
        fetch('simulate.php', { method: 'POST', body: new FormData(form) })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                scoreBox.style.opacity = 1;
                if (result.error) {
                    scoreBox.textContent = 'Hata';
                    decisionBox.textContent = result.error;
                    return;
                }
                scoreBox.textContent = '%' + Math.round(result.score);
                decisionBox.className = 'decision decision-' + result.level;
                decisionBox.textContent = result.decision;
            })
            .catch(function () { scoreBox.style.opacity = 1; scoreBox.textContent = 'Hata'; });
    }

    form.querySelectorAll('input[type=range]').forEach(function (slider) {
        slider.addEventListener('input', function () { showValue(slider); });
    });
    document.getElementById('sim-level').addEventListener('change', levelChanged);
    // Wait until the user stops moving the slider, then calculate once
    form.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(simulate, 350);
    });
    form.addEventListener('change', function () {
        clearTimeout(timer);
        timer = setTimeout(simulate, 350);
    });
    levelChanged();
})();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
