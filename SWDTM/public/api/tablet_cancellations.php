<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$teamCode = current_team_code();

$username = $user && isset($user['username']) && is_string($user['username']) ? trim((string)$user['username']) : '';

$aliases = [];
foreach ([$teamCode, $username] as $c) {
    $c = is_string($c) ? trim($c) : '';
    if ($c !== '') {
        $aliases[] = $c;
    }
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
    echo json_encode(['ok' => true, 'cancellations' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

try {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS dispatch_cancellations (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  dispatch_id INT NOT NULL,\n"
        . "  order_id INT NOT NULL,\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  reason VARCHAR(500) NULL,\n"
        . "  cancelled_by INT NULL,\n"
        . "  cancelled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  acked_by INT NULL,\n"
        . "  acked_at DATETIME NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  KEY idx_dispatch_cancellations_team (team_code),\n"
        . "  KEY idx_dispatch_cancellations_ack (acked_at),\n"
        . "  KEY idx_dispatch_cancellations_dispatch (dispatch_id),\n"
        . "  KEY idx_dispatch_cancellations_order (order_id)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $placeholders = [];
    $params = [];
    foreach ($aliases as $i => $a) {
        $ph = ':tc' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $a;
    }
    $in = implode(', ', $placeholders);

    $stmt = $pdo->prepare(
        "SELECT dc.id, dc.dispatch_id, dc.order_id, dc.team_code, dc.reason, dc.cancelled_at,\n"
        . "  o.order_seq, o.order_month, o.order_year\n"
        . "FROM dispatch_cancellations dc\n"
        . "LEFT JOIN orders o ON o.id = dc.order_id\n"
        . "WHERE dc.acked_at IS NULL\n"
        . "  AND dc.team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
        . "ORDER BY dc.cancelled_at DESC, dc.id DESC\n"
        . "LIMIT 5"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $r) {
        $num = (string)($r['order_seq'] ?? '');
        $mm = isset($r['order_month']) ? str_pad((string)(int)$r['order_month'], 2, '0', STR_PAD_LEFT) : '';
        $yy = (string)($r['order_year'] ?? '');
        $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)($r['order_id'] ?? ''));

        $out[] = [
            'id' => (int)($r['id'] ?? 0),
            'dispatch_id' => (int)($r['dispatch_id'] ?? 0),
            'order_id' => (int)($r['order_id'] ?? 0),
            'order_number' => $orderNumber,
            'reason' => (string)($r['reason'] ?? ''),
            'cancelled_at' => (string)($r['cancelled_at'] ?? ''),
        ];
    }

    echo json_encode(['ok' => true, 'cancellations' => $out], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][tablet_cancellations] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania odwołań.'], JSON_UNESCAPED_UNICODE);
    exit;
}
