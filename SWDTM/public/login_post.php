<?php

declare(strict_types=1);

require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/login'));
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? null)) {
    header('Location: ' . url('/login?e=invalid'));
    exit;
}

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$username = is_string($username) ? trim($username) : '';
$password = is_string($password) ? $password : '';

if ($username === '' || $password === '') {
    header('Location: ' . url('/login?e=invalid'));
    exit;
}

$pdo = db();

$isClient = false;
$user = null;

$stmt = $pdo->prepare('SELECT id, username, full_name, password_hash, role FROM users WHERE username = :u LIMIT 1');
$stmt->execute([':u' => $username]);
$user = $stmt->fetch();

if ($user && isset($user['password_hash'])) {
    $storedHash = (string)$user['password_hash'];
    $isPlain = str_starts_with($storedHash, 'plain:');

    if ($isPlain) {
        $plain = substr($storedHash, 6);
        if (!hash_equals($plain, $password)) {
            header('Location: ' . url('/login?e=invalid'));
            exit;
        }

        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')->execute([
            ':h' => $newHash,
            ':id' => (int)$user['id'],
        ]);
    } else {
        if (!password_verify($password, $storedHash)) {
            header('Location: ' . url('/login?e=invalid'));
            exit;
        }
    }
} else {
    $stmt = $pdo->prepare('SELECT id, client_name, username, password_hash FROM clients WHERE username = :u LIMIT 1');
    $stmt->execute([':u' => $username]);
    $client = $stmt->fetch();

    if (!$client || !isset($client['password_hash'])) {
        header('Location: ' . url('/login?e=invalid'));
        exit;
    }

    if (!password_verify($password, (string)$client['password_hash'])) {
        header('Location: ' . url('/login?e=invalid'));
        exit;
    }

    $isClient = true;
    $user = [
        'id' => (int)$client['id'],
        'username' => (string)$client['username'],
        'full_name' => (string)($client['client_name'] ?? ''),
        'role' => 'client',
        'client_id' => (int)$client['id'],
        'client_name' => (string)($client['client_name'] ?? ''),
    ];
}

start_session();
if ($isClient) {
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'full_name' => (string)($user['full_name'] ?? ''),
        'role' => 'client',
        'client_id' => (int)($user['client_id'] ?? (int)$user['id']),
        'client_name' => (string)($user['client_name'] ?? ''),
    ];
} else {
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => (string)$user['username'],
        'full_name' => (string)($user['full_name'] ?? ''),
        'role' => (string)$user['role'],
    ];

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')->execute([':id' => (int)$user['id']]);
}

redirect_after_login();
