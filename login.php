<?php
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
require __DIR__ . '/includes/demo.php';

ensureDemoAccount($db);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['id'], 'name' => $user['name'], 'role' => $user['role']];
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
    <?= csrfField() ?>
    <p><label>E-posta<input type="email" name="email" id="email" required></label></p>
    <p><label>Şifre<input type="password" name="password" id="password" required></label></p>
    <button class="btn" type="submit">Giriş Yap</button>
</form>
<div class="form card demo-hint">
    <h2>Demo hesapla dene</h2>
    <p class="muted">Kayıt olmadan İK yöneticisi olarak giriş yapabilirsin.</p>
    <ul class="facts">
        <li><span>E-posta</span><?= DEMO_EMAIL ?></li>
        <li><span>Şifre</span><?= DEMO_PASSWORD ?></li>
    </ul>
    <button class="btn btn-outline" type="button" id="fill-demo">Demo bilgilerini doldur</button>
</div>
<script>
document.getElementById('fill-demo').addEventListener('click', function () {
    document.getElementById('email').value = <?= json_encode(DEMO_EMAIL) ?>;
    document.getElementById('password').value = <?= json_encode(DEMO_PASSWORD) ?>;
    document.getElementById('email').form.querySelector('button[type=submit]').focus();
});
</script>
<p class="muted">Hesabın yok mu? <a href="register.php">Kayıt ol</a></p>
<?php require __DIR__ . '/includes/footer.php'; ?>
