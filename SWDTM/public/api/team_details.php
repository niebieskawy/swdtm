<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$teamCode = $_GET['team_code'] ?? '';
$teamCode = is_string($teamCode) ? trim($teamCode) : '';

if ($teamCode === '' || mb_strlen($teamCode) > 20) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy zespół.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

try {
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
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  tl.team_code,\n"
        . "  tl.lat,\n"
        . "  tl.lon,\n"
        . "  tl.accuracy_m,\n"
        . "  tl.heading_deg,\n"
        . "  tl.speed_mps,\n"
        . "  COALESCE(tl.status_code, tp.status_code, '') AS status_code,\n"
        . "  COALESCE(tl.status_label, tp.status_label, '') AS status_label,\n"
        . "  COALESCE(tl.leader_name, tp.leader_name, '') AS leader_name,\n"
        . "  COALESCE(tl.driver_name, tp.driver_name, '') AS driver_name,\n"
        . "  COALESCE(tl.updated_at, tp.last_seen_at) AS updated_at\n"
        . "FROM team_locations tl\n"
        . "LEFT JOIN team_presence tp ON tp.team_code COLLATE utf8mb4_unicode_ci = tl.team_code COLLATE utf8mb4_unicode_ci\n"
        . "WHERE tl.team_code = :code\n"
        . "LIMIT 1"
    );
    $stmt->execute([':code' => $teamCode]);
    $loc = $stmt->fetch();

    if (!$loc) {
        $stmt = $pdo->prepare(
            "SELECT\n"
            . "  tp.team_code,\n"
            . "  NULL AS lat,\n"
            . "  NULL AS lon,\n"
            . "  NULL AS accuracy_m,\n"
            . "  NULL AS heading_deg,\n"
            . "  NULL AS speed_mps,\n"
            . "  COALESCE(tp.status_code, '') AS status_code,\n"
            . "  COALESCE(tp.status_label, '') AS status_label,\n"
            . "  COALESCE(tp.leader_name, '') AS leader_name,\n"
            . "  COALESCE(tp.driver_name, '') AS driver_name,\n"
            . "  tp.last_seen_at AS updated_at\n"
            . "FROM team_presence tp\n"
            . "WHERE tp.team_code = :code\n"
            . "LIMIT 1"
        );
        $stmt->execute([':code' => $teamCode]);
        $loc = $stmt->fetch();
    }

    $active = null;
    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  id, order_seq, order_month, order_year, status,\n"
        . "  from_city, from_street, from_number, from_flat,\n"
        . "  assigned_at, updated_at\n"
        . "FROM orders\n"
        . "WHERE status = 'assigned' AND assigned_team_code = :code\n"
        . "ORDER BY assigned_at DESC, id DESC\n"
        . "LIMIT 1"
    );
    $stmt->execute([':code' => $teamCode]);
    $row = $stmt->fetch();
    if ($row) {
        $fromNumber = trim((string)($row['from_number'] ?? ''));
        $fromFlat = trim((string)($row['from_flat'] ?? ''));
        $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
        $from = trim((string)($row['from_city'] ?? '') . ', ' . (string)($row['from_street'] ?? '') . ' ' . $fromNumLine);
        $active = [
            'id' => (int)$row['id'],
            'order_seq' => (int)$row['order_seq'],
            'order_month' => (int)$row['order_month'],
            'order_year' => (int)$row['order_year'],
            'status' => (string)($row['status'] ?? ''),
            'from' => $from,
        ];
    }

    $history = [];
    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  id, order_seq, order_month, order_year, status,\n"
        . "  from_city, from_street, from_number, from_flat,\n"
        . "  updated_at\n"
        . "FROM orders\n"
        . "WHERE assigned_team_code = :code\n"
        . "  AND status IN ('done','cancelled')\n"
        . "ORDER BY updated_at DESC, id DESC\n"
        . "LIMIT 8"
    );
    $stmt->execute([':code' => $teamCode]);
    $rows = $stmt->fetchAll();

    foreach ($rows as $r) {
        $fromNumber = trim((string)($r['from_number'] ?? ''));
        $fromFlat = trim((string)($r['from_flat'] ?? ''));
        $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
        $from = trim((string)($r['from_city'] ?? '') . ', ' . (string)($r['from_street'] ?? '') . ' ' . $fromNumLine);
        $history[] = [
            'id' => (int)$r['id'],
            'order_seq' => (int)$r['order_seq'],
            'order_month' => (int)$r['order_month'],
            'order_year' => (int)$r['order_year'],
            'status' => (string)($r['status'] ?? ''),
            'from' => $from,
        ];
    }

    $out = [
        'ok' => true,
        'team_code' => $teamCode,
        'lat' => $loc ? (float)$loc['lat'] : null,
        'lon' => $loc ? (float)$loc['lon'] : null,
        'accuracy_m' => $loc && $loc['accuracy_m'] !== null ? (float)$loc['accuracy_m'] : null,
        'heading_deg' => $loc && $loc['heading_deg'] !== null ? (float)$loc['heading_deg'] : null,
        'speed_mps' => $loc && $loc['speed_mps'] !== null ? (float)$loc['speed_mps'] : null,
        'status_code' => $loc ? (string)($loc['status_code'] ?? '') : '',
        'status_label' => $loc ? (string)($loc['status_label'] ?? '') : '',
        'leader_name' => $loc ? (string)($loc['leader_name'] ?? '') : '',
        'driver_name' => $loc ? (string)($loc['driver_name'] ?? '') : '',
        'updated_at' => $loc ? (string)($loc['updated_at'] ?? '') : '',
        'active_order' => $active,
        'history' => $history,
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania danych zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
