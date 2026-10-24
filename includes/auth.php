<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// hr        : HR manager. Can do everything (jobs, hiring status, users).
// evaluator : Can evaluate candidates and see results, but can't post jobs
//             or change a candidate's hiring status.
const USER_ROLES = [
    'hr' => 'İK Yöneticisi',
    'evaluator' => 'Değerlendirici',
];

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin(): void
{
    if (!currentUser()) {
        header('Location: login.php');
        exit;
    }
}

function isHr(): bool
{
    // Sessions from before roles existed have no role: they were HR managers
    return (currentUser()['role'] ?? 'hr') === 'hr';
}

// For pages and actions only HR managers may use
function requireHr(): void
{
    requireLogin();
    if (!isHr()) {
        http_response_code(403);
        exit('Bu işlemi sadece İK Yöneticisi yapabilir.');
    }
}
