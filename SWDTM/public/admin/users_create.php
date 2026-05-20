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
        $username = $_POST['username'] ?? '';
        $fullName = $_POST['full_name'] ?? '';
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? '';

        $username = is_string($username) ? trim($username) : '';
        $fullName = is_string($fullName) ? trim($fullName) : '';
        $password = is_string($password) ? $password : '';
        $role = is_string($role) ? $role : '';

        $allowedRoles = ['admin', 'dispatcher', 'team'];

        if ($username === '' || $fullName === '' || $password === '' || !in_array($role, $allowedRoles, true)) {
            $error = 'Uzupełnij poprawnie wszystkie pola.';
        } elseif (mb_strlen($username) > 50) {
            $error = 'Login jest za długi.';
        } elseif (mb_strlen($fullName) > 100) {
            $error = 'Imię i nazwisko jest za długie.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            $exists = $stmt->fetch();

            if ($exists) {
                $error = 'Taki login już istnieje.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare('INSERT INTO users (username, full_name, password_hash, role) VALUES (:u, :f, :h, :r)')
                    ->execute([':u' => $username, ':f' => $fullName, ':h' => $hash, ':r' => $role]);

                flash_set('users', 'Utworzono konto dla nowego użytkownika: ' . $username);
                header('Location: ' . url('/admin/users'));
                exit;
            }
        }
    }
}

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Dodaj użytkownika</title>
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
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Użytkownicy</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Klienci</a>
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
                    <div class="page-title">Dodaj użytkownika</div>
                    <div class="page-subtitle">Utworzenie nowego konta</div>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Anuluj</a>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form class="form" method="post" action="<?= htmlspecialchars(url('/admin/users/create'), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="field">
                            <span class="label">Login</span>
                            <input class="input" type="text" name="username" required>
                        </label>

                        <label class="field">
                            <span class="label">Imię i nazwisko</span>
                            <input class="input" type="text" name="full_name" required>
                        </label>

                        <label class="field">
                            <span class="label">Hasło</span>
                            <input class="input" type="password" name="password" required>
                        </label>

                        <label class="field">
                            <span class="label">Rola</span>
                            <select class="input" name="role" required>
                                <option value="team">Ratownik/kierowca</option>
                                <option value="dispatcher">Dyspozytor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </label>

                        <button class="btn-primary" type="submit">Zapisz</button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
