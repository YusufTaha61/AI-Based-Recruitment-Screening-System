<?php
// Reads an uploaded CV and returns guessed form values as JSON.
// The CV is only read, not saved: the temporary copy is deleted right away.
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/ml.php';

header('Content-Type: application/json; charset=utf-8');

function reply(array $data): void
{
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!currentUser()) {
    reply(['error' => 'Oturumunuz kapanmış, lütfen tekrar giriş yapın.']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reply(['error' => 'Geçersiz istek.']);
}
verifyCsrf();

$file = $_FILES['cv'] ?? null;
$ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';

if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    reply(['error' => 'Dosya yüklenemedi.']);
}
if (!in_array($ext, ['pdf', 'txt'], true)) {
    reply(['error' => 'Sadece PDF ve TXT dosyaları okunabilir.']);
}
if ($file['size'] > 5 * 1024 * 1024) {
    reply(['error' => 'Dosya çok büyük (en fazla 5 MB).']);
}

// Python needs a real file with the right extension
$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cv_' . bin2hex(random_bytes(6)) . '.' . $ext;
if (!move_uploaded_file($file['tmp_name'], $temp)) {
    reply(['error' => 'Dosya geçici klasöre kopyalanamadı.']);
}
$result = cvProfile($temp);
@unlink($temp);

reply($result);
