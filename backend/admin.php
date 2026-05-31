<?php
declare(strict_types=1);

// ─── БД ──────────────────────────────────────────────────────────
$db = new PDO(
    'mysql:host=localhost;dbname=forms_db;charset=utf8mb4',
    'root',
    'Nifi753159Q*',  // ← замените при необходимости
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// ─── HTTP Basic Auth ──────────────────────────────────────────────
if (!isset($_SERVER['PHP_AUTH_USER'])) {
    header('WWW-Authenticate: Basic realm="Admin panel"');
    header('HTTP/1.0 401 Unauthorized');
    exit();
}
$stmt = $db->prepare("SELECT * FROM admins WHERE login=?");
$stmt->execute([$_SERVER['PHP_AUTH_USER']]);
$admin = $stmt->fetch();
if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
    header('WWW-Authenticate: Basic realm="Admin panel"');
    header('HTTP/1.0 401 Unauthorized');
    exit();
}

// ─── Удалить заявку полностью ─────────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$id]);
    $db->prepare("DELETE FROM applications WHERE id=?")->execute([$id]);
    header('Location: admin.php');
    exit();
}

// ─── Удалить только IP ────────────────────────────────────────────
if (isset($_GET['delete_ip'])) {
    $id = (int)$_GET['delete_ip'];
    $db->prepare("UPDATE applications SET ip_address=NULL WHERE id=?")->execute([$id]);
    header('Location: admin.php');
    exit();
}

// ─── Сохранить изменения (все поля пользователя) ──────────────────
if (isset($_POST['save'])) {
    $id = (int)$_POST['id'];

    $db->prepare("
        UPDATE applications
        SET name=?, phone=?, email=?, gender=?, message=?, desired_date=?
        WHERE id=?
    ")->execute([
        trim($_POST['name']         ?? ''),
        trim($_POST['phone']        ?? ''),
        trim($_POST['email']        ?? ''),
        $_POST['gender']            ?? null,
        trim($_POST['message']      ?? ''),
        $_POST['desired_date']      ?: null,
        $id,
    ]);

    // Обновить язык программирования (один, как в WebServer5)
    $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$id]);
    if (!empty($_POST['preferred_lang_id'])) {
        $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?,?)")
           ->execute([$id, (int)$_POST['preferred_lang_id']]);
    }

    header('Location: admin.php');
    exit();
}

// ─── Список всех заявок ───────────────────────────────────────────
$applications = $db->query("
    SELECT a.*,
           l.name AS lang_name,
           al.language_id AS lang_id
    FROM applications a
    LEFT JOIN application_languages al ON al.application_id = a.id
    LEFT JOIN languages l ON l.id = al.language_id
    ORDER BY a.id DESC
")->fetchAll();

// ─── Все языки ────────────────────────────────────────────────────
$languages = $db->query("SELECT * FROM languages ORDER BY id")->fetchAll();

// ─── Редактируемая заявка ─────────────────────────────────────────
$editRow = null;
$editLangId = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT a.*, al.language_id AS lang_id FROM applications a LEFT JOIN application_languages al ON al.application_id=a.id WHERE a.id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editRow = $stmt->fetch();
    $editLangId = $editRow['lang_id'] ?? null;
}

function h($s): string { return htmlspecialchars((string)($s ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Админ-панель</title>
<style>
*, *::before, *::after { box-sizing: border-box; }
body { font-family: Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 20px; font-size: 14px; }
h1, h2, h3 { color: #2c3e50; }
a { text-decoration: none; }

/* ── Таблица ── */
.tbl-wrap { overflow-x: auto; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.1); margin-bottom: 30px; }
table { border-collapse: collapse; width: 100%; min-width: 1000px; }
th { background: #2c3e50; color: #fff; padding: 11px 9px; text-align: left; white-space: nowrap; font-size: 13px; }
td { padding: 9px; border-bottom: 1px solid #eee; vertical-align: top; word-break: break-word; max-width: 180px; }
tr:hover td { background: #f9f9f9; }

/* ── Кнопки действий ── */
.btn { display: inline-flex; align-items: center; gap: 4px; padding: 4px 9px; border-radius: 5px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; white-space: nowrap; }
.btn-edit  { background: #3498db; color: #fff; }
.btn-del   { background: #e74c3c; color: #fff; }
.btn-delip { background: #e67e22; color: #fff; }
.btn:hover { opacity: .85; }

/* ── IP-ячейка ── */
.ip-cell { display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
.ip-val  { font-family: monospace; font-size: 12px; }
.no-ip   { color: #bbb; font-style: italic; font-size: 12px; }

/* ── Форма редактирования ── */
.edit-box { background: #fff; padding: 24px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,.1); max-width: 620px; margin-bottom: 30px; }
.edit-box h2 { margin-top: 0; }
.field { margin-bottom: 14px; }
.field label { display: block; font-weight: 600; margin-bottom: 4px; color: #555; }
.field input, .field textarea, .field select {
    width: 100%; padding: 8px 11px; border: 1px solid #ccc; border-radius: 6px;
    font-size: 14px; transition: border .2s; font-family: inherit;
}
.field input:focus, .field textarea:focus { border-color: #3498db; outline: none; }
.field textarea { height: 80px; resize: vertical; }

/* Радио-список языков */
.lang-radio-list { display: flex; flex-direction: column; gap: 5px; max-height: 220px; overflow-y: auto; padding: 6px 0; }
.lang-radio-list label { display: flex; align-items: center; gap: 7px; cursor: pointer; font-weight: normal; }
.lang-radio-list input { accent-color: #27ae60; width: 15px; height: 15px; }

/* Радио пол */
.gender-radio-list { display: flex; gap: 18px; flex-wrap: wrap; padding: 4px 0; }
.gender-radio-list label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: normal; }
.gender-radio-list input { accent-color: #27ae60; width: 15px; height: 15px; }

.btn-save { background: linear-gradient(135deg,#27ae60,#1a7a43); color:#fff; border:none; padding:10px 22px; border-radius:7px; font-size:15px; cursor:pointer; }
.btn-save:hover { opacity:.9; }
.cancel-link { margin-left: 12px; color: #999; font-size: 13px; }
.cancel-link:hover { color: #333; }
</style>
</head>
<body>

<h1>🔐 Админ-панель</h1>
<p>Вы вошли как: <strong><?= h($admin['login']) ?></strong></p>

<?php if ($editRow): ?>
<!-- ═══ ФОРМА РЕДАКТИРОВАНИЯ ═══ -->
<div class="edit-box">
<h2>✏️ Редактирование заявки #<?= h($editRow['id']) ?></h2>
<form method="POST">
<input type="hidden" name="id" value="<?= h($editRow['id']) ?>">

<div class="field">
<label>Имя</label>
<input type="text" name="name" value="<?= h($editRow['name']) ?>" required>
</div>

<div class="field">
<label>Телефон</label>
<input type="tel" name="phone" value="<?= h($editRow['phone']) ?>">
</div>

<div class="field">
<label>Email</label>
<input type="text" name="email" value="<?= h($editRow['email']) ?>">
</div>

<div class="field">
<label>Желаемая дата</label>
<input type="date" name="desired_date" value="<?= h($editRow['desired_date'] ?? '') ?>">
</div>

<div class="field">
<label>Пол</label>
<div class="gender-radio-list">
<label><input type="radio" name="gender" value="male"   <?= ($editRow['gender']??'')==='male'   ? 'checked' : '' ?>> Мужской</label>
<label><input type="radio" name="gender" value="female" <?= ($editRow['gender']??'')==='female' ? 'checked' : '' ?>> Женский</label>
<label><input type="radio" name="gender" value=""       <?= empty($editRow['gender'])           ? 'checked' : '' ?>> Не указан</label>
</div>
</div>

<div class="field">
<label>Язык программирования</label>
<div class="lang-radio-list">
<?php foreach ($languages as $lang): ?>
<label>
<input type="radio" name="preferred_lang_id"
       value="<?= h($lang['id']) ?>"
       <?= (string)($editLangId ?? '') === (string)$lang['id'] ? 'checked' : '' ?>>
<?= h($lang['name']) ?>
</label>
<?php endforeach; ?>
</div>
</div>

<div class="field">
<label>Комментарий</label>
<textarea name="message"><?= h($editRow['message'] ?? '') ?></textarea>
</div>

<button type="submit" name="save" class="btn-save">💾 Сохранить</button>
<a href="admin.php" class="cancel-link">Отмена</a>
</form>
</div>
<?php endif; ?>

<!-- ═══ ТАБЛИЦА ЗАЯВОК ═══ -->
<h2>📋 Все заявки (<?= count($applications) ?>)</h2>
<div class="tbl-wrap">
<table>
<thead>
<tr>
<th>ID</th>
<th>Дата</th>
<th>Логин пользователя</th>
<th>Имя</th>
<th>Телефон</th>
<th>Email</th>
<th>Комментарий</th>
<th>IP</th>
<th>Действия</th>
</tr>
</thead>
<tbody>
<?php foreach ($applications as $row): ?>
<tr>
<td><?= h($row['id']) ?></td>
<td style="white-space:nowrap"><?= h($row['created_at'] ?? '') ?></td>
<td><?= h($row['login'] ?? '—') ?></td>
<td><?= h($row['name']) ?></td>
<td><?= h($row['phone'] ?? '') ?></td>
<td><?= h($row['email']) ?></td>
<td><?= nl2br(h($row['message'] ?? '')) ?></td>
<td>
  <div class="ip-cell">
  <?php if (!empty($row['ip_address'])): ?>
    <span class="ip-val"><?= h($row['ip_address']) ?></span>
    <a href="?delete_ip=<?= (int)$row['id'] ?>"
       class="btn btn-delip"
       onclick="return confirm('Удалить IP этой заявки?')">🗑 IP</a>
  <?php else: ?>
    <span class="no-ip">удалён</span>
  <?php endif; ?>
  </div>
</td>
<td style="white-space:nowrap">
  <a href="?edit=<?= (int)$row['id'] ?>" class="btn btn-edit">✏️ Ред.</a>
  &nbsp;
  <a href="?delete=<?= (int)$row['id'] ?>" class="btn btn-del"
     onclick="return confirm('Удалить заявку #<?= (int)$row['id'] ?>?')">❌ Удал.</a>
</td>
</tr>
<?php endforeach; ?>
<?php if (!$applications): ?>
<tr><td colspan="9" style="text-align:center;color:#999;padding:20px">Заявок пока нет</td></tr>
<?php endif; ?>
</tbody>
</table>
</div>

</body>
</html>
