<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

header('Content-Type: application/json; charset=utf-8');

$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

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

    $hasDispatchCreatedAt = false;
    try {
        $stCol = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'dispatch_notifications' AND COLUMN_NAME = 'created_at' LIMIT 1"
        );
        $stCol->execute();
        $hasDispatchCreatedAt = ((int)($stCol->fetchColumn() ?: 0) === 1);
    } catch (Throwable $e) {
        $hasDispatchCreatedAt = false;
    }

    // Pobierz ostatnie zadysponowania z ostatnich 24 godzin
    $sql = "SELECT 
            dn.id,
            dn.order_id,
            dn.team_code,
            dn.status,
            " . ($hasDispatchCreatedAt ? "dn.created_at" : "NULL AS created_at") . ",
            dn.accepted_at,
            dn.rejected_at,
            dn.cancelled_at,
            dn.notification_sent_at,
            o.order_seq,
            o.order_month,
            o.order_year,
            o.status AS order_status,
            o.order_type,
            o.urgency,
            o.sirens,
            o.transport_type,
            o.needed_team,
            o.planned_at,
            o.phone,
            o.assigned_team_code,
            o.patient_first_name,
            o.patient_last_name,
            o.patient_position,";
    $sql .= $hasPatientWeight ? "
            o.patient_weight_kg," : "
            NULL AS patient_weight_kg,";
    $sql .= "
            o.interview_oxygen,
            o.interview_conscious,
            o.interview_notes,
            o.icd10_none,
            o.icd10_code,
            o.icd10_name,
            o.order_description,
            o.from_city,
            o.from_street,
            o.from_number,
            o.from_flat,
            o.to_city,
            o.to_street,
            o.to_number,
            o.to_flat,
            t.type as team_type,
            t.name as team_name,
            d.full_name as dispatcher_name,
            acc.full_name as accepted_by_name,
            rej.full_name as rejected_by_name
        FROM dispatch_notifications dn
        JOIN orders o ON o.id = dn.order_id
        LEFT JOIN teams t ON t.code COLLATE utf8mb4_unicode_ci = dn.team_code COLLATE utf8mb4_unicode_ci
        LEFT JOIN users d ON d.id = dn.dispatcher_id
        LEFT JOIN users acc ON acc.id = dn.accepted_by
        LEFT JOIN users rej ON rej.id = dn.rejected_by
        WHERE COALESCE(" . ($hasDispatchCreatedAt ? "dn.created_at" : "NULL") . ", dn.notification_sent_at, NOW()) >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND dn.status IN ('pending', 'accepted', 'queued', '')
          AND o.status IN ('new', 'assigned')
        ORDER BY COALESCE(" . ($hasDispatchCreatedAt ? "dn.created_at" : "NULL") . ", dn.notification_sent_at) DESC, dn.id DESC
        LIMIT 50";

    $stmt = $pdo->prepare($sql);
    
    $stmt->execute();
    $notifications = $stmt->fetchAll();

    $out = [];
    foreach ($notifications as $n) {
        $num = (string)($n['order_seq'] ?? '');
        $mm = isset($n['order_month']) ? str_pad((string)(int)$n['order_month'], 2, '0', STR_PAD_LEFT) : '';
        $yy = (string)($n['order_year'] ?? '');
        $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$n['order_id']);

        $fromNumber = trim((string)($n['from_number'] ?? ''));
        $fromFlat = trim((string)($n['from_flat'] ?? ''));
        $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
        $from = trim((string)($n['from_city'] ?? '') . ', ' . (string)($n['from_street'] ?? '') . ' ' . $fromNumLine);

        $toNumber = trim((string)($n['to_number'] ?? ''));
        $toFlat = trim((string)($n['to_flat'] ?? ''));
        $toNumLine = $toNumber . ($toFlat !== '' ? ('/' . $toFlat) : '');
        $to = trim((string)($n['to_city'] ?? '') . ', ' . (string)($n['to_street'] ?? '') . ' ' . $toNumLine);

        $rawStatus = (string)($n['status'] ?? '');
        $normStatus = trim($rawStatus) !== '' ? trim($rawStatus) : 'queued';

        $statusLabel = match ($normStatus) {
            'pending' => 'Oczekuje',
            'accepted' => 'Przyjęto',
            'rejected' => 'Odrzucono',
            'queued' => 'W kolejce',
            'cancelled' => 'Anulowano',
            default => $normStatus,
        };

        $out[] = [
            'id' => (int)$n['id'],
            'order_id' => (int)$n['order_id'],
            'order_number' => $orderNumber,
            'team_code' => (string)($n['team_code'] ?? ''),
            'team_type' => (string)($n['team_type'] ?? ''),
            'team_name' => (string)($n['team_name'] ?? ''),
            'status' => $normStatus,
            'status_label' => $statusLabel,
            'order_status' => (string)($n['order_status'] ?? ''),
            'order_type' => (string)($n['order_type'] ?? ''),
            'urgency' => (string)($n['urgency'] ?? ''),
            'sirens' => (int)($n['sirens'] ?? 0),
            'transport_type' => (string)($n['transport_type'] ?? ''),
            'needed_team' => (string)($n['needed_team'] ?? ''),
            'planned_at' => $n['planned_at'] !== null ? (string)$n['planned_at'] : '',
            'phone' => (string)($n['phone'] ?? ''),
            'assigned_team_code' => (string)($n['assigned_team_code'] ?? ''),
            'patient_first_name' => (string)($n['patient_first_name'] ?? ''),
            'patient_last_name' => (string)($n['patient_last_name'] ?? ''),
            'patient_position' => (string)($n['patient_position'] ?? ''),
            'patient_weight_kg' => $n['patient_weight_kg'] !== null ? (int)$n['patient_weight_kg'] : null,
            'to' => $to,
            'interview_oxygen' => (string)($n['interview_oxygen'] ?? ''),
            'interview_conscious' => (string)($n['interview_conscious'] ?? ''),
            'interview_notes' => (string)($n['interview_notes'] ?? ''),
            'icd10_none' => (int)($n['icd10_none'] ?? 0),
            'icd10_code' => (string)($n['icd10_code'] ?? ''),
            'icd10_name' => (string)($n['icd10_name'] ?? ''),
            'order_description' => (string)($n['order_description'] ?? ''),
            'from' => $from,
            'dispatcher_name' => (string)($n['dispatcher_name'] ?? ''),
            'accepted_by_name' => (string)($n['accepted_by_name'] ?? ''),
            'rejected_by_name' => (string)($n['rejected_by_name'] ?? ''),
            'created_at' => (string)($n['created_at'] ?? ''),
            'accepted_at' => (string)($n['accepted_at'] ?? ''),
            'rejected_at' => (string)($n['rejected_at'] ?? ''),
            'cancelled_at' => (string)($n['cancelled_at'] ?? ''),
            'notification_sent_at' => (string)($n['notification_sent_at'] ?? ''),
        ];
    }

    echo json_encode(['ok' => true, 'dispatches' => $out], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    $msg = $e->getMessage();
    $code = $e->getCode();
    error_log('[SWDTM][dispatch_status] error: ' . $msg);

    if ($e instanceof PDOException) {
        $info = $e->errorInfo;
        if (is_array($info) && isset($info[0]) && $info[0] === '42S02') {
            echo json_encode(['ok' => true, 'dispatches' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Bd pobierania statusu zadysponowa.',
        'debug' => $debug ? (string)$msg : null,
        'debug_code' => $debug ? (string)$code : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
