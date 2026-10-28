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
// "team_id": every user belongs to one HR team. Someone who signs up starts
// a new team; users added on the Users page join the team of who added them.
if ($userColumns && !in_array('team_id', $userColumns, true)) {
    $db->exec('ALTER TABLE users ADD COLUMN team_id INTEGER');
    $db->exec('UPDATE users SET team_id = id WHERE created_by IS NULL');
    // Users added by someone: same team as that person (repeat for chains)
    for ($i = 0; $i < 10; $i++) {
        $db->exec('UPDATE users SET team_id = (SELECT p.team_id FROM users p WHERE p.id = users.created_by)
                   WHERE team_id IS NULL');
    }
    $db->exec('UPDATE users SET team_id = id WHERE team_id IS NULL');
}
// Candidates belong to the team of the user who evaluated them
$candidateColumns = array_column($db->query('PRAGMA table_info(candidates)')->fetchAll(PDO::FETCH_ASSOC), 'name');
if ($candidateColumns && !in_array('team_id', $candidateColumns, true)) {
    $db->exec('ALTER TABLE candidates ADD COLUMN team_id INTEGER');
    $db->exec('UPDATE candidates SET team_id = (SELECT u.team_id FROM users u WHERE u.id = candidates.user_id)');
}
// Failed logins, used to lock logging in after too many wrong passwords
$db->exec('CREATE TABLE IF NOT EXISTS login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    ip TEXT NOT NULL,
    attempted_at TEXT DEFAULT CURRENT_TIMESTAMP
)');
