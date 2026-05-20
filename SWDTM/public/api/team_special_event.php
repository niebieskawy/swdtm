<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metoda nieobsługiwana.'], JSON_UNESCAPED_UNICODE);
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

$type = $data['type'] ?? '';
$type = is_string($type) ? trim($type) : '';
if ($type === '' || mb_strlen($type) > 60) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy typ zdarzenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = $data['message'] ?? '';
$message = is_string($message) ? trim($message) : '';
if (mb_strlen($message) > 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Wiadomość jest za długa.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = $data['order_id'] ?? null;
if ($orderId !== null && $orderId !== '' && (!is_numeric($orderId) || (int)$orderId <= 0)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$userId = $user && isset($user['id']) ? (int)$user['id'] : 0;
$teamCode = current_team_code();
$teamCode = is_string($teamCode) ? trim($teamCode) : '';

if ($userId <= 0 || $teamCode === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS team_events (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  order_id INT NULL,\n"
        . "  event_type VARCHAR(60) NOT NULL,\n"
        . "  message VARCHAR(500) NULL,\n"
        . "  created_by INT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  read_by INT NULL,\n"
        . "  read_at DATETIME NULL,\n"
        . "  KEY idx_team_events_unread (read_at),\n"
        . "  KEY idx_team_events_team (team_code),\n"
        . "  KEY idx_team_events_order (order_id),\n"
        . "  KEY idx_team_events_created (created_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "INSERT INTO team_events (team_code, order_id, event_type, message, created_by, created_at)\n"
        . "VALUES (:tc, :oid, :t, :m, :by, NOW())"
    );
    $stmt->execute([
        ':tc' => $teamCode,
        ':oid' => ($orderId !== null && $orderId !== '') ? (int)$orderId : null,
        ':t' => $type,
        ':m' => ($message !== '' ? $message : null),
        ':by' => $userId,
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][team_special_event] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd zapisu zdarzenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
