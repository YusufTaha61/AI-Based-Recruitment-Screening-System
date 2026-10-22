<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
requireLogin();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');

    if ($title === '' || $description === '' || $requirements === '') {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        $stmt = $db->prepare('INSERT INTO jobs (user_id, title, description, requirements) VALUES (?, ?, ?, ?)');
        $stmt->execute([currentUser()['id'], $title, $description, $requirements]);
        header('Location: index.php');
        exit;
    }
}

$pageTitle = 'Yeni İlan';
require __DIR__ . '/includes/header.php';
?>
<h1>Yeni İş İlanı</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form class="form card" method="post">
    <?= csrfField() ?>
    <p><label>İlan başlığı<input type="text" name="title" required></label></p>
    <p><label>Açıklama<textarea name="description" rows="5" required></textarea></label></p>
    <p><label>Aranan nitelikler (virgülle ayırın, ör. Python, SQL, Git)<textarea name="requirements" rows="4" required></textarea></label></p>
    <button class="btn" type="submit">Yayınla</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
