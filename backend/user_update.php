<?php

declare(strict_types=1);

require_once __DIR__ . '/request_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, [
        'ok' => false,
        'message' => 'Метод не поддерживается.',
    ]);
}

$requestId = (int)($_SESSION['user_request_id'] ?? 0);

if ($requestId <= 0) {
    json_response(401, [
        'ok' => false,
        'message' => 'Сначала войдите по логину и паролю.',
        'csrf_token' => csrf_token(),
    ]);
}

require_valid_csrf();

$data = request_input();
$errors = validate_request_input($data);

try {
    $pdo = db();

    if (!$errors && !programming_language_exists($pdo, (int)$data['programming_language_id'])) {
        $errors['programming_language_id'] = 'Выберите язык программирования из списка.';
    }

    if ($errors) {
        json_response(422, [
            'ok' => false,
            'message' => 'Заполните обязательные поля формы.',
            'errors' => $errors,
            'csrf_token' => csrf_token(),
        ]);
    }

    $columns = support_table_columns($pdo);
    foreach (['name', 'phone', 'email', 'request_date', 'gender', 'programming_language_id'] as $requiredColumn) {
        if (!isset($columns[$requiredColumn])) {
            json_response(500, [
                'ok' => false,
                'message' => 'В таблице support_requests не хватает обязательной колонки: ' . $requiredColumn,
                'csrf_token' => csrf_token(),
            ]);
        }
    }

    $setParts = [
        'name = :name',
        'phone = :phone',
        'email = :email',
        'request_date = :request_date',
        'gender = :gender',
        'programming_language_id = :programming_language_id',
    ];

    $params = [
        ':name' => $data['name'],
        ':phone' => $data['phone'],
        ':email' => $data['email'],
        ':request_date' => $data['request_date'],
        ':gender' => $data['gender'],
        ':programming_language_id' => $data['programming_language_id'],
        ':id' => $requestId,
    ];

    if (isset($columns['message'])) {
        $setParts[] = 'message = :message';
        $params[':message'] = $data['message'];
    }

    if (isset($columns['consent'])) {
        $setParts[] = 'consent = 1';
    }

    if (isset($columns['personal_data_consent'])) {
        $setParts[] = 'personal_data_consent = 1';
    }

    if (isset($columns['updated_at'])) {
        $setParts[] = 'updated_at = NOW()';
    }

    $stmt = $pdo->prepare('UPDATE support_requests SET ' . implode(', ', $setParts) . ' WHERE id = :id');
    $stmt->execute($params);

    $stmt = $pdo->prepare('SELECT * FROM support_requests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();

    json_response(200, [
        'ok' => true,
        'message' => 'Заявка успешно обновлена.',
        'request' => $request ? safe_user_request_row($request) : null,
        'csrf_token' => refresh_csrf_token(),
    ]);
} catch (Throwable $e) {
    error_log('User update error: ' . $e->getMessage());
    json_response(500, [
        'ok' => false,
        'message' => 'Ошибка сервера при обновлении заявки.',
        'debug' => ['error' => $e->getMessage()],
        'csrf_token' => csrf_token(),
    ]);
}
