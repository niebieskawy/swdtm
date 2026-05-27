<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$limit = 30;
$lim = $_GET['limit'] ?? '';
if (is_string($lim) && $lim !== '' && ctype_digit($lim)) {
    $limit = (int)$lim;
}
if ($limit < 1) $limit = 1;
if ($limit > 80) $limit = 80;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  id, order_seq, order_month, order_year,\n"
        . "  urgency, transport_type,\n"
        . "  from_city, from_street, from_number,\n"
        . "  assigned_team_code, assigned_team_type,\n"
        . "  assigned_at, accepted_at\n"
        . "FROM orders\n"
        . "WHERE status = 'assigned'\n"
        . "  AND DATE(assigned_at) = CURDATE()\n"
        . "ORDER BY assigned_at DESC, id DESC\n"
        . "LIMIT " . (int)$limit
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $from = trim((string)($r['from_city'] ?? '') . ', ' . (string)($r['from_street'] ?? '') . ' ' . (string)($r['from_number'] ?? ''));
        $out[] = [
            'id' => (int)$r['id'],
            'order_seq' => (int)$r['order_seq'],
            'order_month' => (int)$r['order_month'],
            'order_year' => (int)$r['order_year'],
            'urgency' => (string)($r['urgency'] ?? ''),
            'transport_type' => (string)($r['transport_type'] ?? ''),
            'from' => $from,
            'assigned_team_code' => (string)($r['assigned_team_code'] ?? ''),
            'assigned_team_type' => (string)($r['assigned_team_type'] ?? ''),
            'assigned_at' => (string)($r['assigned_at'] ?? ''),
            'accepted_at' => $r['accepted_at'] !== null ? (string)$r['accepted_at'] : null,
        ];
    }

    echo json_encode(['ok' => true, 'orders' => $out], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania zleceń.'], JSON_UNESCAPED_UNICODE);
    exit;
}
