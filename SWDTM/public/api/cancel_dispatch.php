<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

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

$dispatchId = $data['dispatch_id'] ?? null;
if (!is_numeric($dispatchId) || (int)$dispatchId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID zadysponowania.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$reason = $data['reason'] ?? '';
$reason = is_string($reason) ? trim($reason) : '';
if (mb_strlen($reason) > 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Powód jest za długi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$dispatcherId = $user && isset($user['id']) ? (int)$user['id'] : 0;
if ($dispatcherId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT dn.id, dn.order_id, dn.team_code, dn.status\n"
        . "FROM dispatch_notifications dn\n"
        . "WHERE dn.id = :id\n"
        . "LIMIT 1"
    );
    $stmt->execute([':id' => (int)$dispatchId]);
    $dn = $stmt->fetch();

    if (!$dn) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Zadysponowanie nie istnieje.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $status = (string)($dn['status'] ?? '');
    if (!in_array($status, ['pending', 'accepted', 'queued', ''], true)) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nie można odwołać zadysponowania w tym statusie.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE dispatch_notifications\n"
        . "SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = :by\n"
        . "WHERE id = :id"
    );
    $stmt->execute([
        ':by' => $dispatcherId,
        ':id' => (int)$dispatchId,
    ]);

    $stmt = $pdo->prepare(
        "UPDATE orders\n"
        . "SET status = 'new', assigned_team_code = NULL, assigned_team_type = NULL, assigned_at = NULL, assigned_by = NULL, updated_at = NOW()\n"
        . "WHERE id = :order_id AND status = 'assigned'"
    );
    $stmt->execute([':order_id' => (int)$dn['order_id']]);

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
        "INSERT INTO dispatch_cancellations (dispatch_id, order_id, team_code, reason, cancelled_by, cancelled_at)\n"
        . "VALUES (:did, :oid, :tc, :r, :by, NOW())"
    );
    $stmt->execute([
        ':did' => (int)$dispatchId,
        ':oid' => (int)$dn['order_id'],
        ':tc' => (string)($dn['team_code'] ?? ''),
        ':r' => ($reason !== '' ? $reason : null),
        ':by' => $dispatcherId,
    ]);

    $pdo->commit();

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SWDTM][cancel_dispatch] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd odwołania zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
