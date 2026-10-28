<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
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
            // A new sign-up starts their own team, so they don't see other teams' candidates
            $db->prepare('UPDATE users SET team_id = id WHERE id = ?')->execute([$db->lastInsertId()]);
            header('Location: login.php');
            exit;
        }
    }
}

$pageTitle = 'Kayıt Ol';
require __DIR__ . '/includes/header.php';
?>
<h1>Kayıt Ol</h1>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form class="form card" method="post">
    <?= csrfField() ?>
    <p><label>Ad Soyad<input type="text" name="name" required></label></p>
    <p><label>E-posta<input type="email" name="email" required></label></p>
    <p><label>Şifre<input type="password" name="password" minlength="6" required></label></p>
    <button class="btn" type="submit">Kayıt Ol</button>
</form>
<p class="muted">Zaten hesabın var mı? <a href="login.php">Giriş yap</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
