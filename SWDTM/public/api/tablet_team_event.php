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

start_session();

$type = $data['type'] ?? '';
$type = is_string($type) ? trim($type) : '';
if ($type === '' || mb_strlen($type) > 60) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy typ zdarzenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$currentStatus = $_SESSION['tablet_status_current'] ?? '';
$currentStatus = is_string($currentStatus) ? trim($currentStatus) : '';

$allowedByStatus = true;
if ($currentStatus === 'order_start') {
    $allowedByStatus = false;
} elseif ($currentStatus === 'order_transport') {
    $allowedByStatus = ($type === 'Odmowa pacjenta/opiekuna');
} elseif ($currentStatus === 'order_patient') {
    $allowedByStatus = ($type === 'Odmowa pacjenta/opiekuna' || $type === 'Długi czas oczekiwania');
} elseif ($currentStatus === 'order_realization' || $currentStatus === 'order_handover') {
    $allowedByStatus = true;
}

if (!$allowedByStatus) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ta czynność jest obecnie niedostępna dla aktualnego statusu.' ], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = $data['payload'] ?? null;
if ($payload !== null && !is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe dane formularza.'], JSON_UNESCAPED_UNICODE);
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

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS team_event_files (\n"
        . "  id INT AUTO_INCREMENT PRIMARY KEY,\n"
        . "  event_id INT NULL,\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  order_id INT NULL,\n"
        . "  file_type VARCHAR(40) NOT NULL,\n"
        . "  mime_type VARCHAR(80) NOT NULL,\n"
        . "  data LONGBLOB NOT NULL,\n"
        . "  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
        . "  KEY idx_tef_event (event_id),\n"
        . "  KEY idx_tef_team (team_code),\n"
        . "  KEY idx_tef_order (order_id),\n"
        . "  KEY idx_tef_created (created_at)\n"
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
        "SELECT id\n"
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

    $msg = '';

    if ($type === 'Odmowa przyjęcia' || $type === 'Odmowa przyjęcia z przekierowaniem') {
        $doctor = $payload && isset($payload['doctor']) ? trim((string)$payload['doctor']) : '';
        $reason = $payload && isset($payload['reason']) ? trim((string)$payload['reason']) : '';
        $notes = $payload && isset($payload['notes']) ? trim((string)$payload['notes']) : '';
        if ($doctor === '' || $reason === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Uzupełnij lekarza i powód odmowy.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (mb_strlen($doctor) > 120 || mb_strlen($reason) > 300 || mb_strlen($notes) > 500) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Dane są za długie.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $msgParts = [];
        $msgParts[] = 'Lekarz: ' . $doctor;
        $msgParts[] = 'Powód: ' . $reason;

        if ($type === 'Odmowa przyjęcia z przekierowaniem') {
            $fCity = $payload && isset($payload['facility_city']) ? trim((string)$payload['facility_city']) : '';
            $fPostcode = $payload && isset($payload['facility_postcode']) ? trim((string)$payload['facility_postcode']) : '';
            $fStreet = $payload && isset($payload['facility_street']) ? trim((string)$payload['facility_street']) : '';
            $fNumber = $payload && isset($payload['facility_number']) ? trim((string)$payload['facility_number']) : '';
            $fFlat = $payload && isset($payload['facility_flat']) ? trim((string)$payload['facility_flat']) : '';

            if ($fCity === '' || $fStreet === '' || $fNumber === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Uzupełnij adres nowej placówki (miasto, ulica, numer).'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            if (mb_strlen($fCity) > 120 || mb_strlen($fPostcode) > 30 || mb_strlen($fStreet) > 150 || mb_strlen($fNumber) > 40 || mb_strlen($fFlat) > 40) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Adres nowej placówki jest za długi.'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $facilityLine = trim($fCity . ', ' . $fStreet . ' ' . $fNumber . ($fFlat !== '' ? ('/' . $fFlat) : ''));
            if ($fPostcode !== '') {
                $facilityLine .= ' (' . $fPostcode . ')';
            }
            $msgParts[] = 'Nowa placówka: ' . $facilityLine;
        }

        if ($notes !== '') $msgParts[] = 'Uwagi: ' . $notes;
        $msg = trim(implode("\n", $msgParts));
    } elseif ($type === 'Odmowa pacjenta/opiekuna') {
        $reason = $payload && isset($payload['reason']) ? trim((string)$payload['reason']) : '';
        $notes = $payload && isset($payload['notes']) ? trim((string)$payload['notes']) : '';
        $sig = $payload && isset($payload['signature_png']) ? trim((string)$payload['signature_png']) : '';

        if ($reason === '') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Uzupełnij powód odmowy.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (mb_strlen($reason) > 300 || mb_strlen($notes) > 500) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Dane są za długie.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if ($sig === '' || strpos($sig, 'data:image/png;base64,') !== 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Brak prawidłowego podpisu.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $b64 = substr($sig, strlen('data:image/png;base64,'));
        $bin = base64_decode($b64, true);
        if (!is_string($bin) || $bin === '' || strlen($bin) < 100) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Nieprawidłowy podpis.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (strlen($bin) > 800000) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Podpis jest zbyt duży.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $msgParts = [];
        $msgParts[] = 'Powód: ' . $reason;
        if ($notes !== '') $msgParts[] = 'Uwagi: ' . $notes;
        $msg = trim(implode("\n", $msgParts));

        $stmt = $pdo->prepare(
            "INSERT INTO team_event_files (event_id, team_code, order_id, file_type, mime_type, data, created_at)\n"
            . "VALUES (NULL, :tc, :oid, :ft, :mt, :d, NOW())"
        );
        $stmt->bindValue(':tc', $teamCode);
        $stmt->bindValue(':oid', $orderId, PDO::PARAM_INT);
        $stmt->bindValue(':ft', 'patient_refusal_signature');
        $stmt->bindValue(':mt', 'image/png');
        $stmt->bindValue(':d', $bin, PDO::PARAM_LOB);
        $stmt->execute();
        $fileId = (int)$pdo->lastInsertId();

        $msg = trim($msg . "\n\n" . 'Podpis: #SIG-' . (string)$fileId);
    } elseif ($type === 'Długi czas oczekiwania') {
        $notes = $payload && isset($payload['notes']) ? trim((string)$payload['notes']) : '';
        if (mb_strlen($notes) > 500) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Uwagi są za długie.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $msgParts = [];
        if ($notes !== '') {
            $msgParts[] = 'Uwagi: ' . $notes;
        }
        $msg = trim(implode("\n", $msgParts));
    } else {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Nieobsługiwany typ zdarzenia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO team_events (team_code, order_id, event_type, message, created_by, created_at)\n"
        . "VALUES (:tc, :oid, :t, :m, :by, NOW())"
    );
    $stmt->execute([
        ':tc' => $teamCode,
        ':oid' => $orderId,
        ':t' => $type,
        ':m' => ($msg !== '' ? $msg : null),
        ':by' => $userId,
    ]);

    $eventId = (int)$pdo->lastInsertId();

    if ($type === 'Odmowa pacjenta/opiekuna') {
        $stmt = $pdo->prepare(
            "UPDATE team_event_files SET event_id = :eid WHERE event_id IS NULL AND team_code = :tc AND order_id = :oid AND file_type = :ft ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([
            ':eid' => $eventId,
            ':tc' => $teamCode,
            ':oid' => $orderId,
            ':ft' => 'patient_refusal_signature',
        ]);
    }

    echo json_encode(['ok' => true, 'order_id' => $orderId, 'event_id' => $eventId], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][tablet_team_event] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd zapisu czynności.'], JSON_UNESCAPED_UNICODE);
    exit;
}
