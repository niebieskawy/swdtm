<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

$user = current_user();
$dispatcherId = $user && isset($user['id']) ? (int)$user['id'] : 0;

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$id = is_string($id) ? trim($id) : '';

if ($id === '' || !ctype_digit($id)) {
    header('Location: ' . url('/dispatcher/formatki'));
    exit;
}

$pdo = db();

function load_request(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT cr.*, c.client_name, c.username AS client_username'
        . ' FROM client_requests cr'
        . ' JOIN clients c ON c.id = cr.client_id'
        . ' WHERE cr.id = :id'
        . ' LIMIT 1'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$req = null;
try {
    $req = load_request($pdo, (int)$id);
} catch (Throwable $e) {
    $req = null;
}

if (!$req) {
    flash_set('formatki', 'Nie znaleziono formatki.', 'alert');
    header('Location: ' . url('/dispatcher/formatki'));
    exit;
}

$error = '';
$ok = '';

$allowedPositions = ['chodzi', 'siedzi', 'leży'];
$allowedNeededTeam = ['T', 'P', 'S'];
$allowedSirens = ['0', '1'];
$allowedOrderType = ['nagłe', 'planowe'];
$allowedUrgency = ['zwykłe', 'pilne', 'natychmiast'];
$allowedTransportType = ['hospital', 'poradnia', 'miedzyszpitalna', 'dom', 'transport prywatny'];
$allowedInfra = ['dom', 'blok mieszkalny', 'szpital', 'poradnia', 'inne'];
$allowedOxygen = ['tak', 'nie', 'nie_wiadomo'];
$allowedConscious = ['tak', 'nie', 'nie_wiadomo'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Nieprawidłowy token formularza.';
    } else {
        $action = $_POST['action'] ?? '';
        $action = is_string($action) ? trim($action) : '';

        $statusNow = (string)($req['status'] ?? '');

        if ($action === 'reject') {
            if ($statusNow !== 'pending') {
                $error = 'Tę formatkę nie można już odrzucić.';
            } else {
                try {
                    $pdo->prepare('DELETE FROM client_requests WHERE id = :id AND status = :old')
                        ->execute([
                            ':id' => (int)$req['id'],
                            ':old' => 'pending',
                        ]);
                    flash_set('formatki', 'Odrzucono formatkę.', 'notice');
                    header('Location: ' . url('/dispatcher/formatki'));
                    exit;
                } catch (Throwable $e) {
                    $error = 'Nie udało się odrzucić formatki.';
                }
            }
        } elseif ($action === 'confirm') {
            if ($statusNow !== 'pending') {
                $error = 'Tę formatkę nie można już potwierdzić.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
                    $orderMonth = (int)$now->format('m');
                    $orderYear = (int)$now->format('Y');
                    $lockName = sprintf('orders_number_%04d_%02d', $orderYear, $orderMonth);

                    $lockStmt = $pdo->prepare('SELECT GET_LOCK(:n, 5)');
                    $lockStmt->execute([':n' => $lockName]);
                    $gotLock = ((int)$lockStmt->fetchColumn() === 1);
                    if (!$gotLock) {
                        throw new RuntimeException('Nie udało się uzyskać blokady numeracji.');
                    }

                    try {
                        $seqStmt = $pdo->prepare(
                            'SELECT order_seq FROM orders WHERE order_year = :y AND order_month = :m ORDER BY order_seq DESC LIMIT 1 FOR UPDATE'
                        );
                        $seqStmt->execute([':y' => $orderYear, ':m' => $orderMonth]);
                        $lastSeq = (int)($seqStmt->fetchColumn() ?: 0);
                        $orderSeq = $lastSeq + 1;

                        $insertCols = [
                            'dispatcher_id',
                            'order_seq', 'order_month', 'order_year',
                            'order_type', 'urgency', 'transport_type', 'needed_team', 'sirens',
                            'planned_at',
                            'patient_first_name', 'patient_last_name', 'patient_position',
                            'phone',
                            'patient_weight_kg',
                            'interview_oxygen', 'interview_conscious', 'interview_notes',
                            'icd10_none', 'icd10_code', 'icd10_name',
                            'order_description',
                            'from_infra', 'from_city', 'from_postcode', 'from_street', 'from_number', 'from_flat', 'from_display', 'from_lat', 'from_lon',
                            'to_infra', 'to_city', 'to_postcode', 'to_street', 'to_number', 'to_flat', 'to_display', 'to_lat', 'to_lon',
                            'distance_km'
                        ];

                        $ph = array_map(static fn($c) => ':' . $c, $insertCols);
                        $stmt = $pdo->prepare('INSERT INTO orders (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $ph) . ')');

                        $stmt->execute([
                            ':dispatcher_id' => $dispatcherId > 0 ? $dispatcherId : null,
                            ':order_seq' => $orderSeq,
                            ':order_month' => $orderMonth,
                            ':order_year' => $orderYear,
                            ':order_type' => (string)$req['order_type'],
                            ':urgency' => (string)$req['urgency'],
                            ':transport_type' => (string)$req['transport_type'],
                            ':needed_team' => (string)$req['needed_team'],
                            ':sirens' => (int)$req['sirens'],
                            ':planned_at' => $req['planned_at'] !== null ? (string)$req['planned_at'] : null,
                            ':patient_first_name' => (string)$req['patient_first_name'],
                            ':patient_last_name' => (string)$req['patient_last_name'],
                            ':patient_position' => (string)$req['patient_position'],
                            ':phone' => $req['phone'] !== null ? (string)$req['phone'] : null,
                            ':patient_weight_kg' => $req['patient_weight_kg'] !== null ? (int)$req['patient_weight_kg'] : null,
                            ':interview_oxygen' => (string)$req['interview_oxygen'],
                            ':interview_conscious' => (string)$req['interview_conscious'],
                            ':interview_notes' => $req['interview_notes'] !== null ? (string)$req['interview_notes'] : null,
                            ':icd10_none' => (int)$req['icd10_none'],
                            ':icd10_code' => $req['icd10_code'] !== null ? (string)$req['icd10_code'] : null,
                            ':icd10_name' => $req['icd10_name'] !== null ? (string)$req['icd10_name'] : null,
                            ':order_description' => $req['order_description'] !== null ? (string)$req['order_description'] : null,
                            ':from_infra' => (string)$req['from_infra'],
                            ':from_city' => (string)$req['from_city'],
                            ':from_postcode' => $req['from_postcode'] !== null ? (string)$req['from_postcode'] : null,
                            ':from_street' => (string)$req['from_street'],
                            ':from_number' => (string)$req['from_number'],
                            ':from_flat' => $req['from_flat'] !== null ? (string)$req['from_flat'] : null,
                            ':from_display' => $req['from_display'] !== null ? (string)$req['from_display'] : null,
                            ':from_lat' => $req['from_lat'] !== null ? (float)$req['from_lat'] : null,
                            ':from_lon' => $req['from_lon'] !== null ? (float)$req['from_lon'] : null,
                            ':to_infra' => (string)$req['to_infra'],
                            ':to_city' => $req['to_city'] !== null ? (string)$req['to_city'] : null,
                            ':to_postcode' => $req['to_postcode'] !== null ? (string)$req['to_postcode'] : null,
                            ':to_street' => $req['to_street'] !== null ? (string)$req['to_street'] : null,
                            ':to_number' => $req['to_number'] !== null ? (string)$req['to_number'] : null,
                            ':to_flat' => $req['to_flat'] !== null ? (string)$req['to_flat'] : null,
                            ':to_display' => $req['to_display'] !== null ? (string)$req['to_display'] : null,
                            ':to_lat' => $req['to_lat'] !== null ? (float)$req['to_lat'] : null,
                            ':to_lon' => $req['to_lon'] !== null ? (float)$req['to_lon'] : null,
                            ':distance_km' => $req['distance_km'] !== null ? (float)$req['distance_km'] : null,
                        ]);

                        $orderId = (int)$pdo->lastInsertId();

                        $pdo->prepare('DELETE FROM client_requests WHERE id = :id AND status = :old')
                            ->execute([
                                ':id' => (int)$req['id'],
                                ':old' => 'pending',
                            ]);

                        $pdo->commit();

                        flash_set('formatki', 'Potwierdzono formatkę i utworzono zlecenie #' . $orderId);
                        header('Location: ' . url('/dispatcher'));
                        exit;
                    } finally {
                        $pdo->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $lockName]);
                    }
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = $e->getMessage() !== '' ? $e->getMessage() : 'Nie udało się potwierdzić formatki.';
                }
            }
        } else {
            if ($statusNow !== 'pending') {
                $error = 'Tę formatkę nie można już edytować.';
            } else {
                $patientFirstName = $_POST['patient_first_name'] ?? '';
                $patientLastName = $_POST['patient_last_name'] ?? '';
                $patientPosition = $_POST['patient_position'] ?? '';
                $patientWeightKg = $_POST['patient_weight_kg'] ?? '';
                $phone = $_POST['phone'] ?? '';
                $orderType = $_POST['order_type'] ?? '';
                $urgency = $_POST['urgency'] ?? '';
                $plannedDate = $_POST['planned_date'] ?? '';
                $plannedTime = $_POST['planned_time'] ?? '';
                $neededTeam = $_POST['needed_team'] ?? '';
                $transportType = $_POST['transport_type'] ?? '';
                $interviewOxygen = $_POST['interview_oxygen'] ?? '';
                $interviewConscious = $_POST['interview_conscious'] ?? '';
                $icd10Code = $_POST['icd10_code'] ?? '';
                $icd10Name = $_POST['icd10_name'] ?? '';
                $icd10None = $_POST['icd10_none'] ?? '';
                $interviewNotes = $_POST['interview_notes'] ?? '';
                $orderDescription = $_POST['order_description'] ?? '';
                $fromInfra = $_POST['from_infra'] ?? '';
                $fromCity = $_POST['from_city'] ?? '';
                $fromPostcode = $_POST['from_postcode'] ?? '';
                $fromStreet = $_POST['from_street'] ?? '';
                $fromNumber = $_POST['from_number'] ?? '';
                $fromFlat = $_POST['from_flat'] ?? '';
                $fromLat = $_POST['from_lat'] ?? '';
                $fromLon = $_POST['from_lon'] ?? '';
                $toInfra = $_POST['to_infra'] ?? '';
                $toCity = $_POST['to_city'] ?? '';
                $toPostcode = $_POST['to_postcode'] ?? '';
                $toStreet = $_POST['to_street'] ?? '';
                $toNumber = $_POST['to_number'] ?? '';
                $toFlat = $_POST['to_flat'] ?? '';
                $toLat = $_POST['to_lat'] ?? '';
                $toLon = $_POST['to_lon'] ?? '';
                $fromDisplay = $_POST['from_display'] ?? '';
                $toDisplay = $_POST['to_display'] ?? '';
                $distanceKm = $_POST['distance_km'] ?? '';
                $sirens = $_POST['sirens'] ?? '';

                $patientFirstName = is_string($patientFirstName) ? trim($patientFirstName) : '';
                $patientLastName = is_string($patientLastName) ? trim($patientLastName) : '';
                $patientPosition = is_string($patientPosition) ? trim($patientPosition) : '';
                $patientWeightKg = is_string($patientWeightKg) ? trim($patientWeightKg) : '';
                $phone = is_string($phone) ? trim($phone) : '';
                $orderType = is_string($orderType) ? trim($orderType) : '';
                $urgency = is_string($urgency) ? trim($urgency) : '';
                $plannedDate = is_string($plannedDate) ? trim($plannedDate) : '';
                $plannedTime = is_string($plannedTime) ? trim($plannedTime) : '';
                $neededTeam = is_string($neededTeam) ? trim($neededTeam) : '';
                $transportType = is_string($transportType) ? trim($transportType) : '';
                $interviewOxygen = is_string($interviewOxygen) ? trim($interviewOxygen) : '';
                $interviewConscious = is_string($interviewConscious) ? trim($interviewConscious) : '';
                $icd10Code = is_string($icd10Code) ? trim($icd10Code) : '';
                $icd10Name = is_string($icd10Name) ? trim($icd10Name) : '';
                $icd10None = ($icd10None === '1');
                $interviewNotes = is_string($interviewNotes) ? trim($interviewNotes) : '';
                $orderDescription = is_string($orderDescription) ? trim($orderDescription) : '';
                $fromInfra = is_string($fromInfra) ? trim($fromInfra) : '';
                $fromCity = is_string($fromCity) ? trim($fromCity) : '';
                $fromPostcode = is_string($fromPostcode) ? trim($fromPostcode) : '';
                $fromStreet = is_string($fromStreet) ? trim($fromStreet) : '';
                $fromNumber = is_string($fromNumber) ? trim($fromNumber) : '';
                $fromFlat = is_string($fromFlat) ? trim($fromFlat) : '';
                $fromLat = is_string($fromLat) ? trim($fromLat) : '';
                $fromLon = is_string($fromLon) ? trim($fromLon) : '';
                $toInfra = is_string($toInfra) ? trim($toInfra) : '';
                $toCity = is_string($toCity) ? trim($toCity) : '';
                $toPostcode = is_string($toPostcode) ? trim($toPostcode) : '';
                $toStreet = is_string($toStreet) ? trim($toStreet) : '';
                $toNumber = is_string($toNumber) ? trim($toNumber) : '';
                $toFlat = is_string($toFlat) ? trim($toFlat) : '';
                $toLat = is_string($toLat) ? trim($toLat) : '';
                $toLon = is_string($toLon) ? trim($toLon) : '';
                $fromDisplay = is_string($fromDisplay) ? trim($fromDisplay) : '';
                $toDisplay = is_string($toDisplay) ? trim($toDisplay) : '';
                $distanceKm = is_string($distanceKm) ? trim($distanceKm) : '';
                $sirens = is_string($sirens) ? trim($sirens) : '';

                if ($patientFirstName === '' || $patientLastName === '' || $fromCity === '' || $fromStreet === '' || $fromNumber === '' || !in_array($patientPosition, $allowedPositions, true) || !in_array($neededTeam, $allowedNeededTeam, true) || !in_array($sirens, $allowedSirens, true) || !in_array($orderType, $allowedOrderType, true) || !in_array($urgency, $allowedUrgency, true) || !in_array($transportType, $allowedTransportType, true) || !in_array($fromInfra, $allowedInfra, true) || !in_array($toInfra, $allowedInfra, true) || !in_array($interviewOxygen, $allowedOxygen, true) || !in_array($interviewConscious, $allowedConscious, true) || (!$icd10None && ($icd10Code === '' || $icd10Name === ''))) {
                    $error = 'Uzupełnij dane pacjenta oraz adres miejsca zdarzenia (miasto, ulica, numer) i wymagane pola.';
                } elseif ($orderType === 'planowe' && ($plannedDate === '' || $plannedTime === '')) {
                    $error = 'Dla zlecenia planowego wskaż datę i godzinę rozpoczęcia realizacji.';
                }

                if ($error === '') {
                    $plannedAt = null;
                    if ($orderType === 'planowe') {
                        $tz = new DateTimeZone(date_default_timezone_get());
                        $plannedAt = (new DateTimeImmutable($plannedDate . ' ' . $plannedTime, $tz))->format('Y-m-d H:i:s');
                    }

                    $distanceVal = null;
                    if ($distanceKm !== '') {
                        $distanceVal = (float)str_replace(',', '.', $distanceKm);
                    }

                    $weightVal = null;
                    if ($patientWeightKg !== '') {
                        $wRaw = trim(str_replace(',', '.', $patientWeightKg));
                        if (preg_match('/^\d{1,3}$/', $wRaw)) {
                            $w = (int)$wRaw;
                            if ($w >= 1 && $w <= 350) {
                                $weightVal = $w;
                            }
                        }
                    }

                    try {
                        $pdo->prepare(
                            'UPDATE client_requests SET
                                order_type = :order_type,
                                urgency = :urgency,
                                transport_type = :transport_type,
                                needed_team = :needed_team,
                                sirens = :sirens,
                                planned_at = :planned_at,
                                patient_first_name = :patient_first_name,
                                patient_last_name = :patient_last_name,
                                patient_position = :patient_position,
                                phone = :phone,
                                patient_weight_kg = :patient_weight_kg,
                                interview_oxygen = :interview_oxygen,
                                interview_conscious = :interview_conscious,
                                interview_notes = :interview_notes,
                                icd10_none = :icd10_none,
                                icd10_code = :icd10_code,
                                icd10_name = :icd10_name,
                                order_description = :order_description,
                                from_infra = :from_infra,
                                from_city = :from_city,
                                from_postcode = :from_postcode,
                                from_street = :from_street,
                                from_number = :from_number,
                                from_flat = :from_flat,
                                from_display = :from_display,
                                from_lat = :from_lat,
                                from_lon = :from_lon,
                                to_infra = :to_infra,
                                to_city = :to_city,
                                to_postcode = :to_postcode,
                                to_street = :to_street,
                                to_number = :to_number,
                                to_flat = :to_flat,
                                to_display = :to_display,
                                to_lat = :to_lat,
                                to_lon = :to_lon,
                                distance_km = :distance_km
                             WHERE id = :id AND status = :st'
                        )->execute([
                            ':order_type' => $orderType,
                            ':urgency' => $urgency,
                            ':transport_type' => $transportType,
                            ':needed_team' => $neededTeam,
                            ':sirens' => (int)$sirens,
                            ':planned_at' => $plannedAt,
                            ':patient_first_name' => $patientFirstName,
                            ':patient_last_name' => $patientLastName,
                            ':patient_position' => $patientPosition,
                            ':phone' => ($phone !== '' ? $phone : null),
                            ':patient_weight_kg' => $weightVal,
                            ':interview_oxygen' => $interviewOxygen,
                            ':interview_conscious' => $interviewConscious,
                            ':interview_notes' => ($interviewNotes !== '' ? $interviewNotes : null),
                            ':icd10_none' => $icd10None ? 1 : 0,
                            ':icd10_code' => ($icd10None || $icd10Code === '' ? null : $icd10Code),
                            ':icd10_name' => ($icd10None || $icd10Name === '' ? null : $icd10Name),
                            ':order_description' => ($orderDescription !== '' ? $orderDescription : null),
                            ':from_infra' => $fromInfra,
                            ':from_city' => $fromCity,
                            ':from_postcode' => ($fromPostcode !== '' ? $fromPostcode : null),
                            ':from_street' => $fromStreet,
                            ':from_number' => $fromNumber,
                            ':from_flat' => ($fromFlat !== '' ? $fromFlat : null),
                            ':from_display' => ($fromDisplay !== '' ? $fromDisplay : null),
                            ':from_lat' => ($fromLat !== '' ? (float)$fromLat : null),
                            ':from_lon' => ($fromLon !== '' ? (float)$fromLon : null),
                            ':to_infra' => $toInfra,
                            ':to_city' => ($toCity !== '' ? $toCity : null),
                            ':to_postcode' => ($toPostcode !== '' ? $toPostcode : null),
                            ':to_street' => ($toStreet !== '' ? $toStreet : null),
                            ':to_number' => ($toNumber !== '' ? $toNumber : null),
                            ':to_flat' => ($toFlat !== '' ? $toFlat : null),
                            ':to_display' => ($toDisplay !== '' ? $toDisplay : null),
                            ':to_lat' => ($toLat !== '' ? (float)$toLat : null),
                            ':to_lon' => ($toLon !== '' ? (float)$toLon : null),
                            ':distance_km' => $distanceVal,
                            ':id' => (int)$req['id'],
                            ':st' => 'pending',
                        ]);

                        $req = load_request($pdo, (int)$req['id']) ?: $req;
                        $ok = 'Zmiany zapisane.';
                    } catch (Throwable $e) {
                        $error = 'Nie udało się zapisać zmian.';
                    }
                }
            }
        }
    }
}

$statusNow = (string)($req['status'] ?? '');

$plannedDate = '';
$plannedTime = '';
if (!empty($req['planned_at'])) {
    try {
        $dt = new DateTimeImmutable((string)$req['planned_at']);
        $plannedDate = $dt->format('Y-m-d');
        $plannedTime = $dt->format('H:i');
    } catch (Throwable $e) {
        $plannedDate = '';
        $plannedTime = '';
    }
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Formatka</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(url('/assets/app.js?v=' . @filemtime(__DIR__ . '/../assets/app.js')), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="is-dispatcher">
    <div class="toasts" data-toasts>
        <?php if ($error !== ''): ?>
            <div class="toast is-error" data-toast data-toast-timeout="6000">
                <div class="toast-dot"></div>
                <div>
                    <div class="toast-title">Błąd</div>
                    <div class="toast-msg"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-toast-close>×</button>
            </div>
        <?php elseif ($ok !== ''): ?>
            <div class="toast" data-toast data-toast-timeout="3500">
                <div class="toast-dot"></div>
                <div>
                    <div class="toast-title">Sukces</div>
                    <div class="toast-msg"><?= htmlspecialchars($ok, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-toast-close>×</button>
            </div>
        <?php endif; ?>
    </div>

    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-mark"></div>
                <div>
                    <div class="sidebar-title">SWDTM</div>
                    <div class="sidebar-subtitle">Dyspozytornia</div>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-item" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>">Panel dowodzenia</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/dispatcher/orders'), ENT_QUOTES, 'UTF-8') ?>">Zlecenia</a>
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/dispatcher/formatki'), ENT_QUOTES, 'UTF-8') ?>">Formatki</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/dispatcher/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
            </nav>

            <div class="sidebar-footer">
                <a class="link" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <div class="page-title">Formatka #<?= (int)$req['id'] ?></div>
                    <div class="page-subtitle"><?= htmlspecialchars((string)$req['client_name'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string)$req['client_username'], ENT_QUOTES, 'UTF-8') ?>) — status: <?= htmlspecialchars($statusNow, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher/formatki'), ENT_QUOTES, 'UTF-8') ?>">Wróć</a>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form class="form" method="post" action="<?= htmlspecialchars(url('/dispatcher/formatki/edit?id=' . (int)$req['id']), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="order-address-box">
                            <div class="order-address-title">Parametry zlecenia</div>

                            <div class="order-form">
                                <label class="field">
                                    <span class="label">Typ zlecenia</span>
                                    <select class="input" name="order_type" required data-order-type>
                                        <option value="nagłe" <?= ((string)$req['order_type'] === 'nagłe') ? 'selected' : '' ?>>Zlecenie nagłe</option>
                                        <option value="planowe" <?= ((string)$req['order_type'] === 'planowe') ? 'selected' : '' ?>>Zlecenie planowe</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">Pilność realizacji</span>
                                    <select class="input" name="urgency" required>
                                        <option value="zwykłe" <?= ((string)$req['urgency'] === 'zwykłe') ? 'selected' : '' ?>>Zwykłe</option>
                                        <option value="pilne" <?= ((string)$req['urgency'] === 'pilne') ? 'selected' : '' ?>>Pilne</option>
                                        <option value="natychmiast" <?= ((string)$req['urgency'] === 'natychmiast') ? 'selected' : '' ?>>Natychmiastowa realizacja</option>
                                    </select>
                                </label>

                                <label class="field is-full" data-planned-fields style="display:none">
                                    <span class="label">Rozpoczęcie realizacji (data i godzina)</span>
                                    <div class="order-form" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                        <label class="field">
                                            <span class="label">Data</span>
                                            <input class="input" type="date" name="planned_date" value="<?= htmlspecialchars($plannedDate, ENT_QUOTES, 'UTF-8') ?>" data-planned-date>
                                        </label>
                                        <label class="field">
                                            <span class="label">Godzina</span>
                                            <input class="input" type="text" name="planned_time" value="<?= htmlspecialchars($plannedTime, ENT_QUOTES, 'UTF-8') ?>" data-planned-time data-time-picker readonly placeholder="--:--">
                                        </label>
                                    </div>
                                </label>

                                <label class="field">
                                    <span class="label">Potrzebny zespół</span>
                                    <select class="input" name="needed_team" required>
                                        <option value="T" <?= ((string)$req['needed_team'] === 'T') ? 'selected' : '' ?>>T</option>
                                        <option value="P" <?= ((string)$req['needed_team'] === 'P') ? 'selected' : '' ?>>P</option>
                                        <option value="S" <?= ((string)$req['needed_team'] === 'S') ? 'selected' : '' ?>>S</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">Na sygnale?</span>
                                    <select class="input" name="sirens" required>
                                        <option value="1" <?= ((string)$req['sirens'] === '1') ? 'selected' : '' ?>>Tak</option>
                                        <option value="0" <?= ((string)$req['sirens'] === '0') ? 'selected' : '' ?>>Nie</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="order-address-box">
                            <div class="order-address-title">Rodzaj transportu</div>

                            <div class="order-form">
                                <label class="field is-full">
                                    <select class="input" name="transport_type" required data-transport-type>
                                        <option value="hospital" <?= ((string)$req['transport_type'] === 'hospital') ? 'selected' : '' ?>>Przekazanie do szpitala</option>
                                        <option value="poradnia" <?= ((string)$req['transport_type'] === 'poradnia') ? 'selected' : '' ?>>Wizyta w poradni</option>
                                        <option value="miedzyszpitalna" <?= ((string)$req['transport_type'] === 'miedzyszpitalna') ? 'selected' : '' ?>>Konsultacja międzyszpitalna</option>
                                        <option value="dom" <?= ((string)$req['transport_type'] === 'dom') ? 'selected' : '' ?>>Odwóz do domu</option>
                                        <option value="transport prywatny" <?= ((string)$req['transport_type'] === 'transport prywatny') ? 'selected' : '' ?>>Transport prywatny</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="order-modal-grid">
                            <div class="order-col">
                                <div class="order-address-box">
                                    <div class="order-address-title">Dane pacjenta</div>

                                    <div class="order-form">
                                        <label class="field">
                                            <span class="label">Imię pacjenta</span>
                                            <input class="input" type="text" name="patient_first_name" value="<?= htmlspecialchars((string)$req['patient_first_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </label>

                                        <label class="field">
                                            <span class="label">Nazwisko pacjenta</span>
                                            <input class="input" type="text" name="patient_last_name" value="<?= htmlspecialchars((string)$req['patient_last_name'], ENT_QUOTES, 'UTF-8') ?>" required>
                                        </label>

                                        <label class="field">
                                            <span class="label">Pozycja pacjenta</span>
                                            <select class="input" name="patient_position" required>
                                                <option value="chodzi" <?= ((string)$req['patient_position'] === 'chodzi') ? 'selected' : '' ?>>Chodzi</option>
                                                <option value="siedzi" <?= ((string)$req['patient_position'] === 'siedzi') ? 'selected' : '' ?>>Siedzi</option>
                                                <option value="leży" <?= ((string)$req['patient_position'] === 'leży') ? 'selected' : '' ?>>Leży</option>
                                            </select>
                                        </label>

                                        <label class="field">
                                            <span class="label">Waga (kg)</span>
                                            <input class="input" type="number" name="patient_weight_kg" value="<?= htmlspecialchars((string)($req['patient_weight_kg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" min="1" max="350" step="1">
                                        </label>

                                        <label class="field">
                                            <span class="label">Numer telefonu</span>
                                            <input class="input" type="text" name="phone" value="<?= htmlspecialchars((string)($req['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </label>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Podstawowy wywiad</div>

                                    <div class="order-form">
                                        <label class="field">
                                            <span class="label">Czy pacjent wymaga tlenu?</span>
                                            <select class="input" name="interview_oxygen" required>
                                                <option value="nie" <?= ((string)$req['interview_oxygen'] === 'nie') ? 'selected' : '' ?>>Nie</option>
                                                <option value="tak" <?= ((string)$req['interview_oxygen'] === 'tak') ? 'selected' : '' ?>>Tak</option>
                                                <option value="nie_wiadomo" <?= ((string)$req['interview_oxygen'] === 'nie_wiadomo') ? 'selected' : '' ?>>Nie wiadomo</option>
                                            </select>
                                        </label>

                                        <label class="field">
                                            <span class="label">Czy pacjent jest przytomny?</span>
                                            <select class="input" name="interview_conscious" required>
                                                <option value="tak" <?= ((string)$req['interview_conscious'] === 'tak') ? 'selected' : '' ?>>Tak</option>
                                                <option value="nie" <?= ((string)$req['interview_conscious'] === 'nie') ? 'selected' : '' ?>>Nie</option>
                                                <option value="nie_wiadomo" <?= ((string)$req['interview_conscious'] === 'nie_wiadomo') ? 'selected' : '' ?>>Nie wiadomo</option>
                                            </select>
                                        </label>

                                        <input type="hidden" name="icd10_code" value="<?= htmlspecialchars((string)($req['icd10_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-icd10-code>
                                        <input type="hidden" name="icd10_name" value="<?= htmlspecialchars((string)($req['icd10_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-icd10-name>

                                        <div class="suggest" data-icd10-wrapper>
                                            <label class="field is-full">
                                                <span class="label">Główne rozpoznanie ICD-10</span>
                                                <input class="input" type="text" name="icd10_search" value="<?= htmlspecialchars((string)($req['icd10_code'] ? ((string)$req['icd10_code'] . ' — ' . (string)$req['icd10_name']) : ''), ENT_QUOTES, 'UTF-8') ?>" data-icd10 autocomplete="off">
                                            </label>
                                            <label class="field is-full check-row">
                                                <input class="check-input" type="checkbox" name="icd10_none" value="1" data-icd10-none <?= ((int)($req['icd10_none'] ?? 0) === 1) ? 'checked' : '' ?>>
                                                <span class="check-text">Brak rozpoznania ICD-10</span>
                                            </label>
                                        </div>

                                        <label class="field is-full">
                                            <span class="label">Dodatkowe informacje (opcjonalnie)</span>
                                            <input class="input" type="text" name="interview_notes" value="<?= htmlspecialchars((string)($req['interview_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                        </label>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Opis zlecenia</div>

                                    <div class="order-form">
                                        <label class="field is-full">
                                            <textarea class="input" name="order_description" rows="8"><?= htmlspecialchars((string)($req['order_description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="order-col">
                                <div class="order-address-grid">
                                    <div class="order-address-box" data-photon-scope>
                                        <div class="order-address-title">Skąd</div>

                                        <input type="hidden" name="from_lat" value="<?= htmlspecialchars((string)($req['from_lat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-lat>
                                        <input type="hidden" name="from_lon" value="<?= htmlspecialchars((string)($req['from_lon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-lon>
                                        <input type="hidden" name="from_display" value="<?= htmlspecialchars((string)($req['from_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-display>

                                        <div class="order-form">
                                            <label class="field">
                                                <span class="label">Rodzaj infrastruktury</span>
                                                <select class="input" name="from_infra" required>
                                                    <option value="dom" <?= ((string)$req['from_infra'] === 'dom') ? 'selected' : '' ?>>Dom</option>
                                                    <option value="blok mieszkalny" <?= ((string)$req['from_infra'] === 'blok mieszkalny') ? 'selected' : '' ?>>Blok mieszkalny</option>
                                                    <option value="szpital" <?= ((string)$req['from_infra'] === 'szpital') ? 'selected' : '' ?>>Szpital</option>
                                                    <option value="poradnia" <?= ((string)$req['from_infra'] === 'poradnia') ? 'selected' : '' ?>>Poradnia</option>
                                                    <option value="inne" <?= ((string)$req['from_infra'] === 'inne') ? 'selected' : '' ?>>Inne</option>
                                                </select>
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Miejscowość</span>
                                                    <input class="input" type="text" name="from_city" value="<?= htmlspecialchars((string)$req['from_city'], ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="city" data-photon-field="city" required>
                                                </label>
                                            </div>

                                            <label class="field">
                                                <span class="label">Kod pocztowy</span>
                                                <input class="input" type="text" name="from_postcode" value="<?= htmlspecialchars((string)($req['from_postcode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly data-photon-field="postcode">
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Ulica</span>
                                                    <input class="input" type="text" name="from_street" value="<?= htmlspecialchars((string)$req['from_street'], ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="street" data-photon-field="street" required>
                                                </label>
                                            </div>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Numer</span>
                                                    <input class="input" type="text" name="from_number" value="<?= htmlspecialchars((string)$req['from_number'], ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="number" data-photon-field="number" required>
                                                </label>
                                            </div>

                                            <label class="field is-full">
                                                <span class="label">Lokal</span>
                                                <input class="input" type="text" name="from_flat" value="<?= htmlspecialchars((string)($req['from_flat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="order-address-box" data-photon-scope>
                                        <div class="order-address-title">Dokąd</div>

                                        <input type="hidden" name="to_lat" value="<?= htmlspecialchars((string)($req['to_lat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-lat>
                                        <input type="hidden" name="to_lon" value="<?= htmlspecialchars((string)($req['to_lon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-lon>
                                        <input type="hidden" name="to_display" value="<?= htmlspecialchars((string)($req['to_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-display>

                                        <div class="order-form">
                                            <label class="field">
                                                <span class="label">Rodzaj infrastruktury</span>
                                                <select class="input" name="to_infra" required>
                                                    <option value="dom" <?= ((string)$req['to_infra'] === 'dom') ? 'selected' : '' ?>>Dom</option>
                                                    <option value="blok mieszkalny" <?= ((string)$req['to_infra'] === 'blok mieszkalny') ? 'selected' : '' ?>>Blok mieszkalny</option>
                                                    <option value="szpital" <?= ((string)$req['to_infra'] === 'szpital') ? 'selected' : '' ?>>Szpital</option>
                                                    <option value="poradnia" <?= ((string)$req['to_infra'] === 'poradnia') ? 'selected' : '' ?>>Poradnia</option>
                                                    <option value="inne" <?= ((string)$req['to_infra'] === 'inne') ? 'selected' : '' ?>>Inne</option>
                                                </select>
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Miejscowość</span>
                                                    <input class="input" type="text" name="to_city" value="<?= htmlspecialchars((string)($req['to_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="city" data-photon-field="city">
                                                </label>
                                            </div>

                                            <label class="field">
                                                <span class="label">Kod pocztowy</span>
                                                <input class="input" type="text" name="to_postcode" value="<?= htmlspecialchars((string)($req['to_postcode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly data-photon-field="postcode">
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Ulica</span>
                                                    <input class="input" type="text" name="to_street" value="<?= htmlspecialchars((string)($req['to_street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="street" data-photon-field="street">
                                                </label>
                                            </div>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Numer</span>
                                                    <input class="input" type="text" name="to_number" value="<?= htmlspecialchars((string)($req['to_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="number" data-photon-field="number">
                                                </label>
                                            </div>

                                            <label class="field is-full">
                                                <span class="label">Lokal</span>
                                                <input class="input" type="text" name="to_flat" value="<?= htmlspecialchars((string)($req['to_flat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Dystans</div>

                                    <div class="order-form">
                                        <label class="field is-full">
                                            <span class="label">Ilość km</span>
                                            <input class="input" type="text" name="distance_km" value="<?= htmlspecialchars((string)($req['distance_km'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly data-distance-km>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-actions" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
                            <button class="btn-secondary" type="submit">Zapisz</button>
                            <button class="btn-danger" type="submit" name="action" value="reject">Odrzuć</button>
                            <button class="btn-primary" type="submit" name="action" value="confirm">Potwierdź i utwórz zlecenie</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.SWDTM = window.SWDTM || {};
        window.SWDTM.geocoderProvider = 'photon';
        window.SWDTM.geocoderUrl = <?= json_encode(url('/api/photon.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.icd10Url = <?= json_encode(url('/api/icd10'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
</body>
</html>
