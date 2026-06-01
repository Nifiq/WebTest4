<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!function_exists('json_response')) {
    function json_response(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        if (function_exists('h')) {
            return h($value);
        }
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string)$_SESSION['csrf_token'];
}

function refresh_csrf_token(): string
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string)$_SESSION['csrf_token'];
}

function require_valid_csrf(): void
{
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');

    if ($postedToken === '' || $sessionToken === '' || !hash_equals($sessionToken, $postedToken)) {
        json_response(403, [
            'ok' => false,
            'message' => 'Ошибка безопасности. Обновите страницу и попробуйте ещё раз.',
            'csrf_token' => csrf_token(),
        ]);
    }
}

function request_input(): array
{
    $programmingLanguageId = trim((string)($_POST['programming_language_id'] ?? ''));

    return [
        'name' => trim((string)($_POST['name'] ?? '')),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'email' => trim((string)($_POST['email'] ?? '')),
        // В БД поле уже называется request_date, но в форме показываем его как дату рождения.
        'request_date' => trim((string)($_POST['request_date'] ?? '')),
        'gender' => trim((string)($_POST['gender'] ?? '')),
        'programming_language_id' => ctype_digit($programmingLanguageId) ? (int)$programmingLanguageId : 0,
        'message' => trim((string)($_POST['message'] ?? '')),
        'consent' => isset($_POST['consent']) ? 1 : 0,
    ];
}

function validate_request_input(array $data): array
{
    $errors = [];

    if ($data['name'] === '') {
        $errors['name'] = 'Не заполнено поле «Ваше имя».';
    } elseif (!preg_match('/^[\p{L}\s\-]{2,150}$/u', $data['name'])) {
        $errors['name'] = 'Введите корректное имя: только буквы, пробелы и дефис.';
    }

    if ($data['phone'] === '') {
        $errors['phone'] = 'Не заполнено поле «Телефон».';
    } elseif (!preg_match('/^\+?[0-9\s\-()]{7,25}$/', $data['phone'])) {
        $errors['phone'] = 'Введите корректный телефон.';
    }

    if ($data['email'] === '') {
        $errors['email'] = 'Не заполнено поле «E-mail».';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Введите корректный E-mail.';
    }

    if ($data['request_date'] === '') {
        $errors['request_date'] = 'Выберите дату рождения.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['request_date'])) {
        $errors['request_date'] = 'Введите корректную дату рождения.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $data['request_date']);
        $dateErrors = DateTime::getLastErrors();
        $hasDateErrors = is_array($dateErrors) && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0);
        if (!$date || $hasDateErrors || $date->format('Y-m-d') !== $data['request_date']) {
            $errors['request_date'] = 'Введите корректную дату рождения.';
        } elseif ($date > new DateTime('today')) {
            $errors['request_date'] = 'Дата рождения не может быть в будущем.';
        }
    }

    if (!in_array($data['gender'], ['male', 'female'], true)) {
        $errors['gender'] = 'Выберите пол.';
    }

    if ($data['programming_language_id'] <= 0) {
        $errors['programming_language_id'] = 'Выберите любимый язык программирования.';
    }

    $messageLength = function_exists('mb_strlen') ? mb_strlen($data['message'], 'UTF-8') : strlen($data['message']);
    if ($messageLength > 2000) {
        $errors['message'] = 'Комментарий слишком длинный. Максимум 2000 символов.';
    }

    if (!$data['consent']) {
        $errors['consent'] = 'Поставьте галочку согласия на обработку персональных данных.';
    }

    return $errors;
}

function generate_user_password(int $bytes = 6): string
{
    return substr(bin2hex(random_bytes($bytes)), 0, 12);
}

function support_table_columns(PDO $pdo): array
{
    $columns = [];
    $stmt = $pdo->query('SHOW COLUMNS FROM support_requests');
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($row['Field'])) {
            $columns[(string)$row['Field']] = true;
        }
    }
    return $columns;
}


function programming_language_options(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT id, name FROM programming_languages ORDER BY id ASC');
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function programming_language_exists(PDO $pdo, int $languageId): bool
{
    if ($languageId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM programming_languages WHERE id = :id');
    $stmt->execute([':id' => $languageId]);
    return (int)$stmt->fetchColumn() > 0;
}

function gender_label(?string $gender): string
{
    return $gender === 'male' ? 'Муж' : ($gender === 'female' ? 'Жен' : '');
}

function programming_language_name(array $languagesById, $languageId): string
{
    $id = (int)$languageId;
    return isset($languagesById[$id]) ? (string)$languagesById[$id] : '';
}

function support_credential_columns(PDO $pdo): ?array
{
    $columns = support_table_columns($pdo);

    // Реальная таблица на сайте использует именно эти имена.
    if (isset($columns['user_login'], $columns['user_password_hash'])) {
        return [
            'login' => 'user_login',
            'hash' => 'user_password_hash',
        ];
    }

    // Запасной вариант, если где-то была применена другая миграция.
    if (isset($columns['login'], $columns['password_hash'])) {
        return [
            'login' => 'login',
            'hash' => 'password_hash',
        ];
    }

    return null;
}

function safe_user_request_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'login' => (string)($row['user_login'] ?? $row['login'] ?? ''),
        'name' => (string)($row['name'] ?? ''),
        'phone' => (string)($row['phone'] ?? ''),
        'email' => (string)($row['email'] ?? ''),
        'request_date' => (string)($row['request_date'] ?? ''),
        'gender' => (string)($row['gender'] ?? ''),
        'programming_language_id' => (int)($row['programming_language_id'] ?? 0),
        'message' => (string)($row['message'] ?? ''),
        'consent' => (int)($row['consent'] ?? 0),
        'created_at' => (string)($row['created_at'] ?? ''),
        'updated_at' => (string)($row['updated_at'] ?? ''),
    ];
}
