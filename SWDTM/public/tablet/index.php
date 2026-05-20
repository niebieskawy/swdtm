<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
require_role(['team']);

$user = current_user();
$pdo = db();

$teamCode = current_team_code();

start_session();

$myUserId = $user && isset($user['id']) ? (int)$user['id'] : 0;

$driverError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tablet_action']) && $_POST['tablet_action'] === 'set_driver') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $driverError = 'Nieprawidłowy token formularza.';
    } else {
        $driverUserId = $_POST['driver_user_id'] ?? '';

        $driverUserId = is_string($driverUserId) ? trim($driverUserId) : '';

        $pickedName = '';
        $pickedId = null;

        if ($driverUserId !== '' && ctype_digit($driverUserId)) {
            try {
                $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE id = :id AND role = 'team' AND full_name IS NOT NULL AND full_name <> '' LIMIT 1");
                $stmt->execute([':id' => (int)$driverUserId]);
                $picked = $stmt->fetch();
                if ($picked) {
                    $pickedId = (int)$picked['id'];
                    $pickedName = trim((string)($picked['full_name'] ?? ''));
                }
            } catch (Throwable $e) {
                $pickedName = '';
                $pickedId = null;
            }
        }

        if ($pickedName === '') {
            $driverError = 'Wybierz kierowcę z listy.';
        } else {
            $_SESSION['tablet_driver_name'] = $pickedName;
            $_SESSION['tablet_driver_user_id'] = $pickedId;

            header('Location: ' . url('/tablet'));
            exit;
        }
    }
}

$activeOrder = null;
if ($teamCode !== '') {
    try {
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

        if (!$teamCodeAliases) {
            $activeOrder = null;
        } else {
            $placeholders = [];
            $params = [];
            foreach ($teamCodeAliases as $i => $codeAlias) {
                $ph = ':tc' . $i;
                $placeholders[] = $ph;
                $params[$ph] = $codeAlias;
            }
            $inOrders = implode(', ', $placeholders);

            $placeholders2 = [];
            foreach ($teamCodeAliases as $i => $codeAlias) {
                $ph2 = ':td' . $i;
                $placeholders2[] = $ph2;
                $params[$ph2] = $codeAlias;
            }
            $inDn = implode(', ', $placeholders2);

        $stmt = $pdo->prepare(
            "SELECT
                id,
                order_seq,
                order_month,
                order_year,
                urgency,
                transport_type,
                from_city,
                from_street,
                from_number,
                phone,
                assigned_at
            FROM orders
            WHERE status = 'assigned'
              AND assigned_team_code COLLATE utf8mb4_unicode_ci IN ({$inOrders})
              AND EXISTS (
                SELECT 1 FROM dispatch_notifications dn
                WHERE dn.order_id = orders.id
                  AND dn.team_code COLLATE utf8mb4_unicode_ci IN ({$inDn})
                  AND dn.status = 'accepted'
              )
            ORDER BY assigned_at DESC, id DESC
            LIMIT 1"
        );
        $stmt->execute($params);
        $activeOrder = $stmt->fetch();
        }
    } catch (Throwable $e) {
        $activeOrder = null;
    }
}

$hasActiveOrder = $activeOrder ? true : false;

$statusError = '';

$statusReady = [
    ['code' => 'ready_base', 'label' => 'Gotowy w bazie'],
    ['code' => 'not_ready', 'label' => 'Niegotowy'],
    ['code' => 'restore_ready', 'label' => 'Przywracanie gotowości'],
    ['code' => 'return_base', 'label' => 'Powrót do bazy'],
];

$statusOrder = [
    ['code' => 'order_start', 'label' => 'Wyjazd do zlecenia'],
    ['code' => 'order_patient', 'label' => 'U pacjenta'],
    ['code' => 'order_transport', 'label' => 'Przejazd z pacjentem'],
    ['code' => 'order_realization', 'label' => 'Realizacja (z pacjentem)'],
    ['code' => 'order_handover', 'label' => 'Przekazanie pacjenta'],
    ['code' => 'order_return', 'label' => 'Powrót (z pacjentem)'],
];

$statusOther = [
    ['code' => 'disinfection', 'label' => 'Dezynfekcja'],
    ['code' => 'washing', 'label' => 'Mycie'],
    ['code' => 'refuel', 'label' => 'Tankowanie'],
    ['code' => 'failure', 'label' => 'Awaria'],
];

$allowedStatus = [];
foreach ([$statusReady, $statusOrder, $statusOther] as $grp) {
    foreach ($grp as $s) {
        $allowedStatus[(string)$s['code']] = (string)$s['label'];
    }
}

$statusCurrent = '';
if (isset($_SESSION['tablet_status_current']) && is_string($_SESSION['tablet_status_current'])) {
    $statusCurrent = trim($_SESSION['tablet_status_current']);
}

$statusCurrentLabel = ($statusCurrent !== '' && isset($allowedStatus[$statusCurrent])) ? (string)$allowedStatus[$statusCurrent] : '';

$statusPrev = '';
if (isset($_SESSION['tablet_status_prev']) && is_string($_SESSION['tablet_status_prev'])) {
    $statusPrev = trim($_SESSION['tablet_status_prev']);
}

$statusPrevOrder = '';
if (isset($_SESSION['tablet_status_prev_order']) && is_string($_SESSION['tablet_status_prev_order'])) {
    $statusPrevOrder = trim($_SESSION['tablet_status_prev_order']);
}

$orderCodes = [];
foreach ($statusOrder as $s) {
    $orderCodes[] = (string)$s['code'];
}

$orderIndex = [];
foreach ($orderCodes as $i => $c) {
    $orderIndex[(string)$c] = (int)$i;
}

$readyCodes = [];
foreach ($statusReady as $s) {
    $readyCodes[] = (string)$s['code'];
}

$otherCodes = [];
foreach ($statusOther as $s) {
    $otherCodes[] = (string)$s['code'];
}

$inOrderHandling = in_array($statusCurrent, $orderCodes, true);
$canRestoreOrderStatus = $hasActiveOrder && $inOrderHandling && $statusCurrent !== 'order_start';

$canCloseOrderThroughRestoreReady = $hasActiveOrder && (
    $statusCurrent === 'order_handover'
    || $statusCurrent === 'order_realization'
    || ($statusCurrent === 'order_patient' && $statusPrevOrder === 'order_return')
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tablet_action']) && $_POST['tablet_action'] === 'set_status') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $statusError = 'Nieprawidłowy token formularza.';
    } else {
        $new = $_POST['status_code'] ?? '';
        $new = is_string($new) ? trim($new) : '';

        if ($new === '' || !isset($allowedStatus[$new])) {
            $statusError = 'Nieprawidłowy status.';
        } elseif (!$hasActiveOrder && in_array($new, $orderCodes, true)) {
            $statusError = 'Brak aktywnego zlecenia.';
        } elseif ($hasActiveOrder && in_array($new, $readyCodes, true)) {
            $statusError = 'Nie można użyć statusów gotowości podczas realizacji zlecenia.';
        } elseif ($hasActiveOrder && in_array($new, $otherCodes, true) && $new !== 'failure') {
            $statusError = 'Podczas realizacji zlecenia w sekcji Inne dostępna jest tylko Awaria.';
        } elseif (
            $hasActiveOrder &&
            in_array($statusCurrent, $orderCodes, true) &&
            in_array($new, $orderCodes, true) &&
            isset($orderIndex[$statusCurrent], $orderIndex[$new]) &&
            $orderIndex[$new] < $orderIndex[$statusCurrent]
        ) {
            $statusError = 'Nie można cofnąć statusu obsługi zlecenia. Użyj: Przywróć poprzedni status.';
        } else {
            $prev = $statusCurrent;
            if ($prev !== '' && $prev !== $new) {
                $_SESSION['tablet_status_prev'] = $prev;
            }

            if (
                $prev !== '' &&
                $prev !== $new &&
                in_array($prev, $orderCodes, true) &&
                in_array($new, $orderCodes, true)
            ) {
                $_SESSION['tablet_status_prev_order'] = $prev;
            }

            $_SESSION['tablet_status_current'] = $new;
            header('Location: ' . url('/tablet'));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tablet_action']) && $_POST['tablet_action'] === 'restore_status') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $statusError = 'Nieprawidłowy token formularza.';
    } else {
        if (!$canRestoreOrderStatus) {
            $statusError = 'Nie można przywrócić statusu w tym momencie.';
        } elseif ($statusPrevOrder === '' || !isset($allowedStatus[$statusPrevOrder]) || !in_array($statusPrevOrder, $orderCodes, true)) {
            $statusError = 'Brak poprzedniego statusu.';
        } else {
            $_SESSION['tablet_status_current'] = $statusPrevOrder;
            header('Location: ' . url('/tablet'));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tablet_action']) && $_POST['tablet_action'] === 'set_no_driver') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $driverError = 'Nieprawidłowy token formularza.';
    } else {
        $_SESSION['tablet_driver_name'] = 'Brak kierowcy';
        $_SESSION['tablet_driver_user_id'] = null;

        header('Location: ' . url('/tablet'));
        exit;
    }
}

$driverName = '';
if (isset($_SESSION['tablet_driver_name']) && is_string($_SESSION['tablet_driver_name'])) {
    $driverName = trim($_SESSION['tablet_driver_name']);
}

$crew = [];
try {
    if ($myUserId > 0) {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = 'team' AND full_name IS NOT NULL AND full_name <> '' AND id <> :me ORDER BY full_name ASC");
        $stmt->execute([':me' => $myUserId]);
        $crew = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query("SELECT id, full_name FROM users WHERE role = 'team' AND full_name IS NOT NULL AND full_name <> '' ORDER BY full_name ASC");
        $crew = $stmt->fetchAll();
    }
} catch (Throwable $e) {
    $crew = [];
}

$fullName = $user && isset($user['full_name']) && is_string($user['full_name']) && $user['full_name'] !== ''
    ? $user['full_name']
    : 'Użytkownik';

try {
    $statusLabelNow = ($statusCurrent !== '' && isset($allowedStatus[$statusCurrent])) ? (string)$allowedStatus[$statusCurrent] : '';
    $teamCodeNow = $teamCode !== '' ? $teamCode : '—';

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS team_presence (\n"
        . "  team_code VARCHAR(20) NOT NULL,\n"
        . "  status_code VARCHAR(60) NULL,\n"
        . "  status_label VARCHAR(120) NULL,\n"
        . "  leader_name VARCHAR(120) NULL,\n"
        . "  driver_name VARCHAR(120) NULL,\n"
        . "  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n"
        . "  PRIMARY KEY (team_code),\n"
        . "  KEY idx_team_presence_last_seen (last_seen_at)\n"
        . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $stmt = $pdo->prepare(
        "INSERT INTO team_presence (team_code, status_code, status_label, leader_name, driver_name, last_seen_at)\n"
        . "VALUES (:code, :sc, :sl, :ln, :dn, NOW())\n"
        . "ON DUPLICATE KEY UPDATE\n"
        . "  status_code = VALUES(status_code),\n"
        . "  status_label = VALUES(status_label),\n"
        . "  leader_name = VALUES(leader_name),\n"
        . "  driver_name = VALUES(driver_name),\n"
        . "  last_seen_at = NOW()"
    );

    $stmt->execute([
        ':code' => $teamCodeNow,
        ':sc' => ($statusCurrent !== '' ? $statusCurrent : null),
        ':sl' => ($statusLabelNow !== '' ? $statusLabelNow : null),
        ':ln' => ($fullName !== '' ? $fullName : null),
        ':dn' => ($driverName !== '' ? $driverName : null),
    ]);
} catch (Throwable $e) {
}

$mode = 'Tryb pracy: on-line';
$banner = 'Brak komunikatów';

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b1220">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="SWDTM">
    <link rel="manifest" href="<?= htmlspecialchars(url('/manifest.webmanifest'), ENT_QUOTES, 'UTF-8') ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars(url('/assets/pwa-icon.svg'), ENT_QUOTES, 'UTF-8') ?>">
    <title>SWDTM - Tablet</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="is-tablet">
    <div class="tablet">
        <div class="tablet-top">
            <div class="tablet-statusbar">
                <div class="tablet-status-left">
                    <div class="tablet-user" title="<?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?>">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12 12a4.5 4.5 0 1 0-4.5-4.5A4.51 4.51 0 0 0 12 12Z" stroke="currentColor" stroke-width="2"/>
                            <path d="M20 21a8 8 0 1 0-16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                        <div class="tablet-user-name"><?= htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="tablet-mode"><?= htmlspecialchars($mode, ENT_QUOTES, 'UTF-8') ?></div>
                </div>

                <div class="tablet-status-right">
                    <div class="tablet-alert" title="<?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8') ?>">
                        <svg class="warn" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                            <path d="M12 9v5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M12 17h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                            <path d="M10.3 4.3 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                        <span><?= htmlspecialchars($banner, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <a
                        class="btn-danger"
                        href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>"
                        data-tablet-logout
                        style="height:36px;padding:0 12px;border-color:rgba(239,68,68,.45);background:linear-gradient(135deg,rgba(239,68,68,.95),rgba(220,38,38,.95));color:#fff;box-shadow:none"
                    >Wyloguj</a>
                </div>
            </div>

            <div class="tablet-tabs" role="tablist" aria-label="Nawigacja">
                <div class="tablet-tab is-active" role="tab" tabindex="0" data-tab="status" aria-selected="true">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 2v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 18v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M4.93 4.93 7.76 7.76" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M16.24 16.24l2.83 2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M2 12h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M18 12h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M4.93 19.07 7.76 16.24" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M16.24 7.76l2.83-2.83" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M12 17a5 5 0 1 0-5-5 5 5 0 0 0 5 5Z" stroke="currentColor" stroke-width="2"/>
                    </svg>
                    <div class="tab-label">Status</div>
                </div>

                <div class="tablet-tab<?= $hasActiveOrder ? '' : ' is-disabled' ?>" role="tab" tabindex="<?= $hasActiveOrder ? '0' : '-1' ?>" aria-disabled="<?= $hasActiveOrder ? 'false' : 'true' ?>" data-tab="active" aria-selected="false">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M12 2v10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M6 12a6 6 0 1 0 12 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M9 19h6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="tab-label">Aktywne</div>
                </div>

                <div class="tablet-tab" role="tab" tabindex="0" data-tab="kmcr" aria-selected="false">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M9 3h6v4H9V3Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M7 7h10v14H7V7Z" stroke="currentColor" stroke-width="2"/>
                        <path d="M10 11h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M10 15h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="tab-label">CZYNNOŚCI</div>
                </div>

                <div class="tablet-tab" role="tab" tabindex="0" data-tab="nav" aria-selected="false">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M21 3 3 11l7 2 2 7 9-18Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M10 13 21 3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="tab-label">Nawigacja</div>
                </div>

                <div class="tablet-tab" role="tab" tabindex="0" data-tab="ekd" aria-selected="false">
                    <svg class="tab-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path d="M4 7h10a3 3 0 0 1 3 3v7H7a3 3 0 0 1-3-3V7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
                        <path d="M17 11h2.2a1.8 1.8 0 0 1 1.6.9l1 1.7a2 2 0 0 1 .2.9V16a1 1 0 0 1-1 1h-1" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M7 17a2 2 0 1 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M17 17a2 2 0 1 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M8.5 12h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <path d="M9.5 11v2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <div class="tab-label">EKD</div>
                </div>
            </div>
        </div>

        <main class="tablet-main">
            <section class="tablet-view is-active" data-view="status">
                <div class="tablet-card">
                    <div class="tablet-status-head">
                        <div>
                            <div class="tablet-status-title">Status</div>
                            <div class="tablet-status-sub">Aktualny: <span class="tablet-status-current"><?= htmlspecialchars($statusCurrent !== '' && isset($allowedStatus[$statusCurrent]) ? $allowedStatus[$statusCurrent] : '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                        </div>
                        <?php $restoreEnabled = $canRestoreOrderStatus && $statusPrevOrder !== '' && isset($allowedStatus[$statusPrevOrder]); ?>
                        <button class="tablet-status-restore<?= $restoreEnabled ? '' : ' is-disabled' ?>" type="button" data-status-restore-open <?= $restoreEnabled ? '' : 'disabled' ?>>Przywróć poprzedni status</button>
                    </div>

                    <?php if ($statusError !== ''): ?>
                        <div class="tablet-status-error"><?= htmlspecialchars($statusError, ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>

                    <div class="tablet-status-section">
                        <div class="tablet-status-section-title">Gotowość</div>
                        <div class="tablet-status-grid">
                            <?php foreach ($statusReady as $s): ?>
                                <?php
                                    $code = (string)$s['code'];
                                    $label = (string)$s['label'];
                                    $isActive = ($statusCurrent === $code);
                                    $isDisabled = $hasActiveOrder;
                                    if ($code === 'restore_ready') {
                                        $isDisabled = $hasActiveOrder ? !$canCloseOrderThroughRestoreReady : false;
                                    }
                                ?>
                                <button class="tablet-status-btn<?= $isActive ? ' is-active' : '' ?><?= $isDisabled ? ' is-disabled' : '' ?>" type="button" data-status-pick data-status-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" data-status-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" <?= $isDisabled ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tablet-status-section<?= $hasActiveOrder ? '' : ' is-disabled' ?>" data-order-status-section>
                        <div class="tablet-status-section-title">Obsługa zlecenia</div>
                        <div class="tablet-status-grid">
                            <?php foreach ($statusOrder as $s): ?>
                                <?php
                                    $code = (string)$s['code'];
                                    $label = (string)$s['label'];
                                    $isActive = ($statusCurrent === $code);
                                    $isDisabled = !$hasActiveOrder;
                                    if (!$isDisabled && $inOrderHandling && isset($orderIndex[$statusCurrent], $orderIndex[$code])) {
                                        $isDisabled = $orderIndex[$code] < $orderIndex[$statusCurrent];
                                        if ($statusCurrent === 'order_return' && $code === 'order_patient') {
                                            $isDisabled = false;
                                        }
                                    }
                                ?>
                                <button class="tablet-status-btn<?= $isActive ? ' is-active' : '' ?><?= $isDisabled ? ' is-disabled' : '' ?>" type="button" data-status-pick data-status-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" data-status-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" <?= $isDisabled ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="tablet-status-section">
                        <div class="tablet-status-section-title">Inne</div>
                        <div class="tablet-status-grid">
                            <?php foreach ($statusOther as $s): ?>
                                <?php
                                    $code = (string)$s['code'];
                                    $label = (string)$s['label'];
                                    $isActive = ($statusCurrent === $code);
                                    $isDisabled = $hasActiveOrder && $code !== 'failure';
                                ?>
                                <button class="tablet-status-btn<?= $isActive ? ' is-active' : '' ?><?= $isDisabled ? ' is-disabled' : '' ?>" type="button" data-status-pick data-status-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" data-status-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>" <?= $isDisabled ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>

            <section class="tablet-view" data-view="active">
                <div class="tablet-card">
                    <div style="font-weight:950;font-size:28px;letter-spacing:.2px">Aktywne zlecenie</div>
                    <?php
                        $orderNumber = '—';
                        $from = '—';
                        $phone = '—';
                        if ($activeOrder) {
                            $num = (string)($activeOrder['order_seq'] ?? '');
                            $mm = isset($activeOrder['order_month']) ? str_pad((string)(int)$activeOrder['order_month'], 2, '0', STR_PAD_LEFT) : '';
                            $yy = (string)($activeOrder['order_year'] ?? '');
                            $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$activeOrder['id']);
                            $fromNumber = trim((string)($activeOrder['from_number'] ?? ''));
                            $fromFlat = trim((string)($activeOrder['from_flat'] ?? ''));
                            $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
                            $from = trim((string)($activeOrder['from_city'] ?? '') . ', ' . (string)($activeOrder['from_street'] ?? '') . ' ' . $fromNumLine);
                            $from = ($from !== ',' ? $from : '—');
                            $phone = trim((string)($activeOrder['phone'] ?? ''));
                            $phone = ($phone !== '' ? $phone : '—');
                        }
                    ?>

                    <div class="tablet-muted" data-active-empty style="margin-top:10px;font-size:18px;line-height:1.35;<?= $activeOrder ? 'display:none' : '' ?>">Brak wysłanego zlecenia do Twojego zespołu. Zakładka jest wyszarzona do momentu zadysponowania.</div>

                    <div data-active-details style="margin-top:14px;display:grid;gap:14px;<?= $activeOrder ? '' : 'display:none' ?>">
                        <div class="tablet-active-top">
                            <div>
                                <div class="tablet-muted" style="font-size:14px">Nr</div>
                                <div class="tablet-active-number" data-active-number><?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                            <div class="tablet-active-badges">
                                <div class="tablet-active-badge">
                                    <div class="tablet-muted" style="font-size:13px">Pilność</div>
                                    <div class="tablet-active-value" data-active-urgency>—</div>
                                </div>
                                <div class="tablet-active-badge">
                                    <div class="tablet-muted" style="font-size:13px">Transport</div>
                                    <div class="tablet-active-value" data-active-transport>—</div>
                                </div>
                                <div class="tablet-active-badge">
                                    <div class="tablet-muted" style="font-size:13px">Na sygnale?</div>
                                    <div class="tablet-active-value" data-active-sirens>—</div>
                                </div>
                            </div>
                        </div>

                        <div class="tablet-active-grid">
                            <div class="tablet-active-section is-compact">
                                <div class="tablet-active-section-title">Dane adresowe</div>
                                <div class="tablet-active-row is-address">
                                    <div class="tablet-active-label">Skąd</div>
                                    <div class="tablet-active-text">
                                        <span class="tablet-active-chip" data-active-from-infra>—</span>
                                        <span data-active-from><?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </div>
                                <div class="tablet-active-row is-address">
                                    <div class="tablet-active-label">Dokąd</div>
                                    <div class="tablet-active-text">
                                        <span class="tablet-active-chip" data-active-to-infra>—</span>
                                        <span data-active-to>—</span>
                                    </div>
                                </div>
                            </div>

                            <div class="tablet-active-section is-compact">
                                <div class="tablet-active-section-title">Dane pacjenta</div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">Pacjent</div>
                                    <div class="tablet-active-text" data-active-patient>—</div>
                                </div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">Pozycja</div>
                                    <div class="tablet-active-text" data-active-patient-position>—</div>
                                </div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">Waga</div>
                                    <div class="tablet-active-text" data-active-patient-weight>—</div>
                                </div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">Telefon</div>
                                    <div class="tablet-active-text" data-active-phone><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                            </div>

                            <div class="tablet-active-section">
                                <div class="tablet-active-section-title">Podstawowy wywiad</div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">Tlen</div>
                                    <div class="tablet-active-text" data-active-interview-oxygen>—</div>
                                </div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">Przytomność</div>
                                    <div class="tablet-active-text" data-active-interview-conscious>—</div>
                                </div>
                                <div class="tablet-active-row">
                                    <div class="tablet-active-label">ICD-10</div>
                                    <div class="tablet-active-text" data-active-icd10>—</div>
                                </div>
                                <div class="tablet-active-row is-notes">
                                    <div class="tablet-active-label">Uwagi</div>
                                    <div class="tablet-active-text" data-active-interview-notes>—</div>
                                </div>
                                <div class="tablet-active-row is-notes">
                                    <div class="tablet-active-label">Opis</div>
                                    <div class="tablet-active-text" data-active-order-description>—</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="tablet-view" data-view="kmcr">
                <div class="tablet-card">
                    <div style="font-weight:950;font-size:20px;letter-spacing:.2px">CZYNNOŚCI</div>
                    <div class="tablet-muted" style="margin-top:8px">Sytuacje niestandardowe do zgłoszenia dyspozytorowi.</div>

                    <div class="tablet-kmcr-grid">
                        <button class="tablet-kmcr-btn" type="button" data-kmcr-wrong-address <?= $hasActiveOrder ? '' : 'disabled' ?>>
                            <div class="tablet-kmcr-title">Błędny adres</div>
                            <div class="tablet-kmcr-sub">Wprowadź poprawny adres i powód</div>
                        </button>
                        <button class="tablet-kmcr-btn" type="button" data-kmcr-refusal <?= $hasActiveOrder ? '' : 'disabled' ?>>
                            <div class="tablet-kmcr-title">Odmowa przyjęcia</div>
                            <div class="tablet-kmcr-sub">Zgłoszenie odmowy z opisem</div>
                        </button>
                        <button class="tablet-kmcr-btn" type="button" data-kmcr-patient-refusal <?= $hasActiveOrder ? '' : 'disabled' ?> aria-disabled="true">
                            <div class="tablet-kmcr-title">Odmowa pacjenta/opiekuna</div>
                            <div class="tablet-kmcr-sub">Powód + podpis</div>
                        </button>
                        <button class="tablet-kmcr-btn" type="button" data-kmcr-refusal-redirect <?= $hasActiveOrder ? '' : 'disabled' ?>>
                            <div class="tablet-kmcr-title">Odmowa z przekierowaniem</div>
                            <div class="tablet-kmcr-sub">Wprowadź nowy adres docelowy</div>
                        </button>
                        <button class="tablet-kmcr-btn" type="button" data-kmcr-long-wait <?= $hasActiveOrder ? '' : 'disabled' ?>>
                            <div class="tablet-kmcr-title">Długi czas oczekiwania</div>
                            <div class="tablet-kmcr-sub">Powiadom dyspozytora o długim czasie oczekiwania</div>
                        </button>
                    </div>
                </div>
            </section>

            <section class="tablet-view" data-view="nav">
                <div class="tablet-card">
                    <div style="font-weight:950;font-size:20px;letter-spacing:.2px">Nawigacja</div>
                    <div class="tablet-muted" style="margin-top:8px">Uruchom nawigację w Google Maps. Możesz wrócić do SWDTM przyciskiem poniżej lub klawiszem cofania.</div>

                    <div style="margin-top:12px;display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <button class="btn-primary" type="button" data-nav-open="from" <?= $hasActiveOrder ? '' : 'disabled' ?>>Nawiguj: Skąd</button>
                        <button class="btn-primary" type="button" data-nav-open="to" <?= $hasActiveOrder ? '' : 'disabled' ?>>Nawiguj: Dokąd</button>
                    </div>

                    <div class="tablet-muted" style="margin-top:10px">
                        <div style="font-weight:900;margin-bottom:6px">Aktualne adresy</div>
                        <div><span style="font-weight:900">Skąd:</span> <span data-nav-from><?= htmlspecialchars($hasActiveOrder ? $from : '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                        <div style="margin-top:4px"><span style="font-weight:900">Dokąd:</span> <span data-nav-to><?= htmlspecialchars($hasActiveOrder ? $to : '—', ENT_QUOTES, 'UTF-8') ?></span></div>
                    </div>
                </div>
            </section>

            <section class="tablet-view" data-view="ekd">
                <div class="tablet-card">
                    <div style="font-weight:950;font-size:20px;letter-spacing:.2px">EKD</div>
                    <div class="tablet-muted" style="margin-top:8px">Tu będzie Elektroniczna Karta Drogowa.</div>
                </div>
            </section>
        </main>
    </div>

    <div class="tablet-toast-host" aria-live="polite" aria-atomic="true" data-tablet-toast-host>
        <div class="tablet-toast" role="status" data-tablet-toast>
            <svg class="tablet-toast-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M12 9v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 17h.01" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>
                <path d="M10.3 3.6h3.4c.7 0 1.35.38 1.7 1l6.15 10.7a2 2 0 0 1-1.7 3H4a2 2 0 0 1-1.7-3L8.6 4.6c.35-.62 1-1 1.7-1Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
            <div>
                <div class="tablet-toast-title" data-tablet-toast-title>Nie można wykonać</div>
                <div class="tablet-toast-text" data-tablet-toast-text>Ta czynność jest obecnie niedostępna.</div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-nav-overlay data-outside-close="1" data-esc-close="1" style="z-index:10180">
        <div class="modal" style="max-width:720px">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Nawigacja</div>
                    <div class="modal-text">Otworzymy Google Maps w nowym oknie/aplikacji. Wróć do SWDTM przyciskiem lub cofnij.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-nav-cancel>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <div class="field">
                    <span class="label">Adres</span>
                    <div class="input" style="height:auto;padding:10px 12px;white-space:pre-wrap" data-nav-address>—</div>
                </div>
            </div>

            <div class="modal-actions" style="display:flex;gap:10px;flex-wrap:wrap">
                <a class="btn-primary" href="#" target="_blank" rel="noopener" data-nav-open-maps>Otwórz Google Maps</a>
                <button class="btn-secondary" type="button" data-nav-copy>Kopiuj adres</button>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/tablet'), ENT_QUOTES, 'UTF-8') ?>" data-nav-back>Wróć do SWDTM</a>
                <button class="btn-secondary" type="button" data-nav-cancel>Zamknij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-tablet-logout-overlay data-outside-close="1" data-esc-close="1" style="z-index:10050">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Wylogować?</div>
                    <div class="modal-text">Czy na pewno chcesz się wylogować?</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-tablet-logout-cancel>×</button>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-tablet-logout-cancel>Anuluj</button>
                <a class="btn-danger" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-kmcr-patient-refusal-overlay data-outside-close="1" data-esc-close="1" style="z-index:10115">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Odmowa pacjenta/opiekuna</div>
                    <div class="modal-text">Uzupełnij powód, uwagi i pobierz podpis.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-kmcr-patient-refusal-cancel>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <label class="field">
                    <span class="label">Powód odmowy</span>
                    <textarea class="input" data-kmcr-pr-reason rows="3" style="height:auto;min-height:90px"></textarea>
                </label>
                <label class="field">
                    <span class="label">Uwagi</span>
                    <textarea class="input" data-kmcr-pr-notes rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
                <div class="field">
                    <span class="label">Podpis pacjenta/opiekuna</span>
                    <div class="tablet-sign-wrap">
                        <canvas class="tablet-sign" data-kmcr-pr-sign></canvas>
                    </div>
                    <div class="tablet-sign-actions">
                        <button class="btn-secondary" type="button" data-kmcr-pr-clear>Wyczyść podpis</button>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-kmcr-patient-refusal-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-kmcr-patient-refusal-send>Wyślij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-status-confirm-overlay data-outside-close="1" data-esc-close="1" style="z-index:10060">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title" data-status-confirm-title>Zmienić status?</div>
                    <div class="modal-text" data-status-confirm-text></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-status-confirm-cancel>×</button>
            </div>

            <form method="post" data-status-confirm-form>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="tablet_action" value="set_status" data-status-confirm-action>
                <input type="hidden" name="status_code" value="" data-status-confirm-code>
            </form>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-status-confirm-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-status-confirm-ok>OK</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-dispatch-notification-overlay data-outside-close="0" data-esc-close="0" style="z-index:10070">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Nowe zlecenie</div>
                    <div class="modal-text" data-dispatch-notification-text></div>
                </div>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <div class="field">
                    <span class="label">Numer</span>
                    <div class="input" style="height:auto;padding:10px 12px" data-dispatch-notification-number></div>
                </div>
                <div class="field">
                    <span class="label">Pilność</span>
                    <div class="input" style="height:auto;padding:10px 12px" data-dispatch-notification-urgency></div>
                </div>
                <div class="field">
                    <span class="label">Na sygnale?</span>
                    <div class="input" style="height:auto;padding:10px 12px;font-weight:950" data-dispatch-notification-sirens></div>
                </div>
                <div class="field">
                    <span class="label">Skąd</span>
                    <div class="input" style="height:auto;padding:10px 12px" data-dispatch-notification-from></div>
                </div>
                <div class="field">
                    <span class="label">Telefon</span>
                    <div class="input" style="height:auto;padding:10px 12px" data-dispatch-notification-phone></div>
                </div>
                <div class="field">
                    <span class="label">Dyspozytor</span>
                    <div class="input" style="height:auto;padding:10px 12px" data-dispatch-notification-dispatcher></div>
                </div>
            </div>

            <form method="post" data-dispatch-accept-form>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="notification_id" value="" data-dispatch-notification-id>
                <input type="hidden" name="action" value="accept">
            </form>

            <div class="modal-actions">
                <button class="btn-primary" type="button" data-dispatch-accept>Przyjmij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-dispatch-cancel-overlay data-outside-close="0" data-esc-close="0" style="z-index:10080">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Odwołano zlecenie</div>
                    <div class="modal-text" data-dispatch-cancel-text></div>
                </div>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <div class="field">
                    <span class="label">Numer</span>
                    <div class="input" style="height:auto;padding:10px 12px" data-dispatch-cancel-number></div>
                </div>
                <div class="field">
                    <span class="label">Powód</span>
                    <div class="input" style="height:auto;padding:10px 12px;white-space:pre-wrap" data-dispatch-cancel-reason></div>
                </div>
            </div>

            <form method="post" data-dispatch-cancel-ack-form>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="cancellation_id" value="" data-dispatch-cancel-id>
            </form>

            <div class="modal-actions">
                <button class="btn-primary" type="button" data-dispatch-cancel-ack>Przyjąłem</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-dispatch-urge-overlay data-outside-close="0" data-esc-close="0" style="z-index:10085">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Ponaglenie</div>
                    <div class="modal-text" data-dispatch-urge-text></div>
                </div>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <div class="field">
                    <span class="label">Polecenie</span>
                    <div class="input" style="height:auto;padding:10px 12px;font-weight:950;color:#ef4444">Udaj się na sygnale</div>
                </div>
                <div class="field">
                    <span class="label">Powód</span>
                    <div class="input" style="height:auto;padding:10px 12px;white-space:pre-wrap" data-dispatch-urge-reason></div>
                </div>
            </div>

            <form method="post" data-dispatch-urge-ack-form>
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="urge_id" value="" data-dispatch-urge-id>
            </form>

            <div class="modal-actions">
                <button class="btn-primary" type="button" data-dispatch-urge-ack>Przyjąłem</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-kmcr-wrong-address-overlay data-outside-close="1" data-esc-close="1" style="z-index:10100">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Błędny adres</div>
                    <div class="modal-text">Wybierz, który adres jest błędny i wskaż poprawny.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-kmcr-wrong-address-cancel>×</button>
            </div>

            <div class="order-form">
                <label class="field">
                    <span class="label">Który adres?</span>
                    <select class="input" data-kmcr-side>
                        <option value="">Wybierz…</option>
                        <option value="from">Skąd</option>
                        <option value="to">Dokąd</option>
                    </select>
                </label>

                <div class="suggest">
                    <label class="field">
                        <span class="label">Miejscowość</span>
                        <input class="input" type="text" autocomplete="off" inputmode="search" data-kmcr-addr-city>
                        <div class="tablet-picklist" style="max-height:min(26vh,260px);margin-top:8px" data-kmcr-addr-city-list></div>
                    </label>
                </div>

                <label class="field">
                    <span class="label">Kod pocztowy</span>
                    <input class="input" type="text" readonly data-kmcr-addr-postcode>
                </label>

                <div class="suggest">
                    <label class="field">
                        <span class="label">Ulica</span>
                        <input class="input" type="text" autocomplete="off" inputmode="search" data-kmcr-addr-street disabled>
                        <div class="tablet-picklist" style="max-height:min(26vh,260px);margin-top:8px" data-kmcr-addr-street-list></div>
                    </label>
                </div>

                <div class="suggest">
                    <label class="field">
                        <span class="label">Numer</span>
                        <input class="input" type="text" autocomplete="off" inputmode="search" data-kmcr-addr-number disabled>
                        <div class="tablet-picklist" style="max-height:min(26vh,260px);margin-top:8px" data-kmcr-addr-number-list></div>
                    </label>
                </div>

                <label class="field">
                    <span class="label">Lokal</span>
                    <input class="input" type="text" autocomplete="off" data-kmcr-addr-flat disabled>
                </label>

                <input type="hidden" data-kmcr-addr-lat value="">
                <input type="hidden" data-kmcr-addr-lon value="">

                <label class="field is-full">
                    <span class="label">Uwagi</span>
                    <textarea class="input" data-kmcr-notes rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-kmcr-wrong-address-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-kmcr-wrong-address-send>Wyślij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-kmcr-refusal-overlay data-outside-close="1" data-esc-close="1" style="z-index:10110">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Odmowa przyjęcia</div>
                    <div class="modal-text">Uzupełnij dane odmowy przyjęcia.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-kmcr-refusal-cancel>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <label class="field">
                    <span class="label">Lekarz odmawiający</span>
                    <input class="input" type="text" autocomplete="off" data-kmcr-doctor placeholder="Imię i nazwisko">
                </label>
                <label class="field">
                    <span class="label">Powód odmowy</span>
                    <textarea class="input" data-kmcr-reason rows="3" style="height:auto;min-height:90px"></textarea>
                </label>
                <label class="field">
                    <span class="label">Uwagi</span>
                    <textarea class="input" data-kmcr-notes2 rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-kmcr-refusal-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-kmcr-refusal-send>Wyślij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-kmcr-refusal-redirect-overlay data-outside-close="1" data-esc-close="1" style="z-index:10120">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Odmowa przyjęcia z przekierowaniem</div>
                    <div class="modal-text">Uzupełnij dane odmowy oraz wskaż nową placówkę.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-kmcr-refusal-redirect-cancel>×</button>
            </div>

            <div class="order-form">
                <label class="field">
                    <span class="label">Lekarz odmawiający</span>
                    <input class="input" type="text" autocomplete="off" data-kmcr-doctor3 placeholder="Imię i nazwisko">
                </label>
                <label class="field">
                    <span class="label">Powód odmowy</span>
                    <textarea class="input" data-kmcr-reason3 rows="3" style="height:auto;min-height:90px"></textarea>
                </label>

                <div class="suggest">
                    <label class="field">
                        <span class="label">Nowa placówka – miejscowość</span>
                        <input class="input" type="text" autocomplete="off" inputmode="search" data-kmcr-fac-city>
                        <div class="tablet-picklist" style="max-height:min(26vh,260px);margin-top:8px" data-kmcr-fac-city-list></div>
                    </label>
                </div>

                <label class="field">
                    <span class="label">Nowa placówka – kod pocztowy</span>
                    <input class="input" type="text" readonly data-kmcr-fac-postcode>
                </label>

                <div class="suggest">
                    <label class="field">
                        <span class="label">Nowa placówka – ulica</span>
                        <input class="input" type="text" autocomplete="off" inputmode="search" data-kmcr-fac-street disabled>
                        <div class="tablet-picklist" style="max-height:min(26vh,260px);margin-top:8px" data-kmcr-fac-street-list></div>
                    </label>
                </div>

                <div class="suggest">
                    <label class="field">
                        <span class="label">Nowa placówka – numer</span>
                        <input class="input" type="text" autocomplete="off" inputmode="search" data-kmcr-fac-number disabled>
                        <div class="tablet-picklist" style="max-height:min(26vh,260px);margin-top:8px" data-kmcr-fac-number-list></div>
                    </label>
                </div>

                <label class="field">
                    <span class="label">Nowa placówka – lokal</span>
                    <input class="input" type="text" autocomplete="off" data-kmcr-fac-flat disabled>
                </label>

                <input type="hidden" data-kmcr-fac-lat value="">
                <input type="hidden" data-kmcr-fac-lon value="">

                <label class="field is-full">
                    <span class="label">Uwagi</span>
                    <textarea class="input" data-kmcr-notes3 rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-kmcr-refusal-redirect-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-kmcr-refusal-redirect-send>Wyślij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-kmcr-long-wait-overlay data-outside-close="1" data-esc-close="1" style="z-index:10130">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Długi czas oczekiwania</div>
                    <div class="modal-text">Jeśli czekasz długo na przyjęcie/realizację, zgłoś to dyspozytorowi.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-kmcr-long-wait-cancel>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <label class="field">
                    <span class="label">Uwagi (opcjonalnie)</span>
                    <textarea class="input" data-kmcr-long-wait-notes rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-kmcr-long-wait-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-kmcr-long-wait-send>Wyślij</button>
            </div>
        </div>
    </div>

    <script>
        window.SWDTM = window.SWDTM || {};

        (function () {
            var host = document.querySelector('[data-tablet-toast-host]');
            var toast = document.querySelector('[data-tablet-toast]');
            var titleEl = document.querySelector('[data-tablet-toast-title]');
            var textEl = document.querySelector('[data-tablet-toast-text]');
            var t = null;

            function showToast(title, text) {
                if (!toast) return;
                if (t) {
                    clearTimeout(t);
                    t = null;
                }
                if (titleEl) titleEl.textContent = String(title || 'Informacja');
                if (textEl) textEl.textContent = String(text || '');
                toast.classList.add('is-show');
                t = setTimeout(function () {
                    toast.classList.remove('is-show');
                    t = null;
                }, 2600);
            }

            window.SWDTM.toast = showToast;

            if (host) {
                host.addEventListener('click', function () {
                    if (toast) toast.classList.remove('is-show');
                });
            }
        })();
        window.SWDTM.teamLocationUpdateUrl = <?= json_encode(url('/api/team_location_update.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.teamHeartbeatUrl = <?= json_encode(url('/api/team_heartbeat.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletNotificationsUrl = <?= json_encode(url('/api/tablet_notifications.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletCancellationsUrl = <?= json_encode(url('/api/tablet_cancellations.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletUrgesUrl = <?= json_encode(url('/api/tablet_urges.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.ackDispatchCancellationUrl = <?= json_encode(url('/api/ack_dispatch_cancellation.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.ackDispatchUrgeUrl = <?= json_encode(url('/api/ack_dispatch_urge.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.acceptDispatchUrl = <?= json_encode(url('/api/accept_dispatch.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletSetStatusUrl = <?= json_encode(url('/api/tablet_set_status.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletCloseOrderUrl = <?= json_encode(url('/api/tablet_close_order.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.teamActiveOrderUrl = <?= json_encode(url('/api/team_active_order.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletAddressSuggestUrl = <?= json_encode(url('/api/tablet_address_suggest.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletUpdateActiveOrderAddressUrl = <?= json_encode(url('/api/tablet_update_active_order_address.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletTeamEventUrl = <?= json_encode(url('/api/tablet_team_event.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletStatusCode = <?= json_encode($statusCurrent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletStatusLabel = <?= json_encode($statusCurrentLabel, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletStatusPrevOrder = <?= json_encode($statusPrevOrder, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.canCloseOrderThroughRestoreReady = <?= json_encode($canCloseOrderThroughRestoreReady, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.activeOrderId = <?= json_encode($activeOrder && isset($activeOrder['id']) ? (int)$activeOrder['id'] : null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.alarmUrl = <?= json_encode(url('/mp3/alarm-tablet.mp3'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletLeaderName = <?= json_encode($fullName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.tabletDriverName = <?= json_encode($driverName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.enableGeolocation = false;

        window.SWDTM.closeActiveOrderAndRestoreReady = function () {
            var closeUrl = (window.SWDTM && window.SWDTM.tabletCloseOrderUrl) ? String(window.SWDTM.tabletCloseOrderUrl) : '';
            var statusUrl = (window.SWDTM && window.SWDTM.tabletSetStatusUrl) ? String(window.SWDTM.tabletSetStatusUrl) : '';
            var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
            var orderId = (window.SWDTM && window.SWDTM.activeOrderId != null) ? Number(window.SWDTM.activeOrderId) : 0;
            if (!closeUrl || !statusUrl || !csrf || !isFinite(orderId) || orderId <= 0) return Promise.resolve(null);

            function setStatusRestoreReady() {
                return fetch(statusUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrf
                    },
                    body: JSON.stringify({ csrf: csrf, status_code: 'restore_ready' })
                })
                .then(function (r) { return r && r.ok === true ? r.json() : null; })
                .then(function (s) { return (s && s.ok === true) ? s : null; })
                .catch(function () { return null; });
            }

            return fetch(closeUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrf
                },
                body: JSON.stringify({ csrf: csrf, order_id: orderId })
            })
            .then(function (r) { return r && r.ok === true ? r.json() : null; })
            .then(function (data) {
                if (!data || data.ok !== true) return null;
                return setStatusRestoreReady();
            })
            .catch(function () { return null; });
        };

        (function () {
            window.SWDTM = window.SWDTM || {};
            window.SWDTM.tabletHomeUrl = <?= json_encode(url('/tablet'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            window.SWDTM.activeOrderFromText = <?= json_encode($hasActiveOrder ? $from : '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            window.SWDTM.activeOrderToText = <?= json_encode($hasActiveOrder ? $to : '', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

            try {
                if (window.NodeList && !NodeList.prototype.forEach) {
                    NodeList.prototype.forEach = Array.prototype.forEach;
                }
            } catch (e) {}

            try {
                if (window.Element && !Element.prototype.matches) {
                    Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
                }
            } catch (e) {}

            try {
                if (window.Element && !Element.prototype.closest) {
                    Element.prototype.closest = function (s) {
                        var el = this;
                        while (el && el.nodeType === 1) {
                            try { if (el.matches(s)) return el; } catch (e) { return null; }
                            el = el.parentElement || el.parentNode;
                        }
                        return null;
                    };
                }
            } catch (e) {}

            function onTap(el, handler) {
                if (!el || typeof handler !== 'function') return;

                var lastTapTs = 0;
                function fire(e) {
                    var now = Date.now();
                    if (now - lastTapTs < 450) return;
                    lastTapTs = now;
                    handler(e);
                }

                if (window.PointerEvent) {
                    el.addEventListener('pointerup', function (e) {
                        if (e && e.pointerType === 'mouse') return;
                        fire(e);
                    });
                } else {
                    el.addEventListener('touchend', function (e) {
                        fire(e);
                    }, false);
                }

                el.addEventListener('click', function (e) {
                    fire(e);
                });
            }

            function buildGoogleMapsUrl(addressText) {
                var q = String(addressText || '').trim();
                if (!q) return '';
                return 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(q) + '&travelmode=driving';
            }

            function openNavModal(side) {
                var overlay = document.querySelector('[data-nav-overlay]');
                if (!overlay) return;
                var addrBox = overlay.querySelector('[data-nav-address]');
                var openLink = overlay.querySelector('[data-nav-open-maps]');

                var addr = '';
                if (String(side) === 'from') addr = (window.SWDTM && window.SWDTM.activeOrderFromText) ? String(window.SWDTM.activeOrderFromText) : '';
                if (String(side) === 'to') addr = (window.SWDTM && window.SWDTM.activeOrderToText) ? String(window.SWDTM.activeOrderToText) : '';

                if (addrBox) addrBox.textContent = addr ? addr : '—';
                if (openLink) {
                    var u = buildGoogleMapsUrl(addr);
                    openLink.setAttribute('href', u ? u : '#');
                    openLink.classList.toggle('is-disabled', !u);
                }

                overlay.classList.add('is-open');
            }

            document.querySelectorAll('[data-nav-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.disabled) return;
                    openNavModal(btn.getAttribute('data-nav-open'));
                });
            });

            (function () {
                var overlay = document.querySelector('[data-nav-overlay]');
                if (!overlay) return;
                function close() { overlay.classList.remove('is-open'); }

                overlay.querySelectorAll('[data-nav-cancel]').forEach(function (b) {
                    b.addEventListener('click', function () { close(); });
                });

                var copyBtn = overlay.querySelector('[data-nav-copy]');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function () {
                        var t = overlay.querySelector('[data-nav-address]');
                        var txt = t ? String(t.textContent || '').trim() : '';
                        if (!txt || txt === '—') return;
                        try {
                            if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(txt);
                            } else {
                                var ta = document.createElement('textarea');
                                ta.value = txt;
                                ta.style.position = 'fixed';
                                ta.style.left = '-9999px';
                                document.body.appendChild(ta);
                                ta.focus();
                                ta.select();
                                document.execCommand('copy');
                                document.body.removeChild(ta);
                            }
                            if (window.SWDTM && typeof window.SWDTM.toast === 'function') {
                                window.SWDTM.toast('Skopiowano', 'Adres skopiowany do schowka.');
                            }
                        } catch (e) {
                        }
                    });
                }
            })();

            try {
                setTimeout(function () { window.scrollTo(0, 1); }, 50);
            } catch (e) {
            }

            try {
                if ('serviceWorker' in navigator) {
                    navigator.serviceWorker.register('<?= htmlspecialchars(url('/sw.js'), ENT_QUOTES, 'UTF-8') ?>');
                }
            } catch (e) {}

            function setActive(tab) {
                if (!tab) return;
                document.querySelectorAll('.tablet-tab').forEach(function (t) {
                    t.classList.toggle('is-active', t === tab);
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });

                var key = tab.getAttribute('data-tab') || 'status';
                document.querySelectorAll('.tablet-view').forEach(function (v) {
                    v.classList.toggle('is-active', v.getAttribute('data-view') === key);
                });

                if (key === 'active') {
                    try {
                        if (window.SWDTM && typeof window.SWDTM.refreshActiveOrderDetails === 'function') {
                            window.SWDTM.refreshActiveOrderDetails(true);
                        }
                    } catch (e) {}
                }
            }

            document.querySelectorAll('.tablet-tab').forEach(function (tab) {
                onTap(tab, function () {
                    if (tab.classList.contains('is-disabled')) {
                        if (window.SWDTM && typeof window.SWDTM.toast === 'function') {
                            window.SWDTM.toast('Nie można przejść', 'Zakładka jest niedostępna.');
                        }
                        return;
                    }
                    setActive(tab);
                });

                tab.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    e.preventDefault();
                    if (tab.classList.contains('is-disabled')) {
                        if (window.SWDTM && typeof window.SWDTM.toast === 'function') {
                            window.SWDTM.toast('Nie można przejść', 'Zakładka jest niedostępna.');
                        }
                        return;
                    }
                    setActive(tab);
                });
            });

            var logout = document.querySelector('[data-tablet-logout]');
            if (logout) {
                logout.addEventListener('click', function (e) {
                    e.preventDefault();
                    var overlay = document.querySelector('[data-tablet-logout-overlay]');
                    if (overlay) overlay.classList.add('is-open');
                });
            }

            var overlay = document.querySelector('[data-tablet-logout-overlay]');
            if (overlay) {
                var cancels = overlay.querySelectorAll('[data-tablet-logout-cancel]');
                cancels.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        overlay.classList.remove('is-open');
                    });
                });

                overlay.addEventListener('click', function (e) {
                    if (e.target === overlay && overlay.getAttribute('data-outside-close') === '1') {
                        overlay.classList.remove('is-open');
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    if (overlay.classList.contains('is-open') && overlay.getAttribute('data-esc-close') === '1') {
                        overlay.classList.remove('is-open');
                    }
                });
            }

            var driverOverlay = document.querySelector('[data-tablet-driver-overlay]');
            if (driverOverlay) {
                var filter = driverOverlay.querySelector('[data-driver-filter]');
                var list = driverOverlay.querySelector('[data-driver-list]');
                var hidden = driverOverlay.querySelector('[data-driver-user-id]');
                var submit = driverOverlay.querySelector('[data-driver-submit]');

                function normalize(s) {
                    try { return String(s || '').toLowerCase(); } catch (e) { return ''; }
                }

                function setSelected(btn) {
                    if (!list || !hidden || !submit) return;
                    list.querySelectorAll('[data-driver-pick]').forEach(function (b) {
                        b.classList.toggle('is-selected', b === btn);
                    });
                    var id = btn ? btn.getAttribute('data-driver-id') : '';
                    hidden.value = id ? String(id) : '';
                    submit.disabled = (hidden.value === '');
                }

                function applyFilter() {
                    if (!list || !hidden || !submit) return;
                    var q = normalize(filter ? filter.value : '');
                    var anyVisible = false;
                    list.querySelectorAll('[data-driver-pick]').forEach(function (b) {
                        var label = normalize(b.getAttribute('data-driver-label') || b.textContent || '');
                        var show = (q === '') || (label.indexOf(q) !== -1);
                        b.style.display = show ? '' : 'none';
                        if (show) anyVisible = true;
                    });

                    if (!anyVisible) {
                        hidden.value = '';
                        submit.disabled = true;
                        list.querySelectorAll('[data-driver-pick]').forEach(function (b) {
                            b.classList.remove('is-selected');
                        });
                    }
                }

                if (list) {
                    list.querySelectorAll('[data-driver-pick]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            setSelected(btn);
                        });
                    });
                }

                if (filter) {
                    filter.addEventListener('input', function () {
                        applyFilter();
                    });
                }

                if (submit) {
                    submit.disabled = true;
                }
                applyFilter();
            }

            var noDriverOpen = document.querySelector('[data-no-driver-open]');
            var noDriverOverlay = document.querySelector('[data-no-driver-overlay]');
            if (noDriverOpen && noDriverOverlay) {
                noDriverOpen.addEventListener('click', function () {
                    noDriverOverlay.classList.add('is-open');
                });

                var cancel = noDriverOverlay.querySelector('[data-no-driver-cancel]');
                if (cancel) {
                    cancel.addEventListener('click', function () {
                        noDriverOverlay.classList.remove('is-open');
                    });
                }

                var confirm = noDriverOverlay.querySelector('[data-no-driver-confirm]');
                if (confirm) {
                    confirm.addEventListener('click', function () {
                        var f = noDriverOverlay.querySelector('[data-no-driver-form]');
                        if (f) f.submit();
                    });
                }

                noDriverOverlay.addEventListener('click', function (e) {
                    if (e.target === noDriverOverlay && noDriverOverlay.getAttribute('data-outside-close') === '1') {
                        noDriverOverlay.classList.remove('is-open');
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    if (noDriverOverlay.classList.contains('is-open') && noDriverOverlay.getAttribute('data-esc-close') === '1') {
                        noDriverOverlay.classList.remove('is-open');
                    }
                });
            }

            var statusOverlay = document.querySelector('[data-status-confirm-overlay]');
            if (statusOverlay) {
                var titleEl = statusOverlay.querySelector('[data-status-confirm-title]');
                var textEl = statusOverlay.querySelector('[data-status-confirm-text]');
                var cancelEl = statusOverlay.querySelector('[data-status-confirm-cancel]');
                var okEl = statusOverlay.querySelector('[data-status-confirm-ok]');
                var formEl = statusOverlay.querySelector('[data-status-confirm-form]');
                var actionEl = statusOverlay.querySelector('[data-status-confirm-action]');
                var codeEl = statusOverlay.querySelector('[data-status-confirm-code]');

                var orderCodes = ['order_start', 'order_patient', 'order_transport', 'order_realization', 'order_handover', 'order_return'];

                function applyOrderStatusDisabled() {
                    var cur = (window.SWDTM && window.SWDTM.tabletStatusCode) ? String(window.SWDTM.tabletStatusCode || '') : '';
                    var curIdx = orderCodes.indexOf(cur);
                    if (curIdx < 0) return;

                    document.querySelectorAll('[data-order-status-section] [data-status-pick]').forEach(function (btn) {
                        var code = String(btn.getAttribute('data-status-code') || '');
                        var idx = orderCodes.indexOf(code);
                        if (idx < 0) return;
                        var shouldDisable = idx < curIdx;
                        if (cur === 'order_return' && code === 'order_patient') {
                            shouldDisable = false;
                        }
                        if (shouldDisable) {
                            btn.disabled = true;
                            btn.classList.add('is-disabled');
                        }
                    });
                }

                function openStatusConfirm(action, code, label) {
                    if (!formEl || !actionEl || !codeEl) return;
                    actionEl.value = String(action || 'set_status');
                    codeEl.value = String(code || '');

                    if (titleEl) {
                        titleEl.textContent = (action === 'restore_status') ? 'Przywrócić status?' : 'Zmienić status?';
                    }
                    if (textEl) {
                        if (action === 'restore_status') {
                            textEl.textContent = 'Czy na pewno chcesz przywrócić poprzedni status?';
                        } else {
                            textEl.textContent = 'Czy na pewno chcesz ustawić status: ' + String(label || '') + '?';
                        }
                    }
                    statusOverlay.classList.add('is-open');
                }

                applyOrderStatusDisabled();

                document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                    onTap(btn, function () {
                        if (btn.disabled) return;

                        var cur = (window.SWDTM && window.SWDTM.tabletStatusCode) ? String(window.SWDTM.tabletStatusCode || '') : '';
                        var curIdx = orderCodes.indexOf(cur);
                        var next = String(btn.getAttribute('data-status-code') || '');
                        var nextIdx = orderCodes.indexOf(next);
                        if (cur === 'order_return' && next === 'order_patient') {
                            openStatusConfirm('set_status', btn.getAttribute('data-status-code'), btn.getAttribute('data-status-label'));
                            return;
                        }
                        if (curIdx >= 0 && nextIdx >= 0 && nextIdx < curIdx) {
                            if (window.SWDTM && typeof window.SWDTM.toast === 'function') {
                                window.SWDTM.toast('Nie można cofnąć statusu', 'Użyj: Przywróć poprzedni status.');
                            }
                            btn.disabled = true;
                            btn.classList.add('is-disabled');
                            return;
                        }
                        openStatusConfirm('set_status', btn.getAttribute('data-status-code'), btn.getAttribute('data-status-label'));
                    });
                });

                var restoreOpen = document.querySelector('[data-status-restore-open]');
                if (restoreOpen) {
                    restoreOpen.addEventListener('click', function () {
                        if (restoreOpen.disabled) return;
                        openStatusConfirm('restore_status', '1', '');
                    });
                }

                if (cancelEl) {
                    cancelEl.addEventListener('click', function () {
                        statusOverlay.classList.remove('is-open');
                    });
                }

                if (okEl) {
                    okEl.addEventListener('click', function () {
                        if (!formEl || !actionEl || !codeEl) return;
                        var action = String(actionEl.value || 'set_status');
                        var code = String(codeEl.value || '');

                        if (action !== 'set_status') {
                            formEl.submit();
                            return;
                        }

                        if (code === 'restore_ready') {
                            try {
                                if (window.SWDTM && typeof window.SWDTM.closeActiveOrderAndRestoreReady === 'function') {
                                    window.SWDTM.closeActiveOrderAndRestoreReady().then(function () {
                                        statusOverlay.classList.remove('is-open');
                                        window.location.reload();
                                    });
                                    return;
                                }
                            } catch (e) {
                            }
                            return;
                        }

                        var url = (window.SWDTM && window.SWDTM.tabletSetStatusUrl) ? String(window.SWDTM.tabletSetStatusUrl) : '';
                        var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                        if (!url || !csrf || !code) return;

                        fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrf
                            },
                            body: JSON.stringify({ csrf: csrf, status_code: code })
                        })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (s) {
                            if (!s || s.ok !== true) return;
                            statusOverlay.classList.remove('is-open');
                            window.location.reload();
                        })
                        .catch(function () {});
                    });
                }

                statusOverlay.addEventListener('click', function (e) {
                    if (e.target === statusOverlay && statusOverlay.getAttribute('data-outside-close') === '1') {
                        statusOverlay.classList.remove('is-open');
                    }
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key !== 'Escape') return;
                    if (statusOverlay.classList.contains('is-open') && statusOverlay.getAttribute('data-esc-close') === '1') {
                        statusOverlay.classList.remove('is-open');
                    }
                });
            }

            (function () {
                function open(overlay) {
                    if (!overlay) return;
                    overlay.classList.add('is-open');
                }

                function close(overlay) {
                    if (!overlay) return;
                    overlay.classList.remove('is-open');
                }

                function attachBasicClose(overlay, cancelSelector) {
                    if (!overlay) return;
                    overlay.querySelectorAll(cancelSelector).forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            close(overlay);
                        });
                    });

                    overlay.addEventListener('click', function (e) {
                        if (e.target === overlay && overlay.getAttribute('data-outside-close') === '1') {
                            close(overlay);
                        }
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.key !== 'Escape') return;
                        if (overlay.classList.contains('is-open') && overlay.getAttribute('data-esc-close') === '1') {
                            close(overlay);
                        }
                    });
                }

                function debounce(fn, ms) {
                    var t = null;
                    return function () {
                        var args = arguments;
                        if (t) clearTimeout(t);
                        t = setTimeout(function () {
                            t = null;
                            fn.apply(null, args);
                        }, ms);
                    };
                }

                function renderSuggestList(container, items, onPick) {
                    if (!container) return;
                    while (container.firstChild) container.removeChild(container.firstChild);

                    if (!Array.isArray(items) || !items.length) {
                        var empty = document.createElement('div');
                        empty.className = 'tablet-muted';
                        empty.style.padding = '8px 2px';
                        empty.textContent = 'Brak sugestii.';
                        container.appendChild(empty);
                        return;
                    }

                    items.forEach(function (it) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'tablet-pick';
                        btn.style.padding = '12px 12px';

                        var name = document.createElement('div');
                        name.className = 'tablet-pick-name';
                        name.style.fontSize = '15px';
                        name.textContent = String(it.label || '');

                        btn.appendChild(name);
                        btn.addEventListener('click', function () {
                            if (typeof onPick === 'function') onPick(it, btn);
                        });

                        container.appendChild(btn);
                    });
                }

                function photonUrl(q, type, city, street) {
                    var base = '/api/photon.php';
                    try {
                        if (window.SWDTM && window.SWDTM.geocoderUrl) base = String(window.SWDTM.geocoderUrl);
                    } catch (e) {}
                    var u = new URL(base, window.location.origin);
                    u.searchParams.set('q', String(q || ''));
                    u.searchParams.set('type', String(type || ''));
                    if (city) u.searchParams.set('city', String(city));
                    if (street) u.searchParams.set('street', String(street));
                    return u.toString();
                }

                function fetchPhoton(q, type, city, street, cb) {
                    var url = photonUrl(q, type, city, street);
                    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (r) { return r && r.ok === true ? r.text() : ''; })
                        .then(function (txt) {
                            try {
                                var data = JSON.parse(txt);
                                cb(Array.isArray(data) ? data : []);
                            } catch (e) {
                                cb([]);
                            }
                        })
                        .catch(function () { cb([]); });
                }

                function photonProps(it) {
                    try {
                        return (it && it.properties && typeof it.properties === 'object') ? it.properties : {};
                    } catch (e) {
                        return {};
                    }
                }

                function photonCoords(it) {
                    try {
                        var g = it && it.geometry ? it.geometry : null;
                        var c = g && Array.isArray(g.coordinates) ? g.coordinates : null;
                        if (!c || c.length < 2) return null;
                        return { lon: c[0], lat: c[1] };
                    } catch (e) {
                        return null;
                    }
                }

                function listFromPhoton(items, kind) {
                    var out = [];
                    (Array.isArray(items) ? items : []).slice(0, 6).forEach(function (it) {
                        var p = photonProps(it);
                        var label = '';
                        if (kind === 'city') {
                            label = String(p.name || '');
                        } else if (kind === 'street') {
                            label = String(p.name || p.street || p.road || '');
                        } else if (kind === 'number') {
                            label = String(p.housenumber || p.name || '');
                        }
                        label = String(label || '').trim();
                        if (!label) return;
                        out.push({ label: label, raw: it });
                    });
                    return out;
                }

                function initPhotonChain(root, cfg) {
                    if (!root) return;
                    var cityEl = root.querySelector(cfg.city);
                    var cityList = root.querySelector(cfg.cityList);
                    var pcEl = root.querySelector(cfg.postcode);
                    var streetEl = root.querySelector(cfg.street);
                    var streetList = root.querySelector(cfg.streetList);
                    var numEl = root.querySelector(cfg.number);
                    var numList = root.querySelector(cfg.numberList);
                    var flatEl = root.querySelector(cfg.flat);
                    var latEl = root.querySelector(cfg.lat);
                    var lonEl = root.querySelector(cfg.lon);

                    function resetBelow(level) {
                        try {
                            if (level <= 1) {
                                if (streetEl) { streetEl.value = ''; streetEl.disabled = true; }
                                if (numEl) { numEl.value = ''; numEl.disabled = true; }
                                if (flatEl) { flatEl.value = ''; flatEl.disabled = true; }
                                if (pcEl) pcEl.value = '';
                                if (latEl) latEl.value = '';
                                if (lonEl) lonEl.value = '';
                                if (streetList) while (streetList.firstChild) streetList.removeChild(streetList.firstChild);
                                if (numList) while (numList.firstChild) numList.removeChild(numList.firstChild);
                            }
                            if (level <= 2) {
                                if (numEl) { numEl.value = ''; numEl.disabled = true; }
                                if (flatEl) { flatEl.value = ''; flatEl.disabled = true; }
                                if (latEl) latEl.value = '';
                                if (lonEl) lonEl.value = '';
                                if (numList) while (numList.firstChild) numList.removeChild(numList.firstChild);
                            }
                            if (level <= 3) {
                                if (flatEl) { flatEl.value = ''; flatEl.disabled = false; }
                            }
                        } catch (e) {}
                    }

                    if (flatEl) flatEl.disabled = true;

                    var refreshCity = debounce(function () {
                        var q = cityEl ? String(cityEl.value || '').trim() : '';
                        if (q.length < 3) {
                            if (cityList) while (cityList.firstChild) cityList.removeChild(cityList.firstChild);
                            resetBelow(1);
                            return;
                        }
                        fetchPhoton(q, 'city', '', '', function (items) {
                            var list = listFromPhoton(items, 'city');
                            renderSuggestList(cityList, list, function (it, btn) {
                                if (cityList) {
                                    cityList.querySelectorAll('.tablet-pick').forEach(function (b) { b.classList.toggle('is-selected', b === btn); });
                                }
                                var p = photonProps(it.raw);
                                if (cityEl) cityEl.value = String(p.name || cityEl.value || '');
                                resetBelow(1);
                                if (streetEl) streetEl.disabled = false;
                            });
                        });
                    }, 300);

                    var refreshStreet = debounce(function () {
                        var q = streetEl ? String(streetEl.value || '').trim() : '';
                        var cityVal = cityEl ? String(cityEl.value || '').trim() : '';
                        if (q.length < 3 || !cityVal) {
                            if (streetList) while (streetList.firstChild) streetList.removeChild(streetList.firstChild);
                            resetBelow(2);
                            return;
                        }
                        fetchPhoton(q, 'street', cityVal, '', function (items) {
                            var list = listFromPhoton(items, 'street');
                            renderSuggestList(streetList, list, function (it, btn) {
                                if (streetList) {
                                    streetList.querySelectorAll('.tablet-pick').forEach(function (b) { b.classList.toggle('is-selected', b === btn); });
                                }
                                var p = photonProps(it.raw);
                                if (streetEl) streetEl.value = String((p.street || p.road || p.name || streetEl.value || '')).trim();
                                if (pcEl) pcEl.value = String(p.postcode || pcEl.value || '');
                                resetBelow(2);
                                if (numEl) numEl.disabled = false;
                            });
                        });
                    }, 300);

                    var refreshNumber = debounce(function () {
                        var q = numEl ? String(numEl.value || '').trim() : '';
                        var cityVal = cityEl ? String(cityEl.value || '').trim() : '';
                        var streetVal = streetEl ? String(streetEl.value || '').trim() : '';
                        if (q.length < 1 || !cityVal || !streetVal) {
                            if (numList) while (numList.firstChild) numList.removeChild(numList.firstChild);
                            if (latEl) latEl.value = '';
                            if (lonEl) lonEl.value = '';
                            return;
                        }
                        fetchPhoton(q, 'number', cityVal, streetVal, function (items) {
                            var list = listFromPhoton(items, 'number');
                            renderSuggestList(numList, list, function (it, btn) {
                                if (numList) {
                                    numList.querySelectorAll('.tablet-pick').forEach(function (b) { b.classList.toggle('is-selected', b === btn); });
                                }
                                var p = photonProps(it.raw);
                                var house = String(p.housenumber || p.name || '').trim();
                                if (numEl) numEl.value = house || String(numEl.value || '').trim();
                                if (pcEl && !String(pcEl.value || '').trim()) pcEl.value = String(p.postcode || '');
                                var c = photonCoords(it.raw);
                                if (c) {
                                    if (latEl) latEl.value = String(c.lat);
                                    if (lonEl) lonEl.value = String(c.lon);
                                }
                                if (flatEl) flatEl.disabled = false;
                            });
                        });
                    }, 250);

                    if (cityEl) {
                        cityEl.addEventListener('input', function () {
                            resetBelow(1);
                            refreshCity();
                        });
                    }
                    if (streetEl) {
                        streetEl.addEventListener('input', function () {
                            resetBelow(2);
                            refreshStreet();
                        });
                    }
                    if (numEl) {
                        numEl.addEventListener('input', function () {
                            refreshNumber();
                        });
                    }

                    resetBelow(1);
                }

                var wrongBtn = document.querySelector('[data-kmcr-wrong-address]');
                var wrongOverlay = document.querySelector('[data-kmcr-wrong-address-overlay]');
                var refusalBtn = document.querySelector('[data-kmcr-refusal]');
                var refusalOverlay = document.querySelector('[data-kmcr-refusal-overlay]');
                var patientRefusalBtn = document.querySelector('[data-kmcr-patient-refusal]');
                var patientRefusalOverlay = document.querySelector('[data-kmcr-patient-refusal-overlay]');
                var redirectBtn = document.querySelector('[data-kmcr-refusal-redirect]');
                var redirectOverlay = document.querySelector('[data-kmcr-refusal-redirect-overlay]');
                var longWaitBtn = document.querySelector('[data-kmcr-long-wait]');
                var longWaitOverlay = document.querySelector('[data-kmcr-long-wait-overlay]');

                attachBasicClose(wrongOverlay, '[data-kmcr-wrong-address-cancel]');
                attachBasicClose(refusalOverlay, '[data-kmcr-refusal-cancel]');
                attachBasicClose(patientRefusalOverlay, '[data-kmcr-patient-refusal-cancel]');
                attachBasicClose(redirectOverlay, '[data-kmcr-refusal-redirect-cancel]');
                attachBasicClose(longWaitOverlay, '[data-kmcr-long-wait-cancel]');

                if (patientRefusalBtn && patientRefusalOverlay) {
                    patientRefusalBtn.addEventListener('click', function () {
                        if (patientRefusalBtn.hasAttribute('disabled') || patientRefusalBtn.classList.contains('is-disabled')) {
                            if (window.SWDTM && typeof window.SWDTM.toast === 'function') {
                                window.SWDTM.toast('Nie można wykonać', 'Ta czynność jest obecnie niedostępna.');
                            }
                            return;
                        }

                        open(patientRefusalOverlay);
                        try {
                            var reason = patientRefusalOverlay.querySelector('[data-kmcr-pr-reason]');
                            var notes = patientRefusalOverlay.querySelector('[data-kmcr-pr-notes]');
                            if (reason) reason.value = '';
                            if (notes) notes.value = '';
                            if (patientSig) patientSig.clear();
                            if (patientSig) patientSig.resize();
                        } catch (e) {}
                    });
                }

                if (wrongBtn && wrongOverlay) {
                    wrongBtn.addEventListener('click', function () {
                        open(wrongOverlay);
                        var side = wrongOverlay.querySelector('[data-kmcr-side]');
                        var notes = wrongOverlay.querySelector('[data-kmcr-notes]');
                        if (side) side.value = '';
                        if (notes) notes.value = '';

                        try {
                            var city = wrongOverlay.querySelector('[data-kmcr-addr-city]');
                            var pc = wrongOverlay.querySelector('[data-kmcr-addr-postcode]');
                            var street = wrongOverlay.querySelector('[data-kmcr-addr-street]');
                            var num = wrongOverlay.querySelector('[data-kmcr-addr-number]');
                            var flat = wrongOverlay.querySelector('[data-kmcr-addr-flat]');
                            var lat = wrongOverlay.querySelector('[data-kmcr-addr-lat]');
                            var lon = wrongOverlay.querySelector('[data-kmcr-addr-lon]');
                            if (city) city.value = '';
                            if (pc) pc.value = '';
                            if (street) { street.value = ''; street.disabled = true; }
                            if (num) { num.value = ''; num.disabled = true; }
                            if (flat) { flat.value = ''; flat.disabled = true; }
                            if (lat) lat.value = '';
                            if (lon) lon.value = '';

                            var cityList = wrongOverlay.querySelector('[data-kmcr-addr-city-list]');
                            var streetList = wrongOverlay.querySelector('[data-kmcr-addr-street-list]');
                            var numList = wrongOverlay.querySelector('[data-kmcr-addr-number-list]');
                            if (cityList) while (cityList.firstChild) cityList.removeChild(cityList.firstChild);
                            if (streetList) while (streetList.firstChild) streetList.removeChild(streetList.firstChild);
                            if (numList) while (numList.firstChild) numList.removeChild(numList.firstChild);
                        } catch (e) {}
                    });
                }

                if (wrongOverlay) {
                    var sendBtn = wrongOverlay.querySelector('[data-kmcr-wrong-address-send]');

                    initPhotonChain(wrongOverlay, {
                        city: '[data-kmcr-addr-city]',
                        cityList: '[data-kmcr-addr-city-list]',
                        postcode: '[data-kmcr-addr-postcode]',
                        street: '[data-kmcr-addr-street]',
                        streetList: '[data-kmcr-addr-street-list]',
                        number: '[data-kmcr-addr-number]',
                        numberList: '[data-kmcr-addr-number-list]',
                        flat: '[data-kmcr-addr-flat]',
                        lat: '[data-kmcr-addr-lat]',
                        lon: '[data-kmcr-addr-lon]'
                    });

                    if (sendBtn) {
                        sendBtn.addEventListener('click', function () {
                            var url = (window.SWDTM && window.SWDTM.tabletUpdateActiveOrderAddressUrl) ? String(window.SWDTM.tabletUpdateActiveOrderAddressUrl) : '';
                            var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                            if (!url || !csrf) return;

                            var sideEl = wrongOverlay.querySelector('[data-kmcr-side]');
                            var side = sideEl ? String(sideEl.value || '').trim() : '';
                            if (!side) {
                                alert('Wybierz, który adres jest błędny (skąd/dokąd).');
                                return;
                            }

                            var cityEl = wrongOverlay.querySelector('[data-kmcr-addr-city]');
                            var pcEl = wrongOverlay.querySelector('[data-kmcr-addr-postcode]');
                            var streetEl = wrongOverlay.querySelector('[data-kmcr-addr-street]');
                            var numEl = wrongOverlay.querySelector('[data-kmcr-addr-number]');
                            var flatEl = wrongOverlay.querySelector('[data-kmcr-addr-flat]');
                            var latEl = wrongOverlay.querySelector('[data-kmcr-addr-lat]');
                            var lonEl = wrongOverlay.querySelector('[data-kmcr-addr-lon]');

                            var city = cityEl ? String(cityEl.value || '').trim() : '';
                            var pc = pcEl ? String(pcEl.value || '').trim() : '';
                            var street = streetEl ? String(streetEl.value || '').trim() : '';
                            var num = numEl ? String(numEl.value || '').trim() : '';
                            var flat = flatEl ? String(flatEl.value || '').trim() : '';
                            var lat = latEl ? String(latEl.value || '').trim() : '';
                            var lon = lonEl ? String(lonEl.value || '').trim() : '';

                            if (!city || !street || !num || !lat || !lon) {
                                alert('Uzupełnij adres: miasto, ulica, numer (z sugestii) oraz współrzędne.');
                                return;
                            }

                            var notesEl = wrongOverlay.querySelector('[data-kmcr-notes]');
                            var notes = notesEl ? String(notesEl.value || '').trim() : '';

                            sendBtn.disabled = true;
                            fetch(url, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                                body: JSON.stringify({ csrf: csrf, side: side, city: city, postcode: pc, street: street, number: num, flat: flat, lat: Number(lat), lon: Number(lon), notes: notes })
                            })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                sendBtn.disabled = false;
                                if (!data || data.ok !== true) {
                                    alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się zapisać poprawki adresu.'));
                                    return;
                                }
                                close(wrongOverlay);
                                try {
                                    if (window.SWDTM && typeof window.SWDTM.refreshActiveOrderDetails === 'function') {
                                        window.SWDTM.refreshActiveOrderDetails(true);
                                    }
                                } catch (e) {}
                            })
                            .catch(function (e) {
                                sendBtn.disabled = false;
                                alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                            });
                        });
                    }
                }

                function sendRefusal(overlay, type) {
                    var url = (window.SWDTM && window.SWDTM.tabletTeamEventUrl) ? String(window.SWDTM.tabletTeamEventUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    if (!url || !csrf) return;

                    var doctorEl = overlay.querySelector(type === 'Odmowa przyjęcia' ? '[data-kmcr-doctor]' : '[data-kmcr-doctor3]');
                    var reasonEl = overlay.querySelector(type === 'Odmowa przyjęcia' ? '[data-kmcr-reason]' : '[data-kmcr-reason3]');
                    var notesEl = overlay.querySelector(type === 'Odmowa przyjęcia' ? '[data-kmcr-notes2]' : '[data-kmcr-notes3]');
                    var doctor = doctorEl ? String(doctorEl.value || '').trim() : '';
                    var reason = reasonEl ? String(reasonEl.value || '').trim() : '';
                    var notes = notesEl ? String(notesEl.value || '').trim() : '';

                    if (!doctor || !reason) {
                        alert('Uzupełnij lekarza oraz powód odmowy.');
                        return;
                    }

                    var payload = { doctor: doctor, reason: reason, notes: notes };

                    if (type === 'Odmowa przyjęcia z przekierowaniem') {
                        var fc = overlay.querySelector('[data-kmcr-fac-city]');
                        var fpc = overlay.querySelector('[data-kmcr-fac-postcode]');
                        var fs = overlay.querySelector('[data-kmcr-fac-street]');
                        var fn = overlay.querySelector('[data-kmcr-fac-number]');
                        var ff = overlay.querySelector('[data-kmcr-fac-flat]');
                        var flat = ff ? String(ff.value || '').trim() : '';
                        var city = fc ? String(fc.value || '').trim() : '';
                        var pc = fpc ? String(fpc.value || '').trim() : '';
                        var street = fs ? String(fs.value || '').trim() : '';
                        var num = fn ? String(fn.value || '').trim() : '';

                        var flatEl = overlay.querySelector('[data-kmcr-fac-flat]');
                        var latEl = overlay.querySelector('[data-kmcr-fac-lat]');
                        var lonEl = overlay.querySelector('[data-kmcr-fac-lon]');
                        var lat = latEl ? String(latEl.value || '').trim() : '';
                        var lon = lonEl ? String(lonEl.value || '').trim() : '';

                        if (!city || !street || !num) {
                            alert('Uzupełnij adres nowej placówki: miasto, ulica, numer.');
                            return;
                        }

                        payload.facility_city = city;
                        payload.facility_postcode = pc;
                        payload.facility_street = street;
                        payload.facility_number = num;
                        payload.facility_flat = flat;
                        payload.facility_lat = (lat !== '' ? Number(lat) : null);
                        payload.facility_lon = (lon !== '' ? Number(lon) : null);
                    }

                    var sendBtn = overlay.querySelector(type === 'Odmowa przyjęcia' ? '[data-kmcr-refusal-send]' : '[data-kmcr-refusal-redirect-send]');
                    if (sendBtn) sendBtn.disabled = true;

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        body: JSON.stringify({ csrf: csrf, type: type, payload: payload })
                    })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        if (sendBtn) sendBtn.disabled = false;
                        if (!data || data.ok !== true) {
                            alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się wysłać czynności.'));
                            return;
                        }
                        close(overlay);
                        try {
                            if (doctorEl) doctorEl.value = '';
                            if (reasonEl) reasonEl.value = '';
                            if (notesEl) notesEl.value = '';
                        } catch (e) {}
                    })
                    .catch(function (e) {
                        if (sendBtn) sendBtn.disabled = false;
                        alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                    });
                }

                if (refusalBtn && refusalOverlay) {
                    refusalBtn.addEventListener('click', function () {
                        open(refusalOverlay);
                        var d = refusalOverlay.querySelector('[data-kmcr-doctor]');
                        var r = refusalOverlay.querySelector('[data-kmcr-reason]');
                        var n = refusalOverlay.querySelector('[data-kmcr-notes2]');
                        if (d) d.value = '';
                        if (r) r.value = '';
                        if (n) n.value = '';
                    });
                }
                if (refusalOverlay) {
                    var send = refusalOverlay.querySelector('[data-kmcr-refusal-send]');
                    if (send) {
                        send.addEventListener('click', function () {
                            sendRefusal(refusalOverlay, 'Odmowa przyjęcia');
                        });
                    }
                }

                function initSignaturePad(overlay) {
                    var canvas = overlay ? overlay.querySelector('[data-kmcr-pr-sign]') : null;
                    if (!(canvas instanceof HTMLCanvasElement)) return null;

                    var ctx = canvas.getContext('2d');
                    if (!ctx) return null;

                    var drawing = false;
                    var hasStroke = false;
                    var lastX = 0;
                    var lastY = 0;

                    function resize() {
                        var rect = canvas.getBoundingClientRect();
                        var w = Math.max(1, Math.floor(rect.width));
                        var h = Math.max(1, Math.floor(rect.height));
                        var dpr = window.devicePixelRatio ? Math.max(1, Math.min(3, window.devicePixelRatio)) : 1;

                        var prev = null;
                        try {
                            prev = canvas.toDataURL('image/png');
                        } catch (e) { prev = null; }

                        canvas.width = Math.floor(w * dpr);
                        canvas.height = Math.floor(h * dpr);
                        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

                        ctx.lineWidth = 2.8;
                        ctx.lineCap = 'round';
                        ctx.lineJoin = 'round';
                        ctx.strokeStyle = '#0f172a';

                        ctx.clearRect(0, 0, w, h);

                        if (prev && hasStroke) {
                            var img = new Image();
                            img.onload = function () {
                                try { ctx.drawImage(img, 0, 0, w, h); } catch (e) {}
                            };
                            img.src = prev;
                        }
                    }

                    function posFromEvent(e) {
                        var rect = canvas.getBoundingClientRect();
                        var clientX = 0;
                        var clientY = 0;

                        if (e && e.touches && e.touches[0]) {
                            clientX = e.touches[0].clientX;
                            clientY = e.touches[0].clientY;
                        } else {
                            clientX = e.clientX;
                            clientY = e.clientY;
                        }
                        return {
                            x: clientX - rect.left,
                            y: clientY - rect.top
                        };
                    }

                    function start(e) {
                        drawing = true;
                        var p = posFromEvent(e);
                        lastX = p.x;
                        lastY = p.y;
                        try { ctx.beginPath(); ctx.moveTo(lastX, lastY); } catch (err) {}
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                    }

                    function move(e) {
                        if (!drawing) return;
                        var p = posFromEvent(e);
                        try {
                            ctx.lineTo(p.x, p.y);
                            ctx.stroke();
                        } catch (err) {}
                        lastX = p.x;
                        lastY = p.y;
                        hasStroke = true;
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                    }

                    function end() {
                        drawing = false;
                    }

                    canvas.addEventListener('pointerdown', start);
                    canvas.addEventListener('pointermove', move);
                    canvas.addEventListener('pointerup', end);
                    canvas.addEventListener('pointercancel', end);
                    canvas.addEventListener('pointerleave', end);
                    canvas.addEventListener('touchstart', start, { passive: false });
                    canvas.addEventListener('touchmove', move, { passive: false });
                    canvas.addEventListener('touchend', end);

                    window.addEventListener('resize', function () { try { resize(); } catch (e) {} });
                    setTimeout(function () { try { resize(); } catch (e) {} }, 50);

                    return {
                        resize: resize,
                        clear: function () {
                            hasStroke = false;
                            try {
                                var rect = canvas.getBoundingClientRect();
                                ctx.clearRect(0, 0, rect.width, rect.height);
                            } catch (e) {}
                        },
                        hasStroke: function () { return hasStroke === true; },
                        dataUrl: function () {
                            try { return canvas.toDataURL('image/png'); } catch (e) { return ''; }
                        }
                    };
                }

                var patientSig = patientRefusalOverlay ? initSignaturePad(patientRefusalOverlay) : null;

                if (patientRefusalOverlay) {
                    var clearBtn = patientRefusalOverlay.querySelector('[data-kmcr-pr-clear]');
                    if (clearBtn) {
                        clearBtn.addEventListener('click', function () {
                            if (patientSig) patientSig.clear();
                        });
                    }

                    function closeActiveOrderAndRestoreReady() {
                        var closeUrl = (window.SWDTM && window.SWDTM.tabletCloseOrderUrl) ? String(window.SWDTM.tabletCloseOrderUrl) : '';
                        var statusUrl = (window.SWDTM && window.SWDTM.tabletSetStatusUrl) ? String(window.SWDTM.tabletSetStatusUrl) : '';
                        var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                        var orderId = (window.SWDTM && window.SWDTM.activeOrderId != null) ? Number(window.SWDTM.activeOrderId) : 0;
                        if (!closeUrl || !statusUrl || !csrf || !isFinite(orderId) || orderId <= 0) return Promise.resolve(null);

                        function setStatusRestoreReady() {
                            return fetch(statusUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrf
                                },
                                body: JSON.stringify({ csrf: csrf, status_code: 'restore_ready' })
                            })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (s) {
                                if (!s || s.ok !== true) return null;
                                window.SWDTM.tabletStatusCode = String(s.status_code || '');
                                window.SWDTM.tabletStatusLabel = String(s.status_label || '');
                                var cur = document.querySelector('.tablet-status-current');
                                if (cur) cur.textContent = window.SWDTM.tabletStatusLabel || '—';
                                document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                                    var btnCode = String(btn.getAttribute('data-status-code') || '');
                                    if (!btnCode) return;
                                    if (btnCode === window.SWDTM.tabletStatusCode) {
                                        btn.classList.add('is-active');
                                    } else {
                                        btn.classList.remove('is-active');
                                    }
                                });
                                if (window.SWDTM && typeof window.SWDTM.sendHeartbeatNow === 'function') {
                                    window.SWDTM.sendHeartbeatNow();
                                }
                                return s;
                            })
                            .catch(function () { return null; });
                        }

                        return fetch(closeUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrf
                            },
                            body: JSON.stringify({ csrf: csrf, order_id: orderId })
                        })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (data) {
                            if (!data || data.ok !== true) return null;
                            try {
                                if (typeof clearOrderUi === 'function') {
                                    clearOrderUi();
                                }
                                if (window.SWDTM && typeof window.SWDTM.refreshActiveOrderDetails === 'function') {
                                    window.SWDTM.refreshActiveOrderDetails(true);
                                }
                            } catch (e) {}
                            return setStatusRestoreReady();
                        })
                        .catch(function () { return null; });
                    }

                    var sendPr = patientRefusalOverlay.querySelector('[data-kmcr-patient-refusal-send]');
                    if (sendPr) {
                        sendPr.addEventListener('click', function () {
                            var url = (window.SWDTM && window.SWDTM.tabletTeamEventUrl) ? String(window.SWDTM.tabletTeamEventUrl) : '';
                            var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                            if (!url || !csrf) return;

                            var reasonEl = patientRefusalOverlay.querySelector('[data-kmcr-pr-reason]');
                            var notesEl = patientRefusalOverlay.querySelector('[data-kmcr-pr-notes]');
                            var reason = reasonEl ? String(reasonEl.value || '').trim() : '';
                            var notes = notesEl ? String(notesEl.value || '').trim() : '';
                            var sigOk = patientSig && patientSig.hasStroke();
                            if (!reason) {
                                alert('Uzupełnij powód odmowy.');
                                return;
                            }
                            if (!sigOk) {
                                alert('Wymagany jest podpis pacjenta/opiekuna.');
                                return;
                            }

                            var sig = patientSig ? String(patientSig.dataUrl() || '') : '';
                            if (!sig || sig.indexOf('data:image/png;base64,') !== 0) {
                                alert('Nie udało się odczytać podpisu.');
                                return;
                            }

                            sendPr.disabled = true;
                            fetch(url, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrf
                                },
                                body: JSON.stringify({ csrf: csrf, type: 'Odmowa pacjenta/opiekuna', payload: { reason: reason, notes: notes, signature_png: sig } })
                            })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                sendPr.disabled = false;
                                if (!data || data.ok !== true) {
                                    alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się wysłać czynności.'));
                                    return;
                                }
                                close(patientRefusalOverlay);
                                try {
                                    if (reasonEl) reasonEl.value = '';
                                    if (notesEl) notesEl.value = '';
                                    if (patientSig) patientSig.clear();
                                } catch (e) {}

                                closeActiveOrderAndRestoreReady();
                            })
                            .catch(function (e) {
                                sendPr.disabled = false;
                                alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                            });
                        });
                    }
                }

                if (redirectBtn && redirectOverlay) {
                    redirectBtn.addEventListener('click', function () {
                        open(redirectOverlay);
                        var d = redirectOverlay.querySelector('[data-kmcr-doctor3]');
                        var r = redirectOverlay.querySelector('[data-kmcr-reason3]');
                        var n = redirectOverlay.querySelector('[data-kmcr-notes3]');
                        if (d) d.value = '';
                        if (r) r.value = '';
                        if (n) n.value = '';

                        try {
                            var city = redirectOverlay.querySelector('[data-kmcr-fac-city]');
                            var pc = redirectOverlay.querySelector('[data-kmcr-fac-postcode]');
                            var street = redirectOverlay.querySelector('[data-kmcr-fac-street]');
                            var num = redirectOverlay.querySelector('[data-kmcr-fac-number]');
                            var flat = redirectOverlay.querySelector('[data-kmcr-fac-flat]');
                            var lat = redirectOverlay.querySelector('[data-kmcr-fac-lat]');
                            var lon = redirectOverlay.querySelector('[data-kmcr-fac-lon]');
                            if (city) city.value = '';
                            if (pc) pc.value = '';
                            if (street) { street.value = ''; street.disabled = true; }
                            if (num) { num.value = ''; num.disabled = true; }
                            if (flat) { flat.value = ''; flat.disabled = true; }
                            if (lat) lat.value = '';
                            if (lon) lon.value = '';

                            var cityList = redirectOverlay.querySelector('[data-kmcr-fac-city-list]');
                            var streetList = redirectOverlay.querySelector('[data-kmcr-fac-street-list]');
                            var numList = redirectOverlay.querySelector('[data-kmcr-fac-number-list]');
                            if (cityList) while (cityList.firstChild) cityList.removeChild(cityList.firstChild);
                            if (streetList) while (streetList.firstChild) streetList.removeChild(streetList.firstChild);
                            if (numList) while (numList.firstChild) numList.removeChild(numList.firstChild);
                        } catch (e) {}
                    });
                }

                if (redirectOverlay) {
                    initPhotonChain(redirectOverlay, {
                        city: '[data-kmcr-fac-city]',
                        cityList: '[data-kmcr-fac-city-list]',
                        postcode: '[data-kmcr-fac-postcode]',
                        street: '[data-kmcr-fac-street]',
                        streetList: '[data-kmcr-fac-street-list]',
                        number: '[data-kmcr-fac-number]',
                        numberList: '[data-kmcr-fac-number-list]',
                        flat: '[data-kmcr-fac-flat]',
                        lat: '[data-kmcr-fac-lat]',
                        lon: '[data-kmcr-fac-lon]'
                    });

                    var send3 = redirectOverlay.querySelector('[data-kmcr-refusal-redirect-send]');
                    if (send3) {
                        send3.addEventListener('click', function () {
                            sendRefusal(redirectOverlay, 'Odmowa przyjęcia z przekierowaniem');
                        });
                    }
                }

                if (longWaitBtn && longWaitOverlay) {
                    longWaitBtn.addEventListener('click', function () {
                        open(longWaitOverlay);
                        var notes = longWaitOverlay.querySelector('[data-kmcr-long-wait-notes]');
                        if (notes) notes.value = '';
                    });
                }

                if (longWaitOverlay) {
                    var sendLong = longWaitOverlay.querySelector('[data-kmcr-long-wait-send]');
                    if (sendLong) {
                        sendLong.addEventListener('click', function () {
                            var url = (window.SWDTM && window.SWDTM.tabletTeamEventUrl) ? String(window.SWDTM.tabletTeamEventUrl) : '';
                            var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                            if (!url || !csrf) return;

                            var notesEl = longWaitOverlay.querySelector('[data-kmcr-long-wait-notes]');
                            var notes = notesEl ? String(notesEl.value || '').trim() : '';

                            sendLong.disabled = true;
                            fetch(url, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrf
                                },
                                body: JSON.stringify({ csrf: csrf, type: 'Długi czas oczekiwania', payload: { notes: notes } })
                            })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                sendLong.disabled = false;
                                if (!data || data.ok !== true) {
                                    alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się wysłać czynności.'));
                                    return;
                                }
                                close(longWaitOverlay);
                                try {
                                    if (notesEl) notesEl.value = '';
                                } catch (e) {}
                            })
                            .catch(function (e) {
                                sendLong.disabled = false;
                                alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                            });
                        });
                    }
                }
            })();

            function isEditable(el) {
                if (!el) return false;
                var tag = String(el.tagName || '').toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
                if (tag === 'button' || tag === 'a' || tag === 'label') return true;
                if (el.isContentEditable) return true;
                return false;
            }

            function isInteractive(el) {
                if (!el) return false;
                if (el.nodeType === 3 && el.parentElement) el = el.parentElement;
                if (!(el instanceof Element)) return true;
                if (isEditable(el)) return true;
                try {
                    if (typeof el.closest === 'function') {
                        if (el.closest('button, a, input, textarea, select, label, [role="button"]')) return true;
                    }
                } catch (e) {}
                return false;
            }

            document.addEventListener('selectstart', function (e) {
                var t = e.target;
                if (t && t.nodeType === 3 && t.parentElement) t = t.parentElement;
                if (isInteractive(t)) return;
            });

            document.addEventListener('mousedown', function (e) {
                var t = e.target;
                if (t && t.nodeType === 3 && t.parentElement) t = t.parentElement;
                if (isInteractive(t)) return;
                if (e.button !== 0) return;
            });

            (function () {
                var url = (window.SWDTM && window.SWDTM.teamLocationUpdateUrl) ? String(window.SWDTM.teamLocationUpdateUrl) : '';
                var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                var canSend = !!(url && csrf);

                if (!(window.SWDTM && window.SWDTM.enableGeolocation === true)) {
                    return;
                }

                var lastSentAt = 0;
                var minIntervalMs = 5000;
                var lastLat = null;
                var lastLon = null;

                var overlay = document.querySelector('[data-geo-overlay]');
                var enableBtn = overlay ? overlay.querySelector('[data-geo-enable]') : null;
                var laterBtn = overlay ? overlay.querySelector('[data-geo-later]') : null;
                var textEl = overlay ? overlay.querySelector('[data-geo-text]') : null;

                function setGeoText(t) {
                    if (!textEl) return;
                    textEl.textContent = String(t || '');
                }

                function openGeoModal() {
                    if (!overlay) return;
                    overlay.classList.add('is-open');
                }

                function closeGeoModal() {
                    if (!overlay) return;
                    overlay.classList.remove('is-open');
                }

                function hasSecure() {
                    try {
                        if (window.isSecureContext) return true;
                        return location && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1');
                    } catch (e) {
                        return false;
                    }
                }

                function shouldSend(lat, lon) {
                    var now = Date.now();
                    if (now - lastSentAt < minIntervalMs) return false;
                    if (lastLat === null || lastLon === null) return true;
                    var dLat = Math.abs(lat - lastLat);
                    var dLon = Math.abs(lon - lastLon);
                    return (dLat + dLon) > 0.00008;
                }

                function send(pos) {
                    if (!pos || !pos.coords) return;
                    var lat = Number(pos.coords.latitude);
                    var lon = Number(pos.coords.longitude);
                    if (!isFinite(lat) || !isFinite(lon)) return;

                    if (!shouldSend(lat, lon)) return;

                    lastSentAt = Date.now();
                    lastLat = lat;
                    lastLon = lon;

                    var payload = {
                        csrf: csrf,
                        lat: lat,
                        lon: lon,
                        accuracy: (pos.coords.accuracy != null ? Number(pos.coords.accuracy) : null),
                        heading: (pos.coords.heading != null ? Number(pos.coords.heading) : null),
                        speed: (pos.coords.speed != null ? Number(pos.coords.speed) : null),
                        status_code: (window.SWDTM && window.SWDTM.tabletStatusCode) ? String(window.SWDTM.tabletStatusCode) : '',
                        status_label: (window.SWDTM && window.SWDTM.tabletStatusLabel) ? String(window.SWDTM.tabletStatusLabel) : '',
                        leader_name: (window.SWDTM && window.SWDTM.tabletLeaderName) ? String(window.SWDTM.tabletLeaderName) : '',
                        driver_name: (window.SWDTM && window.SWDTM.tabletDriverName) ? String(window.SWDTM.tabletDriverName) : '',
                    };

                    if (!canSend) return;

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify(payload)
                    }).catch(function () {});
                }

                var started = false;
                function startWatch() {
                    if (started) return;
                    started = true;
                    try {
                        navigator.geolocation.watchPosition(send, function (err) {
                            started = false;
                            if (!err) return;
                            var code = Number(err.code);
                            if (code === 1) {
                                setGeoText('Brak zgody na lokalizację. Włącz ją w ustawieniach przeglądarki/tabletu i spróbuj ponownie.');
                                openGeoModal();
                                return;
                            }
                            setGeoText('Nie udało się pobrać lokalizacji. Spróbuj ponownie.');
                            openGeoModal();
                        }, {
                            enableHighAccuracy: true,
                            maximumAge: 3000,
                            timeout: 8000
                        });
                    } catch (e) {
                        started = false;
                    }
                }

                function requestOnceAndStart() {
                    if (!hasSecure()) {
                        setGeoText('Lokalizacja działa tylko po HTTPS. Otwórz aplikację przez https:// (lub localhost).');
                        openGeoModal();
                        return;
                    }

                    if (!navigator.geolocation) {
                        setGeoText('Ta przeglądarka/aplikacja nie obsługuje geolokalizacji.');
                        openGeoModal();
                        return;
                    }

                    setGeoText('Czekam na zgodę na lokalizację…');

                    try {
                        navigator.geolocation.getCurrentPosition(function (pos) {
                            closeGeoModal();
                            send(pos);
                            startWatch();
                        }, function (err) {
                            var code = err ? Number(err.code) : 0;
                            if (code === 1) {
                                setGeoText('Aplikacja nie ma zgody na lokalizację. Kliknij „Włącz lokalizację” i zaakceptuj prośbę.');
                            } else {
                                setGeoText('Nie udało się pobrać lokalizacji. Spróbuj ponownie.');
                            }
                            openGeoModal();
                        }, { enableHighAccuracy: true, maximumAge: 0, timeout: 8000 });
                    } catch (e) {
                        openGeoModal();
                    }
                }

                function init() {
                    if (!overlay) {
                        requestOnceAndStart();
                        return;
                    }

                    if (laterBtn) {
                        laterBtn.addEventListener('click', function () {
                            closeGeoModal();
                        });
                    }

                    if (enableBtn) {
                        enableBtn.addEventListener('click', function () {
                            requestOnceAndStart();
                        });
                    }

                    if (!hasSecure()) {
                        setGeoText('Lokalizacja działa tylko po HTTPS. Otwórz aplikację przez https:// (lub localhost).');
                        openGeoModal();
                        return;
                    }

                    if (navigator.permissions && navigator.permissions.query) {
                        navigator.permissions.query({ name: 'geolocation' }).then(function (res) {
                            if (!res) return;
                            if (res.state === 'granted') {
                                requestOnceAndStart();
                                return;
                            }
                            openGeoModal();
                        }).catch(function () {
                            openGeoModal();
                        });
                        return;
                    }

                    openGeoModal();
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();

            (function () {
                var url = (window.SWDTM && window.SWDTM.teamHeartbeatUrl) ? String(window.SWDTM.teamHeartbeatUrl) : '';
                var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                if (!url || !csrf) return;

                var lastSentAt = 0;
                var minIntervalMs = 4000;

                function payload() {
                    return {
                        csrf: csrf,
                        status_code: (window.SWDTM && window.SWDTM.tabletStatusCode) ? String(window.SWDTM.tabletStatusCode) : '',
                        status_label: (window.SWDTM && window.SWDTM.tabletStatusLabel) ? String(window.SWDTM.tabletStatusLabel) : '',
                        leader_name: (window.SWDTM && window.SWDTM.tabletLeaderName) ? String(window.SWDTM.tabletLeaderName) : '',
                        driver_name: (window.SWDTM && window.SWDTM.tabletDriverName) ? String(window.SWDTM.tabletDriverName) : '',
                    };
                }

                function sendNow(force) {
                    var now = Date.now();
                    if (!force && now - lastSentAt < minIntervalMs) return;
                    lastSentAt = now;

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify(payload())
                    }).catch(function () {});
                }

                if (window.SWDTM) {
                    window.SWDTM.sendHeartbeatNow = function () {
                        sendNow(true);
                    };
                }

                var started = false;
                function startWatch() {
                    if (started) return;
                    started = true;
                    sendNow(true);
                    setInterval(function () { sendNow(false); }, 5000);
                    document.addEventListener('visibilitychange', function () {
                        if (document.visibilityState === 'visible') {
                            sendNow(true);
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', startWatch);
                } else {
                    startWatch();
                }
            })();

            (function () {
                var lastFetchedAt = 0;
                var inFlight = false;

                function q(sel) {
                    try { return document.querySelector(sel); } catch (e) { return null; }
                }

                function setKmcrAvailability(hasOrder) {
                    try {
                        var on = hasOrder === true;
                        var cur = (window.SWDTM && window.SWDTM.tabletStatusCode) ? String(window.SWDTM.tabletStatusCode || '') : '';

                        var allowWrongAddress = false;
                        var allowRefusal = false;
                        var allowPatientRefusal = false;
                        var allowRefusalRedirect = false;
                        var allowLongWait = false;

                        if (on) {
                            if (cur === 'order_start') {
                                allowWrongAddress = true;
                            } else if (cur === 'order_transport') {
                                allowWrongAddress = true;
                                allowPatientRefusal = true;
                            } else if (cur === 'order_patient') {
                                allowWrongAddress = true;
                                allowPatientRefusal = true;
                                allowLongWait = true;
                            } else if (cur === 'order_realization' || cur === 'order_handover') {
                                allowWrongAddress = true;
                                allowRefusal = true;
                                allowPatientRefusal = true;
                                allowRefusalRedirect = true;
                                allowLongWait = true;
                            } else {
                                allowWrongAddress = true;
                                allowRefusal = true;
                                allowPatientRefusal = true;
                                allowRefusalRedirect = true;
                                allowLongWait = true;
                            }
                        }

                        var map = [
                            { sel: '[data-kmcr-wrong-address]', allow: allowWrongAddress },
                            { sel: '[data-kmcr-refusal]', allow: allowRefusal },
                            { sel: '[data-kmcr-patient-refusal]', allow: allowPatientRefusal },
                            { sel: '[data-kmcr-refusal-redirect]', allow: allowRefusalRedirect },
                            { sel: '[data-kmcr-long-wait]', allow: allowLongWait },
                        ];

                        map.forEach(function (it) {
                            var btn = q(it.sel);
                            if (!(btn instanceof HTMLElement)) return;
                            if (it.allow) {
                                btn.style.display = '';
                                btn.classList.remove('is-disabled');
                                btn.removeAttribute('disabled');
                                btn.style.opacity = '';
                                btn.style.pointerEvents = '';
                                btn.setAttribute('aria-disabled', 'false');
                            } else {
                                btn.style.display = '';
                                btn.classList.add('is-disabled');
                                btn.setAttribute('disabled', 'disabled');
                                btn.style.opacity = '.45';
                                btn.style.pointerEvents = 'none';
                                btn.setAttribute('aria-disabled', 'true');
                            }
                        });
                    } catch (e) {}
                }

                function setText(sel, text) {
                    var node = q(sel);
                    if (!node) return;
                    var t = (text != null) ? String(text) : '';
                    node.textContent = t.trim() !== '' ? t : '—';
                }

                function setStatusAvailability(hasOrder) {
                    try {
                        var on = hasOrder === true;
                        var readyCodes = ['ready_base', 'not_ready', 'restore_ready', 'return_base'];
                        var otherCodes = ['disinfection', 'washing', 'refuel', 'failure'];
                        var orderCodes = ['order_start', 'order_patient', 'order_transport', 'order_realization', 'order_handover', 'order_return'];
                        var cur = (window.SWDTM && window.SWDTM.tabletStatusCode) ? String(window.SWDTM.tabletStatusCode || '') : '';
                        var curIdx = orderCodes.indexOf(cur);
                        var canRestoreReady = (window.SWDTM && window.SWDTM.canCloseOrderThroughRestoreReady === true);

                        document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                            if (!(btn instanceof HTMLElement)) return;
                            var code = String(btn.getAttribute('data-status-code') || '');

                            if (readyCodes.indexOf(code) !== -1) {
                                if (on) {
                                    if (code === 'restore_ready' && canRestoreReady) {
                                        btn.classList.remove('is-disabled');
                                        btn.removeAttribute('disabled');
                                    } else {
                                        btn.classList.add('is-disabled');
                                        btn.setAttribute('disabled', 'disabled');
                                    }
                                } else {
                                    btn.classList.remove('is-disabled');
                                    btn.removeAttribute('disabled');
                                }
                                return;
                            }

                            if (otherCodes.indexOf(code) !== -1) {
                                var allow = (!on) || code === 'failure';
                                if (allow) {
                                    btn.classList.remove('is-disabled');
                                    btn.removeAttribute('disabled');
                                } else {
                                    btn.classList.add('is-disabled');
                                    btn.setAttribute('disabled', 'disabled');
                                }
                                return;
                            }

                            if (code.indexOf('order_') === 0) {
                                if (on) {
                                    var idx = orderCodes.indexOf(code);
                                    var shouldDisable = (curIdx >= 0 && idx >= 0 && idx < curIdx);
                                    if (cur === 'order_return' && code === 'order_patient') {
                                        shouldDisable = false;
                                    }
                                    if (shouldDisable) {
                                        btn.classList.add('is-disabled');
                                        btn.setAttribute('disabled', 'disabled');
                                    } else {
                                        btn.classList.remove('is-disabled');
                                        btn.removeAttribute('disabled');
                                    }
                                } else {
                                    btn.classList.add('is-disabled');
                                    btn.setAttribute('disabled', 'disabled');
                                }
                                return;
                            }
                        });
                    } catch (e) {}
                }

                function refreshActiveOrderDetails(force) {
                    var url = (window.SWDTM && window.SWDTM.teamActiveOrderUrl) ? String(window.SWDTM.teamActiveOrderUrl) : '';
                    if (!url) return;

                    var now = Date.now();
                    if (!force && now - lastFetchedAt < 2500) return;
                    if (inFlight) return;
                    inFlight = true;

                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) {
                            if (!r || r.ok !== true) return null;
                            var ct = '';
                            try {
                                ct = String(r.headers.get('content-type') || '');
                            } catch (e) {
                                ct = '';
                            }
                            if (ct.indexOf('application/json') === -1) return null;
                            return r.json();
                        })
                        .then(function (data) {
                            lastFetchedAt = Date.now();
                            inFlight = false;
                            if (!data || data.ok !== true) return;

                            var emptyBox = q('[data-active-empty]');
                            var detailsBox = q('[data-active-details]');

                            var o = data.order || null;
                            if (!o) {
                                if (window.SWDTM) window.SWDTM.activeOrderId = null;
                                if (emptyBox) emptyBox.style.display = '';
                                if (detailsBox) detailsBox.style.display = 'none';
                                setKmcrAvailability(false);
                                setStatusAvailability(false);
                                setText('[data-active-number]', '—');
                                setText('[data-active-patient]', '—');
                                setText('[data-active-patient-position]', '—');
                                setText('[data-active-patient-weight]', '—');
                                setText('[data-active-urgency]', '—');
                                setText('[data-active-transport]', '—');
                                setText('[data-active-sirens]', '—');
                                setText('[data-active-from]', '—');
                                setText('[data-active-from-infra]', '—');
                                setText('[data-active-to]', '—');
                                setText('[data-active-to-infra]', '—');
                                setText('[data-active-phone]', '—');
                                setText('[data-active-interview-oxygen]', '—');
                                setText('[data-active-interview-conscious]', '—');
                                setText('[data-active-icd10]', '—');
                                setText('[data-active-interview-notes]', '—');
                                setText('[data-active-order-description]', '—');
                                return;
                            }

                            if (window.SWDTM) window.SWDTM.activeOrderId = o.id != null ? Number(o.id) : null;
                            if (emptyBox) emptyBox.style.display = 'none';
                            if (detailsBox) detailsBox.style.display = '';
                            setKmcrAvailability(true);
                            setStatusAvailability(true);

                            try {
                                var sec = q('[data-order-status-section]');
                                if (sec) sec.classList.remove('is-disabled');
                            } catch (e) {}

                            setText('[data-active-number]', o.number);
                            setText('[data-active-patient]', o.patient && o.patient.full_name ? o.patient.full_name : '—');
                            setText('[data-active-patient-position]', o.patient && o.patient.position ? o.patient.position : '—');
                            var wTxt = '—';
                            try {
                                if (o.patient && o.patient.weight_kg != null && o.patient.weight_kg !== '') {
                                    var w = Number(o.patient.weight_kg);
                                    if (isFinite(w) && w >= 1) {
                                        wTxt = String(Math.round(w)) + ' kg';
                                    }
                                }
                            } catch (e) { wTxt = '—'; }
                            setText('[data-active-patient-weight]', wTxt);
                            setText('[data-active-urgency]', o.urgency);
                            setText('[data-active-transport]', o.transport_label || o.transport_type);

                            var sirTxt = (o.sirens != null && Number(o.sirens) === 1) ? 'TAK' : 'NIE';
                            setText('[data-active-sirens]', sirTxt);
                            var sirEl = q('[data-active-sirens]');
                            if (sirEl) {
                                var isSir = sirTxt === 'TAK';
                                sirEl.style.color = isSir ? '#ef4444' : '#111827';
                                sirEl.style.animation = isSir ? 'pulse 1s infinite' : 'none';
                            }

                            setText('[data-active-from]', o.from && o.from.display ? o.from.display : '—');
                            setText('[data-active-from-infra]', o.from && o.from.infra ? o.from.infra : '—');
                            setText('[data-active-to]', o.to && o.to.display ? o.to.display : '—');
                            setText('[data-active-to-infra]', o.to && o.to.infra ? o.to.infra : '—');
                            setText('[data-active-phone]', o.phone);

                            var med = o.medical || null;
                            setText('[data-active-interview-oxygen]', med && med.interview_oxygen ? med.interview_oxygen : '—');
                            setText('[data-active-interview-conscious]', med && med.interview_conscious ? med.interview_conscious : '—');

                            var icdTxt = '—';
                            try {
                                if (med && Number(med.icd10_none || 0) === 1) {
                                    icdTxt = 'Brak';
                                } else if (med && (med.icd10_code || med.icd10_name)) {
                                    var code = String(med.icd10_code || '').trim();
                                    var name = String(med.icd10_name || '').trim();
                                    icdTxt = (code && name) ? (code + ' — ' + name) : (code || name || '—');
                                }
                            } catch (e) {
                                icdTxt = '—';
                            }
                            setText('[data-active-icd10]', icdTxt);

                            setText('[data-active-interview-notes]', (med && med.interview_notes) ? String(med.interview_notes) : '—');
                            setText('[data-active-order-description]', (med && med.description) ? String(med.description) : '—');
                        })
                        .catch(function () {
                            inFlight = false;
                        });
                }

                window.SWDTM = window.SWDTM || {};
                window.SWDTM.refreshActiveOrderDetails = refreshActiveOrderDetails;

                try {
                    var hasOrderNow = (window.SWDTM && window.SWDTM.activeOrderId != null) ? (Number(window.SWDTM.activeOrderId) > 0) : false;
                    setKmcrAvailability(hasOrderNow);
                } catch (e) {}
            })();

            (function () {
                var alarmAudio = null;
                var currentNotification = null;
                var currentCancellation = null;
                var currentUrge = null;
                var notificationPollingInterval = null;
                var cancellationPollingInterval = null;
                var urgePollingInterval = null;
                var audioUnlocked = false;
                var pendingAlarm = false;
                var pendingAlarmListenerAttached = false;

                function unlockAudio() {
                    if (audioUnlocked) return;
                    audioUnlocked = true;
                    try {
                        var u = (window.SWDTM && window.SWDTM.alarmUrl) ? String(window.SWDTM.alarmUrl) : '/mp3/alarm-tablet.mp3';
                        if (!u) return;
                        var a = new Audio(u);
                        a.volume = 0;
                        var p = a.play();
                        if (p && typeof p.then === 'function') {
                            p.then(function () {
                                a.pause();
                                a.currentTime = 0;
                            }).catch(function () {});
                        }
                    } catch (e) {}
                }

                document.addEventListener('pointerdown', unlockAudio, { once: true, passive: true });
                document.addEventListener('touchstart', unlockAudio, { once: true, passive: true });
                document.addEventListener('keydown', unlockAudio, { once: true });

                function attachPendingAlarmListener() {
                    if (pendingAlarmListenerAttached) return;
                    pendingAlarmListenerAttached = true;
                    document.addEventListener('pointerdown', function () {
                        pendingAlarmListenerAttached = false;
                        if (pendingAlarm) {
                            pendingAlarm = false;
                            playAlarm();
                        }
                    }, { once: true, passive: true });
                    document.addEventListener('touchstart', function () {
                        pendingAlarmListenerAttached = false;
                        if (pendingAlarm) {
                            pendingAlarm = false;
                            playAlarm();
                        }
                    }, { once: true, passive: true });
                    document.addEventListener('keydown', function () {
                        pendingAlarmListenerAttached = false;
                        if (pendingAlarm) {
                            pendingAlarm = false;
                            playAlarm();
                        }
                    }, { once: true });
                }

                function playAlarm() {
                    try {
                        if (!audioUnlocked) {
                            unlockAudio();
                        }
                        if (alarmAudio) {
                            alarmAudio.pause();
                            alarmAudio.currentTime = 0;
                        }

                        var u = (window.SWDTM && window.SWDTM.alarmUrl) ? String(window.SWDTM.alarmUrl) : '/mp3/alarm-tablet.mp3';
                        if (!u) return;

                        alarmAudio = new Audio(u);
                        alarmAudio.loop = true;
                        alarmAudio.volume = 0.8;
                        alarmAudio.play().catch(function () {
                            pendingAlarm = true;
                            attachPendingAlarmListener();
                        });
                    } catch (e) {}
                }

                function stopAlarm() {
                    try {
                        if (alarmAudio) {
                            alarmAudio.pause();
                            alarmAudio.currentTime = 0;
                        }
                    } catch (e) {}
                    alarmAudio = null;
                    pendingAlarm = false;
                }

                function showNotificationModal(notification) {
                    var overlay = document.querySelector('[data-dispatch-notification-overlay]');
                    if (!overlay) return;

                    var textEl = overlay.querySelector('[data-dispatch-notification-text]');
                    var numberEl = overlay.querySelector('[data-dispatch-notification-number]');
                    var urgencyEl = overlay.querySelector('[data-dispatch-notification-urgency]');
                    var sirensEl = overlay.querySelector('[data-dispatch-notification-sirens]');
                    var fromEl = overlay.querySelector('[data-dispatch-notification-from]');
                    var phoneEl = overlay.querySelector('[data-dispatch-notification-phone]');
                    var dispatcherEl = overlay.querySelector('[data-dispatch-notification-dispatcher]');
                    var idEl = overlay.querySelector('[data-dispatch-notification-id]');

                    if (textEl) textEl.textContent = 'Otrzymano nowe zlecenie do przyjęcia';
                    if (numberEl) numberEl.textContent = notification.order_number || '';
                    if (urgencyEl) urgencyEl.textContent = notification.urgency || '';
                    if (sirensEl) {
                        var isSirens = Number(notification.sirens || 0) === 1;
                        sirensEl.textContent = isSirens ? 'TAK' : 'NIE';
                        sirensEl.style.color = isSirens ? '#ef4444' : '#111827';
                        sirensEl.style.fontWeight = '950';
                        sirensEl.style.animation = isSirens ? 'pulse 1s infinite' : 'none';
                    }
                    if (fromEl) fromEl.textContent = notification.from || '';
                    if (phoneEl) phoneEl.textContent = notification.phone || '';
                    if (dispatcherEl) dispatcherEl.textContent = notification.dispatcher_name || '';
                    if (idEl) idEl.value = notification.id || '';

                    overlay.classList.add('is-open');
                    playAlarm();
                }

                function hideNotificationModal() {
                    var overlay = document.querySelector('[data-dispatch-notification-overlay]');
                    if (overlay) overlay.classList.remove('is-open');
                    stopAlarm();
                    currentNotification = null;
                }

                function showCancellationModal(c) {
                    try {
                        hideNotificationModal();
                    } catch (e) {}

                    var overlay = document.querySelector('[data-dispatch-cancel-overlay]');
                    if (!overlay) return;

                    var textEl = overlay.querySelector('[data-dispatch-cancel-text]');
                    var numberEl = overlay.querySelector('[data-dispatch-cancel-number]');
                    var reasonEl = overlay.querySelector('[data-dispatch-cancel-reason]');
                    var idEl = overlay.querySelector('[data-dispatch-cancel-id]');

                    if (textEl) textEl.textContent = 'Dyspozytor odwołał zlecenie. Potwierdź odbiór.';
                    if (numberEl) numberEl.textContent = c.order_number || '';
                    if (reasonEl) reasonEl.textContent = (c.reason && String(c.reason).trim() !== '') ? String(c.reason) : '—';
                    if (idEl) idEl.value = c.id || '';

                    overlay.classList.add('is-open');
                    stopAlarm();
                    playAlarm();
                }

                function hideCancellationModal() {
                    var overlay = document.querySelector('[data-dispatch-cancel-overlay]');
                    if (overlay) overlay.classList.remove('is-open');
                    stopAlarm();
                    currentCancellation = null;
                }

                function showUrgeModal(u) {
                    try {
                        hideNotificationModal();
                    } catch (e) {}

                    var overlay = document.querySelector('[data-dispatch-urge-overlay]');
                    if (!overlay) return;

                    var textEl = overlay.querySelector('[data-dispatch-urge-text]');
                    var reasonEl = overlay.querySelector('[data-dispatch-urge-reason]');
                    var idEl = overlay.querySelector('[data-dispatch-urge-id]');

                    if (textEl) textEl.textContent = 'Dyspozytor wysłał ponaglenie. Potwierdź odbiór.';
                    if (reasonEl) reasonEl.textContent = (u.reason && String(u.reason).trim() !== '') ? String(u.reason) : '—';
                    if (idEl) idEl.value = u.id || '';

                    overlay.classList.add('is-open');
                    stopAlarm();
                    playAlarm();
                }

                function hideUrgeModal() {
                    var overlay = document.querySelector('[data-dispatch-urge-overlay]');
                    if (overlay) overlay.classList.remove('is-open');
                    stopAlarm();
                    currentUrge = null;
                }

                function clearOrderUi() {
                    try {
                        if (window.SWDTM) window.SWDTM.activeOrderId = null;

                        var activeTab = document.querySelector('[data-tab="active"]');
                        if (activeTab) {
                            activeTab.classList.add('is-disabled');
                            activeTab.setAttribute('aria-disabled', 'true');
                            activeTab.setAttribute('tabindex', '-1');
                        }

                        var sec = document.querySelector('[data-order-status-section]');
                        if (sec) sec.classList.add('is-disabled');

                        document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                            var code = String(btn.getAttribute('data-status-code') || '');
                            if (code.indexOf('order_') !== 0) return;
                            btn.classList.add('is-disabled');
                            btn.setAttribute('disabled', 'disabled');
                        });

                        var emptyBox = document.querySelector('[data-active-empty]');
                        var detailsBox = document.querySelector('[data-active-details]');
                        if (emptyBox) emptyBox.style.display = '';
                        if (detailsBox) detailsBox.style.display = 'none';

                        var closeOrderBtn = document.querySelector('[data-close-order]');
                        if (closeOrderBtn) {
                            closeOrderBtn.setAttribute('disabled', 'disabled');
                            closeOrderBtn.style.opacity = '.45';
                            closeOrderBtn.style.pointerEvents = 'none';
                        }
                    } catch (e) {}
                }

                function setStatusReturnBase() {
                    var url = (window.SWDTM && window.SWDTM.tabletSetStatusUrl) ? String(window.SWDTM.tabletSetStatusUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    if (!url || !csrf) return Promise.resolve(null);

                    return fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ csrf: csrf, status_code: 'return_base' })
                    })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (s) {
                        if (!s || s.ok !== true) return null;
                        window.SWDTM.tabletStatusCode = String(s.status_code || '');
                        window.SWDTM.tabletStatusLabel = String(s.status_label || '');
                        var cur = document.querySelector('.tablet-status-current');
                        if (cur) cur.textContent = window.SWDTM.tabletStatusLabel || '—';
                        document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                            var btnCode = String(btn.getAttribute('data-status-code') || '');
                            if (!btnCode) return;
                            if (btnCode === window.SWDTM.tabletStatusCode) {
                                btn.classList.add('is-active');
                            } else {
                                btn.classList.remove('is-active');
                            }
                        });
                        if (window.SWDTM && typeof window.SWDTM.sendHeartbeatNow === 'function') {
                            window.SWDTM.sendHeartbeatNow();
                        }
                        return s;
                    })
                    .catch(function () { return null; });
                }

                function ackCancellation() {
                    if (!currentCancellation) return;

                    var url = (window.SWDTM && window.SWDTM.ackDispatchCancellationUrl) ? String(window.SWDTM.ackDispatchCancellationUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    if (!url || !csrf) return;

                    var overlay = document.querySelector('[data-dispatch-cancel-overlay]');
                    var btn = overlay ? overlay.querySelector('[data-dispatch-cancel-ack]') : null;
                    if (btn) btn.disabled = true;

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ csrf: csrf, cancellation_id: Number(currentCancellation.id || 0) })
                    })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        if (!data || data.ok !== true) {
                            alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się potwierdzić odwołania.'));
                            return;
                        }

                        hideCancellationModal();
                        clearOrderUi();

                        try {
                            var statusTab = document.querySelector('[data-tab="status"]');
                            if (statusTab && typeof statusTab.click === 'function') {
                                statusTab.click();
                            }
                        } catch (e) {}

                        setStatusReturnBase().then(function () {
                            try {
                                if (window.SWDTM && typeof window.SWDTM.refreshActiveOrderDetails === 'function') {
                                    window.SWDTM.refreshActiveOrderDetails(true);
                                }
                            } catch (e) {}
                        });
                    })
                    .catch(function (e) {
                        if (btn) btn.disabled = false;
                        alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                    });
                }

                function ackUrge() {
                    if (!currentUrge) return;

                    var url = (window.SWDTM && window.SWDTM.ackDispatchUrgeUrl) ? String(window.SWDTM.ackDispatchUrgeUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    if (!url || !csrf) return;

                    var overlay = document.querySelector('[data-dispatch-urge-overlay]');
                    var btn = overlay ? overlay.querySelector('[data-dispatch-urge-ack]') : null;
                    if (btn) btn.disabled = true;

                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ csrf: csrf, urge_id: Number(currentUrge.id || 0) })
                    })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        if (btn) btn.disabled = false;
                        if (!data || data.ok !== true) {
                            alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się potwierdzić ponaglenia.'));
                            return;
                        }

                        hideUrgeModal();

                        try {
                            checkUrges();
                        } catch (e) {}
                    })
                    .catch(function (e) {
                        if (btn) btn.disabled = false;
                        alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                    });
                }

                function handleAcceptReject(action) {
                    if (!currentNotification) return;

                    var form = document.querySelector('[data-dispatch-' + action + '-form]');
                    if (!form) return;

                    var formData = new FormData(form);
                    var url = (window.SWDTM && window.SWDTM.acceptDispatchUrl) ? String(window.SWDTM.acceptDispatchUrl) : '';
                    if (!url) return;

                    function enableOrderUi() {
                        try {
                            var sec = document.querySelector('[data-order-status-section]');
                            if (sec) sec.classList.remove('is-disabled');

                            var activeTab = document.querySelector('[data-tab="active"]');
                            if (activeTab) {
                                activeTab.classList.remove('is-disabled');
                                activeTab.setAttribute('aria-disabled', 'false');
                                activeTab.setAttribute('tabindex', '0');
                            }
                        } catch (e) {}
                    }

                    function setStatusOrderStart() {
                        var url2 = (window.SWDTM && window.SWDTM.tabletSetStatusUrl) ? String(window.SWDTM.tabletSetStatusUrl) : '';
                        var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                        if (!url2 || !csrf) return Promise.resolve(null);

                        return fetch(url2, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-Token': csrf
                            },
                            body: JSON.stringify({ csrf: csrf, status_code: 'order_start' })
                        })
                        .then(function (r2) { return r2 && r2.ok === true ? r2.json() : null; })
                        .then(function (s) {
                            if (!s || s.ok !== true) return null;
                            window.SWDTM.tabletStatusCode = String(s.status_code || '');
                            window.SWDTM.tabletStatusLabel = String(s.status_label || '');
                            var cur = document.querySelector('.tablet-status-current');
                            if (cur) cur.textContent = window.SWDTM.tabletStatusLabel || '—';
                            document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                                var btnCode = String(btn.getAttribute('data-status-code') || '');
                                if (!btnCode) return;
                                if (btnCode === window.SWDTM.tabletStatusCode) {
                                    btn.classList.add('is-active');
                                } else {
                                    btn.classList.remove('is-active');
                                }
                            });
                            if (window.SWDTM && typeof window.SWDTM.sendHeartbeatNow === 'function') {
                                window.SWDTM.sendHeartbeatNow();
                            }
                            return s;
                        })
                        .catch(function () { return null; });
                    }

                    function refreshActiveOrderDetails(force) {
                        if (!(window.SWDTM && typeof window.SWDTM.refreshActiveOrderDetails === 'function')) return;
                        window.SWDTM.refreshActiveOrderDetails(force);
                    }

                    fetch(url, {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin'
                    })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        if (!data || data.ok !== true) {
                            document.querySelectorAll('[data-status-pick]').forEach(function (btn) {
                                var btnCode = String(btn.getAttribute('data-status-code') || '');
                                if (!btnCode) return;
                                if (btnCode === window.SWDTM.tabletStatusCode) {
                                    btn.classList.add('is-active');
                                } else {
                                    btn.classList.remove('is-active');
                                }
                            });
                            alert('Błąd: nie udało się potwierdzić decyzji.');
                            return;
                        }

                        hideNotificationModal();

                        if (action === 'accept') {
                            enableOrderUi();
                            setStatusOrderStart().then(function () {
                                refreshActiveOrderDetails(true);
                            });
                        }
                    })
                    .catch(function (error) {
                        alert('Błąd sieci: ' + (error && error.message ? error.message : 'Nieznany błąd'));
                    });
                }

                function checkNotifications() {
                    var url = (window.SWDTM && window.SWDTM.tabletNotificationsUrl) ? String(window.SWDTM.tabletNotificationsUrl) : '';
                    if (!url) return;

                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) {
                            if (!r || r.ok !== true) return null;
                            var ct = '';
                            try {
                                ct = String(r.headers.get('content-type') || '');
                            } catch (e) {
                                ct = '';
                            }
                            if (ct.indexOf('application/json') === -1) return null;
                            return r.json();
                        })
                        .then(function (data) {
                            if (!data || !data.ok || !Array.isArray(data.notifications)) return;
                            if (data.notifications.length > 0 && !currentNotification) {
                                currentNotification = data.notifications[0];
                                showNotificationModal(currentNotification);
                            }
                        })
                        .catch(function () {});
                }

                function checkUrges() {
                    var url = (window.SWDTM && window.SWDTM.tabletUrgesUrl) ? String(window.SWDTM.tabletUrgesUrl) : '';
                    if (!url) return;

                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (data) {
                            if (!data || data.ok !== true || !Array.isArray(data.urges)) return;
                            if (data.urges.length > 0 && !currentUrge) {
                                currentUrge = data.urges[0];
                                showUrgeModal(currentUrge);
                            }
                        })
                        .catch(function () {});
                }

                function checkCancellations() {
                    var url = (window.SWDTM && window.SWDTM.tabletCancellationsUrl) ? String(window.SWDTM.tabletCancellationsUrl) : '';
                    if (!url) return;

                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (data) {
                            if (!data || data.ok !== true || !Array.isArray(data.cancellations)) return;
                            if (data.cancellations.length > 0 && !currentCancellation) {
                                currentCancellation = data.cancellations[0];
                                showCancellationModal(currentCancellation);
                            }
                        })
                        .catch(function () {});
                }

                function init() {
                    var acceptBtn = document.querySelector('[data-dispatch-accept]');
                    var cancelAckBtn = document.querySelector('[data-dispatch-cancel-ack]');
                    var urgeAckBtn = document.querySelector('[data-dispatch-urge-ack]');

                    if (acceptBtn) {
                        acceptBtn.addEventListener('click', function () {
                            handleAcceptReject('accept');
                        });
                    }

                    if (cancelAckBtn) {
                        cancelAckBtn.addEventListener('click', function () {
                            ackCancellation();
                        });
                    }

                    if (urgeAckBtn) {
                        urgeAckBtn.addEventListener('click', function () {
                            ackUrge();
                        });
                    }

                    try {
                        if (window.SWDTM && typeof window.SWDTM.refreshActiveOrderDetails === 'function') {
                            window.SWDTM.refreshActiveOrderDetails(true);
                        }
                    } catch (e) {}

                    checkNotifications();
                    notificationPollingInterval = setInterval(checkNotifications, 3000);

                    checkCancellations();
                    cancellationPollingInterval = setInterval(checkCancellations, 3000);

                    checkUrges();
                    urgePollingInterval = setInterval(checkUrges, 3000);

                    document.addEventListener('visibilitychange', function () {
                        if (document.visibilityState === 'visible') {
                            checkNotifications();
                        }
                    });
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            })();
        })();
    </script>
</body>
</html>
