<?php
// Demo HR account so anyone can try the site without signing up.
// It is created automatically the first time the login page opens.

const DEMO_NAME = 'Demo İK Yöneticisi';
const DEMO_EMAIL = 'demo@akilliik.com';
const DEMO_PASSWORD = 'demo123';

function ensureDemoAccount(PDO $db): void
{
    $check = $db->prepare('SELECT id FROM users WHERE email = ?');
    $check->execute([DEMO_EMAIL]);
    if ($check->fetch()) {
        return;
    }
    $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([DEMO_NAME, DEMO_EMAIL, password_hash(DEMO_PASSWORD, PASSWORD_DEFAULT)]);
    $db->prepare('UPDATE users SET team_id = id WHERE id = ?')->execute([$db->lastInsertId()]);
}
