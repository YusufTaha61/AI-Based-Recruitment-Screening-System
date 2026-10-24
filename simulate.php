<?php
// "What if?" simulation: scores changed values but saves nothing.
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/helpers.php';
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

$position = $_POST['position'] ?? '';
$level = $_POST['level'] ?? '';
if (!isset(CANDIDATE_POSITIONS[$position], CANDIDATE_LEVELS[$level])) {
    reply(['error' => 'Geçersiz pozisyon veya seviye.']);
}

$result = evaluateCandidate($position, $level, candidateValuesFromForm($_POST, $level));
if (isset($result['error'])) {
    reply(['error' => $result['error']]);
}

$score = (float) $result['score'];
reply([
    'score' => $score,
    'level' => scoreLevel($score),
    'decision' => decisionLabel($score),
]);
