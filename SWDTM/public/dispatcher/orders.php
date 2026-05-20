<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';
require_role(['dispatcher']);

$user = current_user();

function fmtOrderStatusLabel(string $s): string {
    $s = trim($s);
    return match ($s) {
        'new' => 'Wolne',
        'assigned' => 'Zadysponowane',
        'done' => 'Zakończone',
        'odwolany' => 'Odwołany',
        'canceled', 'cancelled' => 'Anulowane',
        default => ($s !== '' ? $s : '—'),
    };
}

function fmtOrderStatusStyle(string $s): string {
    $s = trim($s);
    return match ($s) {
        'new' => 'background:#22c55e;color:#fff;border-color:#16a34a;',
        'assigned' => 'background:#ef4444;color:#fff;border-color:#dc2626;',
        'done' => 'background:#a855f7;color:#fff;border-color:#9333ea;',
        'odwolany' => 'background:#f97316;color:#fff;border-color:#ea580c;',
        'canceled', 'cancelled' => 'background:#9ca3af;color:#fff;border-color:#6b7280;',
        default => 'background:#e5e7eb;color:#111827;border-color:#d1d5db;',
    };
}

$teams = [
    ['code' => 'ZRM-01', 'type' => 'P', 'status' => 'available', 'status_label' => 'Wolny'],
    ['code' => 'ZRM-02', 'type' => 'S', 'status' => 'en_route', 'status_label' => 'W drodze'],
    ['code' => 'ZRM-03', 'type' => 'P', 'status' => 'on_scene', 'status_label' => 'Na miejscu'],
    ['code' => 'ZRM-04', 'type' => 'S', 'status' => 'transport', 'status_label' => 'Transport'],
];

$q = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) && is_string($_GET['status']) ? trim($_GET['status']) : '';
$orderType = isset($_GET['order_type']) && is_string($_GET['order_type']) ? trim($_GET['order_type']) : '';
$urgency = isset($_GET['urgency']) && is_string($_GET['urgency']) ? trim($_GET['urgency']) : '';
$transportType = isset($_GET['transport_type']) && is_string($_GET['transport_type']) ? trim($_GET['transport_type']) : '';
$day = isset($_GET['day']) && is_string($_GET['day']) ? trim($_GET['day']) : '';

$allowedStatus = ['', 'new', 'assigned', 'done', 'canceled', 'odwolany'];
if (!in_array($status, $allowedStatus, true)) {
    $status = '';
}

$allowedOrderType = ['', 'nagłe', 'planowe'];
if (!in_array($orderType, $allowedOrderType, true)) {
    $orderType = '';
}

$allowedUrgency = ['', 'zwykłe', 'pilne', 'natychmiast'];
if (!in_array($urgency, $allowedUrgency, true)) {
    $urgency = '';
}

$allowedTransportType = ['', 'hospital', 'poradnia', 'miedzyszpitalna', 'dom', 'transport prywatny'];
if (!in_array($transportType, $allowedTransportType, true)) {
    $transportType = '';
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
if ($day === '') {
    $day = $today;
}

$orders = [];
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

    $where = [];
    $params = [];

    if ($status !== '') {
        $where[] = 'status = :status';
        $params[':status'] = $status;
    }

    if ($orderType !== '') {
        $where[] = 'order_type = :order_type';
        $params[':order_type'] = $orderType;
    }

    if ($urgency !== '') {
        $where[] = 'urgency = :urgency';
        $params[':urgency'] = $urgency;
    }

    if ($transportType !== '') {
        $where[] = 'transport_type = :transport_type';
        $params[':transport_type'] = $transportType;
    }

    if ($day !== '') {
        $where[] = 'DATE(COALESCE(planned_at, created_at)) = :day';
        $params[':day'] = $day;
    }

    if ($q !== '') {
        $where[] = '(
            order_seq LIKE :q
            OR from_city LIKE :q
            OR from_street LIKE :q
            OR phone LIKE :q
        )';
        $params[':q'] = '%' . $q . '%';
    }

    $where[] = "(status <> 'new' OR NOT EXISTS (SELECT 1 FROM dispatch_notifications dn WHERE dn.order_id = orders.id AND dn.status IN ('pending','accepted','')))";

    $sql = "SELECT
                id,
                status,
                order_seq,
                order_month,
                order_year,
                order_type,
                urgency,
                transport_type,
                planned_at,
                needed_team,
                sirens,
                phone,
                from_city,
                from_street,
                from_number,
                from_flat,
                to_city,
                to_street,
                to_number,
                to_flat,
                assigned_team_code,
                patient_first_name,
                patient_last_name,
                patient_position,
                " . ($hasPatientWeight ? "patient_weight_kg" : "NULL AS patient_weight_kg") . ",
                interview_oxygen,
                interview_conscious,
                interview_notes,
                icd10_none,
                icd10_code,
                icd10_name,
                order_description,
                created_at
            FROM orders";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY COALESCE(planned_at, created_at) DESC, id DESC LIMIT 300';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
} catch (Throwable $e) {
    $orders = [];
}

$pendingCount = 0;
try {
    $stCnt = $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status = 'pending'");
    $pendingCount = (int)($stCnt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $pendingCount = 0;
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
    <title>SWDTM - Zlecenia</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
    <style>
        .status-badge{display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:999px;font-weight:800;font-size:12px;line-height:1;border:1px solid transparent;min-width:110px}
        tr.is-clickable{cursor:pointer}
        tr.is-clickable:hover td{background:rgba(17,24,39,.03)}

        .orders-table{table-layout:fixed;width:100%}
        .orders-table th,.orders-table td{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:middle}
        .orders-table th:nth-child(1),.orders-table td:nth-child(1){width:96px}
        .orders-table th:nth-child(2),.orders-table td:nth-child(2){width:140px}
        .orders-table th:nth-child(3),.orders-table td:nth-child(3){width:110px}
        .orders-table th:nth-child(4),.orders-table td:nth-child(4){width:220px}
        .orders-table th:nth-child(5),.orders-table td:nth-child(5){width:110px}
        .orders-table th:nth-child(6),.orders-table td:nth-child(6){width:120px}
        .orders-table th:nth-child(7),.orders-table td:nth-child(7){width:auto}
        .orders-table th:nth-child(8),.orders-table td:nth-child(8){width:auto}
        .orders-table th:nth-child(9),.orders-table td:nth-child(9){width:130px}
        .orders-table th:nth-child(10),.orders-table td:nth-child(10){width:150px}

        .order-preview-modal{max-width:min(1400px, 94vw);width:94vw;max-height:90vh;display:flex;flex-direction:column}
        .order-preview-body{padding:18px 20px 16px 20px;background:linear-gradient(180deg, rgba(249,250,251,.9) 0%, rgba(255,255,255,1) 60%);overflow:auto;flex:1;min-height:0}
        .order-preview-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .order-preview-card{border:1px solid rgba(17,24,39,.10);border-radius:14px;padding:14px 16px;background:#fff;box-shadow:0 10px 25px rgba(17,24,39,.06)}
        .order-preview-card-title{display:flex;align-items:center;justify-content:space-between;gap:10px;font-weight:950;letter-spacing:.2px;color:#111827;margin-bottom:10px}
        .order-preview-kv{display:grid;gap:8px}
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
    </style>
    <script defer src="<?= htmlspecialchars(url('/assets/app.js?v=' . @filemtime(__DIR__ . '/../assets/app.js')), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="is-dispatcher">
    <div class="toasts" data-toasts>
        <?php if ($flashMessage !== ''): ?>
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
                <a class="nav-item" href="<?= htmlspecialchars(url('/dispatcher'), ENT_QUOTES, 'UTF-8') ?>">Panel dowodzenia</a>
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/dispatcher/orders'), ENT_QUOTES, 'UTF-8') ?>">Zlecenia</a>
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
                    <div class="page-title">Zlecenia</div>
                    <div class="page-subtitle">Wszystkie zlecenia z filtrowaniem.</div>
                </div>
                <div class="topbar-actions">
                    <a class="btn-primary" href="<?= htmlspecialchars(url('/dispatcher/new-order'), ENT_QUOTES, 'UTF-8') ?>">Nowe zlecenie</a>
                </div>
            </header>

            <section class="panel" style="margin-bottom:14px">
                <div class="panel-head">
                    <div class="panel-title">Filtry</div>
                </div>
                <div class="panel-body">
                    <form class="order-form" method="get" action="<?= htmlspecialchars(url('/dispatcher/orders'), ENT_QUOTES, 'UTF-8') ?>" data-orders-filters style="grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">
                        <label class="field">
                            <span class="label">Dzień realizacji</span>
                            <input class="input" type="date" name="day" value="<?= htmlspecialchars($day, ENT_QUOTES, 'UTF-8') ?>">
                        </label>

                        <label class="field" style="grid-column:span 3">
                            <span class="label">Szukaj (nr, miasto, ulica, telefon)</span>
                            <input class="input" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="np. 12/04/2026, Warszawa, 600...">
                        </label>

                        <label class="field">
                            <span class="label">Status</span>
                            <select class="input" name="status">
                                <option value="" <?= ($status === '') ? 'selected' : '' ?>>Wszystkie</option>
                                <option value="new" <?= ($status === 'new') ? 'selected' : '' ?>>Wolne</option>
                                <option value="assigned" <?= ($status === 'assigned') ? 'selected' : '' ?>>Zadysponowane</option>
                                <option value="done" <?= ($status === 'done') ? 'selected' : '' ?>>Zakończone</option>
                                <option value="canceled" <?= ($status === 'canceled') ? 'selected' : '' ?>>Anulowane</option>
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Typ zlecenia</span>
                            <select class="input" name="order_type">
                                <option value="" <?= ($orderType === '') ? 'selected' : '' ?>>Wszystkie</option>
                                <option value="nagłe" <?= ($orderType === 'nagłe') ? 'selected' : '' ?>>Nagłe</option>
                                <option value="planowe" <?= ($orderType === 'planowe') ? 'selected' : '' ?>>Planowe</option>
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Pilność</span>
                            <select class="input" name="urgency">
                                <option value="" <?= ($urgency === '') ? 'selected' : '' ?>>Wszystkie</option>
                                <option value="zwykłe" <?= ($urgency === 'zwykłe') ? 'selected' : '' ?>>Zwykłe</option>
                                <option value="pilne" <?= ($urgency === 'pilne') ? 'selected' : '' ?>>Pilne</option>
                                <option value="natychmiast" <?= ($urgency === 'natychmiast') ? 'selected' : '' ?>>Natychmiast</option>
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Rodzaj transportu</span>
                            <select class="input" name="transport_type">
                                <option value="" <?= ($transportType === '') ? 'selected' : '' ?>>Wszystkie</option>
                                <option value="hospital" <?= ($transportType === 'hospital') ? 'selected' : '' ?>>Szpital</option>
                                <option value="poradnia" <?= ($transportType === 'poradnia') ? 'selected' : '' ?>>Poradnia</option>
                                <option value="miedzyszpitalna" <?= ($transportType === 'miedzyszpitalna') ? 'selected' : '' ?>>Międzyszpitalna</option>
                                <option value="dom" <?= ($transportType === 'dom') ? 'selected' : '' ?>>Dom</option>
                                <option value="transport prywatny" <?= ($transportType === 'transport prywatny') ? 'selected' : '' ?>>Transport prywatny</option>
                            </select>
                        </label>

                        <div class="field" style="display:flex;gap:10px;justify-content:flex-end;align-items:end;grid-column:span 4">
                            <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher/orders'), ENT_QUOTES, 'UTF-8') ?>?day=<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">Dzisiaj</a>
                            <a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher/orders'), ENT_QUOTES, 'UTF-8') ?>">Wyczyść</a>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Lista</div>
                </div>
                <div class="panel-body">
                    <?php if (!$orders): ?>
                        Brak wyników.
                    <?php else: ?>
                        <table class="table orders-table">
                            <thead>
                                <tr>
                                    <th>Nr</th>
                                    <th>Status</th>
                                    <th>Zespół</th>
                                    <th>Typ</th>
                                    <th>Pilność</th>
                                    <th>Dzień</th>
                                    <th>Skąd</th>
                                    <th>Dokąd</th>
                                    <th>Telefon</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
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

                                        $plannedAt = $o['planned_at'] ?? null;
                                        $createdAt = $o['created_at'] ?? null;
                                        $daySource = ($plannedAt !== null && $plannedAt !== '') ? (string)$plannedAt : (string)$createdAt;
                                        $day = '';
                                        try {
                                            $day = (new DateTimeImmutable($daySource))->format('Y-m-d');
                                        } catch (Throwable $e) {
                                            $day = '';
                                        }

                                        $canDispatch = ($day === $today) && ((string)($o['status'] ?? '') === 'new');

                                        $transportLabel = match ((string)($o['transport_type'] ?? '')) {
                                            'hospital' => 'Przekazanie do szpitala',
                                            'poradnia' => 'Wizyta w poradni',
                                            'miedzyszpitalna' => 'Konsultacja międzyszpitalna',
                                            'dom' => 'Odwóz do domu',
                                            default => (string)($o['transport_type'] ?? ''),
                                        };

                                        $phone = trim((string)($o['phone'] ?? ''));
                                        $assignedTeam = trim((string)($o['assigned_team_code'] ?? ''));
                                        $statusRaw = (string)($o['status'] ?? '');
                                        $statusLabel = fmtOrderStatusLabel($statusRaw);
                                        $statusStyle = fmtOrderStatusStyle($statusRaw);

                                        $patientFirst = trim((string)($o['patient_first_name'] ?? ''));
                                        $patientLast = trim((string)($o['patient_last_name'] ?? ''));
                                        $patientFull = trim($patientFirst . ' ' . $patientLast);

                                        $patientWeight = '';
                                        if (isset($o['patient_weight_kg'])) {
                                            $w = (int)$o['patient_weight_kg'];
                                            if ($w >= 1 && $w <= 350) {
                                                $patientWeight = (string)$w;
                                            }
                                        }
                                    ?>
                                    <tr class="is-clickable"
                                        tabindex="0"
                                        role="button"
                                        data-order-open
                                        data-order-id="<?= htmlspecialchars((string)$o['id'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-number="<?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-status="<?= htmlspecialchars($statusRaw, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-urgency="<?= htmlspecialchars((string)($o['urgency'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-type="<?= htmlspecialchars((string)($o['order_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-transport="<?= htmlspecialchars($transportLabel, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-needed-team="<?= htmlspecialchars((string)($o['needed_team'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-sirens="<?= htmlspecialchars((string)($o['sirens'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-planned-at="<?= htmlspecialchars((string)($o['planned_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-assigned-team="<?= htmlspecialchars($assignedTeam, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-patient="<?= htmlspecialchars($patientFull, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-patient-position="<?= htmlspecialchars((string)($o['patient_position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-patient-weight="<?= htmlspecialchars($patientWeight, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-phone="<?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-from="<?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-to="<?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-interview-oxygen="<?= htmlspecialchars((string)($o['interview_oxygen'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-interview-conscious="<?= htmlspecialchars((string)($o['interview_conscious'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-interview-notes="<?= htmlspecialchars((string)($o['interview_notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-icd10-none="<?= htmlspecialchars((string)($o['icd10_none'] ?? '0'), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-icd10-code="<?= htmlspecialchars((string)($o['icd10_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-icd10-name="<?= htmlspecialchars((string)($o['icd10_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        data-order-description="<?= htmlspecialchars((string)($o['order_description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <td><?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="status-badge" style="<?= htmlspecialchars($statusStyle, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($assignedTeam !== '' ? $assignedTeam : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td title="<?= htmlspecialchars($transportLabel, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($transportLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($o['urgency'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($day !== '' ? $day : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td title="<?= htmlspecialchars($from !== ',' ? $from : '—', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($from !== ',' ? $from : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td title="<?= htmlspecialchars($to !== ',' ? $to : '—', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($to !== ',' ? $to : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td title="<?= htmlspecialchars($phone !== '' ? $phone : '—', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone !== '' ? $phone : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td style="text-align:right">
                                            <button
                                                class="btn-secondary"
                                                type="button"
                                                data-dispatch-open
                                                data-order-id="<?= htmlspecialchars((string)$o['id'], ENT_QUOTES, 'UTF-8') ?>"
                                                data-order-number="<?= htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') ?>"
                                                <?= $canDispatch ? '' : 'disabled' ?>
                                                title="<?= $canDispatch ? 'Zadysponuj zespół' : 'Można dysponować tylko zlecenia na dziś (i status: wolne)' ?>"
                                            >Zadysponuj</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
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

    <script>
        (function () {
            window.SWDTM = window.SWDTM || {};
            window.SWDTM.activeTeamsUrl = <?= json_encode(url('/api/active_teams.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

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

            function teamTypeFromCode(code) {
                var c = String(code || '').trim();
                if (!c) return '';
                var m = c.match(/^([TPS])/i);
                return m ? String(m[1]).toUpperCase() : '';
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
                    tiles.appendChild(makeTeamTile(t));
                });

                tiles.querySelectorAll('.status-dot').forEach(function (dot) {
                    dot.classList.remove('is-pulse');
                    void dot.offsetWidth;
                    dot.classList.add('is-pulse');
                    setTimeout(function () {
                        dot.classList.remove('is-pulse');
                    }, 720);
                });
            }

            function openDispatchModal(orderId, orderNumber) {
                var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
                if (!overlay) return;

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

                var tiles = overlay.querySelector('[data-dispatch-team-tiles]');
                if (tiles) {
                    tiles.querySelectorAll('button').forEach(function (b) {
                        b.addEventListener('click', function () {
                            tiles.querySelectorAll('button').forEach(function (x) {
                                x.style.outline = '';
                            });
                            b.style.outline = '2px solid rgba(59,130,246,.9)';

                            var c = String(b.dataset.teamCode || '');
                            var tt = String(b.dataset.teamType || '');
                            if (teamCode) teamCode.value = c;
                            if (teamType) teamType.value = tt;
                            if (submitBtn) submitBtn.disabled = !c;
                        });
                    });
                }

                overlay.classList.add('is-open');
            }

            window.SWDTM = window.SWDTM || {};
            window.SWDTM.openDispatchModal = openDispatchModal;

            document.querySelectorAll('[data-dispatch-open]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    if (btn.disabled) return;
                    openDispatchModal(btn.getAttribute('data-order-id'), btn.getAttribute('data-order-number'));
                });
            });

            function fetchActiveTeams() {
                var url = (window.SWDTM && window.SWDTM.activeTeamsUrl) ? String(window.SWDTM.activeTeamsUrl) : '';
                if (!url) return;
                fetch(url, { credentials: 'same-origin' })
                    .then(function (r) { return r && r.ok === true ? r.json() : null; })
                    .then(function (data) {
                        if (!data || data.ok !== true || !Array.isArray(data.teams)) return;
                        window.SWDTM.activeTeamsCache = data.teams;

                        var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
                        if (overlay && overlay.classList.contains('is-open')) {
                            renderDispatchTeamTiles();
                        }
                    })
                    .catch(function () {});
            }

            fetchActiveTeams();
            setInterval(fetchActiveTeams, 5000);
        })();
    </script>

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
                    <a class="btn-primary" href="#" data-order-edit>Edytuj</a>
                </div>
                <div class="order-preview-actions-right">
                    <button class="btn-secondary" type="button" data-modal-close>Zamknij</button>
                </div>
            </div>
        </div>
    </div>

    <script>
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

            function openOrderModal(tr) {
                var overlay = document.querySelector('[data-modal-overlay="order"]');
                if (!overlay || !tr) return;

                var id = tr.getAttribute('data-order-id') || '';
                var number = tr.getAttribute('data-order-number') || '';
                var subtitle = overlay.querySelector('[data-order-subtitle]');
                if (subtitle) subtitle.textContent = number ? ('Zlecenie: ' + number) : '';

                var status = tr.getAttribute('data-order-status') || '';
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
                setText(overlay, '[data-order-urgency]', tr.getAttribute('data-order-urgency'));
                setText(overlay, '[data-order-type]', tr.getAttribute('data-order-type'));
                setText(overlay, '[data-order-transport]', translateTransport(tr.getAttribute('data-order-transport')));
                setText(overlay, '[data-order-needed-team]', tr.getAttribute('data-order-needed-team'));

                var sir = tr.getAttribute('data-order-sirens') || '0';
                setText(overlay, '[data-order-sirens]', (String(sir) === '1') ? 'TAK' : 'NIE');

                setText(overlay, '[data-order-planned-at]', tr.getAttribute('data-order-planned-at'));
                setText(overlay, '[data-order-assigned-team]', tr.getAttribute('data-order-assigned-team'));

                setText(overlay, '[data-order-patient]', tr.getAttribute('data-order-patient'));
                setText(overlay, '[data-order-patient-position]', tr.getAttribute('data-order-patient-position'));
                var w = tr.getAttribute('data-order-patient-weight') || '';
                setText(overlay, '[data-order-patient-weight]', w ? (w + ' kg') : '—');
                setText(overlay, '[data-order-phone]', tr.getAttribute('data-order-phone'));

                setText(overlay, '[data-order-from]', tr.getAttribute('data-order-from'));
                setText(overlay, '[data-order-to]', tr.getAttribute('data-order-to'));

                setText(overlay, '[data-order-interview-oxygen]', tr.getAttribute('data-order-interview-oxygen'));
                setText(overlay, '[data-order-interview-conscious]', tr.getAttribute('data-order-interview-conscious'));
                setText(overlay, '[data-order-interview-notes]', tr.getAttribute('data-order-interview-notes'));

                var none = String(tr.getAttribute('data-order-icd10-none') || '') === '1';
                var code = String(tr.getAttribute('data-order-icd10-code') || '');
                var name = String(tr.getAttribute('data-order-icd10-name') || '');
                setText(overlay, '[data-order-icd10]', none ? 'Brak rozpoznania ICD-10' : ((code || name) ? ((code ? (code + ' — ') : '') + name) : '—'));

                setText(overlay, '[data-order-description]', tr.getAttribute('data-order-description'));

                var edit = overlay.querySelector('[data-order-edit]');
                if (edit) {
                    edit.setAttribute('href', <?= json_encode(url('/dispatcher/edit-order'), JSON_UNESCAPED_UNICODE) ?> + '?id=' + encodeURIComponent(id));
                }

                overlay.classList.add('is-open');
            }

            document.querySelectorAll('[data-order-open]').forEach(function (tr) {
                tr.addEventListener('click', function (e) {
                    var tgt = e && e.target ? e.target : null;
                    if (tgt && tgt.closest && tgt.closest('button')) return;
                    openOrderModal(tr);
                });
                tr.addEventListener('keydown', function (e) {
                    if (!e || (e.key !== 'Enter' && e.key !== ' ')) return;
                    e.preventDefault();
                    openOrderModal(tr);
                });
            });

            document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var overlay = btn.closest('.modal-overlay');
                    if (overlay) overlay.classList.remove('is-open');
                });
            });

            document.querySelectorAll('.modal-overlay[data-outside-close="1"]').forEach(function (overlay) {
                overlay.addEventListener('click', function (e) {
                    if (!e || e.target !== overlay) return;
                    overlay.classList.remove('is-open');
                });
            });

            document.addEventListener('keydown', function (e) {
                if (!e || e.key !== 'Escape') return;
                var open = document.querySelector('.modal-overlay.is-open[data-esc-close="1"]');
                if (open) open.classList.remove('is-open');
            });
        })();
    </script>

    <script>
        (function () {
            var form = document.querySelector('form[data-orders-filters]');
            if (!form) return;

            var debounceTimer = null;
            function submitNow() {
                if (debounceTimer) { clearTimeout(debounceTimer); debounceTimer = null; }
                form.submit();
            }

            function submitDebounced() {
                if (debounceTimer) clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    debounceTimer = null;
                    form.submit();
                }, 300);
            }

            form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
                el.addEventListener('change', submitNow);
            });

            var q = form.querySelector('input[name="q"]');
            if (q) {
                q.addEventListener('input', submitDebounced);
                q.addEventListener('change', submitNow);
            }
        })();
    </script>

    <script>
        window.SWDTM = window.SWDTM || {};
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

            if (bellBtn && overlay) {
                bellBtn.addEventListener('click', function () {
                    overlay.classList.add('is-open');
                    fetchEvents(true);
                });
            }

            if (markAllBtn) {
                markAllBtn.addEventListener('click', function () {
                    ackAll();
                });
            }

            fetchEvents(true);
            setInterval(function () {
                fetchEvents(true);
            }, 5000);
        })();
    </script>
</body>
</html>
