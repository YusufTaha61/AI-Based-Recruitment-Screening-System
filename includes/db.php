<?php
$db = new PDO('sqlite:' . __DIR__ . '/../database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Databases created before roles existed: add the "role" column once.
// Existing users become HR managers, so nobody loses access.
$userColumns = array_column($db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if ($userColumns && !in_array('role', $userColumns, true)) {
    $db->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'hr'");
}
// "created_by": the HR manager who added this user on the Users page
if ($userColumns && !in_array('created_by', $userColumns, true)) {
    $db->exec('ALTER TABLE users ADD COLUMN created_by INTEGER');
}
// Failed logins, used to lock logging in after too many wrong passwords
$db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    ip TEXT NOT NULL,
    attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
