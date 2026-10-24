<?php
// Small helpers that run the Python scripts in the ml/ folder.

function pythonBin(): string
{
    $venv = __DIR__ . '/../ml/.venv/Scripts/python.exe'; // Windows virtual env
    if (is_file($venv)) {
        return $venv;
    }
    $venv = __DIR__ . '/../ml/.venv/bin/python'; // Linux / macOS virtual env
    return is_file($venv) ? $venv : 'python';
}

function runPython(string $script, array $args = [], string $input = ''): ?string
{
    $cmd = escapeshellarg(pythonBin()) . ' ' . escapeshellarg(__DIR__ . '/../ml/' . $script);
    foreach ($args as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }

    $process = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        return null;
    }
    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return proc_close($process) === 0 ? $output : null;
}

function extractCvText(string $filePath): string
{
    return trim(runPython('extract_text.py', [$filePath]) ?? '');
}

function scoreCv(string $cvText, string $requirements): ?array
{
    $input = json_encode(['cv_text' => $cvText, 'requirements' => $requirements]);
    $output = runPython('predict.py', [], $input);
    return $output ? json_decode($output, true) : null;
}

// ---- Candidate scoring models (ml/train_candidates.py) ----

const CANDIDATE_POSITIONS = [
    'general' => 'Genel Pozisyon',
    'software' => 'Yazılım Geliştirici',
    'sales' => 'Satış / Müşteri İlişkileri',
    'manager' => 'Proje Yöneticisi',
];

const CANDIDATE_LEVELS = [
    'intern' => 'Stajyer',
    'junior' => 'Yeni Mezun (Junior)',
    'mid' => 'Deneyimli (Orta Seviye)',
    'senior' => 'Kıdemli (Senior)',
];

// Experience range (years) for each level, same as LEVELS in ml/candidate_model.py
const CANDIDATE_LEVEL_YEARS = [
    'intern' => [0, 1],
    'junior' => [0, 2],
    'mid' => [2, 8],
    'senior' => [8, 20],
];

// Same order as FEATURES in ml/candidate_model.py
const CANDIDATE_FEATURES = [
    'education_level' => 'Eğitim',
    'years_experience' => 'Tecrübe',
    'technical_skill' => 'Teknik beceri',
    'communication_skill' => 'İletişim',
    'job_stability' => 'İş istikrarı',
];

/**
 * Read the 5 feature values from a form and keep them inside safe limits.
 * Experience must stay inside the chosen level's range, job stability can't
 * be longer than the experience, and interns don't use job stability.
 */
function candidateValuesFromForm(array $src, string $level): array
{
    [$min, $max] = CANDIDATE_LEVEL_YEARS[$level] ?? [0, 20];
    $years = round((float) ($src['years_experience'] ?? $min) * 2) / 2; // 0.5 steps
    $years = max($min, min($max, $years));

    $stability = max(0.5, min(10, (float) ($src['job_stability'] ?? 0.5)));
    $stability = $level === 'intern' ? 0.5 : min($stability, max(0.5, $years));

    return [
        'education_level' => max(1, min(3, (int) ($src['education_level'] ?? 1))),
        'years_experience' => $years,
        'technical_skill' => max(30, min(100, (float) ($src['technical_skill'] ?? 30))),
        'communication_skill' => max(30, min(100, (float) ($src['communication_skill'] ?? 30))),
        'job_stability' => $stability,
    ];
}

/**
 * Score a candidate profile. $values uses the keys of CANDIDATE_FEATURES.
 * Returns ['score', 'shortlisted', 'baseline', 'contributions', 'importance']
 * or ['error' => '...'] when something goes wrong.
 */
function evaluateCandidate(string $position, string $level, array $values): array
{
    if (!isset(CANDIDATE_POSITIONS[$position], CANDIDATE_LEVELS[$level])) {
        return ['error' => 'Geçersiz pozisyon veya seviye.'];
    }

    $ordered = [];
    foreach (array_keys(CANDIDATE_FEATURES) as $key) {
        $ordered[] = (float) ($values[$key] ?? 0);
    }

    $input = json_encode(['position' => $position, 'level' => $level, 'values' => $ordered]);
    $output = runPython('evaluate.py', [], $input);
    $result = $output ? json_decode($output, true) : null;

    if (!is_array($result)) {
        return ['error' => 'Model çalıştırılamadı. Python kurulumunu kontrol edin.'];
    }
    if (isset($result['error'])) {
        return ['error' => 'Model hatası: ' . $result['error']];
    }
    return $result;
}

/**
 * Strong and weak sides of a candidate. Each value is turned into 0-1
 * (experience is measured inside the candidate's own level range).
 * Returns [strong labels, weak labels].
 */
function candidateStrengths(array $c): array
{
    [$min, $max] = CANDIDATE_LEVEL_YEARS[$c['level']] ?? [0, 20];
    $scaled = [
        'education_level' => ($c['education_level'] - 1) / 2,
        'years_experience' => ($c['years_experience'] - $min) / ($max - $min),
        'technical_skill' => ($c['technical_skill'] - 30) / 70,
        'communication_skill' => ($c['communication_skill'] - 30) / 70,
        'job_stability' => ($c['job_stability'] - 0.5) / 9.5,
    ];
    if ($c['level'] === 'intern') {
        unset($scaled['job_stability']); // not used for interns
    }

    $strong = $weak = [];
    foreach ($scaled as $key => $value) {
        if ($value >= 0.7) {
            $strong[] = CANDIDATE_FEATURES[$key];
        } elseif ($value <= 0.35) {
            $weak[] = CANDIDATE_FEATURES[$key];
        }
    }
    return [$strong, $weak];
}
