<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

$id = $_GET['id'] ?? null;
if (!is_string($id) || !preg_match('/^\d+$/', $id)) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nieprawidłowe ID pliku.';
    exit;
}

$fileId = (int)$id;
if ($fileId <= 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Nieprawidłowe ID pliku.';
    exit;
}

try {
    $pdo = db();

    $stmt = $pdo->prepare(
        "SELECT id, mime_type, data\n"
        . "FROM team_event_files\n"
        . "WHERE id = :id\n"
        . "LIMIT 1"
    );
    $stmt->execute([':id' => $fileId]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Plik nie istnieje.';
        exit;
    }

    $mime = isset($row['mime_type']) && is_string($row['mime_type']) ? trim($row['mime_type']) : 'application/octet-stream';
    $data = $row['data'] ?? '';
    if (!is_string($data) || $data === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Plik jest pusty.';
        exit;
    }

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="team-event-file-' . $fileId . '.bin"');
    header('X-Content-Type-Options: nosniff');

    echo $data;
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][team_event_file] error: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Błąd pobierania pliku.';
    exit;
}
