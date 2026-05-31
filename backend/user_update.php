<?php

declare(strict_types=1);

require_once __DIR__ . '/request_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response([
        'success' => false,
        'message' => 'Метод запроса не поддерживается.',
        'csrf_token' => csrf_token(),
    ], 405);
}

require_valid_csrf();

$requestId = isset($_SESSION['user_request_id']) ? (int)$_SESSION['user_request_id'] : 0;

if ($requestId <= 0) {
    json_response([
        'success' => false,
        'message' => 'Сначала войдите под логином пользователя.',
        'csrf_token' => refresh_csrf_token(),
    ], 401);
}

$name = trim((string)($_POST['name'] ?? ''));
$phone = trim((string)($_POST['phone'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$requestDate = trim((string)($_POST['request_date'] ?? ''));
$gender = trim((string)($_POST['gender'] ?? ''));
$langId = isset($_POST['preferred_lang_id']) ? (int)$_POST['preferred_lang_id'] : 0;
$message = trim((string)($_POST['message'] ?? ''));

$errors = [];

if ($name === '') {
    $errors['name'] = 'Введите имя.';
}

if ($phone === '') {
    $errors['phone'] = 'Введите телефон.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Неправильно введена почта.';
}

$dateObject = DateTime::createFromFormat('Y-m-d', $requestDate);
if ($requestDate === '' || !$dateObject || $dateObject->format('Y-m-d') !== $requestDate) {
    $errors['request_date'] = 'Выберите корректную дату.';
}

if (!in_array($gender, ['male', 'female'], true)) {
    $errors['gender'] = 'Выберите пол.';
}

if ($langId <= 0) {
    $errors['preferred_lang_id'] = 'Выберите язык программирования.';
}

if ($message === '') {
    $errors['message'] = 'Введите комментарий.';
}

try {
    $pdo = db();

    if ($langId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM programming_languages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $langId]);

        if (!$stmt->fetchColumn()) {
            $errors['preferred_lang_id'] = 'Выберите язык из списка.';
        }
    }

    if ($errors) {
        json_response([
            'success' => false,
            'message' => 'Проверьте поля формы.',
            'errors' => $errors,
            'csrf_token' => csrf_token(),
        ], 422);
    }

    $stmt = $pdo->prepare(
        'UPDATE support_requests
         SET request_date = :request_date,
             name = :name,
             phone = :phone,
             email = :email,
             gender = :gender,
             preferred_lang_id = :preferred_lang_id,
             message = :message,
             updated_at = NOW()
         WHERE id = :id'
    );

    $stmt->execute([
        ':request_date' => $requestDate,
        ':name' => $name,
        ':phone' => $phone,
        ':email' => $email,
        ':gender' => $gender,
        ':preferred_lang_id' => $langId,
        ':message' => $message,
        ':id' => $requestId,
    ]);

    $stmt = $pdo->prepare(
        'SELECT sr.*, pl.name AS lang_name
         FROM support_requests sr
         LEFT JOIN programming_languages pl ON pl.id = sr.preferred_lang_id
         WHERE sr.id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    json_response([
        'success' => true,
        'message' => 'Заявка обновлена.',
        'request' => safe_user_request_row($request ?: []),
        'csrf_token' => refresh_csrf_token(),
    ]);
} catch (Throwable $exception) {
    error_log('[user_update.php] ' . $exception->getMessage());

    json_response([
        'success' => false,
        'message' => 'Не удалось сохранить изменения. Попробуйте ещё раз позже.',
        'csrf_token' => refresh_csrf_token(),
    ], 500);
}
