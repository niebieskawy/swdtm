<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$teamCode = current_team_code();

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

$username = $user && isset($user['username']) && is_string($user['username']) ? trim((string)$user['username']) : '';

$teamCodeAliases = [];
foreach ([$teamCode, $username] as $c) {
    $c = is_string($c) ? trim($c) : '';
    if ($c !== '') {
        $teamCodeAliases[] = $c;
    }
}
if ($teamCode !== '' && preg_match('/^T(\d{1,5})$/i', $teamCode, $m)) {
    $teamCodeAliases[] = (string)((int)$m[1]);
}
if ($teamCode !== '' && preg_match('/^(\d{1,5})$/', $teamCode, $m)) {
    $teamCodeAliases[] = 'T' . (string)((int)$m[1]);
}
if ($username !== '' && preg_match('/^RATOL(\d{1,5})$/i', $username, $m)) {
    $teamCodeAliases[] = 'T' . (string)((int)$m[1]);
    $teamCodeAliases[] = (string)((int)$m[1]);
}
$teamCodeAliases = array_values(array_unique(array_filter($teamCodeAliases, static fn($v) => is_string($v) && trim($v) !== '')));

if ($teamCode === '' && !$teamCodeAliases) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespou.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

try {
    // Pobierz oczekujce powiadomienia dla tego zespou
    if (!$teamCodeAliases) {
        $resp = ['ok' => true, 'notifications' => []];
        if ($debug) {
            $resp['debug'] = [
                'team_code' => $teamCode,
                'username' => $username,
                'aliases' => $teamCodeAliases,
                'note' => 'Brak aliasów team_code – zapytanie do bazy pominięte.',
            ];
        }
        echo json_encode($resp, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $placeholders = [];
    $params = [];
    foreach ($teamCodeAliases as $i => $codeAlias) {
        $ph = ':tc' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $codeAlias;
    }

    $in = implode(', ', $placeholders);

    $stmt = $pdo->prepare(
        "SELECT 
            dn.id,
            dn.order_id,
            dn.status,
            dn.created_at,
            dn.notification_sent_at,
            dn.notification_seen_at,
            o.order_seq,
            o.order_month,
            o.order_year,
            o.urgency,
            o.sirens,
            o.transport_type,
            o.from_city,
            o.from_street,
            o.from_number,
            o.phone,
            d.full_name as dispatcher_name
        FROM dispatch_notifications dn
        JOIN orders o ON o.id = dn.order_id
        LEFT JOIN users d ON d.id = dn.dispatcher_id
        WHERE dn.team_code COLLATE utf8mb4_unicode_ci IN ({$in})
          AND dn.status = 'pending'
          AND (dn.notification_seen_at IS NULL OR dn.notification_seen_at < dn.created_at)
        ORDER BY dn.created_at DESC
        LIMIT 10"
    );

    $stmt->execute($params);
    $notifications = $stmt->fetchAll();

    $out = [];
    foreach ($notifications as $n) {
        $num = (string)($n['order_seq'] ?? '');
        $mm = isset($n['order_month']) ? str_pad((string)(int)$n['order_month'], 2, '0', STR_PAD_LEFT) : '';
        $yy = (string)($n['order_year'] ?? '');
        $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$n['order_id']);

        $fromNumber = trim((string)($n['from_number'] ?? ''));
        $fromFlat = trim((string)($n['from_flat'] ?? ''));
        $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
        $from = trim((string)($n['from_city'] ?? '') . ', ' . (string)($n['from_street'] ?? '') . ' ' . $fromNumLine);
        
        $transportLabel = match ((string)$n['transport_type']) {
            'hospital' => 'Przekazanie do szpitala',
            'poradnia' => 'Wizyta w poradni',
            'miedzyszpitalna' => 'Konsultacja midzyszpitalna',
            'dom' => 'Odwóz do domu',
            default => (string)$n['transport_type'],
        };

        $out[] = [
            'id' => (int)$n['id'],
            'order_id' => (int)$n['order_id'],
            'order_number' => $orderNumber,
            'urgency' => (string)($n['urgency'] ?? ''),
            'sirens' => (int)($n['sirens'] ?? 0),
            'transport_type' => (string)($n['transport_type'] ?? ''),
            'transport_label' => $transportLabel,
            'from' => $from,
            'phone' => (string)($n['phone'] ?? ''),
            'dispatcher_name' => (string)($n['dispatcher_name'] ?? ''),
            'created_at' => (string)($n['created_at'] ?? ''),
            'notification_sent_at' => (string)($n['notification_sent_at'] ?? ''),
        ];
    }

    $resp = ['ok' => true, 'notifications' => $out];
    if ($debug) {
        $counts = [];
        $recent = [];
        try {
            $stmt2 = $pdo->prepare(
                "SELECT status, COUNT(*) AS c\n"
                . "FROM dispatch_notifications\n"
                . "WHERE team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
                . "GROUP BY status"
            );
            $stmt2->execute($params);
            $rows2 = $stmt2->fetchAll();
            if (is_array($rows2)) {
                foreach ($rows2 as $r2) {
                    $k = (string)($r2['status'] ?? '');
                    if ($k === '') $k = '—';
                    $counts[$k] = (int)($r2['c'] ?? 0);
                }
            }
        } catch (Throwable $e) {
            $counts = ['_error' => (string)$e->getMessage()];
        }

        try {
            $stmt3 = $pdo->prepare(
                "SELECT id, order_id, team_code, status, created_at, notification_seen_at\n"
                . "FROM dispatch_notifications\n"
                . "WHERE team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
                . "ORDER BY created_at DESC, id DESC\n"
                . "LIMIT 10"
            );
            $stmt3->execute($params);
            $recent = $stmt3->fetchAll();
        } catch (Throwable $e) {
            $recent = [['error' => (string)$e->getMessage()]];
        }

        $resp['debug'] = [
            'team_code' => $teamCode,
            'username' => $username,
            'aliases' => $teamCodeAliases,
            'rows_count' => is_array($notifications) ? count($notifications) : 0,
            'counts_by_status' => $counts,
            'recent_rows' => $recent,
        ];
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][tablet_notifications] error: ' . $e->getMessage());
    http_response_code(500);
    $resp = ['ok' => false, 'error' => 'Bd pobierania powiadomie.'];
    if ($debug) {
        $resp['debug'] = [
            'message' => (string)$e->getMessage(),
            'team_code' => $teamCode,
            'username' => $username,
            'aliases' => $teamCodeAliases,
        ];
    }
    echo json_encode($resp, JSON_UNESCAPED_UNICODE);
    exit;
}
