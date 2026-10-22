<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
requireLogin();

// Only the owner of the job can edit it
$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM jobs WHERE id = ? AND user_id = ?');
$stmt->execute([$id, currentUser()['id']]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$job) {
    http_response_code(404);
    exit('İlan bulunamadı.');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $job['title'] = trim($_POST['title'] ?? '');
    $job['description'] = trim($_POST['description'] ?? '');
    $job['requirements'] = trim($_POST['requirements'] ?? '');

    if ($job['title'] === '' || $job['description'] === '' || $job['requirements'] === '') {
        $error = 'Lütfen tüm alanları doldurun.';
    } else {
        $update = $db->prepare('UPDATE jobs SET title = ?, description = ?, requirements = ? WHERE id = ?');
        $update->execute([$job['title'], $job['description'], $job['requirements'], $id]);
        header('Location: index.php');
        exit;
    }
}

$pageTitle = 'İlanı Düzenle';
require __DIR__ . '/includes/header.php';
?>
<h1>İlanı Düzenle</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form class="form card" method="post">
    <?= csrfField() ?>
    <p><label>İlan başlığı<input type="text" name="title" value="<?= htmlspecialchars($job['title']) ?>" required></label></p>
    <p><label>Açıklama<textarea name="description" rows="5" required><?= htmlspecialchars($job['description']) ?></textarea></label></p>
    <p><label>Aranan nitelikler (virgülle ayırın, ör. Python, SQL, Git)<textarea name="requirements" rows="4" required><?= htmlspecialchars($job['requirements']) ?></textarea></label></p>
    <button class="btn" type="submit">Kaydet</button>
</form>
<p class="muted">Not: Nitelikleri değiştirirsen eski başvuruların puanı değişmez, yeni başvurular yeni niteliklere göre puanlanır.</p>
<p><a href="index.php">&larr; İlanlara dön</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
