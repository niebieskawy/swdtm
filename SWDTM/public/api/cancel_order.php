<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

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

$orderId = $data['order_id'] ?? null;
if (!is_numeric($orderId) || (int)$orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$person = $data['cancelled_by_name'] ?? '';
$person = is_string($person) ? trim($person) : '';
if ($person === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Podaj imię i nazwisko osoby odwołującej.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (mb_strlen($person) > 120) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Imię i nazwisko jest za długie.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$dispatcherId = $user && isset($user['id']) ? (int)$user['id'] : 0;
if ($dispatcherId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();
    $cancelStatus = 'cancelled';
    try {
        $st = $pdo->prepare(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'status' LIMIT 1"
        );
        $st->execute();
        $colType = (string)($st->fetchColumn() ?: '');
        if ($colType !== '' && stripos($colType, "'odwolany'") !== false) {
            $cancelStatus = 'odwolany';
        } else {
            $enumStart = strpos($colType, 'enum(');
            if ($enumStart !== false) {
                $enumBody = trim(substr($colType, $enumStart + 5));
                $enumBody = rtrim($enumBody, ')');
                if ($enumBody !== '') {
                    $newEnum = "ENUM(" . $enumBody . ",'odwolany')";
                    $pdo->exec("ALTER TABLE orders MODIFY status {$newEnum} NOT NULL DEFAULT 'new'");
                    $cancelStatus = 'odwolany';
                }
            }
        }
    } catch (Throwable $e) {
        $cancelStatus = 'cancelled';
    }

    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS order_cancellations (\n"
            . "  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,\n"
            . "  order_id BIGINT UNSIGNED NOT NULL,\n"
            . "  cancelled_by_user INT NULL,\n"
            . "  cancelled_by_name VARCHAR(120) NOT NULL,\n"
            . "  cancelled_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
            . "  KEY idx_order_cancellations_order (order_id),\n"
            . "  KEY idx_order_cancellations_at (cancelled_at)\n"
            . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    } catch (Throwable $e) {
    }

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
    } catch (Throwable $e) {
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare(
        "SELECT id, status, assigned_team_code FROM orders WHERE id = :id LIMIT 1"
    );
    $stmt->execute([':id' => (int)$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Zlecenie nie istnieje.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $status = (string)($order['status'] ?? '');
    if ($status === 'done') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nie można odwołać zakończonego zlecenia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        "UPDATE orders SET status = :st, assigned_team_code = NULL, assigned_team_type = NULL, assigned_at = NULL, assigned_by = NULL, updated_at = NOW() WHERE id = :id"
    );
    $stmt->execute([':st' => $cancelStatus, ':id' => (int)$orderId]);

    $stmt = $pdo->prepare(
        "INSERT INTO order_cancellations (order_id, cancelled_by_user, cancelled_by_name, cancelled_at) VALUES (:oid, :byu, :byn, NOW())"
    );
    $stmt->execute([
        ':oid' => (int)$orderId,
        ':byu' => $dispatcherId,
        ':byn' => $person,
    ]);

    $stmt = $pdo->prepare(
        "SELECT id, team_code, status FROM dispatch_notifications WHERE order_id = :oid AND status IN ('pending','accepted','queued','') ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([':oid' => (int)$orderId]);
    $dn = $stmt->fetch();

    if ($dn) {
        $dispatchId = (int)($dn['id'] ?? 0);
        $teamCode = (string)($dn['team_code'] ?? '');

        if ($dispatchId > 0) {
            $stmt = $pdo->prepare(
                "UPDATE dispatch_notifications SET status = 'cancelled', cancelled_at = NOW(), cancelled_by = :by WHERE id = :id"
            );
            $stmt->execute([':by' => $dispatcherId, ':id' => $dispatchId]);

            $reason = 'Transport odwołany przez: ' . $person;
            $stmt = $pdo->prepare(
                "INSERT INTO dispatch_cancellations (dispatch_id, order_id, team_code, reason, cancelled_by, cancelled_at) VALUES (:did, :oid, :tc, :r, :by, NOW())"
            );
            $stmt->execute([
                ':did' => $dispatchId,
                ':oid' => (int)$orderId,
                ':tc' => $teamCode,
                ':r' => $reason,
                ':by' => $dispatcherId,
            ]);
        }
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[SWDTM][cancel_order] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd odwołania transportu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
