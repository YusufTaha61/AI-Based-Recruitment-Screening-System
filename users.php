<?php
// User management: an HR manager adds team members and decides their role.
// Each HR manager only sees and manages the users they added.
require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/auth.php';
require __DIR__ . '/includes/csrf.php';
requireHr();

$me = currentUser();
$error = '';
$message = $_GET['message'] ?? '';

// A user this HR manager added (never themselves)
function findMyUser(PDO $db, int $id, int $myId): ?array
{
    $stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND created_by = ?');
    $stmt->execute([$id, $myId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = mb_substr(trim($_POST['name'] ?? ''), 0, 60);
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = isset(USER_ROLES[$_POST['role'] ?? '']) ? $_POST['role'] : 'evaluator';

        $check = $db->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Lütfen ad soyad ve geçerli bir e-posta yazın.';
        } elseif (strlen($password) < 6) {
            $error = 'Şifre en az 6 karakter olmalı.';
        } elseif ($check->fetch()) {
            $error = 'Bu e-posta zaten kayıtlı.';
        } else {
            $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, role, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $me['id']]);
            header('Location: users.php?message=' . urlencode($name . ' eklendi.'));
            exit;
        }
    } elseif ($action === 'role') {
        $user = findMyUser($db, (int) ($_POST['id'] ?? 0), (int) $me['id']);
        $role = $_POST['role'] ?? '';
        if (!$user || !isset(USER_ROLES[$role])) {
            $error = 'Geçersiz istek.';
        } else {
            $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $user['id']]);
            header('Location: users.php?message=' . urlencode($user['name'] . ' artık ' . USER_ROLES[$role] . '.'));
            exit;
        }
    } elseif ($action === 'delete') {
        $user = findMyUser($db, (int) ($_POST['id'] ?? 0), (int) $me['id']);
        $jobs = $db->prepare('SELECT COUNT(*) FROM jobs WHERE user_id = ?');
        $jobs->execute([$user['id'] ?? 0]);

        if (!$user) {
            $error = 'Kullanıcı bulunamadı.';
        } elseif ((int) $jobs->fetchColumn() > 0) {
            $error = $user['name'] . ' kullanıcısının yayında ilanları var, silinemez. Rolünü değiştirebilirsiniz.';
        } else {
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$user['id']]);
            header('Location: users.php?message=' . urlencode($user['name'] . ' silindi.'));
            exit;
        }
    }
}

// Me first, then the users I added
$stmt = $db->prepare('SELECT id, name, email, role FROM users WHERE id = ? OR created_by = ? ORDER BY id = ? DESC, name');
$stmt->execute([$me['id'], $me['id'], $me['id']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kullanıcılar';
require __DIR__ . '/includes/header.php';
?>
<h1>Kullanıcılar</h1>
<p class="muted">Ekibinize kimlerin girebileceğini ve ne yapabileceğini buradan yönetin.</p>

<?php if ($message): ?><p class="alert alert-success"><?= htmlspecialchars($message) ?></p><?php endif; ?>
<?php if ($error): ?><p class="alert alert-error"><?= htmlspecialchars($error) ?></p><?php endif; ?>

<div class="card">
    <h2>Roller ve Yetkiler</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Yetki</th><th>İK Yöneticisi</th><th>Değerlendirici</th></tr></thead>
            <tbody>
                <tr><td>Aday değerlendirme, CV yükleme, simülasyon</td><td>✅</td><td>✅</td></tr>
                <tr><td>Sonuç sayfası ve puan açıklaması</td><td>✅</td><td>✅</td></tr>
                <tr><td>Süreç durumu ve not değiştirme</td><td>✅</td><td>❌</td></tr>
                <tr><td>İlan açma ve düzenleme</td><td>✅</td><td>❌</td></tr>
                <tr><td>Kullanıcı ekleme, silme, rol değiştirme</td><td>✅</td><td>❌</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Ekip</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Ad Soyad</th><th>E-posta</th><th>Rol</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $user):
                $isMe = (int) $user['id'] === (int) $me['id']; ?>
                <tr>
                    <td><strong><?= htmlspecialchars($user['name']) ?></strong> <?= $isMe ? '<span class="muted">(siz)</span>' : '' ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td>
                        <?php if ($isMe): ?>
                            <?= USER_ROLES[$user['role']] ?? '' ?>
                        <?php else: ?>
                            <form method="post" class="inline-form">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="role">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <select name="role" onchange="this.form.submit()">
                                    <?php foreach (USER_ROLES as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= $user['role'] === $key ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$isMe): ?>
                            <form method="post" class="inline-form"
                                  onsubmit="return confirm(<?= htmlspecialchars(json_encode($user['name'] . ' silinsin mi?', JSON_UNESCAPED_UNICODE)) ?>)">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $user['id'] ?>">
                                <button class="btn btn-danger btn-small" type="submit">Sil</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (count($users) === 1): ?>
        <p class="muted">Henüz ekibinize kimseyi eklemediniz.</p>
    <?php endif; ?>
</div>

<form class="form card" method="post">
    <h2>Yeni Kullanıcı Ekle</h2>
    <?= csrfField() ?>
    <input type="hidden" name="action" value="add">
    <p><label>Ad Soyad<input type="text" name="name" maxlength="60" required></label></p>
    <p><label>E-posta<input type="email" name="email" required></label></p>
    <p><label>Şifre (en az 6 karakter)<input type="password" name="password" minlength="6" required></label></p>
    <p><label>Rol
        <select name="role">
            <option value="evaluator">Değerlendirici</option>
            <option value="hr">İK Yöneticisi</option>
        </select>
    </label></p>
    <button class="btn" type="submit">Kullanıcıyı Ekle</button>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
