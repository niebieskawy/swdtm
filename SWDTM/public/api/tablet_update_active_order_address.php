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

$side = $data['side'] ?? '';
$side = is_string($side) ? trim($side) : '';
if (!in_array($side, ['from', 'to'], true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Wybierz, który adres poprawiasz (skąd/dokąd).'], JSON_UNESCAPED_UNICODE);
    exit;
}

$city = $data['city'] ?? '';
$postcode = $data['postcode'] ?? '';
$street = $data['street'] ?? '';
$number = $data['number'] ?? '';
$flat = $data['flat'] ?? '';
$lat = $data['lat'] ?? null;
$lon = $data['lon'] ?? null;

$city = is_string($city) ? trim($city) : '';
$postcode = is_string($postcode) ? trim($postcode) : '';
$street = is_string($street) ? trim($street) : '';
$number = is_string($number) ? trim($number) : '';
$flat = is_string($flat) ? trim($flat) : '';

if ($city === '' || $street === '' || $number === '' || !is_numeric($lat) || !is_numeric($lon)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy adres (wymagane: miasto, ulica, numer oraz współrzędne).'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($city) > 120 || mb_strlen($postcode) > 30 || mb_strlen($street) > 150 || mb_strlen($number) > 40 || mb_strlen($flat) > 40) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Pola adresu są za długie.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$notes = $data['notes'] ?? '';
$notes = is_string($notes) ? trim($notes) : '';
if (mb_strlen($notes) > 500) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Uwagi są za długie.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$userId = $user && isset($user['id']) ? (int)$user['id'] : 0;
$teamCode = current_team_code();
$teamCode = is_string($teamCode) ? trim($teamCode) : '';

if ($userId <= 0 || $teamCode === '') {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$username = $user && isset($user['username']) && is_string($user['username']) ? trim((string)$user['username']) : '';

$teamCodeAliases = [];
foreach ([$teamCode, $username] as $c) {
    $c = is_string($c) ? trim($c) : '';
    if ($c !== '') $teamCodeAliases[] = $c;
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

if (!$teamCodeAliases) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

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

    $placeholders = [];
    $params = [];
    foreach ($teamCodeAliases as $i => $codeAlias) {
        $ph = ':tc' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $codeAlias;
    }
    $in = implode(', ', $placeholders);

    $stmt = $pdo->prepare(
        "SELECT id, from_city, from_street, from_number, from_postcode, to_city, to_street, to_number, to_postcode\n"
        . "FROM orders\n"
        . "WHERE status = 'assigned' AND assigned_team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
        . "ORDER BY assigned_at DESC, id DESC\n"
        . "LIMIT 1"
    );
    $stmt->execute($params);
    $order = $stmt->fetch();

    if (!$order) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Brak aktywnego zlecenia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $orderId = (int)$order['id'];

    $oldLine = '';
    if ($side === 'from') {
        $oldLine = trim((string)($order['from_city'] ?? '') . ', ' . (string)($order['from_street'] ?? '') . ' ' . (string)($order['from_number'] ?? ''));
    } else {
        $oldLine = trim((string)($order['to_city'] ?? '') . ', ' . (string)($order['to_street'] ?? '') . ' ' . (string)($order['to_number'] ?? ''));
    }

    $newLine = trim($city . ', ' . $street . ' ' . $number . ($flat !== '' ? ('/' . $flat) : ''));

    $pdo->beginTransaction();

    if ($side === 'from') {
        $stmt = $pdo->prepare(
            "UPDATE orders SET\n"
            . "  from_city = :city, from_postcode = :pc, from_street = :street, from_number = :num, from_flat = :flat,\n"
            . "  from_display = :disp, from_lat = :lat, from_lon = :lon, updated_at = NOW()\n"
            . "WHERE id = :id"
        );
    } else {
        $stmt = $pdo->prepare(
            "UPDATE orders SET\n"
            . "  to_city = :city, to_postcode = :pc, to_street = :street, to_number = :num, to_flat = :flat,\n"
            . "  to_display = :disp, to_lat = :lat, to_lon = :lon, updated_at = NOW()\n"
            . "WHERE id = :id"
        );
    }

    $stmt->execute([
        ':city' => $city,
        ':pc' => ($postcode !== '' ? $postcode : null),
        ':street' => $street,
        ':num' => ($number !== '' ? $number : null),
        ':flat' => ($flat !== '' ? $flat : null),
        ':disp' => ($newLine !== '' ? $newLine : null),
        ':lat' => (float)$lat,
        ':lon' => (float)$lon,
        ':id' => $orderId,
    ]);

    $eventType = 'Błędny adres';
    $msgParts = [];
    $msgParts[] = 'Poprawiono: ' . ($side === 'from' ? 'Skąd' : 'Dokąd');
    if ($oldLine !== '') $msgParts[] = 'Było: ' . $oldLine;
    $msgParts[] = 'Jest: ' . $newLine;
    if ($notes !== '') $msgParts[] = 'Uwagi: ' . $notes;

    $stmt = $pdo->prepare(
        "INSERT INTO team_events (team_code, order_id, event_type, message, created_by, created_at)\n"
        . "VALUES (:tc, :oid, :t, :m, :by, NOW())"
    );
    $stmt->execute([
        ':tc' => $teamCode,
        ':oid' => $orderId,
        ':t' => $eventType,
        ':m' => trim(implode("\n", $msgParts)),
        ':by' => $userId,
    ]);

    $pdo->commit();

    echo json_encode(['ok' => true, 'order_id' => $orderId], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][tablet_update_active_order_address] error: ' . $e->getMessage());
    try {
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    } catch (Throwable $e2) {
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd zapisu adresu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
