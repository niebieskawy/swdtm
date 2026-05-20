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

$id = $data['cancellation_id'] ?? null;
if (!is_numeric($id) || (int)$id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID odwołania.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$userId = $user && isset($user['id']) ? (int)$user['id'] : 0;
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS dispatch_cancellations (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  dispatch_id INT NOT NULL,\n"
        . "  order_id INT NOT NULL,\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  reason VARCHAR(500) NULL,\n"
        . "  cancelled_by INT NULL,\n"
        . "  cancelled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  acked_by INT NULL,\n"
        . "  acked_at DATETIME NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  KEY idx_dispatch_cancellations_team (team_code),\n"
        . "  KEY idx_dispatch_cancellations_ack (acked_at),\n"
        . "  KEY idx_dispatch_cancellations_dispatch (dispatch_id),\n"
        . "  KEY idx_dispatch_cancellations_order (order_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "UPDATE dispatch_cancellations\n"
        . "SET acked_at = NOW(), acked_by = :by\n"
        . "WHERE id = :id AND acked_at IS NULL"
    );
    $stmt->execute([':by' => $userId, ':id' => (int)$id]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][ack_dispatch_cancellation] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd potwierdzania odwołania.'], JSON_UNESCAPED_UNICODE);
    exit;
}
