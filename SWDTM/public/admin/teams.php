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
        $code = $_POST['code'] ?? '';
        $type = $_POST['type'] ?? '';
        $name = $_POST['name'] ?? '';

        $code = is_string($code) ? trim($code) : '';
        $type = is_string($type) ? trim($type) : '';
        $name = is_string($name) ? trim($name) : '';

        $allowedTypes = ['T', 'P', 'S'];
        $active = 1;

        if ($code === '' || !preg_match('/^[A-Za-z0-9\-]{2,20}$/', $code) || !in_array($type, $allowedTypes, true)) {
            $error = 'Uzupełnij poprawnie kod i typ zespołu.';
        } elseif (mb_strlen($name) > 120) {
            $error = 'Nazwa jest za długa.';
        } else {
            try {
                $stmt = $pdo->prepare('INSERT INTO teams (code, type, name, is_active) VALUES (:c, :t, :n, :a)');
                $stmt->execute([
                    ':c' => $code,
                    ':t' => $type,
                    ':n' => ($name !== '' ? $name : null),
                    ':a' => $active,
                ]);

                flash_set('teams', 'Dodano zespół: ' . $code);
                header('Location: ' . url('/admin/teams'));
                exit;
            } catch (Throwable $e) {
                $error = 'Nie udało się dodać zespołu (sprawdź czy kod nie istnieje).';
            }
        }
    }
}

$teams = [];
try {
    $teams = $pdo->query('SELECT id, code, type, name, created_at FROM teams ORDER BY code ASC')->fetchAll();
} catch (Throwable $e) {
    $teams = [];
}

$flash = flash_get('teams');
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
    <title>SWDTM - Zespoły</title>
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
                    <div class="sidebar-subtitle">Administrator</div>
                </div>
            </div>

            <nav class="nav">
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Użytkownicy</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Klienci</a>
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/admin/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
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
                    <div class="page-title">Zespoły</div>
                    <div class="page-subtitle">Zarządzanie zespołami</div>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Wróć</a>
            </header>

            <section class="panel" style="margin-bottom:14px">
                <div class="panel-head">
                    <div class="panel-title">Dodaj zespół</div>
                </div>
                <div class="panel-body">
                    <form class="order-form" method="post" action="<?= htmlspecialchars(url('/admin/teams'), ENT_QUOTES, 'UTF-8') ?>" style="grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="field">
                            <span class="label">Kod</span>
                            <input class="input" type="text" name="code" placeholder="np. ZRM-TEST" required>
                        </label>

                        <label class="field">
                            <span class="label">Typ</span>
                            <select class="input" name="type" required>
                                <option value="P" selected>P</option>
                                <option value="S">S</option>
                                <option value="T">T</option>
                            </select>
                        </label>

                        <label class="field" style="grid-column:span 2">
                            <span class="label">Nazwa (opcjonalnie)</span>
                            <input class="input" type="text" name="name" placeholder="np. Zespół testowy">
                        </label>

                        <div class="field" style="display:flex;justify-content:flex-end;align-items:end;grid-column:span 2">
                            <button class="btn-primary" type="submit">Dodaj</button>
                        </div>
                    </form>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Lista zespołów</div>
                </div>
                <div class="panel-body">
                    <?php if (!$teams): ?>
                        Brak zespołów w bazie.
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Kod</th>
                                    <th>Typ</th>
                                    <th>Nazwa</th>
                                    <th>Utworzono</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teams as $t): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$t['code'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)$t['type'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($t['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string)($t['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
