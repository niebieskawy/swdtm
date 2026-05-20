<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS team_events (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  order_id INT NULL,\n"
        . "  event_type VARCHAR(60) NOT NULL,\n"
        . "  message VARCHAR(500) NULL,\n"
        . "  created_by INT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  read_by INT NULL,\n"
        . "  read_at DATETIME NULL,\n"
        . "  KEY idx_team_events_unread (read_at),\n"
        . "  KEY idx_team_events_team (team_code),\n"
        . "  KEY idx_team_events_order (order_id),\n"
        . "  KEY idx_team_events_created (created_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $unreadStmt = $pdo->query(
        "SELECT COUNT(*)\n"
        . "FROM team_events\n"
        . "WHERE read_at IS NULL\n"
        . "  AND created_at >= (NOW() - INTERVAL 12 HOUR)"
    );
    $unread = (int)$unreadStmt->fetchColumn();

    $stmt = $pdo->query(
        "SELECT id, team_code, order_id, event_type, message, created_at, read_at\n"
        . "FROM team_events\n"
        . "WHERE created_at >= (NOW() - INTERVAL 12 HOUR)\n"
        . "ORDER BY created_at DESC, id DESC\n"
        . "LIMIT 30"
    );
    $rows = $stmt->fetchAll();

    $events = [];
    foreach ($rows as $r) {
        $events[] = [
            'id' => (int)$r['id'],
            'team_code' => (string)($r['team_code'] ?? ''),
            'order_id' => ($r['order_id'] !== null ? (int)$r['order_id'] : null),
            'event_type' => (string)($r['event_type'] ?? ''),
            'message' => (string)($r['message'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
            'read_at' => ($r['read_at'] !== null ? (string)$r['read_at'] : null),
        ];
    }

    echo json_encode([
        'ok' => true,
        'unread_count' => $unread,
        'events' => $events,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][dispatcher_team_events] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania powiadomień.'], JSON_UNESCAPED_UNICODE);
    exit;
}
