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

$now = new DateTimeImmutable();
$cutoff = $now->modify('-20 seconds')->format('Y-m-d H:i:s');

$teams = [];
try {
    $stmt = $pdo->query("SELECT code, type, is_active, COALESCE(name, '') AS name FROM teams ORDER BY code ASC");
    $baseTeams = $stmt->fetchAll();

    $presenceByCode = [];
    try {
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

        $stmt = $pdo->query(
            "SELECT team_code, COALESCE(status_label, '') AS status_label, COALESCE(leader_name, '') AS leader_name, COALESCE(driver_name, '') AS driver_name, last_seen_at\n"
            . "FROM team_presence"
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as $r) {
            $presenceByCode[(string)$r['team_code']] = $r;
        }
    } catch (Throwable $e) {
        $presenceByCode = [];
    }

    $teams = [];
    foreach ($baseTeams as $t) {
        $code = (string)($t['code'] ?? '');
        $p = $presenceByCode[$code] ?? null;
        $lastSeenAt = is_array($p) ? (string)($p['last_seen_at'] ?? '') : '';

        $isOnline = 0;
        if ($lastSeenAt !== '' && $lastSeenAt >= $cutoff) {
            $isOnline = 1;
        }

        $teams[] = [
            'code' => $code,
            'type' => (string)($t['type'] ?? ''),
            'is_active' => (int)($t['is_active'] ?? 0),
            'name' => (string)($t['name'] ?? ''),
            'status_label' => is_array($p) ? (string)($p['status_label'] ?? '') : '',
            'leader_name' => is_array($p) ? (string)($p['leader_name'] ?? '') : '',
            'driver_name' => is_array($p) ? (string)($p['driver_name'] ?? '') : '',
            'last_seen_at' => $lastSeenAt,
            'is_online' => $isOnline,
        ];
    }
} catch (Throwable $e) {
    $teams = [];
}

if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'cutoff' => $cutoff, 'teams' => $teams], JSON_UNESCAPED_UNICODE);
    exit;
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Zespoły</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/../assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(url('/assets/app.js?v=' . @filemtime(__DIR__ . '/../assets/app.js')), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="is-dispatcher">
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
                <a class="nav-item<?= ($pendingCount > 0) ? ' is-attention' : '' ?>" href="<?= htmlspecialchars(url('/dispatcher/formatki'), ENT_QUOTES, 'UTF-8') ?>">Formatki<?php if ($pendingCount > 0): ?><span class="nav-badge"><?= (int)$pendingCount ?></span><?php endif; ?></a>
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/dispatcher/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
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
                    <div class="page-title">Zespoły</div>
                    <div class="page-subtitle">Wszystkie zespoły dodane przez administratora.</div>
                </div>
            </header>

            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Lista zespołów</div>
                </div>
                <div class="panel-body">
                    <?php if (!$teams): ?>
                        Brak zespołów.
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kod</th>
                                    <th>Typ</th>
                                    <th>Status</th>
                                    <th>Kierownik</th>
                                    <th>Kierowca</th>
                                    <th>Ostatnio widziany</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $t): ?>
                                    <?php
                                        $code = (string)($t['code'] ?? '');
                                        $type = (string)($t['type'] ?? '');
                                        $label = (string)($t['status_label'] ?? '');
                                        $statusText = $label !== '' ? $label : '—';

                                        $s = mb_strtolower(trim($label));
                                        $statusClass = '';
                                        if ($s === 'gotowy w bazie' || $s === 'powrót do bazy') {
                                            $statusClass = ' status-green';
                                        } elseif ($s === 'niegotowy' || $s === 'przywracanie gotowości' || $s === 'dezynfekcja' || $s === 'mycie' || $s === 'tankowanie') {
                                            $statusClass = ' status-yellow';
                                        } elseif ($s === 'awaria') {
                                            $statusClass = ' status-purple';
                                        } elseif ($s !== '' && $s !== 'aktywny') {
                                            $statusClass = ' status-red';
                                        }
                                        $leader = (string)($t['leader_name'] ?? '');
                                        $driver = (string)($t['driver_name'] ?? '');
                                        $lastSeen = (string)($t['last_seen_at'] ?? '');
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="team-status<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="status-dot"></span>
                                                <span><?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?></span>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($leader !== '' ? $leader : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($driver !== '' ? $driver : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($lastSeen !== '' ? $lastSeen : '—', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td style="text-align:right">
                                            <button class="btn-secondary" type="button" data-open-today-orders data-team-code="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" data-team-type="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>">Zadysponuj do</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>

            <script>
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

                    function dispatchToPreselectedTeam(orderId, orderNumber, todayOverlay) {
                        var teamCode = (window.SWDTM && window.SWDTM.preselectedTeamCode) ? String(window.SWDTM.preselectedTeamCode) : '';
                        var url = (window.SWDTM && window.SWDTM.dispatchOrderUrl) ? String(window.SWDTM.dispatchOrderUrl) : '';
                        var csrf = (window.SWDTM && window.SWDTM.csrfToken) ? String(window.SWDTM.csrfToken) : '';
                        if (!teamCode) {
                            alert('Brak wybranego zespołu.');
                            return;
                        }
                        if (!url || !csrf) {
                            alert('Brak konfiguracji (csrf/url).');
                            return;
                        }

                        if (todayOverlay) {
                            var box = todayOverlay.querySelector('[data-today-orders-list]');
                            if (box) box.style.pointerEvents = 'none';
                        }

                        var body = new URLSearchParams();
                        body.set('order_id', String(orderId || ''));
                        body.set('team_code', teamCode);
                        body.set('csrf', csrf);

                        fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                            body: body.toString()
                        })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                if (todayOverlay) {
                                    var box = todayOverlay.querySelector('[data-today-orders-list]');
                                    if (box) box.style.pointerEvents = '';
                                }

                                if (!data || data.ok !== true) {
                                    var err = (data && data.error) ? String(data.error) : 'Nie udało się zadysponować.';
                                    alert('Błąd: ' + err);
                                    return;
                                }

                                if (todayOverlay) todayOverlay.classList.remove('is-open');
                                if (window.SWDTM && typeof window.SWDTM.showToast === 'function') {
                                    window.SWDTM.showToast('Zadysponowano ' + teamCode + ' do ' + String(orderNumber || ('#' + String(orderId || ''))) + '.', 'success');
                                }
                            })
                            .catch(function (e) {
                                if (todayOverlay) {
                                    var box = todayOverlay.querySelector('[data-today-orders-list]');
                                    if (box) box.style.pointerEvents = '';
                                }
                                alert('Błąd sieci: ' + (e && e.message ? e.message : 'Nieznany błąd'));
                            });
                    }

                    window.SWDTM = window.SWDTM || {};
                    window.SWDTM.dispatchToPreselectedTeam = dispatchToPreselectedTeam;

                    function fetchTeams() {
                        var url = window.location.pathname + '?ajax=1';
                        fetch(url, { credentials: 'same-origin' })
                            .then(function (r) { return r.json(); })
                            .then(function (data) {
                                if (!data || data.ok !== true || !Array.isArray(data.teams)) return;
                                var tbody = document.querySelector('table.table tbody');
                                if (!tbody) return;
                                while (tbody.firstChild) tbody.removeChild(tbody.firstChild);

                                data.teams.forEach(function (t) {
                                    if (!t) return;
                                    var tr = document.createElement('tr');

                                    function td(text, right) {
                                        var el = document.createElement('td');
                                        if (right) el.style.textAlign = 'right';
                                        el.textContent = text;
                                        return el;
                                    }

                                    var statusLabel = String(t.status_label || '');
                                    var statusText = statusLabel ? statusLabel : '—';

                                    function tdStatus(text, cls) {
                                        var el = document.createElement('td');
                                        var wrap = document.createElement('span');
                                        wrap.className = 'team-status' + String(cls || '');
                                        var dot = document.createElement('span');
                                        dot.className = 'status-dot';
                                        var label = document.createElement('span');
                                        label.textContent = String(text || '—');
                                        wrap.appendChild(dot);
                                        wrap.appendChild(label);
                                        el.appendChild(wrap);
                                        return el;
                                    }

                                    tr.appendChild(td(String(t.code || '')));
                                    tr.appendChild(td(String(t.type || '')));
                                    tr.appendChild(tdStatus(statusText, teamStatusColorClass(statusText)));
                                    tr.appendChild(td(String(t.leader_name || '—')));
                                    tr.appendChild(td(String(t.driver_name || '—')));
                                    tr.appendChild(td(String(t.last_seen_at || '—')));

                                    var actionTd = document.createElement('td');
                                    actionTd.style.textAlign = 'right';
                                    var b = document.createElement('button');
                                    b.className = 'btn-secondary';
                                    b.type = 'button';
                                    b.textContent = 'Zadysponuj do';
                                    b.setAttribute('data-open-today-orders', '');
                                    b.setAttribute('data-team-code', String(t.code || ''));
                                    b.setAttribute('data-team-type', String(t.type || ''));
                                    actionTd.appendChild(b);
                                    tr.appendChild(actionTd);

                                    tbody.appendChild(tr);
                                });

                                pulseDots(tbody);
                            })
                            .catch(function () {});
                    }

                    fetchTeams();
                    setInterval(fetchTeams, 5000);
                })();
            </script>

            <div class="modal-overlay" data-modal-overlay="today-orders" data-outside-close="1" data-esc-close="1" style="z-index:10080">
                <div class="modal" style="width:min(980px,96vw)">
                    <div class="modal-head">
                        <div>
                            <div class="modal-title">Zlecenia na dziś</div>
                            <div class="modal-text">Kolejność: natychmiast → pilne → zwykłe.</div>
                        </div>
                        <button class="toast-close" type="button" aria-label="Zamknij" data-modal-close>×</button>
                    </div>

                    <div class="field">
                        <span class="label">Lista</span>
                        <div class="input" style="height:auto;padding:10px 12px;max-height:min(62vh,560px);overflow:auto" data-today-orders-list></div>
                    </div>

                    <div class="modal-actions">
                        <button class="btn-primary" type="button" data-modal-close>Zamknij</button>
                    </div>
                </div>
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
                window.SWDTM = window.SWDTM || {};
                window.SWDTM.todayOrdersUrl = <?= json_encode(url('/api/dispatcher_today_orders.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                window.SWDTM.activeTeamsUrl = <?= json_encode(url('/api/active_teams.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                window.SWDTM.dispatchOrderUrl = <?= json_encode(url('/api/dispatch_order.php'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                window.SWDTM.csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
                window.SWDTM.preselectedTeamCode = '';
                window.SWDTM.preselectedTeamType = '';

                (function () {
                    function fmtOrderNumber(o) {
                        if (!o) return '';
                        var seq = (o.order_seq != null) ? String(o.order_seq) : '';
                        var mm = (o.order_month != null) ? String(o.order_month).padStart(2, '0') : '';
                        var yy = (o.order_year != null) ? String(o.order_year) : '';
                        if (seq && mm && yy) return seq + '/' + mm + '/' + yy;
                        return '#' + String(o.id || '');
                    }

                    function urgencyClass(u) {
                        var s = String(u || '').toLowerCase();
                        if (s === 'natychmiast') return ' status-red';
                        if (s === 'pilne') return ' status-yellow';
                        return ' status-green';
                    }

                    function renderTodayOrders(list) {
                        var overlay = document.querySelector('[data-modal-overlay="today-orders"]');
                        if (!overlay) return;
                        var box = overlay.querySelector('[data-today-orders-list]');
                        if (!box) return;
                        while (box.firstChild) box.removeChild(box.firstChild);

                        if (list && list._error) {
                            box.textContent = String(list._error || 'Błąd pobierania zleceń.');
                            return;
                        }

                        if (!Array.isArray(list) || !list.length) {
                            box.textContent = 'Brak wolnych zleceń na dziś.';
                            return;
                        }

                        list.forEach(function (o) {
                            var row = document.createElement('div');
                            row.className = 'team-row';
                            row.style.cursor = 'pointer';
                            row.dataset.orderId = String(o.id || '');
                            row.dataset.orderNumber = fmtOrderNumber(o);

                            var meta = document.createElement('div');
                            meta.className = 'team-meta';

                            var code = document.createElement('div');
                            code.className = 'team-code';
                            code.textContent = row.dataset.orderNumber + ' | ' + String(o.transport_type || '');

                            var type = document.createElement('div');
                            type.className = 'team-type';
                            var fromTxt = String(o.from || '—');
                            var toTxt = String(o.to || '—');
                            type.textContent = 'Skąd: ' + fromTxt + ' | Dokąd: ' + toTxt;

                            meta.appendChild(code);
                            meta.appendChild(type);

                            var badge = document.createElement('div');
                            badge.className = 'team-status' + urgencyClass(o.urgency);
                            var dot = document.createElement('span');
                            dot.className = 'status-dot is-pulse';
                            var label = document.createElement('span');
                            label.textContent = String(o.urgency || '');
                            badge.appendChild(dot);
                            badge.appendChild(label);

                            row.appendChild(meta);
                            row.appendChild(badge);

                            row.addEventListener('click', function () {
                                var id = row.dataset.orderId;
                                var number = row.dataset.orderNumber;
                                if (window.SWDTM && typeof window.SWDTM.dispatchToPreselectedTeam === 'function') {
                                    window.SWDTM.dispatchToPreselectedTeam(id, number, overlay);
                                }
                            });

                            box.appendChild(row);
                        });
                    }

                    function fetchTodayOrders() {
                        var url = (window.SWDTM && window.SWDTM.todayOrdersUrl) ? String(window.SWDTM.todayOrdersUrl) : '';
                        if (!url) return;
                        fetch(url + '?limit=80', { credentials: 'same-origin' })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                if (!data || data.ok !== true || !Array.isArray(data.orders)) {
                                    renderTodayOrders({ _error: 'Błąd pobierania zleceń.' });
                                    return;
                                }
                                renderTodayOrders(data.orders);
                            })
                            .catch(function () {
                                renderTodayOrders({ _error: 'Błąd sieci pobierania zleceń.' });
                            });
                    }

                    function teamTypeFromCode(code) {
                        var c = String(code || '').trim();
                        if (!c) return '';
                        var m = c.match(/^([TPS])/i);
                        return m ? String(m[1]).toUpperCase() : '';
                    }

                    function teamStatusColorClass(statusLabel) {
                        var s = String(statusLabel || '').trim().toLowerCase();
                        if (!s || s === 'aktywny') return '';
                        if (s === 'gotowy w bazie' || s === 'powrót do bazy') return ' status-green';
                        if (s === 'niegotowy' || s === 'przywracanie gotowości' || s === 'dezynfekcja' || s === 'mycie' || s === 'tankowanie') return ' status-yellow';
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
                        var st = t.status_label ? String(t.status_label) : 'Aktywny';
                        type.textContent = 'Status: ' + st;

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
                            var preCode = (window.SWDTM && window.SWDTM.preselectedTeamCode) ? String(window.SWDTM.preselectedTeamCode) : '';
                            var preType = (window.SWDTM && window.SWDTM.preselectedTeamType) ? String(window.SWDTM.preselectedTeamType) : '';
                            if (!preCode) {
                                tiles.textContent = 'Brak aktywnych zespołów.';
                                return;
                            }

                            var fallback = {
                                team_code: preCode,
                                status_label: '—'
                            };
                            var tile = makeTeamTile(fallback);
                            tile.dataset.teamType = preType || String(tile.dataset.teamType || '');
                            tiles.appendChild(tile);

                            bindDispatchTiles(overlay);
                            return;
                        }

                        teams.forEach(function (t) {
                            tiles.appendChild(makeTeamTile(t));
                        });

                        tiles.querySelectorAll('.status-dot').forEach(function (dot) {
                            dot.classList.remove('is-pulse');
                            void dot.offsetWidth;
                            dot.classList.add('is-pulse');
                            setTimeout(function () { dot.classList.remove('is-pulse'); }, 720);
                        });

                        bindDispatchTiles(overlay);
                    }

                    function bindDispatchTiles(overlay) {
                        if (!overlay) return;
                        var tiles = overlay.querySelector('[data-dispatch-team-tiles]');
                        if (!tiles) return;

                        var teamCode = overlay.querySelector('[data-dispatch-team-code]');
                        var teamType = overlay.querySelector('[data-dispatch-team-type]');
                        var submitBtn = overlay.querySelector('[data-dispatch-submit]');

                        function selectTile(b, forceCode, forceType) {
                            tiles.querySelectorAll('button').forEach(function (x) { x.style.outline = ''; });
                            b.style.outline = '2px solid rgba(59,130,246,.9)';

                            var c = forceCode != null ? String(forceCode || '') : String(b.dataset.teamCode || '');
                            var tt = forceType != null ? String(forceType || '') : String(b.dataset.teamType || '');
                            if (teamCode) teamCode.value = c;
                            if (teamType) teamType.value = tt;
                            if (submitBtn) submitBtn.disabled = !c;
                        }

                        tiles.querySelectorAll('button').forEach(function (b) {
                            if (b.dataset && b.dataset.dispatchBound === '1') return;
                            if (b.dataset) b.dataset.dispatchBound = '1';
                            b.addEventListener('click', function () {
                                selectTile(b);
                            });
                        });

                        var preCode = (window.SWDTM && window.SWDTM.preselectedTeamCode) ? String(window.SWDTM.preselectedTeamCode) : '';
                        var preType = (window.SWDTM && window.SWDTM.preselectedTeamType) ? String(window.SWDTM.preselectedTeamType) : '';
                        if (preCode) {
                            var found = null;
                            tiles.querySelectorAll('button').forEach(function (b) {
                                if (String(b.dataset.teamCode || '') === preCode) found = b;
                            });
                            if (found) {
                                selectTile(found, preCode, (preType || String(found.dataset.teamType || '')));
                            }
                        }
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

                        bindDispatchTiles(overlay);

                        overlay.classList.add('is-open');
                    }

                    window.SWDTM = window.SWDTM || {};
                    window.SWDTM.openDispatchModal = openDispatchModal;

                    function fetchActiveTeams() {
                        var url = (window.SWDTM && window.SWDTM.activeTeamsUrl) ? String(window.SWDTM.activeTeamsUrl) : '';
                        if (!url) return;
                        fetch(url, { credentials: 'same-origin' })
                            .then(function (r) { return r && r.ok === true ? r.json() : null; })
                            .then(function (data) {
                                if (!data || data.ok !== true || !Array.isArray(data.teams)) return;
                                window.SWDTM.activeTeamsCache = data.teams;
                                var overlay = document.querySelector('[data-modal-overlay="dispatch"]');
                                if (overlay && overlay.classList.contains('is-open')) renderDispatchTeamTiles();
                            })
                            .catch(function () {});
                    }

                    function openTodayOrdersModal() {
                        var overlay = document.querySelector('[data-modal-overlay="today-orders"]');
                        if (!overlay) return;
                        overlay.classList.add('is-open');
                        fetchTodayOrders();
                    }

                    document.addEventListener('click', function (e) {
                        var t = e.target;
                        if (!t || !t.closest) return;
                        var btn = t.closest('[data-open-today-orders]');
                        if (!btn) return;
                        window.SWDTM = window.SWDTM || {};
                        window.SWDTM.preselectedTeamCode = String(btn.getAttribute('data-team-code') || '');
                        window.SWDTM.preselectedTeamType = String(btn.getAttribute('data-team-type') || '');
                        openTodayOrdersModal();
                    });

                    fetchActiveTeams();
                    setInterval(fetchActiveTeams, 5000);
                })();
            </script>
        </main>
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
