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
