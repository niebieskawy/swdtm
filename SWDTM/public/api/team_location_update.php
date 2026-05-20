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

$user = current_user();
$teamCode = current_team_code();
if ($teamCode === '') {
    error_log('[SWDTM][team_location_update] missing teamCode in session user');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($data)) {
    error_log('[SWDTM][team_location_update][' . $teamCode . '] invalid JSON body');
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
    error_log('[SWDTM][team_location_update][' . $teamCode . '] csrf invalid');
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$lat = $data['lat'] ?? null;
$lon = $data['lon'] ?? null;
$accuracy = $data['accuracy'] ?? null;
$heading = $data['heading'] ?? null;
$speed = $data['speed'] ?? null;

if (!is_numeric($lat) || !is_numeric($lon)) {
    error_log('[SWDTM][team_location_update][' . $teamCode . '] missing coords');
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Brak współrzędnych.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$latF = (float)$lat;
$lonF = (float)$lon;
if ($latF < -90 || $latF > 90 || $lonF < -180 || $lonF > 180) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe współrzędne.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$accF = is_numeric($accuracy) ? (float)$accuracy : null;
$headF = is_numeric($heading) ? (float)$heading : null;
$spdF = is_numeric($speed) ? (float)$speed : null;

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
        "CREATE TABLE IF NOT EXISTS team_locations (\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  lat DECIMAL(10,7) NOT NULL,\n"
        . "  lon DECIMAL(10,7) NOT NULL,\n"
        . "  accuracy_m DECIMAL(8,2) NULL,\n"
        . "  heading_deg DECIMAL(6,2) NULL,\n"
        . "  speed_mps DECIMAL(6,2) NULL,\n"
        . "  status_code VARCHAR(60) NULL,\n"
        . "  status_label VARCHAR(120) NULL,\n"
        . "  leader_name VARCHAR(120) NULL,\n"
        . "  driver_name VARCHAR(120) NULL,\n"
        . "  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (team_code),\n"
        . "  KEY idx_team_locations_updated (updated_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $missingCols = [];
    $stmt = $pdo->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS\n"
        . "WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_locations'"
    );
    $stmt->execute();
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $existing = is_array($existing) ? array_map('strtolower', array_map('strval', $existing)) : [];

    $want = [
        'status_code' => "ALTER TABLE team_locations ADD COLUMN status_code VARCHAR(60) NULL",
        'status_label' => "ALTER TABLE team_locations ADD COLUMN status_label VARCHAR(120) NULL",
        'leader_name' => "ALTER TABLE team_locations ADD COLUMN leader_name VARCHAR(120) NULL",
        'driver_name' => "ALTER TABLE team_locations ADD COLUMN driver_name VARCHAR(120) NULL",
    ];

    foreach ($want as $col => $sql) {
        if (!in_array($col, $existing, true)) {
            $missingCols[] = $sql;
        }
    }

    foreach ($missingCols as $sql) {
        $pdo->exec($sql);
    }

    $stmt = $pdo->prepare(
        "INSERT INTO team_locations (team_code, lat, lon, accuracy_m, heading_deg, speed_mps, status_code, status_label, leader_name, driver_name)\n"
        . "VALUES (:code, :lat, :lon, :acc, :head, :spd, :sc, :sl, :ln, :dn)\n"
        . "ON DUPLICATE KEY UPDATE\n"
        . "  lat = VALUES(lat),\n"
        . "  lon = VALUES(lon),\n"
        . "  accuracy_m = VALUES(accuracy_m),\n"
        . "  heading_deg = VALUES(heading_deg),\n"
        . "  speed_mps = VALUES(speed_mps),\n"
        . "  status_code = VALUES(status_code),\n"
        . "  status_label = VALUES(status_label),\n"
        . "  leader_name = VALUES(leader_name),\n"
        . "  driver_name = VALUES(driver_name),\n"
        . "  updated_at = NOW()"
    );

    $stmt->execute([
        ':code' => $teamCode,
        ':lat' => $latF,
        ':lon' => $lonF,
        ':acc' => $accF,
        ':head' => $headF,
        ':spd' => $spdF,
        ':sc' => ($statusCode !== '' ? $statusCode : null),
        ':sl' => ($statusLabel !== '' ? $statusLabel : null),
        ':ln' => ($leaderName !== '' ? $leaderName : null),
        ':dn' => ($driverName !== '' ? $driverName : null),
    ]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][team_location_update][' . $teamCode . '] db error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd zapisu lokalizacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}
