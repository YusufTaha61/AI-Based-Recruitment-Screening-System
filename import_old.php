<?php
// One-time import of the candidates saved by the old demo project.
// Every candidate is scored again with the new models, so the score,
// the explanation and the strong / weak sides all match the new system.
//
// Usage (in the project folder):
//   php import_old.php
//   php import_old.php "C:\path\to\veritabani.sqlite"
// Running it again is safe: candidates that were already imported are skipped.

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit; // only from the terminal, never from the browser
}

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/ml.php';
require __DIR__ . '/includes/demo.php';

$oldFile = $argv[1] ?? __DIR__ . '/../AI-Based-Recruitment-Screening-System-Demo/veritabani.sqlite';
if (!is_file($oldFile)) {
    exit("Eski veritabanı bulunamadı: $oldFile\n");
}
$old = new PDO('sqlite:' . $oldFile);
$old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Old (Turkish) keys => new keys
$positions = ['genel' => 'general', 'yazilim' => 'software', 'satis' => 'sales', 'yonetici' => 'manager'];
$levels = ['stajyer' => 'intern', 'yeni_mezun' => 'junior', 'deneyimli' => 'mid', 'kidemli' => 'senior'];

// Imported candidates belong to the demo HR account
ensureDemoAccount($db);
$owner = $db->prepare('SELECT id FROM users WHERE email = ?');
$owner->execute([DEMO_EMAIL]);
$ownerId = (int) $owner->fetchColumn();

$exists = $db->prepare('SELECT id FROM candidates WHERE name = ? AND created_at = ?');
$insert = $db->prepare(
    'INSERT INTO candidates (user_id, team_id, name, position, level, education_level, years_experience,
        technical_skill, communication_skill, job_stability, score, baseline, contributions, importance,
        status, notes, created_at)
     VALUES (?, (SELECT team_id FROM users WHERE id = ?), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$added = $skipped = $failed = 0;
foreach ($old->query('SELECT * FROM adaylar ORDER BY tarih') as $row) {
    $position = $positions[$row['pozisyon']] ?? 'general';

    // The first old records had no level: guess it from the years of experience
    $years = (float) $row['deneyim'];
    $level = $levels[$row['seviye'] ?? ''] ?? ($years >= 8 ? 'senior' : ($years >= 2 ? 'mid' : 'junior'));

    // Old dates are Turkey time ("12.10.2026 16:58"); the database keeps UTC
    $date = DateTime::createFromFormat('d.m.Y H:i', $row['tarih'], new DateTimeZone('Europe/Istanbul'));
    $createdAt = $date ? $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s');

    $exists->execute([$row['ad'], $createdAt]);
    if ($exists->fetch()) {
        $skipped++;
        continue;
    }

    // Same limits as the evaluation form, then score with the new model
    $values = candidateValuesFromForm([
        'education_level' => $row['egitim'],
        'years_experience' => $years,
        'technical_skill' => $row['teknik'],
        'communication_skill' => $row['iletisim'],
        'job_stability' => $row['istikrar'],
    ], $level);
    $result = evaluateCandidate($position, $level, $values);
    if (isset($result['error'])) {
        echo "HATA ({$row['ad']}): {$result['error']}\n";
        $failed++;
        continue;
    }

    // Keep the old score and evaluator in the notes so nothing is lost
    $note = 'Eski sistemden aktarıldı. Eski puan: %' . round((float) $row['skor'], 1)
        . ($row['ekleyen'] !== '' && $row['ekleyen'] !== null ? '. Değerlendiren: ' . $row['ekleyen'] : '') . '.';
    if (trim((string) $row['notlar']) !== '') {
        $note .= "\n" . trim($row['notlar']);
    }
    $status = in_array($row['durum'], CANDIDATE_STATUSES, true) ? $row['durum'] : 'Yeni';

    $insert->execute([
        $ownerId, $ownerId, $row['ad'], $position, $level,
        $values['education_level'], $values['years_experience'], $values['technical_skill'],
        $values['communication_skill'], $values['job_stability'],
        $result['score'], $result['baseline'],
        json_encode($result['contributions']), json_encode($result['importance']),
        $status, $note, $createdAt,
    ]);
    $added++;
    printf("%-22s %-8s eski %%%5.1f  ->  yeni %%%5.1f\n", $row['ad'], $level, $row['skor'], $result['score']);
}

echo "\nEklenen: $added, zaten vardı: $skipped, hata: $failed\n";
