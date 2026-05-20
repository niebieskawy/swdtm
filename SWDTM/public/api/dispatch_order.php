<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metoda nieobsugiwana.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = $_POST['order_id'] ?? null;
$teamCode = $_POST['team_code'] ?? null;

if (!is_numeric($orderId) || (int)$orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidowe ID zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!is_string($teamCode) || trim($teamCode) === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidowy kod zespou.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = (int)$orderId;
$teamCode = trim($teamCode);
$dispatcherId = current_user()['id'] ?? 0;

if ($dispatcherId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    $teamCodeAliases = [];
    $teamCodeAliases[] = $teamCode;
    if (preg_match('/^T(\d{1,5})$/i', $teamCode, $m)) {
        $teamCodeAliases[] = (string)((int)$m[1]);
    }
    if (preg_match('/^(\d{1,5})$/', $teamCode, $m)) {
        $teamCodeAliases[] = 'T' . (string)((int)$m[1]);
    }
    if (preg_match('/^RATOL(\d{1,5})$/i', $teamCode, $m)) {
        $teamCodeAliases[] = 'T' . (string)((int)$m[1]);
        $teamCodeAliases[] = (string)((int)$m[1]);
    }
    $teamCodeAliases = array_values(array_unique(array_filter($teamCodeAliases, static fn($v) => is_string($v) && trim($v) !== '')));

    // Sprawd czy zlecenie istnieje i ma status 'new'
    $stmt = $pdo->prepare("SELECT id, status FROM orders WHERE id = :order_id LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Zlecenie nie istnieje.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($order['status'] !== 'new') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Zlecenie nie ma statusu "nowe".'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sprawd czy zesp istnieje i jest aktywny (z tolerancją na aliasy kodu)
    $team = null;
    if ($teamCodeAliases) {
        $placeholders = [];
        $params = [];
        foreach ($teamCodeAliases as $i => $c) {
            $ph = ':tc' . $i;
            $placeholders[] = $ph;
            $params[$ph] = $c;
        }
        $in = implode(', ', $placeholders);
        $stmt = $pdo->prepare("SELECT code, type FROM teams WHERE is_active = 1 AND code IN ({$in}) ORDER BY code ASC LIMIT 1");
        $stmt->execute($params);
        $team = $stmt->fetch();
    }

    if (!$team) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Zesp nie istnieje lub nie jest aktywny.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $placeholders = [];
    $params = [];
    foreach ($teamCodeAliases as $i => $c) {
        $ph = ':tca' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $c;
    }
    $inAliases = implode(', ', $placeholders);

    $stmt = $pdo->prepare(
        "SELECT id FROM orders WHERE status = 'assigned' AND assigned_team_code COLLATE utf8mb4_unicode_ci IN ({$inAliases}) ORDER BY assigned_at DESC, id DESC LIMIT 1"
    );
    $stmt->execute($params);
    $teamHasActiveOrder = $stmt->fetch() ? true : false;

    $stmt = $pdo->prepare(
        "SELECT 1 FROM team_presence WHERE status_code = 'restore_ready' AND team_code COLLATE utf8mb4_unicode_ci IN ({$inAliases}) LIMIT 1"
    );
    $stmt->execute($params);
    $teamIsRestoring = ((int)($stmt->fetchColumn() ?: 0) === 1);

    $teamBusy = ($teamHasActiveOrder || $teamIsRestoring);

    // Sprawd czy ju istnieje aktywne lub zakolejkowane zadysponowanie dla tego zlecenia
    // (w starszych bazach 'queued' może zapisywać się jako pusty enum)
    $stmt = $pdo->prepare("SELECT id FROM dispatch_notifications WHERE order_id = :order_id AND status IN ('pending', 'accepted', 'queued', '') LIMIT 1");
    $stmt->execute([':order_id' => $orderId]);
    $existingDispatch = $stmt->fetch();

    if ($existingDispatch) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Zlecenie ju jest zadysponowane.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Utwrz powiadomienie o zadysporowaniu
    $teamCodeCanonical = (string)($team['code'] ?? $teamCode);

    if ($teamBusy) {
        $stmt = $pdo->prepare(
            "INSERT INTO dispatch_notifications (order_id, team_code, dispatcher_id, status)
             VALUES (:order_id, :team_code, :dispatcher_id, 'queued')"
        );
        $stmt->execute([
            ':order_id' => $orderId,
            ':team_code' => $teamCodeCanonical,
            ':dispatcher_id' => $dispatcherId,
        ]);

        $notificationId = (int)$pdo->lastInsertId();

        $pdo->commit();

        echo json_encode([
            'ok' => true,
            'queued' => true,
            'notification_id' => $notificationId,
            'message' => 'Zlecenie dodane do kolejki zespołu.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO dispatch_notifications (order_id, team_code, dispatcher_id, status, notification_sent_at)
         VALUES (:order_id, :team_code, :dispatcher_id, 'pending', NOW())"
    );
    $stmt->execute([
        ':order_id' => $orderId,
        ':team_code' => $teamCodeCanonical,
        ':dispatcher_id' => $dispatcherId,
    ]);

    $notificationId = (int)$pdo->lastInsertId();

    // Zaktualizuj status zlecenia na 'assigned'
    $stmt = $pdo->prepare(
        "UPDATE orders SET 
         status = 'assigned',
         assigned_team_code = :team_code,
         assigned_team_type = :team_type,
         assigned_at = NOW(),
         assigned_by = :dispatcher_id
         WHERE id = :order_id"
    );
    $stmt->execute([
        ':order_id' => $orderId,
        ':team_code' => $teamCodeCanonical,
        ':team_type' => $team['type'],
        ':dispatcher_id' => $dispatcherId,
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'queued' => false,
        'notification_id' => $notificationId,
        'message' => 'Zesp zosta zadysponowany.'
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('[SWDTM][dispatch_order] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błd zadysponowania zespou.'], JSON_UNESCAPED_UNICODE);
    exit;
}
