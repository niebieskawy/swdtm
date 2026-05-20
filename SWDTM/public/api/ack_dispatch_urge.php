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

$urgeId = $data['urge_id'] ?? null;
if (!is_numeric($urgeId) || (int)$urgeId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID ponaglenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = current_user();
$ackedBy = $user && isset($user['id']) ? (int)$user['id'] : 0;
if ($ackedBy <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "UPDATE dispatch_urges\n"
        . "SET acked_at = NOW(), acked_by = :by\n"
        . "WHERE id = :id AND acked_at IS NULL"
    );
    $stmt->execute([':by' => $ackedBy, ':id' => (int)$urgeId]);

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    error_log('[SWDTM][ack_dispatch_urge] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd potwierdzenia ponaglenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
