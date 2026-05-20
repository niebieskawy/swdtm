<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$limit = 80;
$lim = $_GET['limit'] ?? '';
if (is_string($lim) && $lim !== '' && ctype_digit($lim)) {
    $limit = (int)$lim;
}
if ($limit < 1) $limit = 1;
if ($limit > 200) $limit = 200;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  id, order_seq, order_month, order_year,\n"
        . "  status,\n"
        . "  urgency, transport_type,\n"
        . "  from_city, from_street, from_number, from_flat,\n"
        . "  from_lat, from_lon,\n"
        . "  planned_at, created_at,\n"
        . "  assigned_team_code,\n"
        . "  COALESCE(tp.status_label, '') AS team_status_label\n"
        . "FROM orders o\n"
        . "LEFT JOIN team_presence tp ON tp.team_code COLLATE utf8mb4_unicode_ci = o.assigned_team_code COLLATE utf8mb4_unicode_ci\n"
        . "WHERE o.status IN ('new', 'assigned')\n"
        . "  AND DATE(COALESCE(planned_at, created_at)) = CURDATE()\n"
        . "  AND (\n"
        . "    order_type = 'nagłe'\n"
        . "    OR (order_type = 'planowe' AND planned_at IS NOT NULL AND planned_at <= DATE_ADD(NOW(), INTERVAL 2 DAY))\n"
        . "  )\n"
        . "ORDER BY\n"
        . "  CASE urgency\n"
        . "    WHEN 'natychmiast' THEN 0\n"
        . "    WHEN 'pilne' THEN 1\n"
        . "    ELSE 2\n"
        . "  END ASC,\n"
        . "  COALESCE(planned_at, created_at) ASC\n"
        . "LIMIT " . (int)$limit
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $o) {
        $teamStatus = trim((string)($o['team_status_label'] ?? ''));
        if ($teamStatus !== '' && mb_strtolower($teamStatus) === 'przejazd z pacjentem') {
            continue;
        }

        $lat = $o['from_lat'] ?? null;
        $lon = $o['from_lon'] ?? null;
        if ($lat === null || $lon === null || $lat === '' || $lon === '') {
            continue;
        }

        $num = (string)($o['order_seq'] ?? '');
        $mm = isset($o['order_month']) ? str_pad((string)(int)$o['order_month'], 2, '0', STR_PAD_LEFT) : '';
        $yy = (string)($o['order_year'] ?? '');
        $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$o['id']);

        $fromNumber = trim((string)($o['from_number'] ?? ''));
        $fromFlat = trim((string)($o['from_flat'] ?? ''));
        $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
        $from = trim((string)($o['from_city'] ?? '') . ', ' . (string)($o['from_street'] ?? '') . ' ' . $fromNumLine);

        $out[] = [
            'id' => (int)$o['id'],
            'number' => $orderNumber,
            'status' => (string)($o['status'] ?? ''),
            'urgency' => (string)($o['urgency'] ?? ''),
            'transport_type' => (string)($o['transport_type'] ?? ''),
            'from' => $from,
            'lat' => (float)$lat,
            'lon' => (float)$lon,
            'assigned_team_code' => (string)($o['assigned_team_code'] ?? ''),
            'team_status_label' => $teamStatus,
        ];
    }

    echo json_encode(['ok' => true, 'orders' => $out], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania zleceń do mapy.'], JSON_UNESCAPED_UNICODE);
    exit;
}
