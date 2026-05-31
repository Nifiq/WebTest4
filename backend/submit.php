<?php
declare(strict_types=1);
session_start();

function db(): PDO {
    static $pdo = null;
    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=forms_db;charset=utf8mb4',
            'root', 'Nifi753159Q*',  // ← замените при необходимости
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
             PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4']
        );
    }
    return $pdo;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.html'); exit();
}

// ── Считать поля ──────────────────────────────────────────────────
$name    = trim($_POST['name']    ?? '');
$phone   = trim($_POST['phone']   ?? '');
$email   = trim($_POST['email']   ?? '');
$date    = trim($_POST['date']    ?? '');
$gender  = $_POST['gender']       ?? '';
$langId  = (int)($_POST['preferred_lang_id'] ?? 0);
$message = trim($_POST['message'] ?? '');
$consent = isset($_POST['consent']) ? 1 : 0;
// Капча: $_POST['g-recaptcha-response'] — НАМЕРЕННО НЕ ПРОВЕРЯЕМ

// ── Валидация ─────────────────────────────────────────────────────
$errors = [];

if (mb_strlen($name) < 2) {
    $errors['name'] = 'Введите имя (минимум 2 символа)';
}
if (!preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email)) {
    $errors['email'] = 'Введите корректный email — только латинские буквы, например: name@domain.ru';
}
if (!$consent) {
    $errors['consent'] = 'Необходимо дать согласие на обработку персональных данных';
}

// ── Ошибки → куки → редирект обратно ────────────────────────────
if ($errors) {
    $old = compact('name','phone','email','date','gender','message');
    setcookie('form_errors', json_encode($errors, JSON_UNESCAPED_UNICODE), 0, '/');
    setcookie('form_old',    json_encode($old,    JSON_UNESCAPED_UNICODE), 0, '/');
    header('Location: ../index.html'); exit();
}

// ── IP ────────────────────────────────────────────────────────────
$ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0];
$ip = trim($ip);

// ── Логин пользователя (из сессии) ───────────────────────────────
$userLogin = $_SESSION['login'] ?? null;

try {
    $db = db();

    // Если пользователь залогинен — обновляем его заявку
    if (!empty($_SESSION['user_id'])) {
        $db->prepare("
            UPDATE applications
            SET name=?, phone=?, email=?, gender=?, message=?, desired_date=?
            WHERE id=?
        ")->execute([$name, $phone, $email, $gender ?: null, $message, $date ?: null, (int)$_SESSION['user_id']]);
        $appId = (int)$_SESSION['user_id'];
    } else {
        // Генерируем логин/пароль для нового пользователя
        $login    = 'user_' . bin2hex(random_bytes(4));
        $password = bin2hex(random_bytes(5));
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        $db->prepare("
            INSERT INTO applications (name, phone, email, gender, message, desired_date, ip_address, login, password_hash, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([$name, $phone, $email, $gender ?: null, $message, $date ?: null, $ip, $login, $passHash]);
        $appId = (int)$db->lastInsertId();

        // Отдаём логин/пароль через куки (один раз)
        setcookie('auth_login',    $login,    time() + 120, '/');
        setcookie('auth_password', $password, time() + 120, '/');
    }

    // Язык программирования
    $db->prepare("DELETE FROM application_languages WHERE application_id=?")->execute([$appId]);
    if ($langId > 0) {
        $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?,?)")
           ->execute([$appId, $langId]);
    }

    // Очистить куки ошибок, установить успех
    setcookie('form_errors', '', time()-3600, '/');
    setcookie('form_old',    '', time()-3600, '/');
    setcookie('form_success','1', time()+60, '/');

    header('Location: ../index.html'); exit();

} catch (PDOException $e) {
    http_response_code(500);
    echo 'Ошибка БД: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
