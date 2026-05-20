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

$user = current_user();
$teamCode = current_team_code();
if ($teamCode === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$statusCode = $data['status_code'] ?? null;
$statusLabel = $data['status_label'] ?? null;
$leaderName = $data['leader_name'] ?? null;
$driverName = $data['driver_name'] ?? null;

$statusCode = is_string($statusCode) ? trim($statusCode) : '';
$statusLabel = is_string($statusLabel) ? trim($statusLabel) : '';
$leaderName = is_string($leaderName) ? trim($leaderName) : '';
$driverName = is_string($driverName) ? trim($driverName) : '';

if (mb_strlen($statusCode) > 60) {
    $statusCode = mb_substr($statusCode, 0, 60);
}
if (mb_strlen($statusLabel) > 120) {
    $statusLabel = mb_substr($statusLabel, 0, 120);
}
if (mb_strlen($leaderName) > 120) {
    $leaderName = mb_substr($leaderName, 0, 120);
}
if (mb_strlen($driverName) > 120) {
    $driverName = mb_substr($driverName, 0, 120);
}

try {
    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS team_presence (\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  status_code VARCHAR(60) NULL,\n"
        . "  status_label VARCHAR(120) NULL,\n"
        . "  leader_name VARCHAR(120) NULL,\n"
        . "  driver_name VARCHAR(120) NULL,\n"
        . "  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (team_code),\n"
        . "  KEY idx_team_presence_last_seen (last_seen_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "INSERT INTO team_presence (team_code, status_code, status_label, leader_name, driver_name, last_seen_at)\n"
        . "VALUES (:code, :sc, :sl, :ln, :dn, NOW())\n"
        . "ON DUPLICATE KEY UPDATE\n"
        . "  status_code = VALUES(status_code),\n"
        . "  status_label = VALUES(status_label),\n"
        . "  leader_name = VALUES(leader_name),\n"
        . "  driver_name = VALUES(driver_name),\n"
        . "  last_seen_at = NOW()"
    );

    $stmt->execute([
        ':code' => $teamCode,
        ':sc' => ($statusCode !== '' ? $statusCode : null),
        ':sl' => ($statusLabel !== '' ? $statusLabel : null),
        ':ln' => ($leaderName !== '' ? $leaderName : null),
        ':dn' => ($driverName !== '' ? $driverName : null),
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd zapisu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
