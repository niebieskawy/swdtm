<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['team']);

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
$teamCode = current_team_code();
if ($teamCode === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Brak kodu zespołu.'], JSON_UNESCAPED_UNICODE);
    exit;
}

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

    if (!$teamCodeAliases) {
        echo json_encode(['ok' => true, 'order' => null], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $placeholders = [];
    $params = [];
    foreach ($teamCodeAliases as $i => $codeAlias) {
        $ph = ':tc' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $codeAlias;
    }
    $in = implode(', ', $placeholders);

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
        . "  from_infra, from_city, from_postcode, from_street, from_number, from_flat, from_lat, from_lon,\n"
        . "  to_infra, to_city, to_postcode, to_street, to_number, to_flat, to_lat, to_lon,\n"
        . "  assigned_team_code, assigned_team_type, assigned_at, assigned_by,\n"
        . "  status, created_at, updated_at\n"
        . "FROM orders\n"
        . "WHERE status = 'assigned' AND assigned_team_code COLLATE utf8mb4_unicode_ci IN ({$in})\n"
        . "ORDER BY assigned_at DESC, id DESC\n"
        . "LIMIT 1"
    );
    $stmt->execute($params);
    $o = $stmt->fetch();

    if (!$o) {
        echo json_encode(['ok' => true, 'order' => null], JSON_UNESCAPED_UNICODE);
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

    $transportLabel = match ((string)($o['transport_type'] ?? '')) {
        'hospital' => 'Przekazanie do szpitala',
        'poradnia' => 'Wizyta w poradni',
        'miedzyszpitalna' => 'Konsultacja międzyszpitalna',
        'dom' => 'Odwóz do domu',
        default => (string)($o['transport_type'] ?? ''),
    };

    $patientFirst = trim((string)($o['patient_first_name'] ?? ''));
    $patientLast = trim((string)($o['patient_last_name'] ?? ''));
    $patientFull = trim($patientFirst . ' ' . $patientLast);

    $patientWeight = null;
    if (isset($o['patient_weight_kg']) && $o['patient_weight_kg'] !== null && $o['patient_weight_kg'] !== '') {
        $w = (int)$o['patient_weight_kg'];
        if ($w >= 1 && $w <= 350) {
            $patientWeight = $w;
        }
    }

    echo json_encode([
        'ok' => true,
        'order' => [
            'id' => (int)$o['id'],
            'number' => $orderNumber,
            'order_type' => (string)($o['order_type'] ?? ''),
            'urgency' => (string)($o['urgency'] ?? ''),
            'transport_type' => (string)($o['transport_type'] ?? ''),
            'transport_label' => $transportLabel,
            'needed_team' => (string)($o['needed_team'] ?? ''),
            'sirens' => (int)($o['sirens'] ?? 0),
            'planned_at' => (string)($o['planned_at'] ?? ''),
            'patient' => [
                'first_name' => $patientFirst,
                'last_name' => $patientLast,
                'full_name' => ($patientFull !== '' ? $patientFull : '—'),
                'position' => trim((string)($o['patient_position'] ?? '')),
                'weight_kg' => $patientWeight,
            ],
            'phone' => (string)($o['phone'] ?? ''),
            'from' => [
                'infra' => (string)($o['from_infra'] ?? ''),
                'city' => (string)($o['from_city'] ?? ''),
                'postcode' => (string)($o['from_postcode'] ?? ''),
                'street' => (string)($o['from_street'] ?? ''),
                'number' => (string)($o['from_number'] ?? ''),
                'flat' => (string)($o['from_flat'] ?? ''),
                'display' => $from,
                'lat' => $o['from_lat'] !== null ? (float)$o['from_lat'] : null,
                'lon' => $o['from_lon'] !== null ? (float)$o['from_lon'] : null,
            ],
            'to' => [
                'infra' => (string)($o['to_infra'] ?? ''),
                'city' => (string)($o['to_city'] ?? ''),
                'postcode' => (string)($o['to_postcode'] ?? ''),
                'street' => (string)($o['to_street'] ?? ''),
                'number' => (string)($o['to_number'] ?? ''),
                'flat' => (string)($o['to_flat'] ?? ''),
                'display' => $to,
                'lat' => $o['to_lat'] !== null ? (float)$o['to_lat'] : null,
                'lon' => $o['to_lon'] !== null ? (float)$o['to_lon'] : null,
            ],
            'medical' => [
                'interview_oxygen' => (string)($o['interview_oxygen'] ?? ''),
                'interview_conscious' => (string)($o['interview_conscious'] ?? ''),
                'interview_notes' => (string)($o['interview_notes'] ?? ''),
                'icd10_none' => (int)($o['icd10_none'] ?? 0),
                'icd10_code' => (string)($o['icd10_code'] ?? ''),
                'icd10_name' => (string)($o['icd10_name'] ?? ''),
                'description' => (string)($o['order_description'] ?? ''),
            ],
            'assigned_at' => (string)($o['assigned_at'] ?? ''),
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('[SWDTM][team_active_order] error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Błąd pobierania zlecenia.'], JSON_UNESCAPED_UNICODE);
    exit;
}
