<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/dispatcher'));
    exit;
}

if (!csrf_validate($_POST['csrf'] ?? null)) {
    flash_set('dispatcher', 'Nieprawidłowy token formularza.', 'alert');
    header('Location: ' . url('/dispatcher'));
    exit;
}

$orderId = $_POST['order_id'] ?? '';
$teamCode = $_POST['team_code'] ?? '';
$teamType = $_POST['team_type'] ?? '';

$orderId = is_string($orderId) ? trim($orderId) : '';
$teamCode = is_string($teamCode) ? trim($teamCode) : '';
$teamType = is_string($teamType) ? trim($teamType) : '';

if ($orderId === '' || !ctype_digit($orderId)) {
    flash_set('dispatcher', 'Nieprawidłowe ID zlecenia.', 'alert');
    header('Location: ' . url('/dispatcher'));
    exit;
}

$allowedTeamTypes = ['T', 'P', 'S'];
if ($teamCode === '' || !in_array($teamType, $allowedTeamTypes, true)) {
    flash_set('dispatcher', 'Wybierz zespół do zadysponowania.', 'alert');
    header('Location: ' . url('/dispatcher'));
    exit;
}

$user = current_user();
$dispatcherId = $user ? (int)($user['id'] ?? 0) : 0;

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "UPDATE orders
         SET
            status = 'assigned',
            assigned_team_code = :code,
            assigned_team_type = :type,
            assigned_at = NOW(),
            assigned_by = :by,
            updated_at = NOW()
         WHERE id = :id AND status = 'new'")
    ;

    $stmt->execute([
        ':code' => $teamCode,
        ':type' => $teamType,
        ':by' => ($dispatcherId > 0 ? $dispatcherId : null),
        ':id' => (int)$orderId,
    ]);

    if ($stmt->rowCount() < 1) {
        flash_set('dispatcher', 'Nie udało się zadysponować: zlecenie nie jest już dostępne.', 'alert');
        header('Location: ' . url('/dispatcher'));
        exit;
    }

    flash_set('dispatcher', 'Zadysponowano zespół do zlecenia.');
    header('Location: ' . url('/dispatcher'));
    exit;
} catch (Throwable $e) {
    flash_set('dispatcher', 'Nie udało się zadysponować zespołu.', 'alert');
    header('Location: ' . url('/dispatcher'));
    exit;
}
