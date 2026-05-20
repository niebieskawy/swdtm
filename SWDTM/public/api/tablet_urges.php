<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $teamCode = current_team_code();
    $user = current_user();
    $username = $user && isset($user['username']) && is_string($user['username']) ? trim((string)$user['username']) : '';

    $aliases = [];
    foreach ([$teamCode, $username] as $c) {
        $c = is_string($c) ? trim($c) : '';
        if ($c !== '') $aliases[] = $c;
    }
    if ($teamCode !== '' && preg_match('/^T(\d{1,5})$/i', $teamCode, $m)) {
        $aliases[] = (string)((int)$m[1]);
    }
    if ($teamCode !== '' && preg_match('/^(\d{1,5})$/', $teamCode, $m)) {
        $aliases[] = 'T' . (string)((int)$m[1]);
    }
    if ($username !== '' && preg_match('/^RATOL(\d{1,5})$/i', $username, $m)) {
        $aliases[] = 'T' . (string)((int)$m[1]);
        $aliases[] = (string)((int)$m[1]);
    }
    $aliases = array_values(array_unique(array_filter($aliases, static fn($v) => is_string($v) && trim($v) !== '')));

    if (!$aliases) {
        echo json_encode(['ok' => true, 'urges' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ph = [];
    $params = [];
    foreach ($aliases as $i => $c) {
        $k = ':tc' . $i;
        $ph[] = $k;
        $params[$k] = $c;
    }

    $sql =
        "SELECT u.id, u.dispatch_id, u.order_id, u.team_code, u.reason, u.created_at\n"
        . "FROM dispatch_urges u\n"
        . "WHERE u.acked_at IS NULL\n"
        . "  AND u.team_code IN (" . implode(', ', $ph) . ")\n"
        . "ORDER BY u.created_at ASC\n"
        . "LIMIT 5";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'dispatch_id' => (int)$r['dispatch_id'],
            'order_id' => (int)$r['order_id'],
            'team_code' => (string)($r['team_code'] ?? ''),
            'reason' => (string)($r['reason'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
        ];
    }

    echo json_encode(['ok' => true, 'urges' => $out], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania ponagleń.'], JSON_UNESCAPED_UNICODE);
    exit;
}
