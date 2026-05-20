<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['admin']);

$pdo = db();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Nieprawidłowy token formularza.';
    } else {
        $clientName = $_POST['client_name'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $facilityCity = $_POST['facility_city'] ?? '';
        $facilityPostcode = $_POST['facility_postcode'] ?? '';
        $facilityStreet = $_POST['facility_street'] ?? '';
        $facilityNumber = $_POST['facility_number'] ?? '';
        $facilityFlat = $_POST['facility_flat'] ?? '';
        $facilityLat = $_POST['facility_lat'] ?? '';
        $facilityLon = $_POST['facility_lon'] ?? '';
        $facilityDisplay = $_POST['facility_display'] ?? '';

        $clientName = is_string($clientName) ? trim($clientName) : '';
        $username = is_string($username) ? trim($username) : '';
        $password = is_string($password) ? $password : '';
        $facilityCity = is_string($facilityCity) ? trim($facilityCity) : '';
        $facilityPostcode = is_string($facilityPostcode) ? trim($facilityPostcode) : '';
        $facilityStreet = is_string($facilityStreet) ? trim($facilityStreet) : '';
        $facilityNumber = is_string($facilityNumber) ? trim($facilityNumber) : '';
        $facilityFlat = is_string($facilityFlat) ? trim($facilityFlat) : '';
        $facilityLat = is_string($facilityLat) ? trim($facilityLat) : '';
        $facilityLon = is_string($facilityLon) ? trim($facilityLon) : '';
        $facilityDisplay = is_string($facilityDisplay) ? trim($facilityDisplay) : '';

        $parts = [];
        if ($facilityStreet !== '' || $facilityNumber !== '' || $facilityFlat !== '') {
            $line = trim($facilityStreet . ' ' . $facilityNumber);
            if ($facilityFlat !== '') {
                $line .= '/' . $facilityFlat;
            }
            $parts[] = $line;
        }
        $cityLine = trim($facilityPostcode . ' ' . $facilityCity);
        if ($cityLine !== '') {
            $parts[] = $cityLine;
        }
        $facilityAddress = trim(implode(', ', array_filter($parts, fn($x) => trim((string)$x) !== '')));

        if ($clientName === '' || $username === '' || $password === '' || $facilityAddress === '') {
            $error = 'Uzupełnij poprawnie wszystkie pola.';
        } elseif (mb_strlen($clientName) > 120) {
            $error = 'Nazwa klienta jest za długa.';
        } elseif (mb_strlen($username) > 50) {
            $error = 'Login jest za długi.';
        } elseif (mb_strlen($facilityAddress) > 255) {
            $error = 'Adres placówki jest za długi.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM clients WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $exists = $stmt->fetch();

            if ($exists) {
                $error = 'Taki login już istnieje.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                try {
                    $pdo->prepare(
                        'INSERT INTO clients (client_name, username, password_hash, facility_address, facility_city, facility_postcode, facility_street, facility_number, facility_flat, facility_lat, facility_lon, facility_display) '
                        . 'VALUES (:n, :u, :h, :a, :c, :p, :s, :no, :f, :lat, :lon, :d)'
                    )->execute([
                        ':n' => $clientName,
                        ':u' => $username,
                        ':h' => $hash,
                        ':a' => $facilityAddress,
                        ':c' => ($facilityCity !== '' ? $facilityCity : null),
                        ':p' => ($facilityPostcode !== '' ? $facilityPostcode : null),
                        ':s' => ($facilityStreet !== '' ? $facilityStreet : null),
                        ':no' => ($facilityNumber !== '' ? $facilityNumber : null),
                        ':f' => ($facilityFlat !== '' ? $facilityFlat : null),
                        ':lat' => ($facilityLat !== '' ? $facilityLat : null),
                        ':lon' => ($facilityLon !== '' ? $facilityLon : null),
                        ':d' => ($facilityDisplay !== '' ? $facilityDisplay : null),
                    ]);

                    flash_set('clients', 'Utworzono konto klienta: ' . $username);
                    header('Location: ' . url('/admin/clients'));
                    exit;
                } catch (Throwable $e) {
                    $error = 'Nie udało się dodać klienta (sprawdź czy tabela clients ma nowe kolumny dla nazwy i adresu).';
                }
            }
        }
    }
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Dodaj klienta</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(url('/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
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
        <?php endif; ?>
    </div>

    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-mark"></div>
                <div>
                    <div class="sidebar-title">SWDTM</div>
                    <div class="sidebar-subtitle">Administrator</div>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Użytkownicy</a>
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Klienci</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/reports'), ENT_QUOTES, 'UTF-8') ?>">Raporty</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/audit'), ENT_QUOTES, 'UTF-8') ?>">Audyt</a>
            </nav>

            <div class="sidebar-footer">
                <a class="link" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <div class="page-title">Dodaj klienta</div>
                    <div class="page-subtitle">Utworzenie nowego konta klienta</div>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Anuluj</a>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form class="form" method="post" action="<?= htmlspecialchars(url('/admin/clients/create'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="field">
                            <span class="label">Nazwa klienta</span>
                            <input class="input" type="text" name="client_name" required>
                        </label>

                        <label class="field">
                            <span class="label">Login</span>
                            <input class="input" type="text" name="username" required>
                        </label>

                        <label class="field">
                            <span class="label">Hasło</span>
                            <input class="input" type="password" name="password" required>
                        </label>

                        <div class="order-address-box" data-photon-scope>
                            <div class="order-address-title">Adres placówki</div>

                            <input type="hidden" name="facility_lat" value="" data-photon-lat>
                            <input type="hidden" name="facility_lon" value="" data-photon-lon>
                            <input type="hidden" name="facility_display" value="" data-photon-display>

                            <div class="order-form">
                                <div class="suggest" data-photon-wrapper>
                                    <label class="field">
                                        <span class="label">Miejscowość</span>
                                        <input class="input" type="text" name="facility_city" value="" data-photon data-photon-kind="city" data-photon-field="city" required>
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="label">Kod pocztowy</span>
                                    <input class="input" type="text" name="facility_postcode" value="" readonly data-photon-field="postcode">
                                </label>

                                <div class="suggest" data-photon-wrapper>
                                    <label class="field">
                                        <span class="label">Ulica</span>
                                        <input class="input" type="text" name="facility_street" value="" data-photon data-photon-kind="street" data-photon-field="street" disabled required>
                                    </label>
                                </div>

                                <div class="suggest" data-photon-wrapper>
                                    <label class="field">
                                        <span class="label">Numer</span>
                                        <input class="input" type="text" name="facility_number" value="" data-photon data-photon-kind="number" data-photon-field="number" disabled required>
                                    </label>
                                </div>

                                <label class="field is-full">
                                    <span class="label">Lokal</span>
                                    <input class="input" type="text" name="facility_flat" value="" disabled>
                                </label>
                            </div>
                        </div>

                        <button class="btn-primary" type="submit">Zapisz</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        window.SWDTM = window.SWDTM || {};
        window.SWDTM.geocoderProvider = 'photon';
        window.SWDTM.geocoderUrl = <?= json_encode(url('/api/photon.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
</body>
</html>
