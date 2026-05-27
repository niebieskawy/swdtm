<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$id = $_GET['id'] ?? '';
$id = is_string($id) ? trim($id) : '';
if ($id === '' || !ctype_digit($id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$orderId = (int)$id;
if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Nieprawidłowe ID.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = db();

    $hasPatientWeight = false;
    try {
        $stCol = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'patient_weight_kg' LIMIT 1"
        );
        $stCol->execute();
        $hasPatientWeight = ((int)($stCol->fetchColumn() ?: 0) === 1);
    } catch (Throwable $e) {
        $hasPatientWeight = false;
    }

    $stmt = $pdo->prepare(
        "SELECT\n"
        . "  id,\n"
        . "  order_seq, order_month, order_year,\n"
        . "  order_type, urgency, transport_type, needed_team, sirens, planned_at,\n"
        . "  patient_first_name, patient_last_name, patient_position,\n"
        . ($hasPatientWeight ? "  patient_weight_kg,\n" : "  NULL AS patient_weight_kg,\n")
        . "  phone,\n"
        . "  interview_oxygen, interview_conscious, interview_notes,\n"
        . "  icd10_none, icd10_code, icd10_name,\n"
        . "  order_description,\n"
        . "  from_city, from_street, from_number, from_flat,\n"
        . "  to_city, to_street, to_number, to_flat,\n"
        . "  assigned_team_code,\n"
        . "  status\n"
        . "FROM orders\n"
        . "WHERE id = :id\n"
        . "LIMIT 1"
    );
    $stmt->execute([':id' => $orderId]);
    $o = $stmt->fetch();

    if (!$o) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Nie znaleziono zlecenia.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $num = (string)($o['order_seq'] ?? '');
    $mm = isset($o['order_month']) ? str_pad((string)(int)$o['order_month'], 2, '0', STR_PAD_LEFT) : '';
    $yy = (string)($o['order_year'] ?? '');
    $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$o['id']);

    $fromNumber = trim((string)($o['from_number'] ?? ''));
    $fromFlat = trim((string)($o['from_flat'] ?? ''));
    $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
    $from = trim((string)($o['from_city'] ?? '') . ', ' . (string)($o['from_street'] ?? '') . ' ' . $fromNumLine);

    $toNumber = trim((string)($o['to_number'] ?? ''));
    $toFlat = trim((string)($o['to_flat'] ?? ''));
    $toNumLine = $toNumber . ($toFlat !== '' ? ('/' . $toFlat) : '');
    $to = trim((string)($o['to_city'] ?? '') . ', ' . (string)($o['to_street'] ?? '') . ' ' . $toNumLine);

    $out = [
        'ok' => true,
        'order' => [
            'id' => (int)$o['id'],
            'order_number' => $orderNumber,
            'status' => (string)($o['status'] ?? ''),
            'order_type' => (string)($o['order_type'] ?? ''),
            'urgency' => (string)($o['urgency'] ?? ''),
            'transport_type' => (string)($o['transport_type'] ?? ''),
            'needed_team' => (string)($o['needed_team'] ?? ''),
            'sirens' => (int)($o['sirens'] ?? 0),
            'planned_at' => (string)($o['planned_at'] ?? ''),
            'assigned_team_code' => (string)($o['assigned_team_code'] ?? ''),
            'patient_first_name' => (string)($o['patient_first_name'] ?? ''),
            'patient_last_name' => (string)($o['patient_last_name'] ?? ''),
            'patient_position' => (string)($o['patient_position'] ?? ''),
            'patient_weight_kg' => isset($o['patient_weight_kg']) && $o['patient_weight_kg'] !== null && $o['patient_weight_kg'] !== '' ? (int)$o['patient_weight_kg'] : null,
            'phone' => (string)($o['phone'] ?? ''),
            'interview_oxygen' => (string)($o['interview_oxygen'] ?? ''),
            'interview_conscious' => (string)($o['interview_conscious'] ?? ''),
            'interview_notes' => (string)($o['interview_notes'] ?? ''),
            'icd10_none' => (int)($o['icd10_none'] ?? 0),
            'icd10_code' => (string)($o['icd10_code'] ?? ''),
            'icd10_name' => (string)($o['icd10_name'] ?? ''),
            'order_description' => (string)($o['order_description'] ?? ''),
            'from' => $from,
            'to' => $to,
        ],
    ];

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][order_details] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
