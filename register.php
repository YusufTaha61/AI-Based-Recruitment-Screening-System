<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Lütfen tüm alanları doldurun. Şifre en az 6 karakter olmalı.';
    } else {
        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);

        if ($check->fetch()) {
            $error = 'Bu e-posta zaten kayıtlı.';
        } else {
            $stmt = $db->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            header('Location: login.php');
            exit;
        }
    }
}

$pageTitle = 'Kayıt Ol';
require __DIR__ . '/includes/header.php';
?>
<h1>Kayıt Ol</h1>
<?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <p><label>Ad Soyad<br><input type="text" name="name" required></label></p>
    <p><label>E-posta<br><input type="email" name="email" required></label></p>
    <p><label>Şifre<br><input type="password" name="password" minlength="6" required></label></p>
    <button type="submit">Kayıt Ol</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
