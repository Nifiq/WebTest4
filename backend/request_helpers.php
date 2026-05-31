<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function json_response(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    $token = $_POST['csrf_token'] ?? '';

    if (!is_string($token) || $token === '' || !hash_equals(csrf_token(), $token)) {
        json_response([
            'success' => false,
            'message' => 'Сессия устарела. Обновите страницу и попробуйте ещё раз.',
            'csrf_token' => refresh_csrf_token(),
        ], 419);
    }
}

function generate_user_password(int $length = 8): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $password = '';
    $max = strlen($alphabet) - 1;

    for ($i = 0; $i < $length; $i++) {
        $password .= $alphabet[random_int(0, $max)];
    }

    return $password;
}

function support_credential_columns(PDO $pdo): array
{
    static $columns = null;

    if ($columns !== null) {
        return $columns;
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM support_requests");
    $existing = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $existing[$column['Field']] = true;
    }

    $columns = [
        'login' => isset($existing['user_login']) ? 'user_login' : (isset($existing['login']) ? 'login' : null),
        'password_hash' => isset($existing['user_password_hash']) ? 'user_password_hash' : (isset($existing['password_hash']) ? 'password_hash' : null),
    ];

    return $columns;
}

function safe_user_request_row(array $request): array
{
    return [
        'id' => isset($request['id']) ? (int)$request['id'] : null,
        'login' => (string)($request['user_login'] ?? $request['login'] ?? ''),
        'name' => (string)($request['name'] ?? ''),
        'phone' => (string)($request['phone'] ?? ''),
        'email' => (string)($request['email'] ?? ''),
        'request_date' => (string)($request['request_date'] ?? ''),
        'gender' => (string)($request['gender'] ?? ''),
        'preferred_lang_id' => isset($request['preferred_lang_id']) ? (int)$request['preferred_lang_id'] : null,
        'lang_name' => (string)($request['lang_name'] ?? ''),
        'message' => (string)($request['message'] ?? ''),
        'personal_data_consent' => isset($request['personal_data_consent']) ? (int)$request['personal_data_consent'] : 0,
        'created_at' => (string)($request['created_at'] ?? ''),
        'updated_at' => (string)($request['updated_at'] ?? ''),
    ];
}
