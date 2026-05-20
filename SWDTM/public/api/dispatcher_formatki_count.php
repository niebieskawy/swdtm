<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = db();
    $st = $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status = 'pending'");
    $count = (int)($st->fetchColumn() ?: 0);

    echo json_encode([
        'ok' => true,
        'count' => $count,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Błąd serwera.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
