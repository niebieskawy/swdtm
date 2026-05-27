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

// Aktualizuj sesje uzytkownika o pelne dane z bazy (raz na sesje)
if ($user && !isset($_SESSION['user_updated'])) {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, username, full_name, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int)$user['id']]);
    $fullUserData = $stmt->fetch();
    
    if ($fullUserData) {
        $_SESSION['user'] = [
            'id' => (int)$fullUserData['id'],
            'username' => (string)$fullUserData['username'],
            'full_name' => (string)($fullUserData['full_name'] ?? ''),
            'role' => (string)$fullUserData['role'],
        ];
        $user = $_SESSION['user'];
        $_SESSION['user_updated'] = true;
    }
}

$error = '';

$teams = [];
try {
    $pdo = db();
    $teams = $pdo->query("SELECT code, type FROM teams WHERE is_active = 1 ORDER BY code ASC")->fetchAll();
} catch (Throwable $e) {
    $teams = [];
}

$orders = [];
$mapOrders = [];
try {
    $pdo = db();
    $stmt = $pdo->query(
        "SELECT
            id,
            order_seq,
            order_month,
            order_year,
            status,
            order_type,
            urgency,
            transport_type,
            planned_at,
            needed_team,
            sirens,
            phone,
            assigned_team_code,
            patient_first_name,
            patient_last_name,
            patient_position,
            patient_weight_kg,
            interview_oxygen,
            interview_conscious,
            interview_notes,
            icd10_none,
            icd10_code,
            icd10_name,
            order_description,
            from_infra,
            from_city,
            from_street,
            from_number,
            from_postcode,
            from_flat,
            from_lat,
            from_lon,
            to_city,
            to_street,
            to_number,
            to_postcode,
            to_flat,
            to_infra,
            created_at
        FROM orders
        WHERE status = 'new'
          AND NOT EXISTS (
            SELECT 1 FROM dispatch_notifications dn
            WHERE dn.order_id = orders.id
              AND dn.status IN ('pending','accepted','')
          )
          AND DATE(COALESCE(planned_at, created_at)) = CURDATE()
          AND (
            order_type = 'nagłe'
            OR (order_type = 'planowe' AND planned_at IS NOT NULL AND planned_at <= DATE_ADD(NOW(), INTERVAL 2 DAY))
          )
        ORDER BY
          CASE urgency
            WHEN 'natychmiast' THEN 0
            WHEN 'pilne' THEN 1
            ELSE 2
          END ASC,
          COALESCE(planned_at, created_at) ASC
        LIMIT 30"
    );
    $orders = $stmt->fetchAll();

    if ($orders) {
        foreach ($orders as $o) {
            $plannedAt = $o['planned_at'] ?? null;
            $createdAt = $o['created_at'] ?? null;
            $daySource = ($plannedAt !== null && $plannedAt !== '') ? $plannedAt : $createdAt;
            if ($daySource === null || $daySource === '') {
                continue;
            }

            try {
                $day = (new DateTimeImmutable((string)$daySource))->format('Y-m-d');
            } catch (Throwable $e) {
                continue;
            }

            if ($day !== (new DateTimeImmutable('today'))->format('Y-m-d')) {
                continue;
            }

            $lat = $o['from_lat'] ?? null;
            $lon = $o['from_lon'] ?? null;
            if ($lat === null || $lon === null || $lat === '' || $lon === '') {
                continue;
            }

            $num = (string)($o['order_seq'] ?? '');
            $mm = isset($o['order_month']) ? str_pad((string)(int)$o['order_month'], 2, '0', STR_PAD_LEFT) : '';
            $yy = (string)($o['order_year'] ?? '');
            $orderNumber = ($num !== '' && $mm !== '' && $yy !== '') ? ($num . '/' . $mm . '/' . $yy) : ('#' . (string)$o['id']);

            $fromNumber = trim((string)($o['from_number'] ?? ''));
            $fromFlat = trim((string)($o['from_flat'] ?? ''));
            $fromNumLine = $fromNumber . ($fromFlat !== '' ? ('/' . $fromFlat) : '');
            $from = trim((string)($o['from_city'] ?? '') . ', ' . (string)($o['from_street'] ?? '') . ' ' . $fromNumLine);

            $mapOrders[] = [
                'id' => (int)$o['id'],
                'number' => $orderNumber,
                'urgency' => (string)($o['urgency'] ?? ''),
                'transport_type' => (string)($o['transport_type'] ?? ''),
                'from' => $from,
                'lat' => (float)$lat,
                'lon' => (float)$lon,
            ];
        }
    }
} catch (Throwable $e) {
    $orders = [];
    $mapOrders = [];
}

$clientFacilities = [];
try {
    $pdo = db();
    $clientFacilities = $pdo->query(
        "SELECT id, client_name, facility_address, facility_display, facility_lat, facility_lon FROM clients WHERE facility_lat IS NOT NULL AND facility_lon IS NOT NULL"
    )->fetchAll();
} catch (Throwable $e) {
    $clientFacilities = [];
}

$flash = flash_get('dispatcher');
$flashMessage = '';
$flashType = '';
if (is_array($flash)) {
    $flashMessage = isset($flash['message']) && is_string($flash['message']) ? $flash['message'] : '';
    $flashType = isset($flash['type']) && is_string($flash['type']) ? $flash['type'] : 'notice';
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Panel dowodzenia</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .order-preview-modal{max-width:min(1400px, 94vw);width:94vw;max-height:90vh;display:flex;flex-direction:column}
        .order-preview-body{padding:18px 20px 16px 20px;background:linear-gradient(180deg, rgba(249,250,251,.9) 0%, rgba(255,255,255,1) 60%);overflow:auto;flex:1;min-height:0}
        .order-preview-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .dispatch-preview-btn{outline:none !important;box-shadow:none !important}
        .dispatch-preview-btn:focus{outline:none !important;box-shadow:none !important}
        .dispatch-preview-btn:focus-visible{outline:none !important;box-shadow:none !important}
        .team-status .dispatch-preview-btn,.team-status .dispatch-preview-btn:focus,.team-status .dispatch-preview-btn:focus-visible{outline:none !important;box-shadow:none !important}
        .order-preview-card{background:#fff;border:1px solid rgba(229,231,235,.95);border-radius:14px;padding:14px 14px 12px 14px;box-shadow:0 8px 24px rgba(17,24,39,.06)}
        .order-preview-card__title{display:flex;align-items:center;justify-content:space-between;font-weight:700;color:#111827;margin:0 0 10px 0;font-size:13px;letter-spacing:.02em;text-transform:uppercase}
        .order-preview-row{display:flex;gap:10px;padding:6px 0;border-bottom:1px dashed rgba(229,231,235,.9)}
        .order-preview-kv-row{display:grid;grid-template-columns:170px 1fr;gap:10px;align-items:start}
        .order-preview-k{color:rgba(17,24,39,.65);font-weight:800;font-size:12px;text-transform:uppercase;letter-spacing:.6px;line-height:1.2}
        .order-preview-v{color:#111827;font-weight:850;line-height:1.25}
        .order-preview-v.is-muted{font-weight:700;color:rgba(17,24,39,.65)}
        .order-preview-desc{white-space:pre-wrap;line-height:1.35;font-weight:700;color:rgba(17,24,39,.86)}
        .order-preview-chip{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border-radius:999px;border:1px solid rgba(17,24,39,.10);background:rgba(17,24,39,.04);font-weight:950;font-size:12px;line-height:1;color:#111827}
        .order-preview-chip.is-green{background:rgba(34,197,94,.10);border-color:rgba(34,197,94,.28);color:#166534}
        .order-preview-chip.is-red{background:rgba(239,68,68,.10);border-color:rgba(239,68,68,.28);color:#991b1b}
        .order-preview-chip.is-purple{background:rgba(168,85,247,.12);border-color:rgba(168,85,247,.30);color:#6b21a8}
        .order-preview-chip.is-orange{background:rgba(249,115,22,.12);border-color:rgba(249,115,22,.30);color:#9a3412}
        .order-preview-chip.is-gray{background:rgba(156,163,175,.18);border-color:rgba(107,114,128,.30);color:#111827}
        .order-preview-chip-dot{width:8px;height:8px;border-radius:999px;background:currentColor;opacity:.9}
        .order-preview-actions{padding:14px 20px;display:flex;justify-content:space-between;gap:12px;flex:0 0 auto;background:rgba(255,255,255,.98);border-top:1px solid rgba(17,24,39,.10)}
        .order-preview-actions-left{display:flex;gap:10px;align-items:center}
        .order-preview-actions-right{display:flex;gap:10px;align-items:center}

        @media (max-width: 980px){
            .order-preview-modal{max-width:96vw}
            .order-preview-grid{grid-template-columns:1fr}
            .order-preview-kv-row{grid-template-columns:140px 1fr}
        }
        @media (max-width: 560px){
            .order-preview-kv-row{grid-template-columns:1fr;gap:4px}
            .order-preview-k{font-size:11px}
        }

        .map-ctx-menu{position:fixed;z-index:10100;min-width:180px;border-radius:14px;border:1px solid rgba(226,232,240,.9);background:rgba(255,255,255,.96);backdrop-filter: blur(14px);box-shadow:0 22px 70px rgba(2,6,23,.18);padding:8px;display:none}
        .map-ctx-menu.is-open{display:block}
        .map-ctx-menu button{width:100%;display:flex;align-items:center;justify-content:flex-start;gap:10px;height:40px;border-radius:12px;border:1px solid rgba(226,232,240,.9);background:rgba(248,250,252,.92);cursor:pointer;font-weight:900}
        .map-ctx-menu button:hover{background:rgba(60,179,113,.10);border-color:rgba(60,179,113,.35)}
        .map-ctx-menu button + button{margin-top:8px}

        [data-team-history] .team-row[role="button"]{cursor:pointer;transition:transform .16s ease,box-shadow .16s ease,background .16s ease}
        [data-team-history] .team-row[role="button"]:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(2,6,23,.12)}
        [data-team-history] .team-row[role="button"]:focus{outline:none;transform:translateY(-2px);box-shadow:0 18px 40px rgba(2,6,23,.14)}

        .team-location-marker{width:36px;height:36px;border-radius:50%;background:#ffffff;border:3px solid #94a3b8;box-shadow:0 4px 12px rgba(0,0,0,.15),0 1px 4px rgba(0,0,0,.08);display:flex!important;align-items:center!important;justify-content:center!important;position:relative;transition:transform .15s ease,box-shadow .15s ease}
        .team-location-marker:hover{transform:scale(1.12);box-shadow:0 6px 18px rgba(0,0,0,.22),0 2px 6px rgba(0,0,0,.12)}
        .team-location-marker__code{font-weight:800;font-size:11px;line-height:1;color:#1e293b;letter-spacing:.2px;text-transform:uppercase;display:block;text-align:center}

        .team-location-marker--green{border-color:#22c55e}
        .team-location-marker--yellow{border-color:#f59e0b}
        .team-location-marker--red{border-color:#ef4444}
        .team-location-marker--purple{border-color:#a855f7}
        .team-location-marker--gray{border-color:#94a3b8}
    </style>
    <script defer src="<?= htmlspecialchars(url('/assets/app.js?v=' . @filemtime(__DIR__ . '/../assets/app.js')), ENT_QUOTES, 'UTF-8') ?>"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
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
        <?php elseif ($flashMessage !== ''): ?>
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

    <div class="app">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand-mark"></div>
                <div>
                    <div class="sidebar-title">SWDTM</div>
                    <div class="sidebar-subtitle">Dyspozytornia</div>
                </div>
                <button class="btn-secondary" type="button" data-team-events-bell aria-label="Powiadomienia" style="position:relative;height:40px;width:44px;display:grid;place-items:center;padding:0;margin-left:auto">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" style="display:block">
                        <path fill="currentColor" d="M12 22a2.5 2.5 0 0 0 2.45-2h-4.9A2.5 2.5 0 0 0 12 22Zm6-6V11a6 6 0 1 0-12 0v5l-2 2v1h16v-1l-2-2Z"/>
                    </svg>
                    <span data-team-events-badge style="position:absolute;top:-8px;right:-8px;min-width:22px;height:22px;padding:0 6px;border-radius:999px;background:#ef4444;color:#fff;font-weight:950;font-size:12px;display:none;align-items:center;justify-content:center;box-shadow:0 10px 26px rgba(239,68,68,.25)"></span>
                </button>
            </div>

            <nav class="nav">
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>">Panel dowodzenia</a>
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
                    <div class="page-title">Panel dowodzenia</div>
                    <div class="page-subtitle">Mapa i podgląd operacyjny</div>
                </div>
                <div class="topbar-actions">
                    <a class="btn-primary" href="<?= htmlspecialchars(url('/dispatcher/new-order'), ENT_QUOTES, 'UTF-8') ?>">Nowe zlecenie</a>
                </div>
            </header>

            <section class="command-layout">
                <section class="panel map-panel">
                    <div class="panel-body" style="padding:0">
                        <div id="command-map" class="map"></div>
                    </div>
                </section>

                <aside class="command-side">
                    <section class="panel" style="margin-bottom:14px">
                        <div class="panel-head">
                            <div class="panel-title">Zespoły aktywne</div>
                        </div>
                        <div class="panel-body">
                            <div class="team-list" data-active-teams></div>
                        </div>
                    </section>

                    <section class="panel" style="margin-bottom:14px">
                        <div class="panel-head">
                            <div class="panel-title">W realizacji</div>
                        </div>
                        <div class="panel-body">
                            <div class="team-list" data-dispatch-status></div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-head">
                            <div class="panel-title">Zlecenia na dziś</div>
                        </div>
                        <div class="panel-body">
                            <div class="team-list" data-today-orders>
                                <?php if (!$orders): ?>
                                    Brak zleceń.
                                <?php else: ?>
                                    <?php foreach ($orders as $o): ?>
                                        <?php
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
                                            if ($to === ',' || $to === '') {
                                                $to = '—';
                                            }

                                            $transportLabel = match ((string)$o['transport_type']) {
                                                'hospital' => 'Przekazanie do szpitala',
                                                'poradnia' => 'Wizyta w poradni',
                                                'miedzyszpitalna' => 'Konsultacja międzyszpitalna',
                                                'dom' => 'Odwóz do domu',
                                                default => (string)$o['transport_type'],
                                            };
                                        ?>
                                        <div
                                            class="team-row"
                                            role="button"
                                            tabindex="0"
                                            data-order-open
                                            data-order-id="<?= htmlspecialchars((string)$o['id'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-number="<?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-status="<?= htmlspecialchars((string)($o['status'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-urgency="<?= htmlspecialchars((string)$o['urgency'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-type="<?= htmlspecialchars((string)$o['order_type'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-transport="<?= htmlspecialchars((string)$o['transport_type'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-needed-team="<?= htmlspecialchars((string)($o['needed_team'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-sirens="<?= htmlspecialchars((string)($o['sirens'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-planned-at="<?= htmlspecialchars((string)($o['planned_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-assigned-team="<?= htmlspecialchars((string)($o['assigned_team_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-phone="<?= htmlspecialchars((string)($o['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-patient="<?= htmlspecialchars(trim((string)($o['patient_first_name'] ?? '') . ' ' . (string)($o['patient_last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-patient-position="<?= htmlspecialchars((string)($o['patient_position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-patient-weight="<?= htmlspecialchars((string)($o['patient_weight_kg'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-from-flat="<?= htmlspecialchars((string)($o['from_flat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-to-flat="<?= htmlspecialchars((string)($o['to_flat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-interview-oxygen="<?= htmlspecialchars((string)($o['interview_oxygen'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-interview-conscious="<?= htmlspecialchars((string)($o['interview_conscious'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-interview-notes="<?= htmlspecialchars((string)($o['interview_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-icd10-none="<?= htmlspecialchars((string)($o['icd10_none'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-icd10-code="<?= htmlspecialchars((string)($o['icd10_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-icd10-name="<?= htmlspecialchars((string)($o['icd10_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-description="<?= htmlspecialchars((string)($o['order_description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-from="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>"
                                            data-order-to="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <div class="team-meta">
                                                <div class="team-code">
                                                    <?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?>
                                                    <?= htmlspecialchars($transportLabel, ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <div class="team-type">
                                                    <div style="display:grid;gap:4px">
                                                        <div><span class="label">Skąd:</span> <?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?></div>
                                                        <div><span class="label">Dokąd:</span> <?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="display:flex;align-items:center;gap:10px">
                                                <div class="team-status">
                                                    <?= htmlspecialchars((string)$o['urgency'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <button
                                                    class="btn-secondary"
                                                    type="button"
                                                    data-dispatch-open
                                                    data-order-id="<?= htmlspecialchars((string)$o['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                    data-order-number="<?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?>"
                                                >Zadysponuj</button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                </aside>
            </section>
        </main>
    </div>

    <div class="modal-overlay" data-modal-overlay="dispatch" data-outside-close="1" data-esc-close="1" style="z-index:10060">
        <div class="modal" style="width:min(980px,96vw)">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Zadysponuj zespół</div>
                    <div class="modal-text" data-dispatch-subtitle></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <form id="dispatch-form" class="form" method="post" action="<?= htmlspecialchars(url('/dispatcher/assign-order'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="order_id" value="" data-dispatch-order-id>

                <input type="hidden" name="team_code" value="" data-dispatch-team-code required>
                <input type="hidden" name="team_type" value="" data-dispatch-team-type>

                <div class="field">
                    <span class="label">Aktywne zespoły</span>
                    <div data-dispatch-team-tiles style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px"></div>
                </div>

                <div class="modal-actions">
                    <button class="btn-secondary" type="button" data-modal-close>Anuluj</button>
                    <button class="btn-primary" type="submit" data-dispatch-submit disabled>Zadysponuj</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="dispatch-confirm" data-outside-close="1" data-esc-close="1" style="z-index:10065">
        <div class="modal" style="width:min(720px,96vw)">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Potwierdzenie</div>
                    <div class="modal-text">Zlecenie trafi do kolejki zespołu.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-dispatch-confirm-cancel>×</button>
            </div>
            <div class="modal-body" style="padding:16px 18px">
                <div class="modal-text" data-dispatch-confirm-text style="white-space:pre-wrap">—</div>
            </div>
            <div class="modal-actions" style="padding:0 18px 18px 18px">
                <button class="btn-secondary" type="button" data-dispatch-confirm-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-dispatch-confirm-ok>Kontynuuj</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="order" data-outside-close="1" data-esc-close="1" style="z-index:10070">
        <div class="modal order-preview-modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Podgląd zlecenia</div>
                    <div class="modal-text" data-order-subtitle></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="modal-body order-preview-body">
                <div class="order-preview-grid">
                    <div class="order-preview-card">
                        <div class="order-preview-card-title">
                            <span>Podstawowe</span>
                            <span class="order-preview-chip" data-order-status-chip>
                                <span class="order-preview-chip-dot"></span>
                                <span data-order-status>—</span>
                            </span>
                        </div>
                        <div class="order-preview-kv">
                            <div class="order-preview-kv-row"><div class="order-preview-k">Pilność</div><div class="order-preview-v" data-order-urgency>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Typ</div><div class="order-preview-v" data-order-type>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Transport</div><div class="order-preview-v" data-order-transport>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Zespół wymagany</div><div class="order-preview-v" data-order-needed-team>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Na sygnale?</div><div class="order-preview-v" data-order-sirens>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Plan</div><div class="order-preview-v is-muted" data-order-planned-at>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Przydzielony zespół</div><div class="order-preview-v" data-order-assigned-team>—</div></div>
                        </div>
                    </div>

                    <div class="order-preview-card">
                        <div class="order-preview-card-title">Pacjent</div>
                        <div class="order-preview-kv">
                            <div class="order-preview-kv-row"><div class="order-preview-k">Pacjent</div><div class="order-preview-v" data-order-patient>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Pozycja</div><div class="order-preview-v" data-order-patient-position>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Waga</div><div class="order-preview-v" data-order-patient-weight>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Telefon</div><div class="order-preview-v" data-order-phone>—</div></div>
                        </div>
                    </div>

                    <div class="order-preview-card">
                        <div class="order-preview-card-title">Adresy</div>
                        <div class="order-preview-kv">
                            <div class="order-preview-kv-row"><div class="order-preview-k">Skąd</div><div class="order-preview-v" data-order-from>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Dokąd</div><div class="order-preview-v" data-order-to>—</div></div>
                        </div>
                    </div>

                    <div class="order-preview-card">
                        <div class="order-preview-card-title">Medyczne</div>
                        <div class="order-preview-kv">
                            <div class="order-preview-kv-row"><div class="order-preview-k">Tlen</div><div class="order-preview-v" data-order-interview-oxygen>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Przytomny</div><div class="order-preview-v" data-order-interview-conscious>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">Dodatkowe informacje</div><div class="order-preview-v" data-order-interview-notes>—</div></div>
                            <div class="order-preview-kv-row"><div class="order-preview-k">ICD-10</div><div class="order-preview-v" data-order-icd10>—</div></div>
                        </div>
                    </div>
                </div>

                <div class="order-preview-card" style="margin-top:14px">
                    <div class="order-preview-card-title">Opis</div>
                    <div class="order-preview-desc" data-order-description>—</div>
                </div>
            </div>

            <div class="order-preview-actions">
                <div class="order-preview-actions-left">
                    <button class="btn-secondary" type="button" data-order-dispatch-open>Zadysponuj</button>
                    <button class="btn-danger" type="button" data-order-cancel-open>Odwołaj transport</button>
                    <a class="btn-primary" href="#" data-order-edit>Edytuj</a>
                </div>
                <div class="order-preview-actions-right">
                    <button class="btn-secondary" type="button" data-modal-close>Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="order-cancel" data-outside-close="0" data-esc-close="1" style="z-index:10075">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Odwołaj transport</div>
                    <div class="modal-text">Podaj imię i nazwisko osoby odwołującej.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <label class="field">
                    <span class="label">Osoba odwołująca</span>
                    <input class="input" type="text" data-order-cancel-person maxlength="120" autocomplete="off">
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-order-cancel-cancel>Anuluj</button>
                <button class="btn-danger" type="button" data-order-cancel-confirm>Odwołaj transport</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="team" data-outside-close="1" data-esc-close="1" style="z-index:10060">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title" data-team-title>Szczegóły zespołu</div>
                    <div class="modal-text" data-team-subtitle></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="order-grid">
                <div class="order-address-box">
                    <div class="order-address-title">Zespół</div>
                    <div class="order-form" style="grid-template-columns:1fr">
                        <div class="field">
                            <span class="label">Kierownik</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-team-leader></div>
                        </div>
                        <div class="field">
                            <span class="label">Kierowca</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-team-driver></div>
                        </div>
                        <div class="field">
                            <span class="label">Status</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-team-status></div>
                        </div>
                        <div class="field">
                            <span class="label">Ostatnia pozycja</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-team-pos></div>
                        </div>
                    </div>
                </div>

                <div class="order-address-box">
                    <div class="order-address-title">Zlecenie w trakcie</div>
                    <div class="order-form" style="grid-template-columns:1fr">
                        <div class="field">
                            <span class="label">Nr</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-team-active-order></div>
                        </div>
                        <div class="field">
                            <span class="label">Skąd</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-team-active-from></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="order-address-box" style="margin-top:14px">
                <div class="order-address-title">Historia zleceń (ostatnie)</div>
                <div class="team-list" data-team-history></div>
            </div>

            <div class="modal-actions">
                <button class="btn-primary" type="button" data-modal-close>Zamknij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="dispatch-view" data-outside-close="1" data-esc-close="1" style="z-index:10060">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Zadysponowanie</div>
                    <div class="modal-text" data-dispatch-view-subtitle></div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="order-grid">
                <div class="order-address-box">
                    <div class="order-address-title">Dane</div>
                    <div class="order-form" style="grid-template-columns:1fr">
                        <div class="field">
                            <span class="label">Zlecenie / zespół</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-dispatch-view-main></div>
                        </div>
                        <div class="field">
                            <span class="label">Status</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-dispatch-view-status></div>
                        </div>
                        <div class="field">
                            <span class="label">Na sygnale?</span>
                            <div class="input" style="height:auto;padding:10px 12px;font-weight:900" data-dispatch-view-sirens></div>
                        </div>
                        <div class="field">
                            <span class="label">Skąd</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-dispatch-view-from></div>
                        </div>
                        <div class="field">
                            <span class="label">Dyspozytor</span>
                            <div class="input" style="height:auto;padding:10px 12px" data-dispatch-view-dispatcher></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-danger" type="button" data-dispatch-view-cancel>Odwołaj zespół</button>
                <button class="btn-secondary" type="button" data-dispatch-view-urge>Ponaglaj</button>
                <button class="btn-primary" type="button" data-modal-close>Zamknij</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="cancel-reason" data-outside-close="0" data-esc-close="1" style="z-index:10065">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Odwołaj zespół</div>
                    <div class="modal-text">Podaj powód odwołania (zostanie wyświetlony zespołowi).</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <label class="field">
                    <span class="label">Powód</span>
                    <textarea class="input" data-cancel-reason rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-cancel-reason-cancel>Anuluj</button>
                <button class="btn-danger" type="button" data-cancel-reason-confirm>Wyślij odwołanie</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="urge-reason" data-outside-close="0" data-esc-close="1" style="z-index:10065">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Ponaglaj zespół</div>
                    <div class="modal-text">Podaj powód ponaglenia (zostanie wyświetlony zespołowi).</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <label class="field">
                    <span class="label">Powód</span>
                    <textarea class="input" data-urge-reason rows="4" style="height:auto;min-height:110px"></textarea>
                </label>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-urge-reason-cancel>Anuluj</button>
                <button class="btn-primary" type="button" data-urge-reason-confirm>Wyślij ponaglenie</button>
            </div>
        </div>
    </div>

    <div class="modal-overlay" data-modal-overlay="team-events" data-outside-close="1" data-esc-close="1" style="z-index:10090">
        <div class="modal">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Powiadomienia od zespołów</div>
                    <div class="modal-text">Sytuacje niestandardowe zgłaszane przez zespoły.</div>
                </div>
                <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
            </div>

            <div class="order-form" style="grid-template-columns:1fr">
                <div class="field">
                    <span class="label">Lista</span>
                    <div class="input" style="height:auto;padding:10px 12px;max-height:min(56vh,520px);overflow:auto" data-team-events-list></div>
                </div>
            </div>

            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-team-events-mark-all>Oznacz wszystkie jako przeczytane</button>
                <button class="btn-primary" type="button" data-modal-close>Zamknij</button>
            </div>
        </div>
    </div>

    <script>
        window.SWDTM = window.SWDTM || {};
        window.SWDTM.geocoderProvider = 'photon';
        window.SWDTM.geocoderUrl = <?= json_encode(url('/api/photon.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.mapOrders = <?= json_encode($mapOrders, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.activeTeamsUrl = <?= json_encode(url('/api/active_teams.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.teamLocationsUrl = <?= json_encode(url('/api/team_locations.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.teamDetailsUrl = <?= json_encode(url('/api/team_details.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.orderDetailsUrl = <?= json_encode(url('/api/order_details.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.dispatchStatusUrl = <?= json_encode(url('/api/dispatch_status.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.dispatchOrderUrl = <?= json_encode(url('/api/dispatch_order.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.cancelDispatchUrl = <?= json_encode(url('/api/cancel_dispatch.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.urgeDispatchUrl = <?= json_encode(url('/api/urge_dispatch.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.cancelOrderUrl = <?= json_encode(url('/api/cancel_order.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.todayOrdersUrl = <?= json_encode(url('/api/dispatcher_today_orders.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.todayMapOrdersUrl = <?= json_encode(url('/api/dispatcher_today_map_orders.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.dispatcherTeamEventsUrl = <?= json_encode(url('/api/dispatcher_team_events.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.ackTeamEventUrl = <?= json_encode(url('/api/ack_team_event.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        window.SWDTM.csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

        (function () {
            var bellBtn = document.querySelector('[data-team-events-bell]');
            var badgeEl = document.querySelector('[data-team-events-badge]');
            var overlay = document.querySelector('[data-modal-overlay="team-events"]');
            var listEl = overlay ? overlay.querySelector('[data-team-events-list]') : null;
            var markAllBtn = overlay ? overlay.querySelector('[data-team-events-mark-all]') : null;

            var lastUnread = 0;
            var lastIds = [];
            var pollTimer = null;

            function setBadge(n) {
                if (!badgeEl) return;
                var c = Number(n || 0);
                if (c > 0) {
                    badgeEl.style.display = 'inline-flex';
                    badgeEl.textContent = String(c);
                    if (bellBtn) bellBtn.classList.add('is-bell-pulse');
                } else {
                    badgeEl.style.display = 'none';
                    badgeEl.textContent = '';
                    if (bellBtn) bellBtn.classList.remove('is-bell-pulse');
                }
            }

            function animateBell() {
                if (!bellBtn) return;
                bellBtn.classList.remove('is-bell-anim');
                bellBtn.classList.remove('is-bell-burst');
                void bellBtn.offsetWidth;
                bellBtn.classList.add('is-bell-anim');
                bellBtn.classList.add('is-bell-burst');
                setTimeout(function () {
                    bellBtn.classList.remove('is-bell-anim');
                    bellBtn.classList.remove('is-bell-burst');
                }, 950);
            }

            function renderEvents(events) {
                if (!listEl) return;
                while (listEl.firstChild) listEl.removeChild(listEl.firstChild);

                if (!Array.isArray(events) || !events.length) {
                    listEl.textContent = 'Brak powiadomień.';
                    return;
                }

                events.forEach(function (ev) {
                    var row = document.createElement('div');
                    row.className = 'team-row';
                    row.style.cursor = 'pointer';

                    var meta = document.createElement('div');
                    meta.className = 'team-meta';

                    var code = document.createElement('div');
                    code.className = 'team-code';
                    code.textContent = String(ev.team_code || '—') + (ev.order_id ? (' | #' + String(ev.order_id)) : '');

                    var type = document.createElement('div');
                    type.className = 'team-type';
                    var msg = ev.message ? String(ev.message) : '';

                    var head = String(ev.event_type || '—') + (ev.created_at ? (' | ' + String(ev.created_at)) : '');
                    var m = msg.match(/#SIG-(\d+)/);
                    if (!msg || !m) {
                        type.textContent = head + (msg ? (' — ' + msg) : '');
                    } else {
                        var sigId = m[1];
                        var before = msg.slice(0, m.index).trim();
                        var after = msg.slice(m.index + m[0].length).trim();

                        type.textContent = '';

                        var headEl = document.createElement('span');
                        headEl.textContent = head;
                        type.appendChild(headEl);

                        if (before || after) {
                            var desc = (before + (before && after ? ' ' : '') + after).trim();
                            if (desc) {
                                var sep = document.createElement('span');
                                sep.textContent = ' — ';
                                type.appendChild(sep);
                                var descEl = document.createElement('span');
                                descEl.textContent = desc;
                                type.appendChild(descEl);
                            }
                        }

                        var link = document.createElement('a');
                        link.href = '/api/team_event_file.php?id=' + encodeURIComponent(sigId);
                        link.target = '_blank';
                        link.rel = 'noopener';
                        link.textContent = ' Podpis';
                        link.style.fontWeight = '950';
                        link.style.color = 'rgba(16,185,129,.95)';
                        link.style.textDecoration = 'underline';
                        link.style.marginLeft = '8px';
                        type.appendChild(link);
                    }

                    meta.appendChild(code);
                    meta.appendChild(type);

                    var badge = document.createElement('div');
                    badge.className = 'team-status';
                    badge.textContent = ev.read_at ? 'Odczytane' : 'Nowe';
                    badge.style.background = ev.read_at ? 'rgba(148,163,184,.18)' : 'rgba(239,68,68,.12)';
                    badge.style.border = ev.read_at ? '1px solid rgba(148,163,184,.22)' : '1px solid rgba(239,68,68,.22)';
                    badge.style.color = ev.read_at ? 'rgba(15,23,42,.72)' : '#7f1d1d';

                    row.appendChild(meta);
                    row.appendChild(badge);

                    row.addEventListener('click', function () {
                        if (ev.read_at) return;
                        ackOne(ev.id);
                    });

                    listEl.appendChild(row);
                });
            }

            function fetchEvents(silent) {
                var url = (window.SWDTM && window.SWDTM.dispatcherTeamEventsUrl) ? String(window.SWDTM.dispatcherTeamEventsUrl) : '';
                if (!url) return;
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        if (!data || data.ok !== true) return;

                        var unread = Number(data.unread_count || 0);
                        setBadge(unread);

                        var ids = [];
                        if (Array.isArray(data.events)) {
                            for (var i = 0; i < data.events.length; i++) {
                                if (data.events[i] && data.events[i].id != null) ids.push(Number(data.events[i].id));
                            }
                        }

                        var hasNew = unread > lastUnread;
                        if (!hasNew && unread === lastUnread && ids.length && lastIds.length) {
                            hasNew = ids[0] !== lastIds[0];
                        }

                        lastUnread = unread;
                        lastIds = ids;

                        if (hasNew && !silent) {
                            animateBell();
                        }

                        if (overlay && overlay.classList.contains('is-open')) {
                            renderEvents(data.events);
                        }
                    })
                    .catch(function () {});
            }

            function ackOne(id) {
                var url = (window.SWDTM && window.SWDTM.ackTeamEventUrl) ? String(window.SWDTM.ackTeamEventUrl) : '';
                var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                if (!url || !csrf || !id) return;
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ csrf: csrf, event_id: Number(id) })
                }).then(function () {
                    fetchEvents(true);
                }).catch(function () {});
            }

            function ackAll() {
                var url = (window.SWDTM && window.SWDTM.ackTeamEventUrl) ? String(window.SWDTM.ackTeamEventUrl) : '';
                var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                if (!url || !csrf) return;
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                    body: JSON.stringify({ csrf: csrf, mark_all: 1 })
                }).then(function () {
                    fetchEvents(true);
                }).catch(function () {});
            }

            if (bellBtn) {
                bellBtn.addEventListener('click', function () {
                    if (!overlay) return;
                    overlay.classList.add('is-open');
                    fetchEvents(true);
                });
            }

            if (markAllBtn) {
                markAllBtn.addEventListener('click', function () {
                    ackAll();
                });
            }

            function init() {
                fetchEvents(true);
                pollTimer = setInterval(function () { fetchEvents(false); }, 3000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();

        (function () {
            var overlay = document.querySelector('[data-modal-overlay="cancel-reason"]');
            if (!overlay) return;

            var cancelBtn = overlay.querySelector('[data-cancel-reason-cancel]');
            var confirmBtn = overlay.querySelector('[data-cancel-reason-confirm]');
            var ta = overlay.querySelector('[data-cancel-reason]');

            function close() {
                overlay.classList.remove('is-open');
                overlay.dataset.dispatchId = '';
                if (ta) ta.value = '';
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    close();
                });
            }

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    var dispatchId = String(overlay.dataset.dispatchId || '').trim();
                    var url = (window.SWDTM && window.SWDTM.cancelDispatchUrl) ? String(window.SWDTM.cancelDispatchUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    if (!dispatchId || !url || !csrf) return;

                    var reason = ta ? String(ta.value || '').trim() : '';

                    confirmBtn.disabled = true;
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ csrf: csrf, dispatch_id: Number(dispatchId), reason: reason })
                    })
                    .then(function (r) {
                        if (!r) return { _http_ok: false };
                        if (r.ok !== true) return { _http_ok: false, _status: r.status };
                        return r.json().catch(function () { return { ok: true, _non_json: true }; });
                    })
                    .then(function (data) {
                        confirmBtn.disabled = false;

                        close();

                        var dispatchOverlay = document.querySelector('[data-modal-overlay="dispatch-view"]');
                        if (dispatchOverlay) dispatchOverlay.classList.remove('is-open');

                        if (window.SWDTM && typeof window.SWDTM.fetchDispatchStatus === 'function') {
                            window.SWDTM.fetchDispatchStatus();
                        }
                        if (window.SWDTM && typeof window.SWDTM.fetchTodayOrders === 'function') {
                            window.SWDTM.fetchTodayOrders();
                        }
                        if (window.SWDTM && typeof window.SWDTM.refreshMapOrders === 'function') {
                            window.SWDTM.refreshMapOrders(true);
                        }
                    })
                    .catch(function (e) {
                        confirmBtn.disabled = false;
                        
                        close();
                        var dispatchOverlay = document.querySelector('[data-modal-overlay="dispatch-view"]');
                        if (dispatchOverlay) dispatchOverlay.classList.remove('is-open');
                        if (window.SWDTM && typeof window.SWDTM.fetchDispatchStatus === 'function') {
                            window.SWDTM.fetchDispatchStatus();
                        }
                        if (window.SWDTM && typeof window.SWDTM.fetchTodayOrders === 'function') {
                            window.SWDTM.fetchTodayOrders();
                        }
                        if (window.SWDTM && typeof window.SWDTM.refreshMapOrders === 'function') {
                            window.SWDTM.refreshMapOrders(true);
                        }
                    });
                });
            }

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay && overlay.getAttribute('data-outside-close') === '1') {
                    close();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (overlay.classList.contains('is-open') && overlay.getAttribute('data-esc-close') === '1') {
                    close();
                }
            });
        })();

        (function () {
            var overlay = document.querySelector('[data-modal-overlay="order-cancel"]');
            if (!overlay) return;

            var cancelBtn = overlay.querySelector('[data-order-cancel-cancel]');
            var confirmBtn = overlay.querySelector('[data-order-cancel-confirm]');
            var personEl = overlay.querySelector('[data-order-cancel-person]');

            function close() {
                overlay.classList.remove('is-open');
                overlay.dataset.orderId = '';
                overlay.dataset.orderNumber = '';
                if (personEl) personEl.value = '';
                if (confirmBtn) confirmBtn.disabled = false;
            }

            if (cancelBtn) cancelBtn.addEventListener('click', close);

            document.addEventListener('click', function (e) {
                var t = e && e.target ? e.target : null;
                if (!t || !t.closest) return;
                var btn = t.closest('[data-order-cancel-open]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();

                var id = btn.getAttribute('data-order-id') || '';
                var number = btn.getAttribute('data-order-number') || '';
                overlay.dataset.orderId = String(id || '');
                overlay.dataset.orderNumber = String(number || '');
                overlay.classList.add('is-open');
                if (personEl) {
                    personEl.focus();
                    personEl.select();
                }
            });

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    var orderId = String(overlay.dataset.orderId || '').trim();
                    var url = (window.SWDTM && window.SWDTM.cancelOrderUrl) ? String(window.SWDTM.cancelOrderUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    var person = personEl ? String(personEl.value || '').trim() : '';
                    if (!orderId || !url || !csrf) return;
                    if (!person) return;

                    confirmBtn.disabled = true;
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                        body: JSON.stringify({ csrf: csrf, order_id: Number(orderId), cancelled_by_name: person })
                    })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (data) {
                            if (!data || data.ok !== true) {
                                confirmBtn.disabled = false;
                                return;
                            }
                            close();
                            if (window.SWDTM && typeof window.SWDTM.fetchDispatchStatus === 'function') window.SWDTM.fetchDispatchStatus();
                            if (window.SWDTM && typeof window.SWDTM.fetchTodayOrders === 'function') window.SWDTM.fetchTodayOrders();
                            if (window.SWDTM && typeof window.SWDTM.refreshMapOrders === 'function') window.SWDTM.refreshMapOrders(true);
                        })
                        .catch(function () {
                            confirmBtn.disabled = false;
                        });
                });
            }
        })();

        (function () {
            var overlay = document.querySelector('[data-modal-overlay="urge-reason"]');
            if (!overlay) return;

            var cancelBtn = overlay.querySelector('[data-urge-reason-cancel]');
            var confirmBtn = overlay.querySelector('[data-urge-reason-confirm]');
            var ta = overlay.querySelector('[data-urge-reason]');

            function close() {
                overlay.classList.remove('is-open');
                overlay.dataset.dispatchId = '';
                if (ta) ta.value = '';
            }

            if (cancelBtn) {
                cancelBtn.addEventListener('click', function () {
                    close();
                });
            }

            if (confirmBtn) {
                confirmBtn.addEventListener('click', function () {
                    var dispatchId = String(overlay.dataset.dispatchId || '').trim();
                    var url = (window.SWDTM && window.SWDTM.urgeDispatchUrl) ? String(window.SWDTM.urgeDispatchUrl) : '';
                    var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                    if (!dispatchId || !url || !csrf) return;

                    var reason = ta ? String(ta.value || '').trim() : '';

                    confirmBtn.disabled = true;
                    fetch(url, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrf
                        },
                        body: JSON.stringify({ csrf: csrf, dispatch_id: Number(dispatchId), reason: reason })
                    })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        confirmBtn.disabled = false;
                        if (!data || data.ok !== true) {
                            alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się ponaglić zespołu.'));
                            return;
                        }

                        close();
                        var dispatchOverlay = document.querySelector('[data-modal-overlay="dispatch-view"]');
                        if (dispatchOverlay) dispatchOverlay.classList.remove('is-open');

                        if (window.SWDTM && typeof window.SWDTM.showToast === 'function') {
                            window.SWDTM.showToast('Wysłano ponaglenie.', 'success');
                        }
                    })
                    .catch(function (e) {
                        confirmBtn.disabled = false;
                        alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                    });
                });
            }

            overlay.addEventListener('click', function (e) {
                if (e.target === overlay && overlay.getAttribute('data-outside-close') === '1') {
                    close();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key !== 'Escape') return;
                if (overlay.classList.contains('is-open') && overlay.getAttribute('data-esc-close') === '1') {
                    close();
                }
            });
        })();

        (function () {
            function fmtEnum(v) {
                var s = String(v || '');
                if (s === 'new') return 'Wolne';
                if (s === 'assigned') return 'Zadysponowane';
                if (s === 'done') return 'Zakończone';
                if (s === 'canceled' || s === 'cancelled') return 'Anulowane';
                if (s === 'natychmiast') return 'Natychmiast';
                if (s === 'pilne') return 'Pilne';
                if (s === 'zwykłe') return 'Zwykłe';
                if (s === 'nagłe') return 'Nagłe';
                if (s === 'planowe') return 'Planowe';
                if (s === 'transport prywatny') return 'Transport prywatny';
                if (s === 'hospital') return 'Szpital';
                if (s === 'poradnia') return 'Poradnia';
                if (s === 'miedzyszpitalna') return 'Międzyszpitalna';
                if (s === 'dom') return 'Dom';
                return s;
            }

            function fmtOrderNumber(o) {
                if (!o) return '—';
                var num = (o.order_seq != null) ? String(parseInt(o.order_seq, 10)) : '';
                var mm = (o.order_month != null) ? String(parseInt(o.order_month, 10)).padStart(2, '0') : '';
                var yy = (o.order_year != null) ? String(parseInt(o.order_year, 10)) : '';
                if (num && mm && yy) return num + '/' + mm + '/' + yy;
                return o.id ? ('#' + String(o.id)) : '—';
            }

            function renderTodayOrders(orders) {
                var box = document.querySelector('[data-today-orders]');
                if (!box) return;
                while (box.firstChild) box.removeChild(box.firstChild);

                if (orders && orders._error) {
                    box.textContent = String(orders._error || 'Błąd pobierania zleceń.');
                    return;
                }

                if (!orders || !orders.length) {
                    box.textContent = 'Brak zleceń.';
                    return;
                }

                orders.forEach(function (o) {
                    var row = document.createElement('div');
                    row.className = 'team-row';
                    row.setAttribute('role', 'button');
                    row.setAttribute('tabindex', '0');
                    row.setAttribute('data-order-open', '');

                    var orderNumber = fmtOrderNumber(o);
                    row.setAttribute('data-order-id', String(o.id || ''));
                    row.setAttribute('data-order-number', orderNumber);
                    row.setAttribute('data-order-status', String(o.status || ''));
                    row.setAttribute('data-order-urgency', String(o.urgency || ''));
                    row.setAttribute('data-order-type', String(o.order_type || ''));
                    row.setAttribute('data-order-transport', String(o.transport_type || ''));
                    row.setAttribute('data-order-needed-team', String(o.needed_team || ''));
                    row.setAttribute('data-order-sirens', String(o.sirens || '0'));
                    row.setAttribute('data-order-planned-at', String(o.planned_at || ''));
                    row.setAttribute('data-order-assigned-team', String(o.assigned_team_code || ''));
                    row.setAttribute('data-order-phone', String(o.phone || ''));

                    var pFirst = String(o.patient_first_name || '').trim();
                    var pLast = String(o.patient_last_name || '').trim();
                    var pFull = (pFirst + ' ' + pLast).trim();
                    row.setAttribute('data-order-patient', pFull);
                    row.setAttribute('data-order-patient-position', String(o.patient_position || ''));
                    row.setAttribute('data-order-patient-weight', (o.patient_weight_kg != null) ? String(o.patient_weight_kg) : '');
                    row.setAttribute('data-order-from-flat', String(o.from_flat || ''));
                    row.setAttribute('data-order-to-flat', String(o.to_flat || ''));
                    row.setAttribute('data-order-interview-oxygen', String(o.interview_oxygen || ''));
                    row.setAttribute('data-order-interview-conscious', String(o.interview_conscious || ''));
                    row.setAttribute('data-order-interview-notes', String(o.interview_notes || ''));
                    row.setAttribute('data-order-icd10-none', String(o.icd10_none || '0'));
                    row.setAttribute('data-order-icd10-code', String(o.icd10_code || ''));
                    row.setAttribute('data-order-icd10-name', String(o.icd10_name || ''));
                    row.setAttribute('data-order-description', String(o.order_description || ''));
                    row.setAttribute('data-order-from', String(o.from || '—'));
                    row.setAttribute('data-order-to', String(o.to || '—'));

                    var meta = document.createElement('div');
                    meta.className = 'team-meta';

                    var code = document.createElement('div');
                    code.className = 'team-code';
                    code.textContent = orderNumber + ' ' + fmtEnum(o.transport_type);

                    var type = document.createElement('div');
                    type.className = 'team-type';
                    var fromTxt = String(o.from || '—');
                    var toTxt = String(o.to || '—');
                    type.innerHTML = '<div style="display:grid;gap:4px"><div><span class="label">Skąd:</span> ' + escapeHtml(fromTxt) + '</div><div><span class="label">Dokąd:</span> ' + escapeHtml(toTxt) + '</div></div>';

                    meta.appendChild(code);
                    meta.appendChild(type);

                    var right = document.createElement('div');
                    right.style.display = 'flex';
                    right.style.alignItems = 'center';
                    right.style.gap = '10px';

                    var badge = document.createElement('div');
                    badge.className = 'team-status';
                    badge.textContent = String(o.urgency || '');

                    var btn = document.createElement('button');
                    btn.className = 'btn-secondary';
                    btn.type = 'button';
                    btn.setAttribute('data-dispatch-open', '');
                    btn.setAttribute('data-order-id', String(o.id || ''));
                    btn.setAttribute('data-order-number', orderNumber);
                    btn.textContent = 'Zadysponuj';

                    right.appendChild(badge);
                    right.appendChild(btn);

                    row.appendChild(meta);
                    row.appendChild(right);

                    row.addEventListener('click', function (e) {
                        var t = e && e.target ? e.target : null;
                        if (t && t.closest && t.closest('[data-dispatch-open]')) return;
                        if (window.SWDTM && typeof window.SWDTM.openOrderModal === 'function') {
                            window.SWDTM.openOrderModal(row);
                        }
                    });
                    row.addEventListener('keydown', function (e) {
                        if (!e || (e.key !== 'Enter' && e.key !== ' ')) return;
                        e.preventDefault();
                        if (window.SWDTM && typeof window.SWDTM.openOrderModal === 'function') {
                            window.SWDTM.openOrderModal(row);
                        }
                    });

                    box.appendChild(row);
                });

                box.querySelectorAll('[data-dispatch-open]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        if (window.SWDTM && typeof window.SWDTM.openDispatchModal === 'function') {
                            window.SWDTM.openDispatchModal(btn.getAttribute('data-order-id'), btn.getAttribute('data-order-number'));
                        }
                    });
                });
            }

            function escapeHtml(s) {
                return String(s || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#39;');
            }

            function fetchTodayOrders() {
                var url = (window.SWDTM && window.SWDTM.todayOrdersUrl) ? String(window.SWDTM.todayOrdersUrl) : '';
                if (!url) return;
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r || r.ok !== true) {
                            renderTodayOrders({ _error: 'Błąd pobierania zleceń (' + String(r ? r.status : '') + ').' });
                            return null;
                        }
                        var ct = '';
                        try {
                            ct = String(r.headers.get('content-type') || '');
                        } catch (e) {
                            ct = '';
                        }
                        if (ct.indexOf('application/json') === -1) {
                            renderTodayOrders({ _error: 'Błąd pobierania zleceń (odpowiedź nie jest JSON).' });
                            return null;
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        if (!data || data.ok !== true || !Array.isArray(data.orders)) {
                            renderTodayOrders({ _error: 'Błąd pobierania zleceń (nieprawidłowa odpowiedź).' });
                            return;
                        }
                        renderTodayOrders(data.orders);
                    })
                    .catch(function () {
                        renderTodayOrders({ _error: 'Błąd pobierania zleceń (problem sieci / sesja).' });
                    });
            }

            window.SWDTM = window.SWDTM || {};
            window.SWDTM.fetchTodayOrders = fetchTodayOrders;

            function init() {
                fetchTodayOrders();
                setInterval(fetchTodayOrders, 3000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();

        (function () {
            function init() {
                if (!window.L) return;

                var mapEl = document.getElementById('command-map');
                if (!mapEl) return;
                if (mapEl.dataset && mapEl.dataset.leafletInited === '1') return;
                if (mapEl.dataset) mapEl.dataset.leafletInited = '1';

                try {

                var defaultCenter = [52.2297, 21.0122];
                var defaultZoom = 12;

                var saved = null;
                try {
                    saved = JSON.parse(localStorage.getItem('swdtm_map_view') || 'null');
                } catch (e) {
                    saved = null;
                }

                var center = defaultCenter;
                var zoom = defaultZoom;
                if (saved && Array.isArray(saved.center) && saved.center.length === 2 && typeof saved.zoom === 'number') {
                    center = saved.center;
                    zoom = saved.zoom;
                }

                var map = L.map('command-map', { zoomControl: true }).setView(center, zoom);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap'
                }).addTo(map);

                var orderLayer = L.layerGroup().addTo(map);
                var teamLayer = L.layerGroup().addTo(map);

                var facilityLayer = L.layerGroup().addTo(map);
                var facilities = <?= json_encode($clientFacilities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
                if (!Array.isArray(facilities)) facilities = [];

                var spiderState = {
                    active: false,
                    markers: [],
                    originals: new Map(),
                    anchorPt: null,
                };

                function restoreSpider() {
                    if (!spiderState.active) return;
                    spiderState.markers.forEach(function (m) {
                        var orig = spiderState.originals.get(m);
                        if (orig) {
                            try { m.setLatLng(orig); } catch (e) {}
                        }
                    });
                    spiderState.active = false;
                    spiderState.markers = [];
                    spiderState.originals = new Map();
                    spiderState.anchorPt = null;
                }

                function collectAllMarkers() {
                    var ms = [];
                    try {
                        orderLayer.eachLayer(function (l) { ms.push(l); });
                    } catch (e) {}
                    try {
                        facilityLayer.eachLayer(function (l) { ms.push(l); });
                    } catch (e) {}
                    return ms;
                }

                function spiderfyNear(marker) {
                    if (!marker || !map) return;

                    if (spiderState.active) return;

                    restoreSpider();

                    var center = null;
                    try {
                        center = marker.getLatLng();
                    } catch (e) {
                        center = null;
                    }
                    if (!center) return;

                    var centerPt = map.latLngToContainerPoint(center);
                    spiderState.anchorPt = centerPt;
                    var all = collectAllMarkers();
                    var group = [];
                    var distPx = 18;

                    all.forEach(function (m) {
                        if (!m || typeof m.getLatLng !== 'function') return;
                        var ll = null;
                        try { ll = m.getLatLng(); } catch (e) { ll = null; }
                        if (!ll) return;
                        var pt = map.latLngToContainerPoint(ll);
                        if (pt.distanceTo(centerPt) <= distPx) {
                            group.push(m);
                        }
                    });

                    if (group.length <= 1) return;

                    spiderState.active = true;
                    spiderState.markers = group;
                    spiderState.originals = new Map();
                    group.forEach(function (m) {
                        try { spiderState.originals.set(m, m.getLatLng()); } catch (e) {}
                    });

                    var radius = 28;
                    var angleStep = (Math.PI * 2) / group.length;

                    for (var i = 0; i < group.length; i++) {
                        var a = i * angleStep;
                        var dx = Math.cos(a) * radius;
                        var dy = Math.sin(a) * radius;
                        var p2 = centerPt.add([dx, dy]);
                        var ll2 = map.containerPointToLatLng(p2);
                        try { group[i].setLatLng(ll2); } catch (e) {}
                    }
                }

                if (!map._swdtmSpiderBound) {
                    map._swdtmSpiderBound = true;

                    map.on('mousemove', function (e) {
                        if (!spiderState.active || !spiderState.anchorPt) return;
                        if (!e || !e.containerPoint) return;
                        var d = e.containerPoint.distanceTo(spiderState.anchorPt);
                        if (d > 70) {
                            restoreSpider();
                        }
                    });
                    map.on('zoomstart', function () {
                        restoreSpider();
                    });
                    map.on('dragstart', function () {
                        restoreSpider();
                    });
                    map.on('click', function () {
                        restoreSpider();
                    });
                }

                function escapeHtml(s) {
                    return String(s || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#39;');
                }

                function renderFacilities() {
                    facilityLayer.clearLayers();
                    facilities.forEach(function (c) {
                        if (!c) return;
                        var lat = parseFloat(String(c.facility_lat || ''));
                        var lon = parseFloat(String(c.facility_lon || ''));
                        if (!isFinite(lat) || !isFinite(lon)) return;

                        var name = String(c.client_name || '').trim();
                        var addr = String(c.facility_display || c.facility_address || '').trim();

                        var icon = L.divIcon({
                            className: 'facility-marker',
                            html: '<div class="facility-pin"></div>',
                            iconSize: [22, 22],
                            iconAnchor: [11, 11]
                        });

                        var marker = L.marker([lat, lon], { icon: icon, keyboard: false }).addTo(facilityLayer);
                        marker.on('mouseover', function () {
                            spiderfyNear(marker);
                        });

                        if (name || addr) {
                            var html = '';
                            if (name) html += '<div style="font-weight:900">' + escapeHtml(name) + '</div>';
                            if (addr) html += '<div style="margin-top:4px;opacity:.85">' + escapeHtml(addr) + '</div>';
                            marker.bindPopup(html);
                        }
                    });
                }

                renderFacilities();

                function orderBorderClass(o) {
                    var stOrder = o && o.status != null ? String(o.status || '').trim().toLowerCase() : '';
                    if (stOrder !== 'assigned') return '';
                    var assigned = o && o.assigned_team_code != null ? String(o.assigned_team_code || '').trim() : '';
                    if (!assigned) return '';

                    var st = o && o.team_status_label != null ? String(o.team_status_label || '').trim().toLowerCase() : '';
                    if (st === 'u pacjenta') return ' order-marker--border-red';
                    return ' order-marker--border-yellow';
                }

                function iconForOrder(o) {
                    var assignedCode = o && o.assigned_team_code != null ? String(o.assigned_team_code || '').trim() : '';
                    var stOrder = o && o.status != null ? String(o.status || '').trim().toLowerCase() : '';
                    var isAssigned = (stOrder === 'assigned' && !!assignedCode);

                    var cls = 'order-marker order-marker--normal';
                    if (isAssigned) {
                        if (String(o.urgency) === 'pilne') cls = 'order-marker order-marker--urgent';
                        if (String(o.urgency) === 'natychmiast') cls = 'order-marker order-marker--now';
                    }
                    cls += orderBorderClass(o);
                    return L.divIcon({
                        className: cls,
                        html: '<span class="order-marker__badge">' + String(o.number || '') + '</span>',
                        iconSize: [1, 1],
                        iconAnchor: [14, 14]
                    });
                }

                function renderMapOrders(mapOrders, doFit) {
                    if (!Array.isArray(mapOrders)) mapOrders = [];
                    orderLayer.clearLayers();

                    var ctx = document.querySelector('[data-map-ctx-menu]');
                    if (!ctx) {
                        ctx = document.createElement('div');
                        ctx.className = 'map-ctx-menu';
                        ctx.setAttribute('data-map-ctx-menu', '1');
                        ctx.innerHTML = '<button type="button" data-map-ctx-preview>Podgląd</button><button type="button" data-map-ctx-dispatch>Zadysponuj</button>';
                        document.body.appendChild(ctx);

                        document.addEventListener('click', function () {
                            ctx.classList.remove('is-open');
                        });
                        document.addEventListener('keydown', function (e) {
                            if (e && e.key === 'Escape') ctx.classList.remove('is-open');
                        });
                    }

                    function openCtxMenu(pageX, pageY, orderId, orderNumber) {
                        if (!ctx) return;
                        ctx.style.left = Math.max(10, Math.min((pageX || 0), window.innerWidth - 220)) + 'px';
                        ctx.style.top = Math.max(10, Math.min((pageY || 0), window.innerHeight - 140)) + 'px';
                        ctx.dataset.orderId = String(orderId || '');
                        ctx.dataset.orderNumber = String(orderNumber || '');
                        ctx.classList.add('is-open');
                    }

                    if (ctx && !ctx.dataset.bound) {
                        ctx.dataset.bound = '1';
                        ctx.addEventListener('click', function (e) {
                            var t = e && e.target ? e.target : null;
                            if (!t || !t.closest) return;
                            var id = String(ctx.dataset.orderId || '');
                            var number = String(ctx.dataset.orderNumber || '');
                            if (!id) return;

                            if (t.closest('[data-map-ctx-preview]')) {
                                var row = document.querySelector('[data-order-open][data-order-id="' + id + '"]');
                                if (row && window.SWDTM && typeof window.SWDTM.openOrderModal === 'function') {
                                    window.SWDTM.openOrderModal(row);
                                }
                                ctx.classList.remove('is-open');
                                return;
                            }
                            if (t.closest('[data-map-ctx-dispatch]')) {
                                if (window.SWDTM && typeof window.SWDTM.openDispatchModal === 'function') {
                                    window.SWDTM.openDispatchModal(id, number);
                                }
                                ctx.classList.remove('is-open');
                                return;
                            }
                        });
                    }

                    mapOrders.forEach(function (o) {
                        if (!o || typeof o.lat !== 'number' || typeof o.lon !== 'number') return;
                        var marker = L.marker([o.lat, o.lon], { icon: iconForOrder(o), keyboard: false });
                        marker.on('mouseover', function () {
                            spiderfyNear(marker);
                        });
                        marker.on('click', function () {
                            var row = document.querySelector('[data-order-open][data-order-id="' + String(o.id) + '"]');
                            if (row && window.SWDTM && typeof window.SWDTM.openOrderModal === 'function') {
                                window.SWDTM.openOrderModal(row);
                            }
                        });
                        marker.on('contextmenu', function (ev) {
                            try {
                                if (ev && ev.originalEvent && typeof ev.originalEvent.preventDefault === 'function') {
                                    ev.originalEvent.preventDefault();
                                    ev.originalEvent.stopPropagation();
                                }
                            } catch (e) {}

                            var id = String(o.id || '');
                            var number = String(o.number || ('#' + id));
                            var px = 0;
                            var py = 0;
                            try {
                                px = ev && ev.originalEvent ? (ev.originalEvent.clientX || 0) : 0;
                                py = ev && ev.originalEvent ? (ev.originalEvent.clientY || 0) : 0;
                            } catch (e2) {
                                px = 0; py = 0;
                            }
                            openCtxMenu(px, py, id, number);
                        });
                        marker.addTo(orderLayer);
                    });

                    if (doFit && mapOrders.length) {
                        try {
                            var fg = L.featureGroup(orderLayer.getLayers ? orderLayer.getLayers() : []);
                            var bounds = fg.getBounds();
                            if (bounds.isValid()) {
                                map.fitBounds(bounds.pad(0.18), { maxZoom: 14 });
                            }
                        } catch (e) {}
                    }
                }

                function refreshMapOrders(doFit) {
                    var url = (window.SWDTM && window.SWDTM.todayMapOrdersUrl) ? String(window.SWDTM.todayMapOrdersUrl) : '';
                    if (!url) {
                        var initial = (window.SWDTM && Array.isArray(window.SWDTM.mapOrders)) ? window.SWDTM.mapOrders : [];
                        renderMapOrders(initial, doFit);
                        return;
                    }
                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (data) {
                            if (!data || data.ok !== true || !Array.isArray(data.orders)) return;
                            renderMapOrders(data.orders, doFit);
                        })
                        .catch(function () {});
                }

                window.SWDTM = window.SWDTM || {};
                window.SWDTM.refreshMapOrders = refreshMapOrders;

                function teamIcon(t) {
                    var code = t && t.team_code != null ? String(t.team_code || '').trim() : '';
                    var cls = 'team-location-marker team-location-marker--gray';

                    var st = t && t.status_label != null ? String(t.status_label || '').trim().toLowerCase() : '';
                    var sc = t && t.status_code != null ? String(t.status_code || '').trim().toLowerCase() : '';

                    if (!st) {
                        cls = 'team-location-marker team-location-marker--gray';
                    } else if (st === 'gotowy w bazie' || st === 'powrót do bazy' || st === 'aktywny') {
                        cls = 'team-location-marker team-location-marker--green';
                    } else if (st === 'niegotowy' || st === 'przywracanie gotowości' || st === 'dezynfekcja' || st === 'mycie' || st === 'tankowanie') {
                        cls = 'team-location-marker team-location-marker--yellow';
                    } else if (st === 'awaria') {
                        cls = 'team-location-marker team-location-marker--purple';
                    } else {
                        cls = 'team-location-marker team-location-marker--red';
                    }

                    if (sc === 'ready_base') {
                        cls = 'team-location-marker team-location-marker--green';
                    }

                    return L.divIcon({
                        className: cls,
                        html: '<span class="team-location-marker__code">' + escapeHtml(code ? code : 'Z') + '</span>',
                        iconSize: [36, 36],
                        iconAnchor: [18, 18]
                    });
                }

                function renderTeamLocations(teams) {
                    if (!Array.isArray(teams)) teams = [];
                    teamLayer.clearLayers();

                    teams.forEach(function (t) {
                        if (!t) return;
                        var lat = typeof t.lat === 'number' ? t.lat : parseFloat(String(t.lat || ''));
                        var lon = typeof t.lon === 'number' ? t.lon : parseFloat(String(t.lon || ''));
                        if (!isFinite(lat) || !isFinite(lon)) return;

                        var marker = L.marker([lat, lon], { icon: teamIcon(t), keyboard: false });
                        marker.on('mouseover', function () { spiderfyNear(marker); });
                        marker.on('click', function () {
                            var code = String(t.team_code || '').trim();
                            if (!code) return;
                            if (window.SWDTM && typeof window.SWDTM.openTeamModal === 'function') {
                                window.SWDTM.openTeamModal(code);
                            }
                        });

                        var html = '';
                        var codeTxt = String(t.team_code || '').trim();
                        var st = String(t.status_label || '').trim();
                        var upd = String(t.updated_at || '').trim();
                        var km = (t.mileage_km_today != null && isFinite(Number(t.mileage_km_today))) ? Number(t.mileage_km_today).toFixed(2) : '';
                        if (codeTxt) html += '<div style="font-weight:950">' + escapeHtml(codeTxt) + '</div>';
                        if (st) html += '<div style="margin-top:4px;opacity:.9">' + escapeHtml(st) + '</div>';
                        if (km) html += '<div style="margin-top:4px;opacity:.85">Dzisiaj: ' + escapeHtml(km) + ' km</div>';
                        if (upd) html += '<div style="margin-top:4px;opacity:.75">' + escapeHtml(upd) + '</div>';
                        if (html) marker.bindPopup(html);

                        marker.addTo(teamLayer);
                    });
                }

                function refreshTeamLocations() {
                    var url = (window.SWDTM && window.SWDTM.teamLocationsUrl) ? String(window.SWDTM.teamLocationsUrl) : '';
                    if (!url) return;
                    fetch(url, { credentials: 'same-origin' })
                        .then(function (r) { return r && r.ok === true ? r.json() : null; })
                        .then(function (data) {
                            if (!data || data.ok !== true) return;
                            renderTeamLocations(data.teams);
                        })
                        .catch(function () {});
                }

                window.SWDTM.refreshTeamLocations = refreshTeamLocations;

                refreshMapOrders(true);
                setInterval(function () {
                    refreshMapOrders(false);
                }, 3000);

                refreshTeamLocations();
                setInterval(function () {
                    refreshTeamLocations();
                }, 3000);

                map.on('moveend zoomend', function () {
                    var c = map.getCenter();
                    localStorage.setItem('swdtm_map_view', JSON.stringify({ center: [c.lat, c.lng], zoom: map.getZoom() }));
                });

                setTimeout(function () {
                    map.invalidateSize();
                }, 150);

                document.addEventListener('visibilitychange', function () {
                    if (document.visibilityState === 'visible') {
                        try { map.invalidateSize(); } catch (e) {}
                    }
                });
                } catch (e) {
                    try { if (mapEl && mapEl.dataset) mapEl.dataset.leafletInited = '0'; } catch (e2) {}
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();

        (function () {
            function teamStatusColorClass(statusLabel) {
                var s = String(statusLabel || '').trim().toLowerCase();
                if (!s || s === 'aktywny') return '';

                if (s === 'gotowy w bazie' || s === 'powrót do bazy') return ' status-green';

                if (
                    s === 'niegotowy' ||
                    s === 'przywracanie gotowości' ||
                    s === 'dezynfekcja' ||
                    s === 'mycie' ||
                    s === 'tankowanie'
                ) {
                    return ' status-yellow';
                }

                if (s === 'awaria') return ' status-purple';

                return ' status-red';
            }

            function pulseDots(root) {
                if (!root || !root.querySelectorAll) return;
                root.querySelectorAll('.status-dot').forEach(function (dot) {
                    dot.classList.remove('is-pulse');
                    void dot.offsetWidth;
                    dot.classList.add('is-pulse');
                    setTimeout(function () {
                        dot.classList.remove('is-pulse');
                    }, 720);
                });
            }

            function renderActiveTeamsList(teams) {
                var box = document.querySelector('[data-active-teams]');
                if (!box) return;
                while (box.firstChild) box.removeChild(box.firstChild);

                if (teams && teams._error) {
                    box.textContent = String(teams._error || 'Błąd pobierania aktywnych zespołów.');
                    return;
                }

                if (!teams || !teams.length) {
                    box.textContent = 'Brak aktywnych zespołów.';
                    if (window.SWDTM) window.SWDTM.activeTeamsCache = [];
                    return;
                }

                if (window.SWDTM) window.SWDTM.activeTeamsCache = teams;

                teams.forEach(function (t) {
                    var row = document.createElement('div');
                    row.className = 'team-row';
                    row.setAttribute('data-team-open', '1');
                    row.setAttribute('data-team-code', String(t.team_code || ''));
                    row.setAttribute('tabindex', '0');

                    var meta = document.createElement('div');
                    meta.className = 'team-meta';

                    var code = document.createElement('div');
                    code.className = 'team-code';
                    code.textContent = String(t.team_code || '');

                    var type = document.createElement('div');
                    type.className = 'team-type';
                    var seen = t.updated_at ? (' | ' + String(t.updated_at)) : '';
                    type.textContent = 'Typ: ' + String(t.team_type || '') + seen;

                    meta.appendChild(code);
                    meta.appendChild(type);

                    var status = document.createElement('div');
                    var statusLabel = String(t.status_label || 'Aktywny');
                    status.className = 'team-status' + teamStatusColorClass(statusLabel);
                    var dot = document.createElement('span');
                    dot.className = 'status-dot';
                    var label = document.createElement('span');
                    label.textContent = statusLabel;
                    status.appendChild(dot);
                    status.appendChild(label);

                    row.appendChild(meta);
                    row.appendChild(status);

                    row.addEventListener('click', function () {
                        if (window.SWDTM && typeof window.SWDTM.openTeamModal === 'function') {
                            window.SWDTM.openTeamModal(String(t.team_code || ''));
                        }
                    });
                    row.addEventListener('keydown', function (e) {
                        if (e.key !== 'Enter' && e.key !== ' ') return;
                        e.preventDefault();
                        if (window.SWDTM && typeof window.SWDTM.openTeamModal === 'function') {
                            window.SWDTM.openTeamModal(String(t.team_code || ''));
                        }
                    });

                    box.appendChild(row);
                });

                pulseDots(box);
            }

            function fetchActiveTeams() {
                var url = (window.SWDTM && window.SWDTM.activeTeamsUrl) ? String(window.SWDTM.activeTeamsUrl) : '';
                if (!url) return;
                fetch(url + '?since=300', { credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r || r.ok !== true) {
                            renderActiveTeamsList({ _error: 'Błąd pobierania aktywnych zespołów (' + String(r ? r.status : '') + ').' });
                            return null;
                        }
                        var ct = '';
                        try {
                            ct = String(r.headers.get('content-type') || '');
                        } catch (e) {
                            ct = '';
                        }
                        if (ct.indexOf('application/json') === -1) {
                            renderActiveTeamsList({ _error: 'Błąd pobierania aktywnych zespołów (odpowiedź nie jest JSON).' });
                            return null;
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        if (!data || data.ok !== true || !Array.isArray(data.teams)) {
                            renderActiveTeamsList({ _error: 'Błąd pobierania aktywnych zespołów (nieprawidłowa odpowiedź).' });
                            return;
                        }
                        renderActiveTeamsList(data.teams);
                    })
                    .catch(function () {
                        renderActiveTeamsList({ _error: 'Błąd pobierania aktywnych zespołów (problem sieci / sesja).' });
                    });
            }

            function init() {
                renderActiveTeamsList({ _error: 'Ładowanie aktywnych zespołów…' });
                fetchActiveTeams();
                setInterval(fetchActiveTeams, 3000);
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();

        (function () {
            function teamTypeFromCode(code) {
                var m = String(code || '').match(/[A-Za-z]+/);
                return m ? String(m[1]).toUpperCase() : '';
            }

            function teamStatusColorClass(statusLabel) {
                var s = String(statusLabel || '').trim().toLowerCase();
                if (!s || s === 'aktywny') return '';

                if (s === 'gotowy w bazie' || s === 'powrót do bazy') return ' status-green';

                if (
                    s === 'niegotowy' ||
                    s === 'przywracanie gotowości' ||
                    s === 'dezynfekcja' ||
                    s === 'mycie' ||
                    s === 'tankowanie'
                ) {
                    return ' status-yellow';
                }

                if (s === 'awaria') return ' status-purple';

                return ' status-red';
            }

            function makeTeamTile(t) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'team-row';
                btn.style.display = 'grid';
                btn.style.gridTemplateColumns = '1fr auto';
                btn.style.alignItems = 'center';
                btn.style.gap = '10px';

                var meta = document.createElement('div');
                meta.className = 'team-meta';

                var code = document.createElement('div');
                code.className = 'team-code';
                code.textContent = String(t.team_code || '');

                var type = document.createElement('div');
                type.className = 'team-type';
                var a = [];
                var st = t.status_label ? String(t.status_label) : 'Aktywny';
                a.push('Status: ' + st);
                var leaderName = t.leader_name ? String(t.leader_name) : '';
                if (leaderName && (/^RATOL\d{1,5}$/i).test(leaderName)) leaderName = '';
                if (leaderName) a.push('Kier.: ' + leaderName);
                if (t.driver_name) a.push('Kierowca: ' + String(t.driver_name));
                type.textContent = a.join(' | ');

                meta.appendChild(code);
                meta.appendChild(type);

                var status = document.createElement('div');
                status.className = 'team-status' + teamStatusColorClass(st);
                var dot = document.createElement('span');
                dot.className = 'status-dot';
                var label = document.createElement('span');
                label.textContent = st;
                status.appendChild(dot);
                status.appendChild(label);

                btn.appendChild(meta);
                btn.appendChild(status);

                btn.dataset.teamCode = String(t.team_code || '');
                btn.dataset.teamType = teamTypeFromCode(t.team_code || '');
                return btn;
            }

            function pulseDots(root) {
                if (!root || !root.querySelectorAll) return;
                root.querySelectorAll('.status-dot').forEach(function (dot) {
                    dot.classList.remove('is-pulse');
                    void dot.offsetWidth;
                    dot.classList.add('is-pulse');
                    setTimeout(function () {
                        dot.classList.remove('is-pulse');
                    }, 720);
                });
            }

            function renderDispatchTeamTiles() {
                var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
                if (!overlay) return;
                var tiles = overlay.querySelector('[data-dispatch-team-tiles]');
                if (!tiles) return;
                while (tiles.firstChild) tiles.removeChild(tiles.firstChild);

                var teams = (window.SWDTM && Array.isArray(window.SWDTM.activeTeamsCache)) ? window.SWDTM.activeTeamsCache : [];
                if (!teams.length) {
                    tiles.textContent = 'Brak aktywnych zespołów.';
                    return;
                }

                teams.forEach(function (t) {
                    var tile = makeTeamTile(t);
                    tiles.appendChild(tile);
                });

                pulseDots(tiles);
            }

            function openDispatchModal(orderId, orderNumber) {
                var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
                if (!overlay) return;

                var orderOverlay = document.querySelector('[data-modal-overlay="order"].is-open');
                if (orderOverlay) orderOverlay.classList.remove('is-open');

                var subtitle = overlay.querySelector('[data-dispatch-subtitle]');
                if (subtitle) subtitle.textContent = (orderNumber ? ('Zlecenie: ' + orderNumber) : '');

                var idField = overlay.querySelector('[data-dispatch-order-id]');
                if (idField) idField.value = String(orderId || '');

                var teamCode = overlay.querySelector('[data-dispatch-team-code]');
                var teamType = overlay.querySelector('[data-dispatch-team-type]');
                if (teamCode) teamCode.value = '';
                if (teamType) teamType.value = '';

                var submitBtn = overlay.querySelector('[data-dispatch-submit]');
                if (submitBtn) submitBtn.disabled = true;

                renderDispatchTeamTiles();

                bindDispatchTiles(overlay);

                overlay.classList.add('is-open');
            }

            window.SWDTM = window.SWDTM || {};
            window.SWDTM.openDispatchModal = openDispatchModal;

            document.addEventListener('click', function (e) {
                var t = e && e.target ? e.target : null;
                if (!t || !t.closest) return;
                var btn = t.closest('[data-dispatch-open]');
                if (!btn) return;
                e.preventDefault();
                e.stopPropagation();
                openDispatchModal(btn.getAttribute('data-order-id'), btn.getAttribute('data-order-number'));
            });

            function bindDispatchTiles(overlay) {
                if (!overlay) return;
                var tiles = overlay.querySelector('[data-dispatch-team-tiles]');
                if (!tiles) return;
                if (tiles.dataset && tiles.dataset.dispatchBound === '1') return;
                if (tiles.dataset) tiles.dataset.dispatchBound = '1';

                tiles.addEventListener('click', function (e) {
                    var t = e && e.target ? e.target : null;
                    if (!t || !t.closest) return;
                    var b = t.closest('button');
                    if (!b || !tiles.contains(b)) return;

                    tiles.querySelectorAll('button').forEach(function (x) { x.style.outline = ''; });
                    b.style.outline = '2px solid rgba(59,130,246,.9)';

                    var teamCode = overlay.querySelector('[data-dispatch-team-code]');
                    var teamType = overlay.querySelector('[data-dispatch-team-type]');
                    var submitBtn = overlay.querySelector('[data-dispatch-submit]');

                    var c = String(b.dataset.teamCode || '');
                    var tt = String(b.dataset.teamType || '');
                    if (teamCode) teamCode.value = c;
                    if (teamType) teamType.value = tt;
                    if (submitBtn) submitBtn.disabled = !c;
                });
            }

            var orderOverlay = document.querySelector('[data-modal-overlay="order"]');
            if (orderOverlay) {
                var dispatchFromView = orderOverlay.querySelector('[data-order-dispatch-open]');
                if (dispatchFromView) {
                    dispatchFromView.addEventListener('click', function () {
                        var id = dispatchFromView.getAttribute('data-order-id') || '';
                        var number = dispatchFromView.getAttribute('data-order-number') || '';
                        if (!id) return;
                        openDispatchModal(id, number);
                    });
                }
            }

            document.querySelectorAll('[data-dispatch-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    openDispatchModal(btn.getAttribute('data-order-id'), btn.getAttribute('data-order-number'));
                });
            });

            var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
            if (!overlay) return;
        })();

        (function () {
            function setText(root, sel, txt) {
                if (!root) return;
                var el = root.querySelector(sel);
                if (!el) return;
                el.textContent = (txt != null && String(txt).trim() !== '') ? String(txt) : '—';
            }

            function translateTransport(v) {
                var x = (v != null) ? String(v).trim() : '';
                if (!x) return '';
                var k = x.toLowerCase();
                if (k === 'hospital') return 'Przekazanie do szpitala';
                if (k === 'poradnia') return 'Wizyta w poradni';
                if (k === 'miedzyszpitalna') return 'Konsultacja międzyszpitalna';
                if (k === 'dom') return 'Odwóz do domu';
                return x;
            }

            function openOrderModal(el) {
                var overlay = document.querySelector('[data-modal-overlay="order"]');
                if (!overlay || !el) return;

                var id = el.getAttribute('data-order-id') || '';
                var number = el.getAttribute('data-order-number') || '';
                var subtitle = overlay.querySelector('[data-order-subtitle]');
                if (subtitle) subtitle.textContent = number ? ('Zlecenie: ' + number) : '';

                var status = el.getAttribute('data-order-status') || '';
                var statusLabel = (status === 'new') ? 'Wolne'
                    : (status === 'assigned') ? 'Zadysponowane'
                    : (status === 'done') ? 'Zakończone'
                    : ((status === 'odwolany') ? 'Odwołany'
                    : ((status === 'canceled' || status === 'cancelled') ? 'Anulowane' : status));

                var chip = overlay.querySelector('[data-order-status-chip]');
                if (chip) {
                    chip.classList.remove('is-green', 'is-red', 'is-purple', 'is-gray', 'is-orange');
                    if (status === 'new') chip.classList.add('is-green');
                    else if (status === 'assigned') chip.classList.add('is-red');
                    else if (status === 'done') chip.classList.add('is-purple');
                    else if (status === 'odwolany') chip.classList.add('is-orange');
                    else if (status === 'canceled' || status === 'cancelled') chip.classList.add('is-gray');
                }
                setText(overlay, '[data-order-status]', statusLabel);
                setText(overlay, '[data-order-urgency]', el.getAttribute('data-order-urgency'));
                setText(overlay, '[data-order-type]', el.getAttribute('data-order-type'));
                setText(overlay, '[data-order-transport]', translateTransport(el.getAttribute('data-order-transport')));
                setText(overlay, '[data-order-needed-team]', el.getAttribute('data-order-needed-team'));

                var sir = el.getAttribute('data-order-sirens') || '0';
                setText(overlay, '[data-order-sirens]', (String(sir) === '1') ? 'TAK' : 'NIE');

                setText(overlay, '[data-order-planned-at]', el.getAttribute('data-order-planned-at'));
                setText(overlay, '[data-order-assigned-team]', el.getAttribute('data-order-assigned-team'));

                setText(overlay, '[data-order-patient]', el.getAttribute('data-order-patient'));
                setText(overlay, '[data-order-patient-position]', el.getAttribute('data-order-patient-position'));
                var w = el.getAttribute('data-order-patient-weight') || '';
                setText(overlay, '[data-order-patient-weight]', w ? (w + ' kg') : '—');
                setText(overlay, '[data-order-phone]', el.getAttribute('data-order-phone'));

                setText(overlay, '[data-order-from]', el.getAttribute('data-order-from'));
                setText(overlay, '[data-order-to]', el.getAttribute('data-order-to'));

                setText(overlay, '[data-order-interview-oxygen]', el.getAttribute('data-order-interview-oxygen'));
                setText(overlay, '[data-order-interview-conscious]', el.getAttribute('data-order-interview-conscious'));
                setText(overlay, '[data-order-interview-notes]', el.getAttribute('data-order-interview-notes'));

                var none = String(el.getAttribute('data-order-icd10-none') || '') === '1';
                var code = String(el.getAttribute('data-order-icd10-code') || '');
                var name = String(el.getAttribute('data-order-icd10-name') || '');
                setText(overlay, '[data-order-icd10]', none ? 'Brak rozpoznania ICD-10' : ((code || name) ? ((code ? (code + ' — ') : '') + name) : '—'));

                setText(overlay, '[data-order-description]', el.getAttribute('data-order-description'));

                var edit = overlay.querySelector('[data-order-edit]');
                if (edit) {
                    edit.setAttribute('href', <?= json_encode(url('/dispatcher/edit-order'), JSON_UNESCAPED_UNICODE) ?> + '?id=' + encodeURIComponent(id));
                }

                var dispatchFromView = overlay.querySelector('[data-order-dispatch-open]');
                if (dispatchFromView) {
                    dispatchFromView.setAttribute('data-order-id', id);
                    dispatchFromView.setAttribute('data-order-number', number);
                }

                var cancelBtn = overlay.querySelector('[data-order-cancel-open]');
                if (cancelBtn) {
                    cancelBtn.disabled = (status === 'done' || status === 'odwolany' || status === 'canceled' || status === 'cancelled');
                    cancelBtn.setAttribute('data-order-id', id);
                    cancelBtn.setAttribute('data-order-number', number);
                }

                overlay.classList.add('is-open');
            }

            window.SWDTM = window.SWDTM || {};
            window.SWDTM.openOrderModal = openOrderModal;

            document.querySelectorAll('[data-order-open]').forEach(function (row) {
                row.addEventListener('click', function (e) {
                    var t = e.target;
                    if (t && t.closest && t.closest('[data-dispatch-open]')) return;
                    openOrderModal(row);
                });
                row.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    e.preventDefault();
                    openOrderModal(row);
                });
            });
        })();

        (function () {
            function fmtOrderNumber(o) {
                if (!o) return '—';
                var num = String(o.order_seq || '');
                var mm = (o.order_month != null) ? String(parseInt(o.order_month, 10)).padStart(2, '0') : '';
                var yy = String(o.order_year || '');
                if (num && mm && yy) return num + '/' + mm + '/' + yy;
                return o.id ? ('#' + String(o.id)) : '—';
            }

            function openTeamModal(teamCode) {
                teamCode = String(teamCode || '').trim();
                if (!teamCode) return;

                var overlay = document.querySelector('[data-modal-overlay="team"]');
                if (!overlay) return;

                var title = overlay.querySelector('[data-team-title]');
                if (title) title.textContent = 'Zespół: ' + teamCode;

                var url = (window.SWDTM && window.SWDTM.teamDetailsUrl) ? String(window.SWDTM.teamDetailsUrl) : '';
                if (!url) return;

                var subtitle = overlay.querySelector('[data-team-subtitle]');
                if (subtitle) subtitle.textContent = 'Pobieranie danych…';

                fetch(url + '?team_code=' + encodeURIComponent(teamCode), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (!data) return;

                        if (subtitle) {
                            subtitle.textContent = data.updated_at ? ('Ostatnia aktualizacja: ' + String(data.updated_at)) : '';
                        }

                        var leader = overlay.querySelector('[data-team-leader]');
                        if (leader) {
                            var ln = data.leader_name ? String(data.leader_name) : '';
                            if (ln && (/^RATOL\d{1,5}$/i).test(ln)) ln = '';
                            leader.textContent = ln ? ln : '—';
                        }

                        var driver = overlay.querySelector('[data-team-driver]');
                        if (driver) driver.textContent = data.driver_name ? String(data.driver_name) : '—';

                        var st = overlay.querySelector('[data-team-status]');
                        if (st) st.textContent = data.status_label ? String(data.status_label) : '—';

                        var pos = overlay.querySelector('[data-team-pos]');
                        if (pos) {
                            var acc = (data.accuracy_m != null) ? (' | dokł.: ' + String(Math.round(Number(data.accuracy_m))) + 'm') : '';
                            pos.textContent = (data.lat != null && data.lon != null) ? (String(data.lat) + ', ' + String(data.lon) + acc) : '—';
                        }

                        var activeOrder = overlay.querySelector('[data-team-active-order]');
                        var activeFrom = overlay.querySelector('[data-team-active-from]');
                        if (data.active_order) {
                            if (activeOrder) activeOrder.textContent = fmtOrderNumber(data.active_order);
                            if (activeFrom) activeFrom.textContent = String(data.active_order.from || '—');
                        } else {
                            if (activeOrder) activeOrder.textContent = '—';
                            if (activeFrom) activeFrom.textContent = '—';
                        }

                        var hist = overlay.querySelector('[data-team-history]');
                        if (hist) {
                            while (hist.firstChild) hist.removeChild(hist.firstChild);
                            if (!Array.isArray(data.history) || !data.history.length) {
                                hist.textContent = 'Brak historii.';
                            } else {
                                data.history.forEach(function (o) {
                                    var row = document.createElement('div');
                                    row.className = 'team-row';
                                    row.setAttribute('role', 'button');
                                    row.setAttribute('tabindex', '0');

                                    var meta = document.createElement('div');
                                    meta.className = 'team-meta';

                                    var codeEl = document.createElement('div');
                                    codeEl.className = 'team-code';
                                    codeEl.textContent = fmtOrderNumber(o);

                                    var typeEl = document.createElement('div');
                                    typeEl.className = 'team-type';
                                    typeEl.textContent = String(o.from || '—');

                                    meta.appendChild(codeEl);
                                    meta.appendChild(typeEl);

                                    var badge = document.createElement('div');
                                    badge.className = 'team-status';
                                    var hs = String(o.status || '').trim().toLowerCase();
                                    if (!hs) {
                                        badge.textContent = '—';
                                    } else if (hs === 'done') {
                                        badge.textContent = '';
                                        badge.style.display = 'none';
                                    } else if (hs === 'cancelled') {
                                        badge.textContent = 'Anulowane';
                                    } else {
                                        badge.textContent = String(o.status || '—');
                                    }

                                    row.appendChild(meta);
                                    row.appendChild(badge);
                                    hist.appendChild(row);

                                    function openPreview() {
                                        if (!window.SWDTM || typeof window.SWDTM.openOrderModal !== 'function') return;
                                        var id = String(o && o.id != null ? o.id : '');
                                        if (!id) return;

                                        var url = window.SWDTM.orderDetailsUrl ? String(window.SWDTM.orderDetailsUrl) : '';
                                        if (!url) return;

                                        fetch(url + '?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                                            .then(function (d) {
                                                if (!d || d.ok !== true || !d.order) return;

                                                var tmp = document.createElement('div');
                                                tmp.setAttribute('data-order-id', String(d.order.id || ''));
                                                tmp.setAttribute('data-order-number', String(d.order.order_number || ''));
                                                tmp.setAttribute('data-order-status', String(d.order.status || ''));
                                                tmp.setAttribute('data-order-urgency', String(d.order.urgency || ''));
                                                tmp.setAttribute('data-order-type', String(d.order.order_type || ''));
                                                tmp.setAttribute('data-order-transport', String(d.order.transport_type || ''));
                                                tmp.setAttribute('data-order-needed-team', String(d.order.needed_team || ''));
                                                tmp.setAttribute('data-order-sirens', String(d.order.sirens || '0'));
                                                tmp.setAttribute('data-order-planned-at', String(d.order.planned_at || ''));
                                                tmp.setAttribute('data-order-assigned-team', String(d.order.assigned_team_code || ''));

                                                tmp.setAttribute('data-order-phone', String(d.order.phone || ''));

                                                var pFull = (String(d.order.patient_first_name || '') + ' ' + String(d.order.patient_last_name || '')).trim();
                                                tmp.setAttribute('data-order-patient', pFull);
                                                tmp.setAttribute('data-order-patient-position', String(d.order.patient_position || ''));
                                                tmp.setAttribute('data-order-patient-weight', d.order.patient_weight_kg != null ? String(d.order.patient_weight_kg) : '');

                                                tmp.setAttribute('data-order-from', String(d.order.from || ''));
                                                tmp.setAttribute('data-order-to', String(d.order.to || ''));

                                                tmp.setAttribute('data-order-interview-oxygen', String(d.order.interview_oxygen || ''));
                                                tmp.setAttribute('data-order-interview-conscious', String(d.order.interview_conscious || ''));
                                                tmp.setAttribute('data-order-interview-notes', String(d.order.interview_notes || ''));
                                                tmp.setAttribute('data-order-icd10-none', String(d.order.icd10_none != null ? d.order.icd10_none : '0'));
                                                tmp.setAttribute('data-order-icd10-code', String(d.order.icd10_code || ''));
                                                tmp.setAttribute('data-order-icd10-name', String(d.order.icd10_name || ''));
                                                tmp.setAttribute('data-order-description', String(d.order.order_description || ''));

                                                window.SWDTM.openOrderModal(tmp);
                                            })
                                            .catch(function () {});
                                    }

                                    row.addEventListener('click', function (e) {
                                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                                        openPreview();
                                    });
                                    row.addEventListener('keydown', function (e) {
                                        if (!e || (e.key !== 'Enter' && e.key !== ' ')) return;
                                        e.preventDefault();
                                        openPreview();
                                    });
                                });
                            }
                        }
                    })
                    .catch(function () {});

                overlay.classList.add('is-open');
            }

            window.SWDTM = window.SWDTM || {};
            window.SWDTM.openTeamModal = openTeamModal;
        })();

        (function () {
            function renderDispatchStatusList(dispatches) {
                var box = document.querySelector('[data-dispatch-status]');
                if (!box) return;
                while (box.firstChild) box.removeChild(box.firstChild);

                function openDispatchFromEl(el) {
                    if (!el) return;
                    var overlay = document.querySelector('[data-modal-overlay="dispatch-view"]');
                    if (!overlay) return;

                    var subtitle = overlay.querySelector('[data-dispatch-view-subtitle]');
                    var main = overlay.querySelector('[data-dispatch-view-main]');
                    var status = overlay.querySelector('[data-dispatch-view-status]');
                    var sirens = overlay.querySelector('[data-dispatch-view-sirens]');
                    var from = overlay.querySelector('[data-dispatch-view-from]');
                    var dispatcher = overlay.querySelector('[data-dispatch-view-dispatcher]');
                    var cancelBtn = overlay.querySelector('[data-dispatch-view-cancel]');
                    var urgeBtn = overlay.querySelector('[data-dispatch-view-urge]');

                    var dispatchId = String(el.dataset.dispatchId || '');
                    var orderNumber = String(el.dataset.orderNumber || '');
                    var teamCode = String(el.dataset.teamCode || '');
                    var statusLabel = String(el.dataset.statusLabel || '');
                    var fromTxt = String(el.dataset.from || '');
                    var dispatcherName = String(el.dataset.dispatcherName || '');
                    var sirensVal = Number(el.dataset.sirens || 0) === 1;
                    var st = String(el.dataset.status || '');

                    if (subtitle) subtitle.textContent = dispatchId ? ('ID: ' + dispatchId) : '';
                    if (main) main.textContent = (orderNumber ? orderNumber : '') + (teamCode ? (' → ' + teamCode) : '');
                    if (status) status.textContent = statusLabel || st;
                    if (from) from.textContent = fromTxt || '—';
                    if (dispatcher) dispatcher.textContent = dispatcherName || '—';
                    if (sirens) {
                        sirens.textContent = sirensVal ? 'TAK' : 'NIE';
                        sirens.style.color = sirensVal ? '#ef4444' : '#111827';
                        sirens.style.animation = sirensVal ? 'pulse 1s infinite' : 'none';
                    }

                    if (urgeBtn) {
                        var canUrge = (!sirensVal) && (st === 'pending' || st === 'accepted');
                        urgeBtn.disabled = !canUrge;
                        urgeBtn.style.opacity = canUrge ? '' : '.45';
                    }
                    if (cancelBtn) {
                        var canCancel = (st === 'pending' || st === 'accepted' || st === 'queued');
                        cancelBtn.disabled = !canCancel;
                        cancelBtn.style.opacity = canCancel ? '' : '.45';
                    }

                    overlay.classList.add('is-open');

                    if (cancelBtn) {
                        cancelBtn.onclick = function () {
                            var reasonOverlay = document.querySelector('[data-modal-overlay="cancel-reason"]');
                            if (!reasonOverlay) return;

                            reasonOverlay.dataset.dispatchId = String(dispatchId || '');
                            var ta = reasonOverlay.querySelector('[data-cancel-reason]');
                            if (ta) ta.value = '';
                            reasonOverlay.classList.add('is-open');
                        };
                    }

                    if (urgeBtn) {
                        urgeBtn.onclick = function () {
                            var url = (window.SWDTM && window.SWDTM.urgeDispatchUrl) ? String(window.SWDTM.urgeDispatchUrl) : '';
                            var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                            if (!url || !csrf || !dispatchId) return;
                            urgeBtn.disabled = true;
                            fetch(url, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-Token': csrf
                                },
                                body: JSON.stringify({ csrf: csrf, dispatch_id: Number(dispatchId) })
                            })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                urgeBtn.disabled = false;
                                if (!data || data.ok !== true) {
                                    alert('Błąd: ' + (data && data.error ? data.error : 'Nie udało się ponaglić zespołu.'));
                                    return;
                                }
                                if (window.SWDTM && typeof window.SWDTM.showToast === 'function') {
                                    window.SWDTM.showToast('Wysłano ponaglenie.', 'success');
                                }
                                overlay.classList.remove('is-open');
                            })
                            .catch(function (e) {
                                urgeBtn.disabled = false;
                                alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                            });
                        };
                    }
                }

                if (dispatches && dispatches._error) {
                    box.textContent = String(dispatches._error || 'Błąd pobierania zadysponowań.');
                    return;
                }

                if (!dispatches || !dispatches.length) {
                    box.textContent = 'Brak zadysponowań.';
                    return;
                }

                dispatches.forEach(function (d) {
                    var row = document.createElement('div');
                    row.className = 'team-row';
                    row.setAttribute('role', 'button');
                    row.setAttribute('tabindex', '0');
                    row.dataset.dispatchView = '1';
                    row.dataset.dispatchId = String(d.id || '');
                    row.dataset.orderId = String(d.order_id || '');
                    row.dataset.orderNumber = String(d.order_number || '');
                    row.dataset.teamCode = String(d.team_code || '');
                    row.dataset.status = String(d.status || '');
                    row.dataset.statusLabel = String(d.status_label || d.status || '');
                    row.dataset.from = String(d.from || '');
                    row.dataset.dispatcherName = String(d.dispatcher_name || '');
                    row.dataset.sirens = String(d.sirens || '0');

                    var statusClass = '';
                    if (d.status === 'pending') statusClass = ' status-pending';
                    if (d.status === 'accepted') statusClass = ' status-accepted';
                    if (d.status === 'rejected') statusClass = ' status-rejected';
                    if (d.status === 'queued') statusClass = ' status-pending';

                    var meta = document.createElement('div');
                    meta.className = 'team-meta';

                    var code = document.createElement('div');
                    code.className = 'team-code';
                    code.textContent = String(d.order_number || '') + ' → ' + String(d.team_code || '');

                    var type = document.createElement('div');
                    type.className = 'team-type';
                    var timeInfo = d.created_at ? (' | ' + String(d.created_at)) : '';
                    var statusText = d.status_label || d.status;
                    if (String(d.status) === 'queued') {
                        type.textContent = ('W kolejce dla zespołu ' + String(d.team_code || '')) + timeInfo;
                    } else {
                        type.textContent = statusText + timeInfo;
                    }

                    meta.appendChild(code);
                    meta.appendChild(type);

                    var status = document.createElement('div');
                    status.className = 'team-status' + statusClass;
                    if (String(d.status) === 'accepted') {
                        var previewBtn = document.createElement('button');
                        previewBtn.type = 'button';
                        previewBtn.className = 'btn-secondary dispatch-preview-btn';
                        previewBtn.style.outline = 'none';
                        previewBtn.style.boxShadow = 'none';
                        previewBtn.textContent = 'Podgląd';
                        previewBtn.addEventListener('click', function (e) {
                            if (e) { e.preventDefault(); e.stopPropagation(); }
                            if (!(window.SWDTM && typeof window.SWDTM.openOrderModal === 'function')) return;

                            var tmp = document.createElement('div');
                            tmp.setAttribute('data-order-id', String(d.order_id || ''));
                            tmp.setAttribute('data-order-number', String(d.order_number || ''));
                            tmp.setAttribute('data-order-status', String(d.order_status || ''));
                            tmp.setAttribute('data-order-urgency', String(d.urgency || ''));
                            tmp.setAttribute('data-order-type', String(d.order_type || ''));
                            tmp.setAttribute('data-order-transport', String(d.transport_type || ''));
                            tmp.setAttribute('data-order-needed-team', String(d.needed_team || ''));
                            tmp.setAttribute('data-order-sirens', String(d.sirens || '0'));
                            tmp.setAttribute('data-order-planned-at', String(d.planned_at || ''));
                            tmp.setAttribute('data-order-assigned-team', String(d.assigned_team_code || ''));

                            var pFull = (String(d.patient_first_name || '') + ' ' + String(d.patient_last_name || '')).trim();
                            tmp.setAttribute('data-order-patient', pFull);
                            tmp.setAttribute('data-order-patient-position', String(d.patient_position || ''));
                            tmp.setAttribute('data-order-patient-weight', d.patient_weight_kg != null ? String(d.patient_weight_kg) : '');
                            tmp.setAttribute('data-order-phone', String(d.phone || ''));

                            tmp.setAttribute('data-order-from', String(d.from || ''));
                            tmp.setAttribute('data-order-to', String(d.to || ''));

                            tmp.setAttribute('data-order-interview-oxygen', String(d.interview_oxygen || ''));
                            tmp.setAttribute('data-order-interview-conscious', String(d.interview_conscious || ''));
                            tmp.setAttribute('data-order-interview-notes', String(d.interview_notes || ''));

                            tmp.setAttribute('data-order-icd10-none', String(d.icd10_none != null ? d.icd10_none : '0'));
                            tmp.setAttribute('data-order-icd10-code', String(d.icd10_code || ''));
                            tmp.setAttribute('data-order-icd10-name', String(d.icd10_name || ''));
                            tmp.setAttribute('data-order-description', String(d.order_description || ''));

                            window.SWDTM.openOrderModal(tmp);
                        });
                        status.textContent = '';
                        status.appendChild(previewBtn);
                    } else {
                        status.textContent = String(d.status_label || d.status);
                    }

                    row.appendChild(meta);
                    row.appendChild(status);

                    box.appendChild(row);
                });

                if (!box.dataset.dispatchClickBound) {
                    box.dataset.dispatchClickBound = '1';

                    box.addEventListener('click', function (e) {
                        var t = e.target;
                        if (!t) return;
                        if (t && t.closest && t.closest('button')) return;
                        var el = (typeof t.closest === 'function') ? t.closest('[data-dispatch-view]') : null;
                        if (!el) return;
                        openDispatchFromEl(el);
                    });

                    box.addEventListener('keydown', function (e) {
                        if (e.key !== 'Enter' && e.key !== ' ') return;
                        var t = e.target;
                        if (!t) return;
                        if (t && t.closest && t.closest('button')) return;
                        var el = (t && t.dataset && t.dataset.dispatchView) ? t : ((typeof t.closest === 'function') ? t.closest('[data-dispatch-view]') : null);
                        if (!el) return;
                        e.preventDefault();
                        openDispatchFromEl(el);
                    });
                }
            }

            function fetchDispatchStatus() {
                var url = (window.SWDTM && window.SWDTM.dispatchStatusUrl) ? String(window.SWDTM.dispatchStatusUrl) : '';
                if (!url) return;
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) {
                        if (!r || r.ok !== true) {
                            renderDispatchStatusList({ _error: 'Błąd pobierania zadysponowań (' + String(r ? r.status : '') + ').' });
                            return null;
                        }
                        var ct = '';
                        try {
                            ct = String(r.headers.get('content-type') || '');
                        } catch (e) {
                            ct = '';
                        }
                        if (ct.indexOf('application/json') === -1) {
                            renderDispatchStatusList({ _error: 'Błąd pobierania zadysponowań (odpowiedź nie jest JSON).' });
                            return null;
                        }
                        return r.json();
                    })
                    .then(function (data) {
                        if (!data) return;
                        if (!data || data.ok !== true || !Array.isArray(data.dispatches)) {
                            renderDispatchStatusList({ _error: 'Błąd pobierania zadysponowań (nieprawidłowa odpowiedź).' });
                            return;
                        }
                        renderDispatchStatusList(data.dispatches);
                    })
                    .catch(function () {
                        renderDispatchStatusList({ _error: 'Błąd pobierania zadysponowań (problem sieci / sesja).' });
                    });
            }

            function init() {
                renderDispatchStatusList({ _error: 'Ładowanie zadysponowań...' });
                fetchDispatchStatus();
                setInterval(fetchDispatchStatus, 5000);
            }

            window.SWDTM = window.SWDTM || {};
            window.SWDTM.fetchDispatchStatus = fetchDispatchStatus;

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();

        (function () {
            var form = document.getElementById('dispatch-form');
            if (!form) return;

            var confirmOverlay = document.querySelector('[data-modal-overlay="dispatch-confirm"]');
            var confirmText = confirmOverlay ? confirmOverlay.querySelector('[data-dispatch-confirm-text]') : null;
            var confirmOk = confirmOverlay ? confirmOverlay.querySelector('[data-dispatch-confirm-ok]') : null;
            var confirmCancel = confirmOverlay ? confirmOverlay.querySelectorAll('[data-dispatch-confirm-cancel]') : null;

            function closeConfirm() {
                if (confirmOverlay) confirmOverlay.classList.remove('is-open');
                if (confirmOverlay) confirmOverlay.dataset.onOk = '';
            }

            function openConfirm(message, onOk) {
                if (!confirmOverlay) return false;
                if (confirmText) confirmText.textContent = String(message || '');
                confirmOverlay._onOk = (typeof onOk === 'function') ? onOk : null;
                confirmOverlay.classList.add('is-open');
                return true;
            }

            if (confirmOverlay) {
                if (confirmCancel) {
                    confirmCancel.forEach(function (b) {
                        b.addEventListener('click', function () {
                            closeConfirm();
                        });
                    });
                }
                if (confirmOk) {
                    confirmOk.addEventListener('click', function () {
                        var fn = confirmOverlay ? confirmOverlay._onOk : null;
                        closeConfirm();
                        try {
                            if (typeof fn === 'function') fn();
                        } catch (e) {
                        }
                    });
                }
                confirmOverlay.addEventListener('click', function (e) {
                    if (e && e.target === confirmOverlay && confirmOverlay.getAttribute('data-outside-close') === '1') {
                        closeConfirm();
                    }
                });
                document.addEventListener('keydown', function (e) {
                    if (!e || e.key !== 'Escape') return;
                    if (confirmOverlay.classList.contains('is-open') && confirmOverlay.getAttribute('data-esc-close') === '1') {
                        closeConfirm();
                    }
                });
            }

            function doDispatchSubmit() {
                var formData = new FormData(form);
                var url = (window.SWDTM && window.SWDTM.dispatchOrderUrl) ? String(window.SWDTM.dispatchOrderUrl) : '';
                if (!url) return;

                var submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = 'Wysyłanie...';
                }

                fetch(url, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Zadysponuj';
                    }

                    if (!data || data.ok !== true) {
                        alert('Błąd: ' + (data.error || 'Nie udało się zadysponować zespołu.'));
                        return;
                    }

                    var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
                    if (overlay) overlay.classList.remove('is-open');

                    if (window.SWDTM && typeof window.SWDTM.showToast === 'function') {
                        if (data && data.queued === true) {
                            window.SWDTM.showToast('Zlecenie dodane do kolejki zespołu.', 'success');
                        } else {
                            window.SWDTM.showToast('Zespół został zadysponowany.', 'success');
                        }
                    }

                    try {
                        var orderId = form.querySelector('[data-dispatch-order-id]') ? String(form.querySelector('[data-dispatch-order-id]').value || '') : '';
                        if (orderId) {
                            var row = document.querySelector('[data-order-open][data-order-id="' + orderId + '"]');
                            if (row && row.parentNode) {
                                row.parentNode.removeChild(row);
                            }
                        }
                    } catch (e) {
                    }

                    if (window.SWDTM && typeof window.SWDTM.fetchDispatchStatus === 'function') {
                        window.SWDTM.fetchDispatchStatus();
                    }
                })
                .catch(function (error) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Zadysponuj';
                    }
                    alert('Błąd sieci: ' + error.message);
                });
            }

            form.addEventListener('submit', function (e) {
                e.preventDefault();

                try {
                    var teamCodeField = form.querySelector('input[name="team_code"]');
                    var teamCode = teamCodeField ? String(teamCodeField.value || '').trim() : '';
                    var teams = (window.SWDTM && Array.isArray(window.SWDTM.activeTeamsCache)) ? window.SWDTM.activeTeamsCache : [];
                    var team = null;
                    if (teamCode && teams && teams.length) {
                        team = teams.find(function (t) {
                            return String(t.team_code || '').trim() === teamCode;
                        }) || null;
                    }
                    var st = team ? String(team.status_label || '') : '';
                    var s = st.trim().toLowerCase();
                    var busy = (s && s !== 'aktywny' && s !== 'gotowy w bazie' && s !== 'powrót do bazy');
                    if (busy) {
                        var msg = 'Ten zespół realizuje inne zlecenie (status: ' + (st || '—') + ').\n\nNowe zlecenie trafi do kolejki i zostanie wysłane dopiero po zakończeniu poprzedniego.';
                        openConfirm(msg, function () {
                            doDispatchSubmit();
                        });
                        return;
                    }
                } catch (e) {
                }

                doDispatchSubmit();
            });
        })();
    </script>

</body>
</html>
