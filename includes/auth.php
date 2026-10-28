<?php
require_once __DIR__ . '/session.php';

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

// The HR team of the logged-in user. Each team only sees its own candidates.
function currentTeamId(PDO $db): int
{
    if (!isset($_SESSION['user']['team_id'])) {
        // Sessions from before teams existed: look it up once
        $stmt = $db->prepare('SELECT team_id FROM users WHERE id = ?');
        $stmt->execute([currentUser()['id'] ?? 0]);
        $_SESSION['user']['team_id'] = (int) $stmt->fetchColumn();
    }
    return (int) $_SESSION['user']['team_id'];
}
