<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Recruitment Screening') ?></title>
</head>
<body>
<nav>
    <a href="index.php">Jobs</a>
    <?php if (currentUser()): ?>
        | <a href="job_create.php">New job</a>
        | <a href="logout.php">Logout (<?= htmlspecialchars(currentUser()['name']) ?>)</a>
    <?php else: ?>
        | <a href="login.php">Login</a>
        | <a href="register.php">Register</a>
    <?php endif; ?>
</nav>
<hr>
