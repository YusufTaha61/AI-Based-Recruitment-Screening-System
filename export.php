<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
require __DIR__ . '/includes/candidate_filters.php';
requireHr(); // only HR managers can download the candidate list

// Same filters as the history page
[$candidates] = filteredCandidates($db, $_GET);

// Excel could run a cell that starts with = + - @ as a formula; add ' in front
function csvCell($value): string
{
    $value = (string) $value;
    return preg_match('/^[=+\-@]./', $value) ? "'" . $value : $value;
}

// Comma is the decimal sign in Turkish, so 12,5 instead of 12.5
function trNumber(float $number): string
{
    return number_format($number, 1, ',', '');
}

// Tell the browser to download a file instead of showing it
$fileName = 'adaylar_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // tells Excel the file is UTF-8, so ş, ğ, İ look right

// Turkish Excel expects ";" between columns
$separator = ';';
fputcsv($out, [
    'Aday', 'Pozisyon', 'Seviye', 'Eğitim', 'Tecrübe (yıl)', 'Teknik beceri', 'İletişim',
    'İş istikrarı (yıl)', 'Uyum puanı (%)', 'Karar', 'Süreç durumu', 'Notlar', 'Değerlendiren', 'Tarih',
], $separator, '"', '');

foreach ($candidates as $c) {
    $score = (float) $c['score'];
    fputcsv($out, array_map('csvCell', [
        $c['name'],
        CANDIDATE_POSITIONS[$c['position']] ?? $c['position'],
        CANDIDATE_LEVELS[$c['level']] ?? $c['level'],
        educationLabel((int) $c['education_level']),
        trNumber((float) $c['years_experience']),
        trNumber((float) $c['technical_skill']),
        trNumber((float) $c['communication_skill']),
        $c['level'] === 'intern' ? '-' : trNumber((float) $c['job_stability']),
        trNumber($score),
        decisionLabel($score),
        $c['status'],
        $c['notes'],
        $c['evaluator'],
        localDateTime($c['created_at']),
    ]), $separator, '"', '');
}
fclose($out);
