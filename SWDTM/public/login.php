<?php

declare(strict_types=1);

require __DIR__ . '/../src/auth.php';

if (is_logged_in()) {
    redirect_after_login();
}

$flash = $_GET['e'] ?? '';
$flash = is_string($flash) ? $flash : '';

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Logowanie</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css?v=' . @filemtime(__DIR__ . '/assets/app.css')), ENT_QUOTES, 'UTF-8') ?>">
    <script defer src="<?= htmlspecialchars(url('/assets/app.js?v=' . @filemtime(__DIR__ . '/assets/app.js')), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body>
    <main class="auth">
        <div class="auth-card">
            <div class="auth-brand">
                <div class="brand-mark"></div>
                <div>
                    <div class="brand-title">SWDTM</div>
                    <div class="brand-subtitle">Logowanie do systemu</div>
                </div>
            </div>
            <h1 class="auth-title">Zaloguj się</h1>

            <?php if ($flash === 'invalid'): ?>
                <div class="alert">Nieprawidłowy login lub hasło.</div>
            <?php endif; ?>

            <form class="form" method="post" action="<?= htmlspecialchars(url('/login_post'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <label class="field">
                    <span class="label">Login</span>
                    <input class="input" type="text" name="username" inputmode="text" autocomplete="username" required autofocus>
                </label>

                <label class="field">
                    <span class="label">Hasło</span>
                    <input class="input" type="password" name="password" autocomplete="current-password" required>
                </label>

                <button class="btn" type="submit">Zaloguj</button>
            </form>
        </div>
    </main>
</body>
</html>
