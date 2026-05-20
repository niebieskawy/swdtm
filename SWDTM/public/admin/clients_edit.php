<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['admin']);

$pdo = db();

$id = $_GET['id'] ?? null;
$id = is_string($id) ? (int)$id : (is_int($id) ? $id : 0);

$stmt = $pdo->prepare('SELECT id, client_name, username, facility_address, facility_city, facility_postcode, facility_street, facility_number, facility_flat, facility_lat, facility_lon, facility_display, created_at FROM clients WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: ' . url('/admin/clients'));
    exit;
}

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Nieprawidłowy token formularza.';
    } else {
        $action = $_POST['action'] ?? '';
        $action = is_string($action) ? $action : '';

        if ($action === 'delete') {
            try {
                $pdo->prepare('DELETE FROM clients WHERE id = :id')->execute([':id' => (int)$client['id']]);
                flash_set('clients', 'Usunięto klienta: ' . (string)$client['client_name']);
                header('Location: ' . url('/admin/clients'));
                exit;
            } catch (Throwable $e) {
                $error = 'Nie udało się usunąć klienta.';
            }
        } else {
            $clientName = $_POST['client_name'] ?? '';
            $username = $_POST['username'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';

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
            $newPassword = is_string($newPassword) ? $newPassword : '';

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

            if ($clientName === '' || $username === '' || $facilityAddress === '') {
                $error = 'Uzupełnij poprawnie wszystkie pola.';
            } elseif (mb_strlen($clientName) > 120) {
                $error = 'Nazwa klienta jest za długa.';
            } elseif (mb_strlen($username) > 50) {
                $error = 'Login jest za długi.';
            } elseif (mb_strlen($facilityAddress) > 255) {
                $error = 'Adres placówki jest za długi.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM clients WHERE username = :u AND id <> :id LIMIT 1');
                $stmt->execute([':u' => $username, ':id' => (int)$client['id']]);
                $exists = $stmt->fetch();

                if ($exists) {
                    $error = 'Taki login już istnieje.';
                } else {
                    try {
                        $pdo->prepare(
                            'UPDATE clients SET client_name = :n, username = :u, facility_address = :a, facility_city = :c, facility_postcode = :p, facility_street = :s, facility_number = :no, facility_flat = :f, facility_lat = :lat, facility_lon = :lon, facility_display = :d WHERE id = :id'
                        )->execute([
                            ':n' => $clientName,
                            ':u' => $username,
                            ':a' => $facilityAddress,
                            ':c' => ($facilityCity !== '' ? $facilityCity : null),
                            ':p' => ($facilityPostcode !== '' ? $facilityPostcode : null),
                            ':s' => ($facilityStreet !== '' ? $facilityStreet : null),
                            ':no' => ($facilityNumber !== '' ? $facilityNumber : null),
                            ':f' => ($facilityFlat !== '' ? $facilityFlat : null),
                            ':lat' => ($facilityLat !== '' ? $facilityLat : null),
                            ':lon' => ($facilityLon !== '' ? $facilityLon : null),
                            ':d' => ($facilityDisplay !== '' ? $facilityDisplay : null),
                            ':id' => (int)$client['id'],
                        ]);

                        if (trim($newPassword) !== '') {
                            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                            $pdo->prepare('UPDATE clients SET password_hash = :h WHERE id = :id')->execute([':h' => $hash, ':id' => (int)$client['id']]);
                        }

                        $stmt = $pdo->prepare('SELECT id, client_name, username, facility_address, facility_city, facility_postcode, facility_street, facility_number, facility_flat, facility_lat, facility_lon, facility_display, created_at FROM clients WHERE id = :id LIMIT 1');
                        $stmt->execute([':id' => (int)$client['id']]);
                        $client = $stmt->fetch();

                        $ok = 'Zmiany zapisane.';
                        flash_set('clients', 'Zapisano zmiany klienta: ' . (string)$client['client_name']);
                    } catch (Throwable $e) {
                        $error = 'Nie udało się zapisać zmian.';
                    }
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
    <title>SWDTM - Edytuj klienta</title>
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
                    <div class="page-title">Edytuj klienta</div>
                    <div class="page-subtitle"><?= htmlspecialchars((string)($client['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Wróć</a>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form class="form" method="post" action="<?= htmlspecialchars(url('/admin/clients/edit?id=' . (int)$client['id']), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="field">
                            <span class="label">Nazwa klienta</span>
                            <input class="input" type="text" name="client_name" value="<?= htmlspecialchars((string)($client['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>

                        <label class="field">
                            <span class="label">Login</span>
                            <input class="input" type="text" name="username" value="<?= htmlspecialchars((string)($client['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>

                        <label class="field">
                            <span class="label">Nowe hasło (opcjonalnie)</span>
                            <input class="input" type="password" name="new_password" value="">
                        </label>

                        <div class="order-address-box" data-photon-scope>
                            <div class="order-address-title">Adres placówki</div>

                            <input type="hidden" name="facility_lat" value="<?= htmlspecialchars((string)($client['facility_lat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-lat>
                            <input type="hidden" name="facility_lon" value="<?= htmlspecialchars((string)($client['facility_lon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-lon>
                            <input type="hidden" name="facility_display" value="<?= htmlspecialchars((string)($client['facility_display'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon-display>

                            <div class="order-form">
                                <div class="suggest" data-photon-wrapper>
                                    <label class="field">
                                        <span class="label">Miejscowość</span>
                                        <input class="input" type="text" name="facility_city" value="<?= htmlspecialchars((string)($client['facility_city'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="city" data-photon-field="city" required>
                                    </label>
                                </div>

                                <label class="field">
                                    <span class="label">Kod pocztowy</span>
                                    <input class="input" type="text" name="facility_postcode" value="<?= htmlspecialchars((string)($client['facility_postcode'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" readonly data-photon-field="postcode">
                                </label>

                                <div class="suggest" data-photon-wrapper>
                                    <label class="field">
                                        <span class="label">Ulica</span>
                                        <input class="input" type="text" name="facility_street" value="<?= htmlspecialchars((string)($client['facility_street'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="street" data-photon-field="street" <?= ((string)($client['facility_city'] ?? '') !== '') ? '' : 'disabled' ?> required>
                                    </label>
                                </div>

                                <div class="suggest" data-photon-wrapper>
                                    <label class="field">
                                        <span class="label">Numer</span>
                                        <input class="input" type="text" name="facility_number" value="<?= htmlspecialchars((string)($client['facility_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-photon data-photon-kind="number" data-photon-field="number" <?= (((string)($client['facility_city'] ?? '') !== '') && ((string)($client['facility_street'] ?? '') !== '')) ? '' : 'disabled' ?> required>
                                    </label>
                                </div>

                                <label class="field is-full">
                                    <span class="label">Lokal</span>
                                    <input class="input" type="text" name="facility_flat" value="<?= htmlspecialchars((string)($client['facility_flat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ((string)($client['facility_city'] ?? '') !== '') ? '' : 'disabled' ?>>
                                </label>
                            </div>
                        </div>

                        <button class="btn-primary" type="submit">Zapisz</button>
                    </form>

                    <div style="height:14px"></div>

                    <form id="delete-client-form" method="post" action="<?= htmlspecialchars(url('/admin/clients/edit?id=' . (int)$client['id']), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn-danger" type="button" data-modal-open="delete-client">Usuń klienta</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div class="modal-overlay" data-modal-overlay="delete-client">
        <div class="modal" role="dialog" aria-modal="true" aria-label="Potwierdzenie usunięcia">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Usunąć klienta?</div>
                    <div class="modal-text">Ta operacja jest nieodwracalna. Klient zostanie trwale usunięty z systemu.</div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-modal-close>Anuluj</button>
                <button class="btn-danger" type="button" data-modal-confirm-submit="delete-client-form">Usuń</button>
            </div>
        </div>
    </div>

    <script>
        window.SWDTM = window.SWDTM || {};
        window.SWDTM.geocoderProvider = 'photon';
        window.SWDTM.geocoderUrl = <?= json_encode(url('/api/photon.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    </script>
</body>
</html>
