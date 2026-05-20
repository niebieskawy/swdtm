<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metoda nieobsugiwana.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$notificationId = $_POST['notification_id'] ?? null;
$action = $_POST['action'] ?? null; // 'accept' lub 'reject'

if (!is_numeric($notificationId) || (int)$notificationId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidowe ID powiadomienia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!in_array($action, ['accept', 'reject'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidowa akcja.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$notificationId = (int)$notificationId;
$user = current_user();
$teamCode = current_team_code();
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
$userId = $user && isset($user['id']) ? (int)$user['id'] : 0;

if (($teamCode === '' && !$teamCodeAliases) || $userId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = db();

try {
    $pdo->beginTransaction();

    // Pobierz powiadomienie
    if (!$teamCodeAliases) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Powiadomienie nie istnieje.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $placeholders = [];
    $params = [':notification_id' => $notificationId];
    foreach ($teamCodeAliases as $i => $codeAlias) {
        $ph = ':tc' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $codeAlias;
    }
    $in = implode(', ', $placeholders);

    $stmt = $pdo->prepare(
        "SELECT id, order_id, team_code, status FROM dispatch_notifications 
         WHERE id = :notification_id AND team_code COLLATE utf8mb4_unicode_ci IN ({$in}) LIMIT 1"
    );
    $stmt->execute($params);
    $notification = $stmt->fetch();

    if (!$notification) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Powiadomienie nie istnieje.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($notification['status'] !== 'pending') {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Powiadomienie nie ma statusu "oczekujce".'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Zaktualizuj status powiadomienia
    $newStatus = $action === 'accept' ? 'accepted' : 'rejected';
    $timestampField = $action === 'accept' ? 'accepted_at' : 'rejected_at';
    $userField = $action === 'accept' ? 'accepted_by' : 'rejected_by';

    $stmt = $pdo->prepare(
        "UPDATE dispatch_notifications SET 
         status = :new_status,
         {$timestampField} = NOW(),
         {$userField} = :user_id
         WHERE id = :notification_id"
    );
    $stmt->execute([
        ':new_status' => $newStatus,
        ':user_id' => $userId,
        ':notification_id' => $notificationId,
    ]);

    // jeli odrzucono, przywr status zlecenia na 'new'
    if ($action === 'reject') {
        $stmt = $pdo->prepare(
            "UPDATE orders SET 
             status = 'new',
             assigned_team_code = NULL,
             assigned_team_type = NULL,
             assigned_at = NULL,
             assigned_by = NULL
             WHERE id = :order_id"
        );
        $stmt->execute([':order_id' => $notification['order_id']]);
    }

    $pdo->commit();

    $message = $action === 'accept' ? 'Zlecenie przyjte.' : 'Zlecenie odrzucone.';
    
    echo json_encode([
        'ok' => true,
        'message' => $message,
        'action' => $action,
        'order_id' => $notification['order_id']
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('[SWDTM][accept_dispatch] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Bd przetwarzania odpowiedzi.'], JSON_UNESCAPED_UNICODE);
    exit;
}
