<?php
// Demo HR account so anyone can try the site without signing up.
// It is created automatically the first time the login page opens.
// The password is not a common one, so browsers don't warn that it was leaked.

const DEMO_NAME = 'Demo İK Yöneticisi';
const DEMO_EMAIL = 'demo.yonetici@akilliik.com';
const DEMO_PASSWORD = 'Akilli-IK-Demo-7342';

// Old demo e-mail: that account is moved to the new e-mail and password,
// so its team and candidates stay the same
const OLD_DEMO_EMAIL = 'demo@akilliik.com';

function ensureDemoAccount(PDO $db): void
{
    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([DEMO_EMAIL]);
    if ($check->fetch()) {
        return;
    }

    $check->execute([OLD_DEMO_EMAIL]);
    $oldId = $check->fetchColumn();
    if ($oldId) {
        $db->prepare('UPDATE users SET email = ?, password_hash = ? WHERE id = ?')
            ->execute([DEMO_EMAIL, password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT), $oldId]);
        return;
    }

    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([DEMO_NAME, DEMO_EMAIL, password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT)]);
    $db->prepare('UPDATE users SET team_id = id WHERE id = ?')->execute([$db->lastInsertId()]);
}
