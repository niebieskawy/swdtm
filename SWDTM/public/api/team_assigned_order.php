<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$teamCode = $user && isset($user['username']) && is_string($user['username']) ? trim($user['username']) : '';
if ($teamCode === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$teamCode = 'T1';

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  id,\n"
        . "  order_seq, order_month, order_year,\n"
        . "  urgency, transport_type,\n"
        . "  from_city, from_street, from_number, from_postcode, from_flat,\n"
        . "  to_city, to_street, to_number, to_postcode, to_flat,\n"
        . "  phone,\n"
        . "  order_description,\n"
        . "  assigned_team_code,\n"
        . "  assigned_at,\n"
        . "  accepted_at,\n"
        . "  updated_at\n"
        . "FROM orders\n"
        . "WHERE status = 'assigned' AND assigned_team_code = :code\n"
        . "ORDER BY assigned_at DESC, id DESC\n"
        . "LIMIT 1"
    );
    $stmt->execute([':code' => $teamCode]);
    $o = $stmt->fetch();

    if (!$o) {
        echo json_encode(['ok' => true, 'order' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $out = [
        'id' => (int)$o['id'],
        'order_seq' => (int)$o['order_seq'],
        'order_month' => (int)$o['order_month'],
        'order_year' => (int)$o['order_year'],
        'urgency' => (string)($o['urgency'] ?? ''),
        'transport_type' => (string)($o['transport_type'] ?? ''),
        'from' => trim((string)($o['from_city'] ?? '') . ', ' . (string)($o['from_street'] ?? '') . ' ' . (string)($o['from_number'] ?? '')),
        'to' => trim((string)($o['to_city'] ?? '') . ', ' . (string)($o['to_street'] ?? '') . ' ' . (string)($o['to_number'] ?? '')),
        'phone' => (string)($o['phone'] ?? ''),
        'description' => (string)($o['order_description'] ?? ''),
        'assigned_at' => (string)($o['assigned_at'] ?? ''),
        'accepted_at' => $o['accepted_at'] !== null ? (string)$o['accepted_at'] : null,
        'updated_at' => (string)($o['updated_at'] ?? ''),
    ];

    echo json_encode(['ok' => true, 'order' => $out], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
