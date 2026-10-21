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
<?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <p><label>E-posta<br><input type="email" name="email" required></label></p>
    <p><label>Şifre<br><input type="password" name="password" required></label></p>
    <button type="submit">Giriş Yap</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
