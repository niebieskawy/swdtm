<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require __DIR__ . '/../../src/db.php';

require_role(['admin']);

$pdo = db();

$me = current_user();
$myId = is_array($me) && isset($me['id']) ? (int)$me['id'] : 0;

$id = $_GET['id'] ?? null;
$id = is_string($id) ? (int)$id : (is_int($id) ? $id : 0);

$stmt = $pdo->prepare('SELECT id, username, full_name, role, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: ' . url('/admin/users'));
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
            if ((int)$user['id'] === $myId) {
                $error = 'Nie możesz usunąć aktualnie zalogowanego konta.';
            } else {
                if ((string)$user['role'] === 'admin') {
                    $adminsCount = (int)$pdo->query("SELECT COUNT(*) AS c FROM users WHERE role = 'admin'")->fetchColumn();
                    if ($adminsCount <= 1) {
                        $error = 'Nie można usunąć ostatniego konta administratora.';
                    }
                }

                if ($error === '') {
                    $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => (int)$user['id']]);
                    flash_set('users', 'Usunięto konto użytkownika: ' . (string)$user['username']);
                    header('Location: ' . url('/admin/users'));
                    exit;
                }
            }
            // delete zakończone błędem walidacji: pokaż toast z $error i nie rób edycji
        } else {
            $username = $_POST['username'] ?? '';
            $fullName = $_POST['full_name'] ?? '';
            $role = $_POST['role'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';

            $username = is_string($username) ? trim($username) : '';
            $fullName = is_string($fullName) ? trim($fullName) : '';
            $role = is_string($role) ? $role : '';
            $newPassword = is_string($newPassword) ? $newPassword : '';

            $allowedRoles = ['admin', 'dispatcher', 'team'];

            if ($username === '' || $fullName === '' || !in_array($role, $allowedRoles, true)) {
                $error = 'Uzupełnij poprawnie wszystkie pola.';
            } elseif (mb_strlen($username) > 50) {
                $error = 'Login jest za długi.';
            } elseif (mb_strlen($fullName) > 100) {
                $error = 'Imię i nazwisko jest za długie.';
            } else {
                $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1');
                $stmt->execute([':u' => $username, ':id' => $id]);
                $exists = $stmt->fetch();

                if ($exists) {
                    $error = 'Taki login już istnieje.';
                } else {
                    $pdo->prepare('UPDATE users SET username = :u, full_name = :f, role = :r WHERE id = :id')
                        ->execute([':u' => $username, ':f' => $fullName, ':r' => $role, ':id' => $id]);

                    if ($newPassword !== '') {
                        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                            ->execute([':h' => $hash, ':id' => $id]);
                    }

                    $stmt = $pdo->prepare('SELECT id, username, full_name, role, last_login_at, created_at FROM users WHERE id = :id LIMIT 1');
                    $stmt->execute([':id' => $id]);
                    $user = $stmt->fetch();

                    $ok = 'Zmiany zapisane.';
                    flash_set('users', 'Zapisano zmiany użytkownika: ' . (string)$user['username']);
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
    <title>SWDTM - Edytuj użytkownika</title>
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
                    <div class="page-title">Edytuj użytkownika</div>
                    <div class="page-subtitle"><?= htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8') ?></div>
                </div>
                <a class="btn-secondary" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Wróć</a>
            </header>

            <div class="panel">
                <div class="panel-body">
                    <form class="form" method="post" action="<?= htmlspecialchars(url('/admin/users/edit?id=' . (int)$user['id']), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                        <label class="field">
                            <span class="label">Login</span>
                            <input class="input" type="text" name="username" value="<?= htmlspecialchars((string)$user['username'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>

                        <label class="field">
                            <span class="label">Imię i nazwisko</span>
                            <input class="input" type="text" name="full_name" value="<?= htmlspecialchars((string)($user['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
                        </label>

                        <label class="field">
                            <span class="label">Rola</span>
                            <select class="input" name="role" required>
                                <option value="team" <?= ((string)$user['role'] === 'team') ? 'selected' : '' ?>>Ratownik/kierowca</option>
                                <option value="dispatcher" <?= ((string)$user['role'] === 'dispatcher') ? 'selected' : '' ?>>Dyspozytor</option>
                                <option value="admin" <?= ((string)$user['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </label>

                        <label class="field">
                            <span class="label">Nowe hasło (opcjonalnie)</span>
                            <input class="input" type="password" name="new_password" value="">
                        </label>

                        <button class="btn-primary" type="submit">Zapisz</button>
                    </form>

                    <div style="height:14px"></div>

                    <form id="delete-user-form" method="post" action="<?= htmlspecialchars(url('/admin/users/edit?id=' . (int)$user['id']), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="btn-danger" type="button" data-modal-open="delete-user">Usuń konto</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <div class="modal-overlay" data-modal-overlay="delete-user">
        <div class="modal" role="dialog" aria-modal="true" aria-label="Potwierdzenie usunięcia">
            <div class="modal-head">
                <div>
                    <div class="modal-title">Usunąć konto użytkownika?</div>
                    <div class="modal-text">Ta operacja jest nieodwracalna. Konto zostanie trwale usunięte z systemu.</div>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-secondary" type="button" data-modal-close>Anuluj</button>
                <button class="btn-danger" type="button" data-modal-confirm-submit="delete-user-form">Usuń</button>
            </div>
        </div>
    </div>
</body>
</html>
