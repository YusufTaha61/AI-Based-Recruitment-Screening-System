<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
requireLogin();

// Results saved by ml/train_candidates.py
$metricsFile = __DIR__ . '/ml/models/metrics.json';
$metrics = is_file($metricsFile) ? json_decode(file_get_contents($metricsFile), true) : null;

// Results saved by ml/fairness.py (may not exist yet)
$fairnessFile = __DIR__ . '/ml/models/fairness.json';
$fairness = is_file($fairnessFile) ? json_decode(file_get_contents($fairnessFile), true) : null;

$pageTitle = 'Model Raporu';
require __DIR__ . '/includes/header.php';

if (!$metrics || empty($metrics['positions'])): ?>
    <h1>Model Raporu</h1>
    <div class="card">
        <h2>Model henüz eğitilmemiş</h2>
        <p>Terminalde <code>ml</code> klasörüne girip şu komutu çalıştırın:</p>
        <pre class="cv-text">py train_candidates.py</pre>
    </div>
<?php
    require __DIR__ . '/includes/footer.php';
    exit;
endif;

// Which model to show (one model per position + level)
$position = isset($metrics['positions'][$_GET['position'] ?? '']) ? $_GET['position'] : 'software';
$levels = $metrics['positions'][$position]['levels'];
$level = isset($levels[$_GET['level'] ?? '']) ? $_GET['level'] : 'junior';
$model = $levels[$level];
$rf = $model['random_forest'];
$lr = $model['logistic_regression'];
$cv = $model['cross_validation'];
$cm = $model['confusion'];

$featureLabels = array_values(CANDIDATE_FEATURES);
$percent = fn(float $x) => '%' . number_format($x * 100, 1, ',', '');
$modelCount = array_sum(array_map(fn($p) => count($p['levels']), $metrics['positions']));

// In how many of the models is Random Forest at least as accurate as Logistic Regression?
$rfWins = 0;
foreach ($metrics['positions'] as $p) {
    foreach ($p['levels'] as $m) {
        $rfWins += $m['random_forest']['accuracy'] >= $m['logistic_regression']['accuracy'] ? 1 : 0;
    }
}
$metricNames = ['accuracy' => 'Doğruluk', 'precision' => 'Kesinlik', 'recall' => 'Duyarlılık', 'f1' => 'F1 puanı'];
?>
<h1>Model Raporu</h1>
<p class="muted">
    Yapay zeka modelinin ne kadar başarılı olduğunu gösterir. Her pozisyon ve tecrübe seviyesi için ayrı bir
    Random Forest modeli var (<?= $modelCount ?> model). Son eğitim: <?= htmlspecialchars($metrics['created']) ?>.
</p>

<!-- Choose a position, then a level -->
<div class="tabs">
    <?php foreach ($metrics['positions'] as $key => $p): ?>
        <a href="?position=<?= $key ?>&level=<?= $level ?>" class="<?= $key === $position ? 'active' : '' ?>"><?= htmlspecialchars($p['name']) ?></a>
    <?php endforeach; ?>
</div>
<div class="tabs tabs-small">
    <?php foreach ($levels as $key => $l): ?>
        <a href="?position=<?= $position ?>&level=<?= $key ?>" class="<?= $key === $level ? 'active' : '' ?>"><?= htmlspecialchars($metrics['levels'][$key]['name']) ?></a>
    <?php endforeach; ?>
</div>

<!-- Level weights: how much each feature counts at each level of this position -->
<div class="card">
    <h2>Seviyeye Göre Değerlendirme Kıstasları — <?= htmlspecialchars($metrics['positions'][$position]['name']) ?></h2>
    <p class="muted small">Aynı pozisyonda bile stajyer ile kıdemli bir çalışan aynı kriterlerle değerlendirilmez.
        Tablo, her seviyede hangi özelliğin puana ne kadar etki ettiğini gösterir.</p>
    <div class="table-wrap flat">
        <table class="weight-table">
            <thead>
                <tr><th>Seviye</th><?php foreach ($featureLabels as $label): ?><th><?= $label ?></th><?php endforeach; ?></tr>
            </thead>
            <tbody>
            <?php foreach ($levels as $lKey => $l): $years = $metrics['levels'][$lKey]['years']; ?>
                <tr class="<?= $lKey === $level ? 'selected' : '' ?>">
                    <td>
                        <a href="?position=<?= $position ?>&level=<?= $lKey ?>"><b><?= htmlspecialchars($metrics['levels'][$lKey]['name']) ?></b></a>
                        <span class="hint"><?= $years[0] ?>-<?= $years[1] ?> yıl</span>
                    </td>
                    <?php foreach ($metrics['features'] as $feature): $w = $l['weights'][$feature] * 100; ?>
                        <td>
                            <div class="mini-bar"><div style="width: <?= min(100, round($w * 1.6)) ?>%"></div></div>
                            <b>%<?= round($w) ?></b>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="muted small">Seçili seviye renkli gösterilir. Bir seviyeye tıklayınca o modelin ayrıntıları açılır.</p>
</div>

<div class="stats">
    <div class="stat"><b><?= $percent($rf['accuracy']) ?></b><span>Doğruluk (Accuracy)</span></div>
    <div class="stat"><b><?= $percent($rf['precision']) ?></b><span>Kesinlik (Precision)</span></div>
    <div class="stat"><b><?= $percent($rf['recall']) ?></b><span>Duyarlılık (Recall)</span></div>
    <div class="stat"><b><?= number_format($rf['f1'], 3, ',', '') ?></b><span>F1 puanı</span></div>
</div>
<p class="muted small">
    <b>Doğruluk:</b> tahminlerin yüzde kaçı doğru. <b>Kesinlik:</b> modelin "kısa liste" dediklerinin yüzde kaçı gerçekten iyi.
    <b>Duyarlılık:</b> gerçekten iyi adayların yüzde kaçını yakaladı. <b>F1:</b> kesinlik ile duyarlılığın ortalaması.
</p>

<div class="result-grid even">
    <div class="card">
        <h2>Karmaşıklık Matrisi</h2>
        <p class="muted small">Test verisindeki <?= (int) $model['test_size'] ?> adayın gerçek sonucu ile modelin tahmini
            (model <?= (int) $model['train_size'] ?> adayla eğitildi).</p>
        <table class="confusion">
            <tr><th></th><th>Model: Elendi</th><th>Model: Kısa liste</th></tr>
            <tr>
                <th>Gerçek: Elendi</th>
                <td class="right"><b><?= (int) $cm['tn'] ?></b><span>Doğru red</span></td>
                <td class="wrong"><b><?= (int) $cm['fp'] ?></b><span>Yanlış kabul</span></td>
            </tr>
            <tr>
                <th>Gerçek: Kısa liste</th>
                <td class="wrong"><b><?= (int) $cm['fn'] ?></b><span>Kaçırılan aday</span></td>
                <td class="right"><b><?= (int) $cm['tp'] ?></b><span>Doğru kabul</span></td>
            </tr>
        </table>
        <p class="muted small">Yeşil kutular doğru, kırmızı kutular hatalı tahmin sayısıdır.</p>
    </div>

    <div class="card">
        <h2>Model Doğru Şeyi Öğrendi mi?</h2>
        <p class="muted small">Eğitim verisini üretirken her özelliğe bir "gerçek" ağırlık verdik.
            Gri çubuk bu ağırlığı, mor çubuk modelin veriden kendi bulduğu önemi gösterir.</p>
        <?php foreach ($metrics['features'] as $i => $feature):
            $real = $model['weights'][$feature] * 100;
            $learned = $model['importance'][$i] * 100; ?>
            <div class="bar-row">
                <div class="bar-label">
                    <span><?= $featureLabels[$i] ?></span>
                    <span>Gerçek <b>%<?= round($real) ?></b> · Model <b>%<?= round($learned) ?></b></span>
                </div>
                <div class="bar bar-real"><div style="width: <?= min(100, round($real)) ?>%"></div></div>
                <div class="bar bar-gap"><div style="width: <?= min(100, round($learned)) ?>%"></div></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="result-grid even">
    <div class="card">
        <h2>Başka Bir Modelle Karşılaştırma</h2>
        <p class="muted small">Random Forest'ı daha basit bir model olan Logistic Regression ile aynı test verisinde karşılaştırdık.
            Yeşil değer o satırda daha iyi olan modeldir.</p>
        <table class="compare-table">
            <thead><tr><th>Ölçüt</th><th>Random Forest</th><th>Logistic Regression</th></tr></thead>
            <tbody>
            <?php foreach ($metricNames as $key => $name): ?>
                <tr>
                    <td><?= $name ?></td>
                    <td class="<?= $rf[$key] > $lr[$key] ? 'best' : '' ?>"><?= $percent($rf[$key]) ?></td>
                    <td class="<?= $lr[$key] > $rf[$key] ? 'best' : '' ?>"><?= $percent($lr[$key]) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="muted small">
            <?= $modelCount ?> modelin <?= $rfWins ?> tanesinde Random Forest en az Logistic Regression kadar doğru.
            Fark genelde 1-2 puan. Eğitim verisi özelliklerin ağırlıklı toplamıyla üretildiği için basit bir model de iyi sonuç veriyor.
            Random Forest'ı seçmemizin sebebi, her adayın puanını hangi özelliğin ne kadar etkilediğini açıklayabilmemiz.
        </p>
    </div>

    <div class="card">
        <h2>Çapraz Doğrulama (5 katlı)</h2>
        <p class="muted small">Veriyi 5 parçaya böldük. Model her seferinde 4 parçayla eğitilip kalan 1 parçayla test edildi.
            Böylece sonucun tek bir şanslı bölmeye bağlı olmadığını görüyoruz.</p>
        <div class="stats">
            <div class="stat"><b><?= $percent($cv['mean']) ?></b><span>Ortalama doğruluk</span></div>
            <div class="stat"><b>± <?= number_format($cv['std'] * 100, 1, ',', '') ?></b><span>Sapma (puan)</span></div>
        </div>
        <p class="muted small">Sapma küçükse model her veri parçasında benzer başarı gösteriyor demektir.
            Tek testteki doğruluk (<?= $percent($rf['accuracy']) ?>) ile ortalamanın yakın olması da iyi bir işaret.</p>
    </div>
</div>

<?php $fair = $fairness['positions'][$position] ?? null; ?>
<div class="card">
    <h2>Adillik (Bias) Kontrolü — <?= htmlspecialchars($metrics['positions'][$position]['name']) ?></h2>
    <?php if (!$fair): ?>
        <p>Adillik kontrolü henüz yapılmamış. Terminalde <code>ml</code> klasörüne girip şu komutu çalıştırın:</p>
        <pre class="cv-text">py fairness.py</pre>
    <?php else: ?>
        <p class="muted small">
            Modele yaş ve cinsiyet bilgisi <b>verilmiyor</b>. Yine de dolaylı yoldan bir gruba haksızlık yapıyor mu diye
            yeni test adaylarının kısa listeye alınma oranlarını karşılaştırdık.
            <b>4/5 kuralına</b> göre en düşük oran, en yüksek oranın en az %80'i olmalı.
        </p>

        <?php
        // One block for each check: title => results
        $checks = [
            'Yaş · Tek model (herkes aynı modelde yarışırsa)' => $fair['age_single'],
            'Yaş · Seviyeli sistem (bu proje)' => $fair['age_levels'],
            'Cinsiyet · Seviyeli sistem' => $fair['gender'],
        ];
        ?>
        <div class="fairness-grid">
            <?php foreach ($checks as $title => $check): ?>
                <div>
                    <p><b><?= $title ?></b></p>
                    <?php foreach ($check['groups'] as $group => $g): ?>
                        <div class="bar-row">
                            <div class="bar-label">
                                <span><?= htmlspecialchars($group) ?> (<?= (int) $g['count'] ?> aday)</span>
                                <b><?= $percent($g['rate']) ?></b>
                            </div>
                            <div class="bar"><div style="width: <?= round($g['rate'] * 100) ?>%"></div></div>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($check['ratio'] !== null): ?>
                        <p class="alert <?= $check['fair'] ? 'alert-success' : 'alert-warning' ?>">
                            Oran: <b><?= number_format($check['ratio'], 2, ',', '') ?></b> —
                            <?= $check['fair'] ? '4/5 kuralına uygun.' : '4/5 kuralının altında.' ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <p class="muted small">
            <b>Neden fark var?</b> Model yaşı bilmiyor ama tecrübeyi biliyor. Herkes aynı modelle değerlendirilirse tecrübeli
            (genelde daha yaşlı) adaylar öne geçer, gençler neredeyse hiç seçilmez. Buna <b>dolaylı ayrımcılık (proxy bias)</b> denir.
            Adayları kendi seviyesindekilerle karşılaştırınca oran
            <b><?= number_format($fair['age_single']['ratio'], 2, ',', '') ?></b>'den
            <b><?= number_format($fair['age_levels']['ratio'], 2, ',', '') ?></b>'e çıkıyor.
            <?php if (!$fair['age_levels']['fair']): ?>
                Bu pozisyonda tecrübenin ağırlığı yüksek olduğu için seviye içinde de küçük bir fark kalıyor;
                son karar verilirken bu göz önünde bulundurulmalı.
            <?php endif; ?>
            Cinsiyet modelde hiç kullanılmadığı için iki grubun oranı birbirine yakın.
            Son kontrol: <?= htmlspecialchars($fairness['created']) ?>.
        </p>
    <?php endif; ?>
</div>

<h2>Bütün Modeller</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Pozisyon</th><?php foreach ($metrics['levels'] as $l): ?><th><?= htmlspecialchars($l['name']) ?></th><?php endforeach; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($metrics['positions'] as $pKey => $p): ?>
            <tr>
                <td><b><?= htmlspecialchars($p['name']) ?></b></td>
                <?php foreach ($p['levels'] as $lKey => $m): ?>
                    <td class="<?= $pKey === $position && $lKey === $level ? 'best' : '' ?>">
                        <a href="?position=<?= $pKey ?>&level=<?= $lKey ?>"><?= $percent($m['random_forest']['accuracy']) ?></a>
                    </td>
                <?php endforeach; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="muted small">Tablodaki değerler test verisindeki doğruluktur. Bir değere tıklayınca o modelin ayrıntıları açılır.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>
