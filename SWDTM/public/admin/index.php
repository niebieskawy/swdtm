<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require_role(['admin']);

$user = current_user();

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Panel administratora</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(url('/assets/app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
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
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Użytkownicy</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Klienci</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/reports'), ENT_QUOTES, 'UTF-8') ?>">Raporty</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/audit'), ENT_QUOTES, 'UTF-8') ?>">Audyt</a>
            </nav>

            <div class="sidebar-footer">
                <div class="userchip">
                    <div class="avatar"><?= htmlspecialchars(mb_strtoupper(mb_substr((string)($user['username'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8') ?></div>
                    <div>
                        <div class="userchip-name"><?= htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="userchip-role"><?= htmlspecialchars((string)($user['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                </div>
                <a class="link" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <div class="page-title">Dashboard</div>
                    <div class="page-subtitle">Przegląd systemu</div>
                </div>
            </header>

            <section class="grid">
                <div class="card">
                    <div class="card-title">Użytkownicy</div>
                    <div class="card-value">—</div>
                    <div class="card-hint">W przygotowaniu</div>
                </div>
                <div class="card">
                    <div class="card-title">Zespoły</div>
                    <div class="card-value">—</div>
                    <div class="card-hint">W przygotowaniu</div>
                </div>
                <div class="card">
                    <div class="card-title">Zlecenia</div>
                    <div class="card-value">—</div>
                    <div class="card-hint">W przygotowaniu</div>
                </div>
                <div class="card">
                    <div class="card-title">Średni czas realizacji</div>
                    <div class="card-value">—</div>
                    <div class="card-hint">W przygotowaniu</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <div class="panel-title">Szybkie akcje</div>
                </div>
                <div class="panel-body actions">
                    <a class="action" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Zarządzaj użytkownikami</a>
                    <a class="action" href="<?= htmlspecialchars(url('/admin/teams'), ENT_QUOTES, 'UTF-8') ?>">Zarządzaj zespołami</a>
                    <a class="action" href="<?= htmlspecialchars(url('/admin/reports'), ENT_QUOTES, 'UTF-8') ?>">Zobacz raporty</a>
                    <a class="action" href="<?= htmlspecialchars(url('/admin/audit'), ENT_QUOTES, 'UTF-8') ?>">Przeglądaj audyt</a>
                </div>
            </section>
        </main>
    </div>
</body>
</html>
