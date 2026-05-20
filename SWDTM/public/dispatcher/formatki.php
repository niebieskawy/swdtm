<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['dispatcher']);

$user = current_user();
$pdo = db();

$pendingCount = 0;
try {
    $stCnt = $pdo->query("SELECT COUNT(*) FROM client_requests WHERE status = 'pending'");
    $pendingCount = (int)($stCnt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $pendingCount = 0;
}

$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim($q) : '';

$where = [];
$params = [];

$where[] = 'cr.status = :st';
$params[':st'] = 'pending';

if ($q !== '') {
    $where[] = '(c.client_name LIKE :q OR c.username LIKE :q OR cr.patient_last_name LIKE :q OR cr.patient_first_name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$sql = 'SELECT cr.id, cr.status, cr.created_at, cr.order_type, cr.urgency, cr.transport_type, cr.patient_first_name, cr.patient_last_name, cr.from_city, cr.from_street, cr.from_number, cr.to_city, cr.to_street, cr.to_number, c.client_name, c.username AS client_username'
    . ' FROM client_requests cr'
    . ' JOIN clients c ON c.id = cr.client_id';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY cr.id DESC';

$rows = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    $rows = [];
}

$flash = flash_get('formatki');
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
    <title>SWDTM - Formatki</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
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
                <a class="nav-item" href="<?= htmlspecialchars(url('/dispatcher/orders'), ENT_QUOTES, 'UTF-8') ?>">Zlecenia</a>
                <a class="nav-item is-active<?= ($pendingCount > 0) ? ' is-attention' : '' ?>" href="<?= htmlspecialchars(url('/dispatcher/formatki'), ENT_QUOTES, 'UTF-8') ?>">Formatki<?php if ($pendingCount > 0): ?><span class="nav-badge"><?= (int)$pendingCount ?></span><?php endif; ?></a>
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
                    <div class="page-title">Formatki</div>
                    <div class="page-subtitle">Zgłoszenia od klientów (tworzą zlecenie dopiero po potwierdzeniu)</div>
                </div>
                <div class="topbar-actions">
                    <form method="get" action="<?= htmlspecialchars(url('/dispatcher/formatki'), ENT_QUOTES, 'UTF-8') ?>" data-autosubmit data-debounce="300" autocomplete="off" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
                        <input class="input" type="text" name="q" placeholder="Szukaj" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn-secondary" type="submit">Filtruj</button>
                    </form>
                </div>
            </header>

            <div class="panel">
                <div class="panel-body" style="overflow:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Klient</th>
                                <th>Pacjent</th>
                                <th>Transport</th>
                                <th>Skąd</th>
                                <th>Dokąd</th>
                                <th>Utworzono</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): ?>
                                <?php
                                    $from = trim((string)($r['from_city'] ?? '') . ', ' . (string)($r['from_street'] ?? '') . ' ' . (string)($r['from_number'] ?? ''));
                                    $to = trim((string)($r['to_city'] ?? '') . ', ' . (string)($r['to_street'] ?? '') . ' ' . (string)($r['to_number'] ?? ''));
                                    $patient = trim((string)($r['patient_first_name'] ?? '') . ' ' . (string)($r['patient_last_name'] ?? ''));
                                    $clientLabel = trim((string)($r['client_name'] ?? ''));
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($clientLabel, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($patient, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($r['transport_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($from, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($to, ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($r['created_at'] ?? '---'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><a class="btn-secondary" href="<?= htmlspecialchars(url('/dispatcher/formatki/edit?id=' . (int)$r['id']), ENT_QUOTES, 'UTF-8') ?>">Otwórz</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
