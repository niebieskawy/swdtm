<?php

declare(strict_types=1);

// Prosty endpoint do odbierania lokalizacji
// W przyszłości warto dodać autoryzację, np. przez token API

require __DIR__ . '/../../src/db.php';
require __DIR__ . '/../../src/geo.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$teamCode = $_POST['team_code'] ?? '';
$lat = $_POST['lat'] ?? '';
$lon = $_POST['lon'] ?? '';

if (empty($teamCode) || !is_string($teamCode) || trim($teamCode) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid team_code']);
    exit;
}

$lat = filter_var($lat, FILTER_VALIDATE_FLOAT);
$lon = filter_var($lon, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lon === false) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid lat/lon values']);
    exit;
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

    $hasMileage = false;
    try {
        $st = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_mileage_daily' LIMIT 1"
        );
        $st->execute();
        $hasMileage = ((int)($st->fetchColumn() ?: 0) === 1);
    } catch (Throwable $e) {
        $hasMileage = false;
    }

    // 1. Pobierz ostatnią lokalizację
    $stmt = $pdo->prepare('SELECT lat, lon FROM team_locations WHERE team_code = :team_code ORDER BY updated_at DESC LIMIT 1');
    $stmt->execute([':team_code' => trim($teamCode)]);
    $lastLocation = $stmt->fetch(PDO::FETCH_ASSOC);

    // 2. Zapisz nową lokalizację (użyj INSERT ... ON DUPLICATE KEY UPDATE)
    $stmt = $pdo->prepare(
        'INSERT INTO team_locations (team_code, lat, lon) VALUES (:team_code, :lat, :lon) '
        . 'ON DUPLICATE KEY UPDATE lat = VALUES(lat), lon = VALUES(lon), updated_at = NOW()'
    );
    $stmt->execute([
        ':team_code' => trim($teamCode),
        ':lat' => $lat,
        ':lon' => $lon,
    ]);

    // 3. Oblicz i zaktualizuj dystans, jeśli jest poprzednia lokalizacja
    if ($lastLocation && $hasMileage) {
        $distance = haversine_distance((float)$lastLocation['lat'], (float)$lastLocation['lon'], $lat, $lon);

        // Aktualizuj dzienny przebieg
        if ($distance > 0.005) { // Zapisuj tylko, jeśli ruch jest większy niż 5 metrów
            $stmt = $pdo->prepare(
                'INSERT INTO team_mileage_daily (team_code, date, distance_km) VALUES (:team_code, CURDATE(), :distance) ON DUPLICATE KEY UPDATE distance_km = distance_km + :distance'
            );
            $stmt->execute([
                ':team_code' => trim($teamCode),
                ':distance' => $distance,
            ]);
        }
    }


    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    // W środowisku produkcyjnym loguj błędy, a nie je wyświetlaj
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Internal Server Error', 'details' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
