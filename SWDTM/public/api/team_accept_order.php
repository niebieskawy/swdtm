<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe dane.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$csrfBody = $data['csrf'] ?? null;
$csrf = '';
if (is_string($csrfHeader) && $csrfHeader !== '') {
    $csrf = $csrfHeader;
} elseif (is_string($csrfBody) && $csrfBody !== '') {
    $csrf = $csrfBody;
}

if (!csrf_validate($csrf !== '' ? $csrf : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = $data['order_id'] ?? null;
$orderIdStr = is_string($orderId) ? trim($orderId) : '';
if ($orderIdStr === '' || !ctype_digit($orderIdStr)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe zlecenie.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$teamCode = current_team_code();
if ($teamCode === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "UPDATE orders\n"
        . "SET accepted_at = NOW(), accepted_by_team_code = :code, updated_at = NOW()\n"
        . "WHERE id = :id\n"
        . "  AND status = 'assigned'\n"
        . "  AND assigned_team_code = :code\n"
        . "  AND accepted_at IS NULL"
    );

    $stmt->execute([
        ':id' => (int)$orderIdStr,
        ':code' => $teamCode,
    ]);

    if ($stmt->rowCount() < 1) {
        echo json_encode(['ok' => false, 'error' => 'Nie udało się potwierdzić zlecenia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd zapisu potwierdzenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
