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
if ($sinceSeconds > 3600) {
    $sinceSeconds = 3600;
}

$cutoff = null;
try {
    $cutoff = (new DateTimeImmutable())->modify('-' . $sinceSeconds . ' seconds')->format('Y-m-d H:i:s');
} catch (Throwable $e) {
    $cutoff = null;
}

try {
    if (!is_string($cutoff) || $cutoff === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Błąd obliczania czasu.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT\n"
            . "  tp.team_code AS team_code,\n"
            . "  COALESCE(t.type, '') AS team_type,\n"
            . "  COALESCE(t.name, '') AS team_name,\n"
            . "  COALESCE(tp.status_code, '') AS status_code,\n"
            . "  COALESCE(tp.status_label, '') AS status_label,\n"
            . "  COALESCE(tp.leader_name, '') AS leader_name,\n"
            . "  COALESCE(tp.driver_name, '') AS driver_name,\n"
            . "  tp.last_seen_at\n"
            . "FROM team_presence tp\n"
            . "LEFT JOIN teams t ON t.code COLLATE utf8mb4_unicode_ci = tp.team_code COLLATE utf8mb4_unicode_ci\n"
            . "WHERE tp.last_seen_at >= :cutoff\n"
            . "ORDER BY tp.team_code ASC"
        );
        $stmt->execute([':cutoff' => $cutoff]);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $code = $e->getCode();
        error_log('[SWDTM][active_teams] query failed: ' . $msg);

        if ($e instanceof PDOException) {
            $info = $e->errorInfo;
            if (is_array($info) && isset($info[0]) && $info[0] === '42S02') {
                echo json_encode(['ok' => true, 'teams' => []], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Błąd pobierania zespołów.',
            'debug' => $debug ? (string)$msg : null,
            'debug_code' => $debug ? (string)$code : null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $out = [];
    foreach ($rows as $r) {
        $label = (string)($r['status_label'] ?? '');
        if ($label === '') {
            $label = 'Aktywny';
        }
        $out[] = [
            'team_code' => (string)$r['team_code'],
            'team_type' => (string)$r['team_type'],
            'team_name' => (string)($r['team_name'] ?? ''),
            'status_code' => (string)($r['status_code'] ?? ''),
            'status_label' => $label,
            'leader_name' => (string)($r['leader_name'] ?? ''),
            'driver_name' => (string)($r['driver_name'] ?? ''),
            'updated_at' => (string)($r['last_seen_at'] ?? ''),
        ];
    }

    echo json_encode(['ok' => true, 'teams' => $out], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][active_teams] fatal: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Błąd pobierania zespołów.',
        'debug' => $debug ? (string)$e->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
