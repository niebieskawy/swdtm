<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$pdo = db();

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

$sinceSeconds = 180;
$since = $_GET['since'] ?? '';
if (is_string($since) && $since !== '' && ctype_digit($since)) {
    $sinceSeconds = (int)$since;
}
if ($sinceSeconds < 10) {
    $sinceSeconds = 10;
}
if ($sinceSeconds > 86400) {
    $sinceSeconds = 86400;
}

$cutoff = null;
try {
    $cutoff = (new DateTimeImmutable())->modify('-' . $sinceSeconds . ' seconds')->format('Y-m-d H:i:s');
} catch (Throwable $e) {
    $cutoff = null;
}

if (!is_string($cutoff) || $cutoff === '') {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd obliczania czasu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$mileageJoin = '';
$mileageSelect = 'NULL AS mileage_km_today';
try {
    $st = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'team_mileage_daily' LIMIT 1"
    );
    $st->execute();
    $has = ((int)($st->fetchColumn() ?: 0) === 1);
    if ($has) {
        $mileageSelect = 'tmd.distance_km AS mileage_km_today';
        $mileageJoin = "LEFT JOIN team_mileage_daily tmd ON tmd.team_code COLLATE utf8mb4_unicode_ci = tl.team_code COLLATE utf8mb4_unicode_ci AND tmd.date = CURDATE()";
    }
} catch (Throwable $e) {
    $mileageJoin = '';
    $mileageSelect = 'NULL AS mileage_km_today';
}

try {
    $sql = "SELECT\n"
        . "  tl.team_code AS team_code,\n"
        . "  COALESCE(t.type, '') AS team_type,\n"
        . "  COALESCE(t.name, '') AS team_name,\n"
        . "  tl.lat AS lat,\n"
        . "  tl.lon AS lon,\n"
        . "  tl.accuracy_m AS accuracy_m,\n"
        . "  tl.heading_deg AS heading_deg,\n"
        . "  tl.speed_mps AS speed_mps,\n"
        . "  COALESCE(tp.status_code, tl.status_code, '') AS status_code,\n"
        . "  COALESCE(tp.status_label, tl.status_label, '') AS status_label,\n"
        . "  COALESCE(tp.leader_name, tl.leader_name, '') AS leader_name,\n"
        . "  COALESCE(tp.driver_name, tl.driver_name, '') AS driver_name,\n"
        . "  tl.updated_at AS updated_at,\n"
        . "  " . $mileageSelect . "\n"
        . "FROM team_locations tl\n"
        . "LEFT JOIN teams t ON t.code COLLATE utf8mb4_unicode_ci = tl.team_code COLLATE utf8mb4_unicode_ci\n"
        . "LEFT JOIN team_presence tp ON tp.team_code COLLATE utf8mb4_unicode_ci = tl.team_code COLLATE utf8mb4_unicode_ci\n"
        . ($mileageJoin !== '' ? ($mileageJoin . "\n") : '')
        . "WHERE tl.updated_at >= :cutoff\n"
        . "ORDER BY tl.team_code ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cutoff' => $cutoff]);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'team_code' => (string)($r['team_code'] ?? ''),
            'team_type' => (string)($r['team_type'] ?? ''),
            'team_name' => (string)($r['team_name'] ?? ''),
            'lat' => isset($r['lat']) ? (float)$r['lat'] : null,
            'lon' => isset($r['lon']) ? (float)$r['lon'] : null,
            'accuracy_m' => isset($r['accuracy_m']) ? (float)$r['accuracy_m'] : null,
            'heading_deg' => isset($r['heading_deg']) ? (float)$r['heading_deg'] : null,
            'speed_mps' => isset($r['speed_mps']) ? (float)$r['speed_mps'] : null,
            'status_code' => (string)($r['status_code'] ?? ''),
            'status_label' => (string)($r['status_label'] ?? ''),
            'leader_name' => (string)($r['leader_name'] ?? ''),
            'driver_name' => (string)($r['driver_name'] ?? ''),
            'updated_at' => (string)($r['updated_at'] ?? ''),
            'mileage_km_today' => isset($r['mileage_km_today']) ? (float)$r['mileage_km_today'] : null,
        ];
    }

    echo json_encode(['ok' => true, 'cutoff' => $cutoff, 'teams' => $out], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][team_locations] fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Błąd pobierania lokalizacji zespołów.',
        'debug' => $debug ? (string)$e->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
