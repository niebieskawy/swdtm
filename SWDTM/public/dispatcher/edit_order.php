<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

$user = current_user();

$pendingCount = 0;
try {
    $pdo0 = db();
    $stCnt = $pdo0->query("SELECT COUNT(*) FROM client_requests WHERE status = 'pending'");
    $pendingCount = (int)($stCnt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $pendingCount = 0;
}

$error = '';

$id = $_GET['id'] ?? ($_POST['id'] ?? '');
$id = is_string($id) ? trim($id) : '';

if ($id === '' || !ctype_digit($id)) {
    header('Location: ' . url('/dispatcher'));
    exit;
}

$order = null;
try {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$id]);
    $order = $stmt->fetch();
} catch (Throwable $e) {
    $order = null;
}

if (!$order) {
    flash_set('dispatcher', 'Nie znaleziono zlecenia.', 'alert');
    header('Location: ' . url('/dispatcher'));
    exit;
}

$patientFirstName = (string)($order['patient_first_name'] ?? '');
$patientLastName = (string)($order['patient_last_name'] ?? '');
$patientPosition = (string)($order['patient_position'] ?? '');
$patientWeightKg = (string)($order['patient_weight_kg'] ?? '');
$phone = (string)($order['phone'] ?? '');
$orderType = (string)($order['order_type'] ?? 'nagłe');
$urgency = (string)($order['urgency'] ?? 'zwykłe');
$neededTeam = (string)($order['needed_team'] ?? 'P');
$transportType = (string)($order['transport_type'] ?? 'hospital');
$sirens = (string)($order['sirens'] ?? '0');

$plannedDate = '';
$plannedTime = '';
if (!empty($order['planned_at'])) {
    try {
        $dt = new DateTimeImmutable((string)$order['planned_at']);
        $plannedDate = $dt->format('Y-m-d');
        $plannedTime = $dt->format('H:i');
    } catch (Throwable $e) {
        $plannedDate = '';
        $plannedTime = '';
    }
}

$interviewOxygen = (string)($order['interview_oxygen'] ?? 'nie');
$interviewConscious = (string)($order['interview_conscious'] ?? 'tak');
$interviewNotes = (string)($order['interview_notes'] ?? '');

$icd10None = ((int)($order['icd10_none'] ?? 0) === 1);
$icd10Code = (string)($order['icd10_code'] ?? '');
$icd10Name = (string)($order['icd10_name'] ?? '');
$orderDescription = (string)($order['order_description'] ?? '');

$fromInfra = (string)($order['from_infra'] ?? 'dom');
$fromCity = (string)($order['from_city'] ?? '');
$fromPostcode = (string)($order['from_postcode'] ?? '');
$fromStreet = (string)($order['from_street'] ?? '');
$fromNumber = (string)($order['from_number'] ?? '');
$fromFlat = (string)($order['from_flat'] ?? '');
$fromLat = (string)($order['from_lat'] ?? '');
$fromLon = (string)($order['from_lon'] ?? '');
$fromDisplay = (string)($order['from_display'] ?? '');

$toInfra = (string)($order['to_infra'] ?? 'dom');
$toCity = (string)($order['to_city'] ?? '');
$toPostcode = (string)($order['to_postcode'] ?? '');
$toStreet = (string)($order['to_street'] ?? '');
$toNumber = (string)($order['to_number'] ?? '');
$toFlat = (string)($order['to_flat'] ?? '');
$toLat = (string)($order['to_lat'] ?? '');
$toLon = (string)($order['to_lon'] ?? '');
$toDisplay = (string)($order['to_display'] ?? '');

$distanceKm = (string)($order['distance_km'] ?? '');

$num = (string)($order['order_seq'] ?? '');
$mm = isset($order['order_month']) ? str_pad((string)(int)$order['order_month'], 2, '0', STR_PAD_LEFT) : '';
$yy = (string)($order['order_year'] ?? '');
$orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$order['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Nieprawidłowy token formularza.';
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

        $allowedPositions = ['chodzi', 'siedzi', 'leży'];
        $allowedNeededTeam = ['T', 'P', 'S'];
        $allowedSirens = ['0', '1'];
        $allowedOrderType = ['nagłe', 'planowe'];
        $allowedUrgency = ['zwykłe', 'pilne', 'natychmiast'];
        $allowedTransportType = ['hospital', 'poradnia', 'miedzyszpitalna', 'dom', 'transport prywatny'];
        $allowedInfra = ['dom', 'blok mieszkalny', 'szpital', 'poradnia', 'inne'];
        $allowedOxygen = ['tak', 'nie', 'nie_wiadomo'];
        $allowedConscious = ['tak', 'nie', 'nie_wiadomo'];

        if ($patientFirstName === '' || $patientLastName === '' || $fromCity === '' || $fromStreet === '' || $fromNumber === '' || !in_array($patientPosition, $allowedPositions, true) || !in_array($neededTeam, $allowedNeededTeam, true) || !in_array($sirens, $allowedSirens, true) || !in_array($orderType, $allowedOrderType, true) || !in_array($urgency, $allowedUrgency, true) || !in_array($transportType, $allowedTransportType, true) || !in_array($fromInfra, $allowedInfra, true) || !in_array($toInfra, $allowedInfra, true) || !in_array($interviewOxygen, $allowedOxygen, true) || !in_array($interviewConscious, $allowedConscious, true) || (!$icd10None && ($icd10Code === '' || $icd10Name === ''))) {
            $error = 'Uzupełnij dane pacjenta oraz adres miejsca zdarzenia (miasto, ulica, numer) i wymagane pola.';
        } elseif ($orderType === 'planowe' && ($plannedDate === '' || $plannedTime === '')) {
            $error = 'Dla zlecenia planowego wskaż datę i godzinę rozpoczęcia realizacji.';
        }

        if ($error === '') {
            $plannedAt = null;
            if ($orderType === 'planowe') {
                try {
                    $tz = new DateTimeZone(date_default_timezone_get());
                    $plannedAt = (new DateTimeImmutable($plannedDate . ' ' . $plannedTime, $tz))->format('Y-m-d H:i:s');
                } catch (Throwable $e) {
                    $plannedAt = null;
                }
            }

            $distanceVal = null;
            if ($distanceKm !== '') {
                $distanceVal = (float)str_replace(',', '.', $distanceKm);
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

                $patientWeightVal = null;
                if ($patientWeightKg !== '') {
                    $wRaw = trim(str_replace(',', '.', $patientWeightKg));
                    if (!preg_match('/^\d{1,3}$/', $wRaw)) {
                        throw new RuntimeException('Nieprawidłowa waga pacjenta.');
                    }
                    $w = (int)$wRaw;
                    if ($w < 1 || $w > 350) {
                        throw new RuntimeException('Nieprawidłowa waga pacjenta.');
                    }
                    $patientWeightVal = $w;
                }

                if (!$hasPatientWeight && $patientWeightVal !== null) {
                    try {
                        $pdo->exec('ALTER TABLE orders ADD COLUMN patient_weight_kg INT NULL');
                        $hasPatientWeight = true;
                    } catch (Throwable $e) {
                        throw new RuntimeException('Brak możliwości zapisania wagi pacjenta (kolumna patient_weight_kg nie istnieje w bazie).');
                    }
                }

                $sql = 'UPDATE orders SET
                        order_type = :order_type,
                        urgency = :urgency,
                        transport_type = :transport_type,
                        needed_team = :needed_team,
                        sirens = :sirens,
                        planned_at = :planned_at,
                        patient_first_name = :patient_first_name,
                        patient_last_name = :patient_last_name,
                        patient_position = :patient_position,';
                if ($hasPatientWeight) {
                    $sql .= "\n                        patient_weight_kg = :patient_weight_kg,";
                }
                $sql .= '
                        phone = :phone,
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
                    WHERE id = :id';
                $stmt = $pdo->prepare($sql);

                $paramsUp = [
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
                    ':id' => (int)$id,
                ];
                if ($hasPatientWeight) {
                    $paramsUp[':patient_weight_kg'] = $patientWeightVal;
                }
                $stmt->execute($paramsUp);

                flash_set('dispatcher', 'Zapisano zmiany w zleceniu.');
                header('Location: ' . url('/dispatcher'));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage() !== '' ? $e->getMessage() : 'Nie udało się zapisać zmian.';
            }
        }
    }
}

$icd10Search = '';
if (!$icd10None) {
    $icd10Search = $icd10Code !== '' ? ($icd10Code . ' — ' . $icd10Name) : $icd10Name;
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Edycja zlecenia</title>
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
                <a class="nav-item<?= ($pendingCount > 0) ? ' is-attention' : '' ?>" href="<?= htmlspecialchars(url('/dispatcher/formatki'), ENT_QUOTES, 'UTF-8') ?>">Formatki<?php if ($pendingCount > 0): ?><span class="nav-badge"><?= (int)$pendingCount ?></span><?php endif; ?></a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/dispatcher/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
            </nav>

            <div class="sidebar-footer">
                <div class="userchip">
                    <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr(!empty($user['full_name']) ? (string)$user['full_name'] : (string)$user['username'], 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div>
                        <div class="userchip-name"><?= htmlspecialchars(!empty($user['full_name']) ? (string)$user['full_name'] : (string)$user['username'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="userchip-role">Dyspozytor</div>
                    </div>
                </div>
                <a class="link" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <div class="page-title">Edycja zlecenia</div>
                    <div class="page-subtitle"><?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <div class="topbar-actions">
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>">Wróć</a>
                </div>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form id="edit-order-form" class="form" method="post" action="<?= htmlspecialchars(url('/dispatcher/edit-order'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="id" value="<?= htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') ?>">

                        <div class="order-address-box">
                            <div class="order-address-title">Parametry zlecenia</div>

                            <div class="order-form">
                                <label class="field">
                                    <span class="label">Typ zlecenia</span>
                                    <select class="input" name="order_type" required data-order-type>
                                        <option value="nagłe"<?= $orderType === 'nagłe' ? ' selected' : '' ?>>Zlecenie nagłe</option>
                                        <option value="planowe"<?= $orderType === 'planowe' ? ' selected' : '' ?>>Zlecenie planowe</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">Pilność realizacji</span>
                                    <select class="input" name="urgency" required>
                                        <option value="zwykłe"<?= $urgency === 'zwykłe' ? ' selected' : '' ?>>Zwykłe</option>
                                        <option value="pilne"<?= $urgency === 'pilne' ? ' selected' : '' ?>>Pilne</option>
                                        <option value="natychmiast"<?= $urgency === 'natychmiast' ? ' selected' : '' ?>>Natychmiastowa realizacja</option>
                                    </select>
                                </label>

                                <label class="field is-full" data-planned-fields style="<?= $orderType === 'planowe' ? '' : 'display:none' ?>">
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
                                        <option value="T"<?= $neededTeam === 'T' ? ' selected' : '' ?>>T</option>
                                        <option value="P"<?= $neededTeam === 'P' ? ' selected' : '' ?>>P</option>
                                        <option value="S"<?= $neededTeam === 'S' ? ' selected' : '' ?>>S</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">Na sygnale?</span>
                                    <select class="input" name="sirens" required>
                                        <option value="1"<?= $sirens === '1' ? ' selected' : '' ?>>Tak</option>
                                        <option value="0"<?= $sirens === '0' ? ' selected' : '' ?>>Nie</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="order-address-box">
                            <div class="order-address-title">Rodzaj transportu</div>

                            <div class="order-form">
                                <label class="field is-full">
                                    <select class="input" name="transport_type" required data-transport-type>
                                        <option value="hospital"<?= $transportType === 'hospital' ? ' selected' : '' ?>>Przekazanie do szpitala</option>
                                        <option value="poradnia"<?= $transportType === 'poradnia' ? ' selected' : '' ?>>Wizyta w poradni</option>
                                        <option value="miedzyszpitalna"<?= $transportType === 'miedzyszpitalna' ? ' selected' : '' ?>>Konsultacja międzyszpitalna</option>
                                        <option value="dom"<?= $transportType === 'dom' ? ' selected' : '' ?>>Odwóz do domu</option>
                                        <option value="transport prywatny"<?= $transportType === 'transport prywatny' ? ' selected' : '' ?>>Transport prywatny</option>
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
                                            <input class="input" type="text" name="patient_first_name" value="<?= htmlspecialchars($patientFirstName, ENT_QUOTES, 'UTF-8') ?>" required>
                                        </label>

                                        <label class="field">
                                            <span class="label">Nazwisko pacjenta</span>
                                            <input class="input" type="text" name="patient_last_name" value="<?= htmlspecialchars($patientLastName, ENT_QUOTES, 'UTF-8') ?>" required>
                                        </label>

                                        <label class="field">
                                            <span class="label">Pozycja pacjenta</span>
                                            <select class="input" name="patient_position" required>
                                                <option value="chodzi"<?= $patientPosition === 'chodzi' ? ' selected' : '' ?>>Chodzi</option>
                                                <option value="siedzi"<?= $patientPosition === 'siedzi' ? ' selected' : '' ?>>Siedzi</option>
                                                <option value="leży"<?= $patientPosition === 'leży' ? ' selected' : '' ?>>Leży</option>
                                            </select>
                                        </label>

                                        <label class="field">
                                            <span class="label">Waga (kg)</span>
                                            <input class="input" type="number" name="patient_weight_kg" value="<?= htmlspecialchars($patientWeightKg, ENT_QUOTES, 'UTF-8') ?>" inputmode="numeric" min="1" max="350" step="1">
                                        </label>

                                        <label class="field">
                                            <span class="label">Numer telefonu</span>
                                            <input class="input" type="text" name="phone" value="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>">
                                        </label>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Podstawowy wywiad</div>

                                    <div class="order-form">
                                        <label class="field">
                                            <span class="label">Czy pacjent wymaga tlenu?</span>
                                            <select class="input" name="interview_oxygen" required>
                                                <option value="nie"<?= $interviewOxygen === 'nie' ? ' selected' : '' ?>>Nie</option>
                                                <option value="tak"<?= $interviewOxygen === 'tak' ? ' selected' : '' ?>>Tak</option>
                                                <option value="nie_wiadomo"<?= $interviewOxygen === 'nie_wiadomo' ? ' selected' : '' ?>>Nie wiadomo</option>
                                            </select>
                                        </label>

                                        <label class="field">
                                            <span class="label">Czy pacjent jest przytomny?</span>
                                            <select class="input" name="interview_conscious" required>
                                                <option value="tak"<?= $interviewConscious === 'tak' ? ' selected' : '' ?>>Tak</option>
                                                <option value="nie"<?= $interviewConscious === 'nie' ? ' selected' : '' ?>>Nie</option>
                                                <option value="nie_wiadomo"<?= $interviewConscious === 'nie_wiadomo' ? ' selected' : '' ?>>Nie wiadomo</option>
                                            </select>
                                        </label>

                                        <input type="hidden" name="icd10_code" value="<?= htmlspecialchars($icd10Code, ENT_QUOTES, 'UTF-8') ?>" data-icd10-code>
                                        <input type="hidden" name="icd10_name" value="<?= htmlspecialchars($icd10Name, ENT_QUOTES, 'UTF-8') ?>" data-icd10-name>

                                        <div class="suggest" data-icd10-wrapper>
                                            <label class="field is-full">
                                                <span class="label">Główne rozpoznanie ICD-10</span>
                                                <input class="input" type="text" name="icd10_search" value="<?= htmlspecialchars($icd10Search, ENT_QUOTES, 'UTF-8') ?>" data-icd10 autocomplete="off">
                                            </label>
                                            <label class="field is-full check-row">
                                                <input class="check-input" type="checkbox" name="icd10_none" value="1"<?= $icd10None ? ' checked' : '' ?> data-icd10-none>
                                                <span class="check-text">Brak rozpoznania ICD-10</span>
                                            </label>
                                        </div>

                                        <label class="field is-full">
                                            <span class="label">Dodatkowe informacje (opcjonalnie)</span>
                                            <input class="input" type="text" name="interview_notes" value="<?= htmlspecialchars($interviewNotes, ENT_QUOTES, 'UTF-8') ?>">
                                        </label>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Opis zlecenia</div>

                                    <div class="order-form">
                                        <label class="field is-full">
                                            <textarea class="input" name="order_description" rows="8"><?= htmlspecialchars($orderDescription, ENT_QUOTES, 'UTF-8') ?></textarea>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="order-col">
                                <div class="order-address-grid">
                                    <div class="order-address-box" data-photon-scope>
                                        <div class="order-address-title">Skąd</div>

                                        <input type="hidden" name="from_lat" value="<?= htmlspecialchars($fromLat, ENT_QUOTES, 'UTF-8') ?>" data-photon-lat>
                                        <input type="hidden" name="from_lon" value="<?= htmlspecialchars($fromLon, ENT_QUOTES, 'UTF-8') ?>" data-photon-lon>
                                        <input type="hidden" name="from_display" value="<?= htmlspecialchars($fromDisplay, ENT_QUOTES, 'UTF-8') ?>" data-photon-display>

                                        <div class="order-form">
                                            <label class="field">
                                                <span class="label">Rodzaj infrastruktury</span>
                                                <select class="input" name="from_infra" required>
                                                    <option value="dom"<?= $fromInfra === 'dom' ? ' selected' : '' ?>>Dom</option>
                                                    <option value="blok mieszkalny"<?= $fromInfra === 'blok mieszkalny' ? ' selected' : '' ?>>Blok mieszkalny</option>
                                                    <option value="szpital"<?= $fromInfra === 'szpital' ? ' selected' : '' ?>>Szpital</option>
                                                    <option value="poradnia"<?= $fromInfra === 'poradnia' ? ' selected' : '' ?>>Poradnia</option>
                                                    <option value="inne"<?= $fromInfra === 'inne' ? ' selected' : '' ?>>Inne</option>
                                                </select>
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Miejscowość</span>
                                                    <input class="input" type="text" name="from_city" value="<?= htmlspecialchars($fromCity, ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="city" data-photon-field="city" required>
                                                </label>
                                            </div>

                                            <label class="field">
                                                <span class="label">Kod pocztowy</span>
                                                <input class="input" type="text" name="from_postcode" value="<?= htmlspecialchars($fromPostcode, ENT_QUOTES, 'UTF-8') ?>" readonly data-photon-field="postcode">
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Ulica</span>
                                                    <input class="input" type="text" name="from_street" value="<?= htmlspecialchars($fromStreet, ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="street" data-photon-field="street" required>
                                                </label>
                                            </div>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Numer</span>
                                                    <input class="input" type="text" name="from_number" value="<?= htmlspecialchars($fromNumber, ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="number" data-photon-field="number" required>
                                                </label>
                                            </div>

                                            <label class="field is-full">
                                                <span class="label">Lokal</span>
                                                <input class="input" type="text" name="from_flat" value="<?= htmlspecialchars($fromFlat, ENT_QUOTES, 'UTF-8') ?>">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="order-address-box" data-photon-scope>
                                        <div class="order-address-title">Dokąd</div>

                                        <input type="hidden" name="to_lat" value="<?= htmlspecialchars($toLat, ENT_QUOTES, 'UTF-8') ?>" data-photon-lat>
                                        <input type="hidden" name="to_lon" value="<?= htmlspecialchars($toLon, ENT_QUOTES, 'UTF-8') ?>" data-photon-lon>
                                        <input type="hidden" name="to_display" value="<?= htmlspecialchars($toDisplay, ENT_QUOTES, 'UTF-8') ?>" data-photon-display>

                                        <div class="order-form">
                                            <label class="field">
                                                <span class="label">Rodzaj infrastruktury</span>
                                                <select class="input" name="to_infra" required>
                                                    <option value="dom"<?= $toInfra === 'dom' ? ' selected' : '' ?>>Dom</option>
                                                    <option value="blok mieszkalny"<?= $toInfra === 'blok mieszkalny' ? ' selected' : '' ?>>Blok mieszkalny</option>
                                                    <option value="szpital"<?= $toInfra === 'szpital' ? ' selected' : '' ?>>Szpital</option>
                                                    <option value="poradnia"<?= $toInfra === 'poradnia' ? ' selected' : '' ?>>Poradnia</option>
                                                    <option value="inne"<?= $toInfra === 'inne' ? ' selected' : '' ?>>Inne</option>
                                                </select>
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Miejscowość</span>
                                                    <input class="input" type="text" name="to_city" value="<?= htmlspecialchars($toCity, ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="city" data-photon-field="city">
                                                </label>
                                            </div>

                                            <label class="field">
                                                <span class="label">Kod pocztowy</span>
                                                <input class="input" type="text" name="to_postcode" value="<?= htmlspecialchars($toPostcode, ENT_QUOTES, 'UTF-8') ?>" readonly data-photon-field="postcode">
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Ulica</span>
                                                    <input class="input" type="text" name="to_street" value="<?= htmlspecialchars($toStreet, ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="street" data-photon-field="street">
                                                </label>
                                            </div>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Numer</span>
                                                    <input class="input" type="text" name="to_number" value="<?= htmlspecialchars($toNumber, ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="number" data-photon-field="number">
                                                </label>
                                            </div>

                                            <label class="field is-full">
                                                <span class="label">Lokal</span>
                                                <input class="input" type="text" name="to_flat" value="<?= htmlspecialchars($toFlat, ENT_QUOTES, 'UTF-8') ?>">
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <input type="hidden" name="distance_km" value="<?= htmlspecialchars($distanceKm, ENT_QUOTES, 'UTF-8') ?>" data-distance-km>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <button class="btn-primary" type="submit">Zapisz zmiany</button>
                            <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>">Anuluj</a>
                        </div>

                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
