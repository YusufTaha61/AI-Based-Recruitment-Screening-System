<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/ml.php';

$jobId = (int) ($_GET['job_id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    exit('İlan bulunamadı.');
}

$error = '';
$success = false;
$allowed = ['pdf', 'txt'];
$maxSize = 5 * 1024 * 1024; // 5 MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $file = $_FILES['cv'] ?? null;
    $ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Lütfen adınızı ve geçerli bir e-posta adresi girin.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Lütfen bir CV dosyası seçin.';
    } elseif (!in_array($ext, $allowed, true)) {
        $error = 'Sadece PDF ve TXT dosyaları yüklenebilir.';
    } elseif ($file['size'] > $maxSize) {
        $error = 'Dosya çok büyük (en fazla 5 MB).';
    } else {
        $fileName = uniqid('cv_', true) . '.' . $ext;
        $filePath = __DIR__ . '/uploads/' . $fileName;
        move_uploaded_file($file['tmp_name'], $filePath);

        // Read the CV and let the ML model score it against the job requirements
        $cvText = extractCvText($filePath);
        $result = $cvText !== '' ? scoreCv($cvText, $job['requirements']) : null;
        $score = $result['score'] ?? null;

        $insert = $db->prepare('INSERT INTO applications (job_id, name, email, cv_path, cv_text, score) VALUES (?, ?, ?, ?, ?, ?)');
        $insert->execute([$jobId, $name, $email, $fileName, $cvText, $score]);
        $success = true;
    }
}

$pageTitle = 'Başvuru - ' . $job['title'];
require __DIR__ . '/includes/header.php';
?>
<h1>Başvuru: <?= htmlspecialchars($job['title']) ?></h1>
<?php if ($success): ?>
    <p class="alert alert-success">Başvurunuz alındı. Teşekkür ederiz!</p>
<?php else: ?>
    <?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form class="form card" method="post" enctype="multipart/form-data">
        <p><label>Ad Soyad<input type="text" name="name" required></label></p>
        <p><label>E-posta<input type="email" name="email" required></label></p>
        <p><label>CV (PDF veya TXT, en fazla 5 MB)<input type="file" name="cv" accept=".pdf,.txt" required></label></p>
        <button class="btn" type="submit">Başvuruyu Gönder</button>
    </form>
<?php endif; ?>
<p><a href="index.php">&larr; İlanlara dön</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
