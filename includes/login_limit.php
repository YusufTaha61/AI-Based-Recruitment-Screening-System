<?php
// Limit failed logins: after 5 wrong passwords for the same e-mail
// from the same computer, logging in is locked for 15 minutes.
// This stops someone from trying thousands of passwords one by one.

const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_LOCK_MINUTES = 15;

function clientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Minutes left until the user can try again (0 = not locked)
function loginLockMinutes(PDO $db, string $email): int
{
    $stmt = $db->prepare(
        "SELECT COUNT(*), MIN(attempted_at) FROM login_attempts
         WHERE email = ? AND ip = ? AND attempted_at > datetime('now', ?)"
    );
    $stmt->execute([strtolower($email), clientIp(), '-' . LOGIN_LOCK_MINUTES . ' minutes']);
    [$count, $first] = $stmt->fetch(PDO::FETCH_NUM);

    if ((int) $count < LOGIN_MAX_ATTEMPTS) {
        return 0;
    }
    $unlockAt = strtotime($first . ' UTC') + LOGIN_LOCK_MINUTES * 60;
    return max(1, (int) ceil(($unlockAt - time()) / 60));
}

function recordFailedLogin(PDO $db, string $email): void
{
    $db->prepare('INSERT INTO login_attempts (email, ip) VALUES (?, ?)')
        ->execute([strtolower($email), clientIp()]);
    // Old rows are not needed any more
    $db->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-1 day')");
}

// After a correct password the counter starts again from zero
function clearFailedLogins(PDO $db, string $email): void
{
    $db->prepare('DELETE FROM login_attempts WHERE email = ? AND ip = ?')
        ->execute([strtolower($email), clientIp()]);
}
