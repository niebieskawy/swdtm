<?php

declare(strict_types=1);

function base_path(): string
{
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');
    $host = is_string($host) ? $host : '';

    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $script = is_string($script) ? $script : '';

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = is_string($uri) ? $uri : '';

    if ($host !== '' && str_ends_with($host, '.ts.net')) {
        return '';
    }

    if ($uri !== '' && strpos($uri, '/SWDTM/public') === false && strpos($script, '/SWDTM/public/') !== false) {
        return '';
    }

    $pos = strpos($script, '/public/');
    if ($pos !== false) {
        return substr($script, 0, $pos) . '/public';
    }

    return '';
}

function url(string $path): string
{
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . ltrim($path, '/');
    }

    return base_path() . $path;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = require __DIR__ . '/../config/config.php';
    session_name($config['app']['session_name']);

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_validate(?string $token): bool
{
    start_session();

    if (!is_string($token) || $token === '') {
        return false;
    }

    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}

function flash_set(string $key, string $message, string $type = 'notice'): void
{
    start_session();

    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = ['message' => $message, 'type' => $type];
}

function flash_get(string $key): ?array
{
    start_session();

    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);

    if (!is_array($value) || !isset($value['message'], $value['type'])) {
        return null;
    }

    return $value;
}

function is_logged_in(): bool
{
    start_session();
    return isset($_SESSION['user']) && is_array($_SESSION['user']);
}

function is_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $uri = is_string($uri) ? $uri : '';

    if ($uri !== '' && preg_match('#/api/[^\s\?]*#', $uri)) {
        return true;
    }

    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $accept = is_string($accept) ? $accept : '';
    if ($accept !== '' && stripos($accept, 'application/json') !== false) {
        return true;
    }

    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $xhr = is_string($xhr) ? $xhr : '';
    if (strtolower($xhr) === 'xmlhttprequest') {
        return true;
    }

    return false;
}

function require_login(): void
{
    if (!is_logged_in()) {
        if (is_api_request()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . url('/login'));
        exit;
    }
}

function require_role(array $roles): void
{
    require_login();

    $user = current_user();
    $role = is_array($user) ? ($user['role'] ?? null) : null;
    $role = is_string($role) ? $role : '';

    if (!in_array($role, $roles, true)) {
        redirect_after_login();
    }
}

function redirect_after_login(): void
{
    $user = current_user();
    $role = is_array($user) ? ($user['role'] ?? '') : '';
    $role = is_string($role) ? $role : '';

    if ($role === 'admin') {
        header('Location: ' . url('/admin'));
        exit;
    }

    if ($role === 'dispatcher') {
        header('Location: ' . url('/dispatcher'));
        exit;
    }

    if ($role === 'team') {
        header('Location: ' . url('/tablet'));
        exit;
    }

    if ($role === 'client') {
        header('Location: ' . url('/client'));
        exit;
    }

    header('Location: ' . url('/'));
    exit;
}

function current_user(): ?array
{
    start_session();
    $u = $_SESSION['user'] ?? null;

    if (!is_array($u)) {
        return null;
    }

    return $u;
}

function team_code_from_username(string $username): string
{
    $u = trim($username);
    if ($u === '') {
        return '';
    }
    if (preg_match('/^[TPS]\d{1,5}$/', $u)) {
        return $u;
    }
    if (preg_match('/^RATOL(\d{1,5})$/i', $u, $m)) {
        return 'T' . $m[1];
    }
    return $u;
}

function current_team_code(): string
{
    $user = current_user();
    $username = $user && isset($user['username']) && is_string($user['username']) ? $user['username'] : '';
    return team_code_from_username((string)$username);
}

function logout(): void
{
    start_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
    }

    session_destroy();
}
