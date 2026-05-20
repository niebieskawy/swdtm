<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['admin']);

$pdo = db();

$q = $_GET['q'] ?? '';
$q = is_string($q) ? trim($q) : '';

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(username LIKE :q OR client_name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$sql = 'SELECT id, client_name, username, facility_address, created_at FROM clients';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY id DESC';

$flash = flash_get('clients');

$flashMessage = '';
$flashType = '';
if (is_array($flash)) {
    $flashMessage = isset($flash['message']) && is_string($flash['message']) ? $flash['message'] : '';
    $flashType = isset($flash['type']) && is_string($flash['type']) ? $flash['type'] : 'notice';
}

$clients = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();
} catch (Throwable $e) {
    $clients = [];
    if ($flashMessage === '') {
        $flashMessage = 'Brak tabeli klientów w bazie (clients). Dodaj tabelę i odśwież.';
        $flashType = 'alert';
    }
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Klienci</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(url('/assets/app.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body>
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
                    <div class="page-title">Klienci</div>
                    <div class="page-subtitle">Lista klientów</div>
                </div>
                <div class="topbar-actions">
                    <form method="get" action="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>" data-autosubmit data-debounce="300" autocomplete="off" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end">
                        <input class="input" type="text" name="q" placeholder="Znajdź klienta" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="btn-secondary" type="submit">Szukaj</button>
                    </form>
                    <a class="btn-primary" href="<?= htmlspecialchars(url('/admin/clients/create'), ENT_QUOTES, 'UTF-8') ?>">Dodaj klienta</a>
                </div>
            </header>

            <div class="panel">
                <div class="panel-body" style="overflow:auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nazwa</th>
                                <th>Login</th>
                                <th>Adres placówki</th>
                                <th>Utworzono</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $c): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string)($c['client_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)$c['username'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($c['facility_address'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string)($c['created_at'] ?? '---'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><a class="btn-secondary" href="<?= htmlspecialchars(url('/admin/clients/edit?id=' . (int)$c['id']), ENT_QUOTES, 'UTF-8') ?>">Edytuj</a></td>
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
