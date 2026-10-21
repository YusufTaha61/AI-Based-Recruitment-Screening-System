<?php
require_once __DIR__ . '/auth.php';

$navUser = currentUser();
$currentPage = basename($_SERVER['SCRIPT_NAME']);

// Pages that belong to the "İlanlar" menu item
if (in_array($currentPage, ['apply.php', 'applicants.php', 'application.php'], true)) {
    $currentPage = 'index.php';
}

// Sidebar menu: file => [label, svg icon]
$menu = [
    'index.php' => ['İlanlar', '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/>'],
    'job_create.php' => ['Yeni İlan', '<path d="M12 5v14M5 12h14"/>'],
];

// First letter of the user's name for the avatar
$initial = '';
if ($navUser && preg_match('/\S/u', $navUser['name'], $m)) {
    $initial = $m[0];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Ana Sayfa') ?> | Akıllı İK</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="<?= $navUser ? 'with-sidebar' : '' ?>">
<?php if ($navUser): ?>
<aside class="sidebar">
    <a class="brand" href="index.php">
        <span class="brand-mark">İK</span>
        <span class="brand-text">Akıllı İK<small>AI ön eleme</small></span>
    </a>
    <div class="sidebar-title">Menü</div>
    <nav class="sidebar-menu">
        <?php foreach ($menu as $file => [$label, $icon]): ?>
            <a href="<?= $file ?>" class="<?= $currentPage === $file ? 'active' : '' ?>">
                <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $icon ?></svg>
                <span><?= $label ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-bottom">
        <div class="user-box">
            <span class="avatar"><?= htmlspecialchars($initial) ?></span>
            <b><?= htmlspecialchars($navUser['name']) ?></b>
        </div>
        <a class="logout" href="logout.php" title="Çıkış yap">
            <svg viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            <span>Çıkış</span>
        </a>
    </div>
</aside>
<?php else: ?>
<header class="topbar">
    <div class="container topbar-inner">
        <a class="brand" href="index.php">
            <span class="brand-mark">İK</span>
            <span class="brand-text">Akıllı İK<small>AI ön eleme</small></span>
        </a>
        <nav>
            <a href="index.php">İlanlar</a>
            <a href="login.php">Giriş Yap</a>
            <a href="register.php">Kayıt Ol</a>
        </nav>
    </div>
</header>
<?php endif; ?>
<div class="page">
<main class="container">
