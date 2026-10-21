<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Ana Sayfa') ?> | Akıllı İK</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="index.php">Akıllı İK</a>
        <nav>
            <a href="index.php">İlanlar</a>
            <?php if (currentUser()): ?>
                <a href="job_create.php">Yeni İlan</a>
                <a href="logout.php">Çıkış (<?= htmlspecialchars(currentUser()['name']) ?>)</a>
            <?php else: ?>
                <a href="login.php">Giriş Yap</a>
                <a href="register.php">Kayıt Ol</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
