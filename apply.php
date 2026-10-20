<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/ml.php';

$jobId = (int) ($_GET['job_id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ?');
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    exit('Job not found.');
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
        $error = 'Please enter your name and a valid email.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please choose a CV file.';
    } elseif (!in_array($ext, $allowed, true)) {
        $error = 'Only PDF and TXT files are allowed.';
    } elseif ($file['size'] > $maxSize) {
        $error = 'File is too large (max 5 MB).';
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

$pageTitle = 'Apply - ' . $job['title'];
require __DIR__ . '/includes/header.php';
?>
<h1>Apply: <?= htmlspecialchars($job['title']) ?></h1>
<?php if ($success): ?>
    <p style="color:green">Your application has been received. Thank you!</p>
<?php else: ?>
    <?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data">
        <p><label>Full name<br><input type="text" name="name" required></label></p>
        <p><label>Email<br><input type="email" name="email" required></label></p>
        <p><label>CV (PDF or TXT, max 5 MB)<br><input type="file" name="cv" accept=".pdf,.txt" required></label></p>
        <button type="submit">Send application</button>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
