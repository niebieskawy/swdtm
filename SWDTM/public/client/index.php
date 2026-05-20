<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['client']);

$user = current_user();
$clientId = $user && isset($user['client_id']) ? (int)$user['client_id'] : 0;

$client = null;
if ($clientId > 0) {
    try {
        $pdo = db();
        $stClient = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
        $stClient->execute([':id' => $clientId]);
        $client = $stClient->fetch();
    } catch (Throwable $e) {
        $client = null;
    }
}

$clientRow = is_array($client) ? $client : [];

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
        $urgency = 'zwykłe';
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
        $urgency = 'zwykłe';
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
        $allowedTransportType = ['hospital', 'poradnia', 'miedzyszpitalna', 'dom'];
        $allowedInfra = ['dom', 'blok mieszkalny', 'szpital', 'poradnia', 'inne'];
        $allowedOxygen = ['tak', 'nie', 'nie_wiadomo'];
        $allowedConscious = ['tak', 'nie', 'nie_wiadomo'];

        if ($clientId < 1) {
            $error = 'Brak klienta w sesji.';
        } elseif ($patientFirstName === '' || $patientLastName === '' || $fromCity === '' || $fromStreet === '' || $fromNumber === '' || !in_array($patientPosition, $allowedPositions, true) || !in_array($neededTeam, $allowedNeededTeam, true) || !in_array($sirens, $allowedSirens, true) || !in_array($orderType, $allowedOrderType, true) || !in_array($urgency, $allowedUrgency, true) || !in_array($transportType, $allowedTransportType, true) || !in_array($fromInfra, $allowedInfra, true) || !in_array($toInfra, $allowedInfra, true) || !in_array($interviewOxygen, $allowedOxygen, true) || !in_array($interviewConscious, $allowedConscious, true) || (!$icd10None && ($icd10Code === '' || $icd10Name === ''))) {
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
                    'client_id',
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
                    'distance_km',
                    'status'
                ];

                $ph = array_map(static fn($c) => ':' . $c, $insertCols);
                $stmt = $pdo->prepare(
                    'INSERT INTO client_requests (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $ph) . ')'
                );

                $stmt->execute([
                    ':client_id' => $clientId,
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
                    ':patient_weight_kg' => ($patientWeightKg !== '' ? (int)trim(str_replace(',', '.', $patientWeightKg)) : null),
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
                    ':status' => 'pending',
                ]);

                header('Location: ' . url('/client?sent=1'));
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage() !== '' ? $e->getMessage() : 'Nie udało się wysłać zgłoszenia.';
            }
        }
    }
}

$flash = flash_get('client');
$flashMessage = '';
$flashType = '';
if (is_array($flash)) {
    $flashMessage = isset($flash['message']) && is_string($flash['message']) ? $flash['message'] : '';
    $flashType = isset($flash['type']) && is_string($flash['type']) ? $flash['type'] : 'notice';
}

$sent = $_GET['sent'] ?? '';
$sent = is_string($sent) ? trim($sent) : '';
$isSent = ($sent === '1');

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Zgłoś transport</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(url('/assets/app.js?v=' . @filemtime(__DIR__ . '/../assets/app.js')), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body>
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
        <?php elseif (!$isSent && $flashMessage !== ''): ?>
            <div class="toast<?= ($flashType === 'alert') ? ' is-error' : '' ?>" data-toast data-toast-timeout="4500">
                <div class="toast-dot"></div>
                <div>
                    <div class="toast-title"><?= ($flashType === 'alert') ? 'Błąd' : 'Sukces' ?></div>
                    <div class="toast-msg"><?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-toast-close>×</button>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isSent): ?>
        <div class="modal-overlay is-open" data-client-sent-overlay>
            <div class="modal">
                <div class="modal-head">
                    <div>
                        <div class="modal-title">Zgłoszenie wysłane</div>
                        <div class="modal-text">Zgłoszenie zostało pomyślnie wysłane i oczekuje na potwierdzenie dyspozytora.</div>
                    </div>
                </div>
                <div class="modal-actions">
                    <a class="btn-secondary" href="<?= htmlspecialchars(url('/client'), ENT_QUOTES, 'UTF-8') ?>">Nowe zgłoszenie</a>
                    <a class="btn-primary" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-mark"></div>
                <div>
                    <div class="sidebar-title">SWDTM</div>
                    <div class="sidebar-subtitle">Klient</div>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/client'), ENT_QUOTES, 'UTF-8') ?>">Zgłoś transport</a>
            </nav>

            <div class="sidebar-footer">
                <div class="userchip">
                    <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr((string)($user['full_name'] ?? $user['username'] ?? 'K'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div>
                        <div class="userchip-name"><?= htmlspecialchars((string)($user['full_name'] ?? $user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="userchip-role">Klient</div>
                    </div>
                </div>
                <a class="link" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <div class="page-title">Zgłoś transport</div>
                </div>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form method="post" action="<?= htmlspecialchars(url('/client'), ENT_QUOTES, 'UTF-8') ?>">
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

                                <input type="hidden" name="urgency" value="zwykłe">

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
                                <div class="order-address-box">
                                    <div class="order-address-title">Tryb trasy</div>
                                    <div class="order-form">
                                        <label class="field is-full check-row">
                                            <input class="check-input" type="checkbox" value="from_facility" data-route-mode>
                                            <span class="check-text">Z naszej placówki (automatycznie uzupełnij „Skąd”)</span>
                                        </label>
                                        <label class="field is-full check-row">
                                            <input class="check-input" type="checkbox" value="to_facility" data-route-mode>
                                            <span class="check-text">Do naszej placówki (automatycznie uzupełnij „Dokąd”)</span>
                                        </label>
                                        <label class="field is-full check-row">
                                            <input class="check-input" type="checkbox" value="other" data-route-mode checked>
                                            <span class="check-text">Inne</span>
                                        </label>
                                    </div>
                                </div>

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
                            <button class="btn-primary" type="submit">Wyślij zgłoszenie</button>
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

        (function () {
            var facility = <?= json_encode([
                'city' => (string)($clientRow['facility_city'] ?? ''),
                'postcode' => (string)($clientRow['facility_postcode'] ?? ''),
                'street' => (string)($clientRow['facility_street'] ?? ''),
                'number' => (string)($clientRow['facility_number'] ?? ''),
                'flat' => (string)($clientRow['facility_flat'] ?? ''),
                'lat' => (string)($clientRow['facility_lat'] ?? ''),
                'lon' => (string)($clientRow['facility_lon'] ?? ''),
                'display' => (string)($clientRow['facility_display'] ?? ''),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

            function qs(sel, root) {
                return (root || document).querySelector(sel);
            }

            function qsa(sel, root) {
                return Array.prototype.slice.call((root || document).querySelectorAll(sel));
            }

            function getScope(kind) {
                var title = kind === 'from' ? 'Skąd' : 'Dokąd';
                var boxes = qsa('.order-address-box[data-photon-scope]');
                for (var i = 0; i < boxes.length; i++) {
                    var h = qs('.order-address-title', boxes[i]);
                    if (h && String(h.textContent || '').trim() === title) return boxes[i];
                }
                return null;
            }

            function setLocked(scope, locked) {
                if (!scope) return;
                scope.dataset.locked = locked ? '1' : '0';

                qsa('input,select,textarea', scope).forEach(function (el) {
                    if (el.name === 'from_infra' || el.name === 'to_infra') {
                        el.dataset.locked = '0';
                        el.removeAttribute('data-locked-value');
                        return;
                    }
                    if (el.name === 'from_flat' || el.name === 'to_flat') {
                        el.readOnly = !!locked;
                        return;
                    }
                    if (el.tagName && el.tagName.toLowerCase() === 'select') {
                        el.dataset.locked = locked ? '1' : '0';
                    } else if (el.type !== 'hidden') {
                        el.readOnly = !!locked;
                    }
                });
            }

            function preventSelectChangeWhenLocked(scope) {
                if (!scope) return;
                qsa('select', scope).forEach(function (sel) {
                    sel.addEventListener('change', function () {
                        if (sel.name === 'from_infra' || sel.name === 'to_infra') return;
                        if (sel.dataset.locked !== '1') return;
                        var v = sel.getAttribute('data-locked-value');
                        if (v !== null) sel.value = v;
                    });
                });
            }

            function fillScope(kind, data) {
                var scope = getScope(kind);
                if (!scope) return;

                var prefix = kind + '_';

                function byName(name) {
                    return qs('[name="' + name + '"]', scope);
                }

                var infra = byName(prefix + 'infra');
                if (infra) {
                    infra.removeAttribute('data-locked-value');
                }

                var city = byName(prefix + 'city');
                if (city) city.value = String(data.city || '');

                var postcode = byName(prefix + 'postcode');
                if (postcode) postcode.value = String(data.postcode || '');

                var street = byName(prefix + 'street');
                if (street) {
                    street.disabled = false;
                    street.value = String(data.street || '');
                }

                var number = byName(prefix + 'number');
                if (number) {
                    number.disabled = false;
                    number.value = String(data.number || '');
                }

                var flat = byName(prefix + 'flat');
                if (flat) flat.value = String(data.flat || '');

                var lat = qs('input[data-photon-lat]', scope);
                if (lat) lat.value = String(data.lat || '');

                var lon = qs('input[data-photon-lon]', scope);
                if (lon) lon.value = String(data.lon || '');

                var display = qs('input[data-photon-display]', scope);
                if (display) display.value = String(data.display || '');
            }

            function clearScope(kind) {
                var scope = getScope(kind);
                if (!scope) return;

                var prefix = kind + '_';
                function byName(name) {
                    return qs('[name="' + name + '"]', scope);
                }

                var infra = byName(prefix + 'infra');
                if (infra) {
                    infra.removeAttribute('data-locked-value');
                }

                var city = byName(prefix + 'city');
                if (city) city.value = '';

                var postcode = byName(prefix + 'postcode');
                if (postcode) postcode.value = '';

                var street = byName(prefix + 'street');
                if (street) {
                    street.value = '';
                    street.disabled = true;
                }

                var number = byName(prefix + 'number');
                if (number) {
                    number.value = '';
                    number.disabled = true;
                }

                var flat = byName(prefix + 'flat');
                if (flat) flat.value = '';

                var lat = qs('input[data-photon-lat]', scope);
                if (lat) lat.value = '';

                var lon = qs('input[data-photon-lon]', scope);
                if (lon) lon.value = '';

                var display = qs('input[data-photon-display]', scope);
                if (display) display.value = '';
            }

            function syncMode(mode) {
                if (mode === 'from_facility') {
                    clearScope('to');
                    fillScope('from', facility);
                    setLocked(getScope('from'), true);
                    setLocked(getScope('to'), false);
                } else if (mode === 'to_facility') {
                    clearScope('from');
                    fillScope('to', facility);
                    setLocked(getScope('to'), true);
                    setLocked(getScope('from'), false);
                } else {
                    setLocked(getScope('from'), false);
                    setLocked(getScope('to'), false);
                    clearScope('from');
                    clearScope('to');
                }
            }

            var modes = qsa('input[data-route-mode]');
            if (!modes.length) return;

            preventSelectChangeWhenLocked(getScope('from'));
            preventSelectChangeWhenLocked(getScope('to'));

            function setOneChecked(target) {
                modes.forEach(function (cb) {
                    cb.checked = cb === target;
                });
            }

            function activeMode() {
                var m = modes.find(function (cb) {
                    return cb.checked;
                });
                return m ? String(m.value || '') : 'other';
            }

            modes.forEach(function (cb) {
                cb.addEventListener('change', function () {
                    if (!cb.checked) {
                        setOneChecked(cb);
                    } else {
                        setOneChecked(cb);
                    }
                    syncMode(activeMode());
                });
            });

            if (!modes.some(function (cb) { return cb.checked; })) {
                setOneChecked(modes[modes.length - 1]);
            }

            syncMode(activeMode());
        })();
    </script>
</body>
</html>
