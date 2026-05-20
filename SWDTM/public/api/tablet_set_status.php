<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metoda nieobsługiwana.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe dane.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$csrfBody = $data['csrf'] ?? null;
$csrf = '';
if (is_string($csrfHeader) && $csrfHeader !== '') {
    $csrf = $csrfHeader;
} elseif (is_string($csrfBody) && $csrfBody !== '') {
    $csrf = $csrfBody;
}

if (!csrf_validate($csrf !== '' ? $csrf : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy token.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$statusCode = $data['status_code'] ?? null;
$statusCode = is_string($statusCode) ? trim($statusCode) : '';

$allowed = [
    'ready_base' => 'Gotowy w bazie',
    'not_ready' => 'Niegotowy',
    'return_base' => 'Powrót do bazy',
    'order_start' => 'Wyjazd do zlecenia',
    'order_patient' => 'U pacjenta',
    'order_transport' => 'Przejazd z pacjentem',
    'order_realization' => 'Realizacja (z pacjentem)',
    'order_handover' => 'Przekazanie pacjenta',
    'order_return' => 'Powrót (z pacjentem)',
    'disinfection' => 'Dezynfekcja',
    'washing' => 'Mycie',
    'refuel' => 'Tankowanie',
    'failure' => 'Awaria',
    'restore_ready' => 'Przywracanie gotowości',
];

if ($statusCode === '' || !isset($allowed[$statusCode])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy status.'], JSON_UNESCAPED_UNICODE);
    exit;
}

start_session();

$statusReady = ['ready_base', 'not_ready', 'restore_ready', 'return_base'];
$statusOrder = ['order_start', 'order_patient', 'order_transport', 'order_realization', 'order_handover', 'order_return'];
$statusOther = ['disinfection', 'washing', 'refuel', 'failure'];

$orderIndex = [];
foreach ($statusOrder as $i => $c) {
    $orderIndex[(string)$c] = (int)$i;
}

$user = current_user();
$teamCode = current_team_code();

$hasActiveOrder = false;
if ($teamCode !== '') {
    try {
        $pdo = db();
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

        if ($teamCodeAliases) {
            $placeholders = [];
            $params = [];
            foreach ($teamCodeAliases as $i => $codeAlias) {
                $ph = ':tc' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $codeAlias;
            }

            $in = implode(', ', $placeholders);
            $stmt = $pdo->prepare(
                "SELECT id FROM orders\n"
                . "WHERE status = 'assigned'\n"
                . "  AND assigned_team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
                . "ORDER BY assigned_at DESC, id DESC\n"
                . "LIMIT 1"
            );
            $stmt->execute($params);
            $row = $stmt->fetch();
            $hasActiveOrder = $row ? true : false;
        }
    } catch (Throwable $e) {
        $hasActiveOrder = false;
    }
}

$prev = '';
if (isset($_SESSION['tablet_status_current']) && is_string($_SESSION['tablet_status_current'])) {
    $prev = trim($_SESSION['tablet_status_current']);
}

$prevOrder = '';
if (isset($_SESSION['tablet_status_prev_order']) && is_string($_SESSION['tablet_status_prev_order'])) {
    $prevOrder = trim($_SESSION['tablet_status_prev_order']);
}

if (!$hasActiveOrder && in_array($statusCode, $statusOrder, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak aktywnego zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($hasActiveOrder && in_array($statusCode, $statusReady, true)) {
    if ($statusCode === 'restore_ready') {
        $canClose = (
            $prev === 'order_handover'
            || $prev === 'order_realization'
            || ($prev === 'order_patient' && $prevOrder === 'order_return')
        );
        if (!$canClose) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nie można użyć statusu Przywracanie gotowości na tym etapie obsługi zlecenia.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nie można użyć statusów gotowości podczas realizacji zlecenia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($hasActiveOrder && in_array($statusCode, $statusOther, true) && $statusCode !== 'failure') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Podczas realizacji zlecenia w sekcji Inne dostępna jest tylko Awaria.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    $hasActiveOrder &&
    in_array($prev, $statusOrder, true) &&
    in_array($statusCode, $statusOrder, true) &&
    isset($orderIndex[$prev], $orderIndex[$statusCode]) &&
    $orderIndex[$statusCode] < $orderIndex[$prev]
) {
    if ($prev === 'order_return' && $statusCode === 'order_patient') {
    } else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nie można cofnąć statusu obsługi zlecenia. Użyj: Przywróć poprzedni status.'], JSON_UNESCAPED_UNICODE);
    exit;
    }
}

if ($prev !== '' && $prev !== $statusCode) {
    $_SESSION['tablet_status_prev'] = $prev;
}

if (
    $prev !== '' &&
    $prev !== $statusCode &&
    in_array($prev, $statusOrder, true) &&
    in_array($statusCode, $statusOrder, true)
) {
    $_SESSION['tablet_status_prev_order'] = $prev;
}
$_SESSION['tablet_status_current'] = $statusCode;

// Kolejkowanie: po zakończeniu zlecenia (Przywracanie gotowości) i przejściu do statusów gotowości
// aktywuj najstarsze zlecenie z kolejki zespołu.
if (
    !$hasActiveOrder
    && ($prev === 'restore_ready')
    && ($statusCode === 'return_base' || $statusCode === 'ready_base')
    && $teamCode !== ''
) {
    try {
        $pdo = db();
        $pdo->beginTransaction();

        $username = $user && isset($user['username']) && is_string($user['username']) ? trim((string)$user['username']) : '';

        $teamCodeAliases2 = [];
        foreach ([$teamCode, $username] as $c) {
            $c = is_string($c) ? trim($c) : '';
            if ($c !== '') {
                $teamCodeAliases2[] = $c;
            }
        }
        if ($teamCode !== '' && preg_match('/^T(\d{1,5})$/i', $teamCode, $m)) {
            $teamCodeAliases2[] = (string)((int)$m[1]);
        }
        if ($teamCode !== '' && preg_match('/^(\d{1,5})$/', $teamCode, $m)) {
            $teamCodeAliases2[] = 'T' . (string)((int)$m[1]);
        }
        if ($username !== '' && preg_match('/^RATOL(\d{1,5})$/i', $username, $m)) {
            $teamCodeAliases2[] = 'T' . (string)((int)$m[1]);
            $teamCodeAliases2[] = (string)((int)$m[1]);
        }
        $teamCodeAliases2 = array_values(array_unique(array_filter($teamCodeAliases2, static fn($v) => is_string($v) && trim($v) !== '')));

        if ($teamCodeAliases2) {
            $placeholders = [];
            $params = [];
            foreach ($teamCodeAliases2 as $i => $codeAlias) {
                $ph = ':tc' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $codeAlias;
            }
            $in = implode(', ', $placeholders);

            $stmt = $pdo->prepare(
                "SELECT dn.id, dn.order_id, dn.team_code, dn.dispatcher_id\n"
                . "FROM dispatch_notifications dn\n"
                . "JOIN orders o ON o.id = dn.order_id\n"
                . "WHERE dn.status IN ('queued', '')\n"
                . "  AND dn.team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
                . "  AND o.status = 'new'\n"
                . "ORDER BY dn.created_at ASC, dn.id ASC\n"
                . "LIMIT 1"
            );
            $stmt->execute($params);
            $dn = $stmt->fetch();

            if ($dn) {
                $orderId2 = (int)($dn['order_id'] ?? 0);
                $dispatchId2 = (int)($dn['id'] ?? 0);
                $teamCodeDb = (string)($dn['team_code'] ?? '');
                $dispatcherId2 = (int)($dn['dispatcher_id'] ?? 0);

                if ($orderId2 > 0 && $dispatchId2 > 0 && $teamCodeDb !== '' && $dispatcherId2 > 0) {
                    $stmt = $pdo->prepare(
                        "SELECT code, type FROM teams WHERE code COLLATE utf8mb4_unicode_ci = :c LIMIT 1"
                    );
                    $stmt->execute([':c' => $teamCodeDb]);
                    $teamRow = $stmt->fetch();

                    $teamTypeDb = (string)(($teamRow && isset($teamRow['type'])) ? $teamRow['type'] : '');

                    $stmt = $pdo->prepare(
                        "UPDATE dispatch_notifications\n"
                        . "SET status = 'pending', notification_sent_at = NOW()\n"
                        . "WHERE id = :id AND status IN ('queued', '')"
                    );
                    $stmt->execute([':id' => $dispatchId2]);

                    $stmt = $pdo->prepare(
                        "UPDATE orders SET\n"
                        . "  status = 'assigned',\n"
                        . "  assigned_team_code = :tc,\n"
                        . "  assigned_team_type = :tt,\n"
                        . "  assigned_at = NOW(),\n"
                        . "  assigned_by = :by,\n"
                        . "  updated_at = NOW()\n"
                        . "WHERE id = :oid AND status = 'new'"
                    );
                    $stmt->execute([
                        ':tc' => $teamCodeDb,
                        ':tt' => $teamTypeDb,
                        ':by' => $dispatcherId2,
                        ':oid' => $orderId2,
                    ]);
                }
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

echo json_encode([
    'ok' => true,
    'status_code' => $statusCode,
    'status_label' => $allowed[$statusCode],
], JSON_UNESCAPED_UNICODE);
exit;
