<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name']];
        header('Location: index.php');
        exit;
    }
    $error = 'E-posta veya şifre hatalı.';
}

$pageTitle = 'Giriş Yap';
require __DIR__ . '/includes/header.php';
?>
<h1>Giriş Yap</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form class="form card" method="post">
    <p><label>E-posta<input type="email" name="email" required></label></p>
    <p><label>Şifre<input type="password" name="password" required></label></p>
    <button class="btn" type="submit">Giriş Yap</button>
</form>
<p class="muted">Hesabın yok mu? <a href="register.php">Kayıt ol</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
