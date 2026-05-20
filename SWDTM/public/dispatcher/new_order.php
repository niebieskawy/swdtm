<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
require_role(['dispatcher']);

$user = current_user();

$pendingCount = 0;
try {
    $pdo = db();
    $stCnt = $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status = 'pending'");
    $pendingCount = (int)($stCnt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $pendingCount = 0;
}

$error = '';

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
        } elseif ($orderType === 'planowe') {
            $m = [];
            if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $plannedTime, $m)) {
                $error = 'Nieprawidłowa godzina.';
            } else {
                $minutes = (int)$m[2];
                if ($minutes % 10 !== 0) {
                    $error = 'Godzina musi być ustawiana co 10 minut.';
                } else {
                    try {
                        $tz = new DateTimeZone(date_default_timezone_get());
                        $now = new DateTimeImmutable('now', $tz);
                        $planned = new DateTimeImmutable($plannedDate . ' ' . $plannedTime, $tz);
                        if ($planned < $now) {
                            $error = 'Nie można wybrać daty i godziny w przeszłości.';
                        }
                    } catch (Throwable $e) {
                        $error = 'Nieprawidłowa data lub godzina.';
                    }
                }
            }
        }

        if ($error === '') {
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

                $now = new DateTimeImmutable('now', new DateTimeZone(date_default_timezone_get()));
                $orderMonth = (int)$now->format('m');
                $orderYear = (int)$now->format('Y');
                $lockName = sprintf('orders_number_%04d_%02d', $orderYear, $orderMonth);

                $pdo->beginTransaction();
                $gotLock = false;
                try {
                    $lockStmt = $pdo->prepare('SELECT GET_LOCK(:n, 5)');
                    $lockStmt->execute([':n' => $lockName]);
                    $gotLock = ((int)$lockStmt->fetchColumn() === 1);
                    if (!$gotLock) {
                        throw new RuntimeException('Nie udało się uzyskać blokady numeracji.');
                    }

                    $seqStmt = $pdo->prepare(
                        'SELECT order_seq
                         FROM orders
                         WHERE order_year = :y AND order_month = :m
                         ORDER BY order_seq DESC
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $seqStmt->execute([':y' => $orderYear, ':m' => $orderMonth]);
                    $lastSeq = (int)($seqStmt->fetchColumn() ?: 0);
                    $orderSeq = $lastSeq + 1;

                $plannedAt = null;
                if ($orderType === 'planowe') {
                    $tz = new DateTimeZone(date_default_timezone_get());
                    $plannedAt = (new DateTimeImmutable($plannedDate . ' ' . $plannedTime, $tz))->format('Y-m-d H:i:s');
                }

                $distanceVal = null;
                if ($distanceKm !== '') {
                    $distanceVal = (float)str_replace(',', '.', $distanceKm);
                }

                $insertCols = [
                    'dispatcher_id',
                    'order_seq', 'order_month', 'order_year',
                    'order_type', 'urgency', 'transport_type', 'needed_team', 'sirens',
                    'planned_at',
                    'patient_first_name', 'patient_last_name', 'patient_position',
                    'phone',
                ];
                if ($hasPatientWeight) {
                    $insertCols[] = 'patient_weight_kg';
                }
                $insertCols = array_merge($insertCols, [
                    'interview_oxygen', 'interview_conscious', 'interview_notes',
                    'icd10_none', 'icd10_code', 'icd10_name',
                    'order_description',
                    'from_infra', 'from_city', 'from_postcode', 'from_street', 'from_number', 'from_flat', 'from_display', 'from_lat', 'from_lon',
                    'to_infra', 'to_city', 'to_postcode', 'to_street', 'to_number', 'to_flat', 'to_display', 'to_lat', 'to_lon',
                    'distance_km',
                ]);

                $ph = array_map(static fn($c) => ':' . $c, $insertCols);
                $stmt = $pdo->prepare(
                    'INSERT INTO orders (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $ph) . ')'
                );

                $paramsIns = [
                    ':dispatcher_id' => $user ? (int)$user['id'] : null,
                    ':order_seq' => $orderSeq,
                    ':order_month' => $orderMonth,
                    ':order_year' => $orderYear,
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
                ];
                if ($hasPatientWeight) {
                    $paramsIns[':patient_weight_kg'] = $patientWeightVal;
                }
                $stmt->execute($paramsIns);

                    $pdo->commit();
                } finally {
                    if ($gotLock) {
                        $pdo->prepare('SELECT RELEASE_LOCK(:n)')->execute([':n' => $lockName]);
                    }
                }

                flash_set('dispatcher', 'Przyjęto nowe zlecenie.');
                header('Location: ' . url('/dispatcher'));
                exit;
            } catch (Throwable $e) {
                if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage() !== '' ? $e->getMessage() : 'Nie udało się zapisać zlecenia do bazy.';
            }
        }
    }
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Nowe zlecenie</title>
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
                    <div class="page-title">Nowe zlecenie</div>
                    <div class="page-subtitle">Wprowadzanie danych zlecenia</div>
                </div>
                <div class="topbar-actions">
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>" data-confirm-leave>Wróć</a>
                </div>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form id="new-order-form" class="form" method="post" action="<?= htmlspecialchars(url('/dispatcher/new-order'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <div class="order-address-box">
                            <div class="order-address-title">Parametry zlecenia</div>

                            <div class="order-form">
                                <label class="field">
                                    <span class="label">Typ zlecenia</span>
                                    <select class="input" name="order_type" required data-order-type>
                                        <option value="nagłe" selected>Zlecenie nagłe</option>
                                        <option value="planowe">Zlecenie planowe</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">Pilność realizacji</span>
                                    <select class="input" name="urgency" required>
                                        <option value="zwykłe" selected>Zwykłe</option>
                                        <option value="pilne">Pilne</option>
                                        <option value="natychmiast">Natychmiastowa realizacja</option>
                                    </select>
                                </label>

                                <label class="field is-full" data-planned-fields style="display:none">
                                    <span class="label">Rozpoczęcie realizacji (data i godzina)</span>
                                    <div class="order-form" style="grid-template-columns:repeat(2,minmax(0,1fr));gap:12px">
                                        <label class="field">
                                            <span class="label">Data</span>
                                            <input class="input" type="date" name="planned_date" value="" data-planned-date>
                                        </label>
                                        <label class="field">
                                            <span class="label">Godzina</span>
                                            <input class="input" type="text" name="planned_time" value="" data-planned-time data-time-picker readonly placeholder="--:--">
                                        </label>
                                    </div>
                                </label>

                                <label class="field">
                                    <span class="label">Potrzebny zespół</span>
                                    <select class="input" name="needed_team" required>
                                        <option value="T">T</option>
                                        <option value="P" selected>P</option>
                                        <option value="S">S</option>
                                    </select>
                                </label>

                                <label class="field">
                                    <span class="label">Na sygnale?</span>
                                    <select class="input" name="sirens" required>
                                        <option value="1">Tak</option>
                                        <option value="0" selected>Nie</option>
                                    </select>
                                </label>
                            </div>
                        </div>

                        <div class="order-address-box">
                            <div class="order-address-title">Rodzaj transportu</div>

                            <div class="order-form">
                                <label class="field is-full">
                                    <select class="input" name="transport_type" required data-transport-type>
                                        <option value="hospital" selected>Przekazanie do szpitala</option>
                                        <option value="poradnia">Wizyta w poradni</option>
                                        <option value="miedzyszpitalna">Konsultacja międzyszpitalna</option>
                                        <option value="dom">Odwóz do domu</option>
                                        <option value="transport prywatny">Transport prywatny</option>
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
                                            <input class="input" type="text" name="patient_first_name" value="" required>
                                        </label>

                                        <label class="field">
                                            <span class="label">Nazwisko pacjenta</span>
                                            <input class="input" type="text" name="patient_last_name" value="" required>
                                        </label>

                                        <label class="field">
                                            <span class="label">Pozycja pacjenta</span>
                                            <select class="input" name="patient_position" required>
                                                <option value="chodzi">Chodzi</option>
                                                <option value="siedzi">Siedzi</option>
                                                <option value="leży">Leży</option>
                                            </select>
                                        </label>

                                        <label class="field">
                                            <span class="label">Waga (kg)</span>
                                            <input class="input" type="number" name="patient_weight_kg" value="" inputmode="numeric" min="1" max="350" step="1">
                                        </label>

                                        <label class="field">
                                            <span class="label">Numer telefonu</span>
                                            <input class="input" type="text" name="phone" value="">
                                        </label>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Podstawowy wywiad</div>

                                    <div class="order-form">
                                        <label class="field">
                                            <span class="label">Czy pacjent wymaga tlenu?</span>
                                            <select class="input" name="interview_oxygen" required>
                                                <option value="nie" selected>Nie</option>
                                                <option value="tak">Tak</option>
                                                <option value="nie_wiadomo">Nie wiadomo</option>
                                            </select>
                                        </label>

                                        <label class="field">
                                            <span class="label">Czy pacjent jest przytomny?</span>
                                            <select class="input" name="interview_conscious" required>
                                                <option value="tak" selected>Tak</option>
                                                <option value="nie">Nie</option>
                                                <option value="nie_wiadomo">Nie wiadomo</option>
                                            </select>
                                        </label>

                                        <input type="hidden" name="icd10_code" value="" data-icd10-code>
                                        <input type="hidden" name="icd10_name" value="" data-icd10-name>

                                        <div class="suggest" data-icd10-wrapper>
                                            <label class="field is-full">
                                                <span class="label">Główne rozpoznanie ICD-10</span>
                                                <input class="input" type="text" name="icd10_search" value="" data-icd10 autocomplete="off">
                                            </label>
                                            <label class="field is-full check-row">
                                                <input class="check-input" type="checkbox" name="icd10_none" value="1" data-icd10-none>
                                                <span class="check-text">Brak rozpoznania ICD-10</span>
                                            </label>
                                        </div>

                                        <label class="field is-full">
                                            <span class="label">Dodatkowe informacje (opcjonalnie)</span>
                                            <input class="input" type="text" name="interview_notes" value="">
                                        </label>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Opis zlecenia</div>

                                    <div class="order-form">
                                        <label class="field is-full">
                                            <textarea class="input" name="order_description" rows="8"></textarea>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="order-col">
                                <div class="order-address-grid">
                                    <div class="order-address-box" data-photon-scope>
                                        <div class="order-address-title">Skąd</div>

                                        <input type="hidden" name="from_lat" value="" data-photon-lat>
                                        <input type="hidden" name="from_lon" value="" data-photon-lon>
                                        <input type="hidden" name="from_display" value="" data-photon-display>

                                        <div class="order-form">
                                            <label class="field">
                                                <span class="label">Rodzaj infrastruktury</span>
                                                <select class="input" name="from_infra" required>
                                                    <option value="dom" selected>Dom</option>
                                                    <option value="blok mieszkalny">Blok mieszkalny</option>
                                                    <option value="szpital">Szpital</option>
                                                    <option value="poradnia">Poradnia</option>
                                                    <option value="inne">Inne</option>
                                                </select>
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Miejscowość</span>
                                                    <input class="input" type="text" name="from_city" value="" data-photon data-photon-kind="city" data-photon-field="city" required>
                                                </label>
                                            </div>

                                            <label class="field">
                                                <span class="label">Kod pocztowy</span>
                                                <input class="input" type="text" name="from_postcode" value="" readonly data-photon-field="postcode">
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Ulica</span>
                                                    <input class="input" type="text" name="from_street" value="" data-photon data-photon-kind="street" data-photon-field="street" disabled required>
                                                </label>
                                            </div>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Numer</span>
                                                    <input class="input" type="text" name="from_number" value="" data-photon data-photon-kind="number" data-photon-field="number" disabled required>
                                                </label>
                                            </div>

                                            <label class="field is-full">
                                                <span class="label">Lokal</span>
                                                <input class="input" type="text" name="from_flat" value="">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="order-address-box" data-photon-scope>
                                        <div class="order-address-title">Dokąd</div>

                                        <input type="hidden" name="to_lat" value="" data-photon-lat>
                                        <input type="hidden" name="to_lon" value="" data-photon-lon>
                                        <input type="hidden" name="to_display" value="" data-photon-display>

                                        <div class="order-form">
                                            <label class="field">
                                                <span class="label">Rodzaj infrastruktury</span>
                                                <select class="input" name="to_infra" required>
                                                    <option value="dom" selected>Dom</option>
                                                    <option value="blok mieszkalny">Blok mieszkalny</option>
                                                    <option value="szpital">Szpital</option>
                                                    <option value="poradnia">Poradnia</option>
                                                    <option value="inne">Inne</option>
                                                </select>
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Miejscowość</span>
                                                    <input class="input" type="text" name="to_city" value="" data-photon data-photon-kind="city" data-photon-field="city">
                                                </label>
                                            </div>

                                            <label class="field">
                                                <span class="label">Kod pocztowy</span>
                                                <input class="input" type="text" name="to_postcode" value="" readonly data-photon-field="postcode">
                                            </label>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Ulica</span>
                                                    <input class="input" type="text" name="to_street" value="" data-photon data-photon-kind="street" data-photon-field="street" disabled>
                                                </label>
                                            </div>

                                            <div class="suggest" data-photon-wrapper>
                                                <label class="field">
                                                    <span class="label">Numer</span>
                                                    <input class="input" type="text" name="to_number" value="" data-photon data-photon-kind="number" data-photon-field="number" disabled>
                                                </label>
                                            </div>

                                            <label class="field is-full">
                                                <span class="label">Lokal</span>
                                                <input class="input" type="text" name="to_flat" value="">
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="order-address-box">
                                    <div class="order-address-title">Dystans</div>

                                    <div class="order-form">
                                        <label class="field is-full">
                                            <span class="label">Ilość km</span>
                                            <input class="input" type="text" name="distance_km" value="" readonly data-distance-km>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-actions">
                            <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>" data-confirm-leave>Anuluj</a>
                            <button class="btn-primary" type="submit">Przyjmij</button>
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
