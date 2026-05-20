<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$limit = 30;
$lim = $_GET['limit'] ?? '';
if (is_string($lim) && $lim !== '' && ctype_digit($lim)) {
    $limit = (int)$lim;
}
if ($limit < 1) $limit = 1;
if ($limit > 80) $limit = 80;

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
        . "  o.id, o.order_seq, o.order_month, o.order_year,\n"
        . "  o.status, o.assigned_team_code,\n"
        . "  o.urgency, o.order_type, o.transport_type, o.needed_team, o.sirens, o.planned_at, o.phone,\n"
        . "  o.patient_first_name, o.patient_last_name, o.patient_position, " . ($hasPatientWeight ? "o.patient_weight_kg" : "NULL AS patient_weight_kg") . ",\n"
        . "  o.from_city, o.from_street, o.from_number, o.from_postcode, o.from_flat,\n"
        . "  o.to_city, o.to_street, o.to_number, o.to_postcode, o.to_flat,\n"
        . "  o.interview_oxygen, o.interview_conscious, o.interview_notes,\n"
        . "  o.icd10_none, o.icd10_code, o.icd10_name,\n"
        . "  o.order_description\n"
        . "FROM orders o\n"
        . "LEFT JOIN dispatch_notifications dn ON dn.order_id = o.id AND dn.status IN ('pending','accepted','queued','')\n"
        . "WHERE o.status = 'new'\n"
        . "  AND dn.id IS NULL\n"
        . "  AND DATE(COALESCE(o.planned_at, o.created_at)) = CURDATE()\n"
        . "  AND (\n"
        . "    o.order_type = 'nagłe'\n"
        . "    OR (o.order_type = 'planowe' AND o.planned_at IS NOT NULL AND o.planned_at <= DATE_ADD(NOW(), INTERVAL 2 DAY))\n"
        . "  )\n"
        . "ORDER BY\n"
        . "  CASE o.urgency\n"
        . "    WHEN 'natychmiast' THEN 0\n"
        . "    WHEN 'pilne' THEN 1\n"
        . "    ELSE 2\n"
        . "  END ASC,\n"
        . "  COALESCE(o.planned_at, o.created_at) ASC\n"
        . "LIMIT " . (int)$limit
    );

    $stmt->execute();
    $rows = $stmt->fetchAll();

    $out = [];
    foreach ($rows as $o) {
        $fromNumber = trim((string)($o['from_number'] ?? ''));
        $fromFlat = trim((string)($o['from_flat'] ?? ''));
        $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
        $from = trim((string)($o['from_city'] ?? '') . ', ' . (string)($o['from_street'] ?? '') . ' ' . $fromNumLine);

        $toNumber = trim((string)($o['to_number'] ?? ''));
        $toFlat = trim((string)($o['to_flat'] ?? ''));
        $toNumLine = $toNumber . ($toFlat !== '' ? ('/' . $toFlat) : '');
        $to = trim((string)($o['to_city'] ?? '') . ', ' . (string)($o['to_street'] ?? '') . ' ' . $toNumLine);
        if ($to === ',' || $to === '') {
            $to = '—';
        }

        $out[] = [
            'id' => (int)$o['id'],
            'order_seq' => $o['order_seq'] !== null ? (int)$o['order_seq'] : null,
            'order_month' => $o['order_month'] !== null ? (int)$o['order_month'] : null,
            'order_year' => $o['order_year'] !== null ? (int)$o['order_year'] : null,
            'status' => (string)($o['status'] ?? ''),
            'assigned_team_code' => (string)($o['assigned_team_code'] ?? ''),
            'urgency' => (string)($o['urgency'] ?? ''),
            'order_type' => (string)($o['order_type'] ?? ''),
            'transport_type' => (string)($o['transport_type'] ?? ''),
            'needed_team' => (string)($o['needed_team'] ?? ''),
            'sirens' => (int)($o['sirens'] ?? 0),
            'planned_at' => $o['planned_at'] !== null ? (string)$o['planned_at'] : '',
            'phone' => (string)($o['phone'] ?? ''),
            'patient_first_name' => (string)($o['patient_first_name'] ?? ''),
            'patient_last_name' => (string)($o['patient_last_name'] ?? ''),
            'patient_position' => (string)($o['patient_position'] ?? ''),
            'patient_weight_kg' => $o['patient_weight_kg'] !== null ? (int)$o['patient_weight_kg'] : null,
            'from' => $from,
            'to' => $to,
            'from_flat' => (string)($o['from_flat'] ?? ''),
            'to_flat' => (string)($o['to_flat'] ?? ''),
            'interview_oxygen' => (string)($o['interview_oxygen'] ?? ''),
            'interview_conscious' => (string)($o['interview_conscious'] ?? ''),
            'interview_notes' => (string)($o['interview_notes'] ?? ''),
            'icd10_none' => (int)($o['icd10_none'] ?? 0),
            'icd10_code' => (string)($o['icd10_code'] ?? ''),
            'icd10_name' => (string)($o['icd10_name'] ?? ''),
            'order_description' => (string)($o['order_description'] ?? ''),
        ];
    }

    echo json_encode(['ok' => true, 'orders' => $out], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania zleceń.'], JSON_UNESCAPED_UNICODE);
    exit;
}
