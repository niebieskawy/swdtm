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

    $stmt = $pdo->prepare(
        "SELECT dn.id, dn.status, dn.order_id, dn.team_code, o.sirens\n"
        . "FROM dispatch_notifications dn\n"
        . "JOIN orders o ON o.id = dn.order_id\n"
        . "WHERE dn.id = :id\n"
        . "LIMIT 1"
    );
    $stmt->execute([':id' => (int)$dispatchId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Zadysponowanie nie istnieje.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $st = (string)($row['status'] ?? '');
    if (!in_array($st, ['pending', 'accepted'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Można ponaglić tylko zadysponowanie oczekujące lub przyjęte.' ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ((int)($row['sirens'] ?? 0) === 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nie można ponaglić zespołu wysłanego na sygnale.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE dispatch_notifications\n"
        . "SET notification_sent_at = NOW(), notification_seen_at = NULL, updated_at = NOW()\n"
        . "WHERE id = :id"
    );
    $stmt->execute([':id' => (int)$dispatchId]);

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS dispatch_urges (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  dispatch_id INT NOT NULL,\n"
        . "  order_id INT NOT NULL,\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  reason VARCHAR(500) NULL,\n"
        . "  urged_by INT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  acked_by INT NULL,\n"
        . "  acked_at DATETIME NULL,\n"
        . "  KEY idx_dispatch_urges_team (team_code),\n"
        . "  KEY idx_dispatch_urges_ack (acked_at),\n"
        . "  KEY idx_dispatch_urges_dispatch (dispatch_id),\n"
        . "  KEY idx_dispatch_urges_order (order_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "INSERT INTO dispatch_urges (dispatch_id, order_id, team_code, reason, urged_by, created_at)\n"
        . "VALUES (:did, :oid, :tc, :r, :by, NOW())"
    );
    $stmt->execute([
        ':did' => (int)$dispatchId,
        ':oid' => (int)($row['order_id'] ?? 0),
        ':tc' => (string)($row['team_code'] ?? ''),
        ':r' => ($reason !== '' ? $reason : null),
        ':by' => $dispatcherId,
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][urge_dispatch] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd ponaglenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
