<?php

declare(strict_types=1);

require __DIR__ . '/../../src/auth.php';
require_role(['admin']);

?><!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SWDTM - Raporty</title>
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
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin'), ENT_QUOTES, 'UTF-8') ?>">Dashboard</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/users'), ENT_QUOTES, 'UTF-8') ?>">Użytkownicy</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/clients'), ENT_QUOTES, 'UTF-8') ?>">Klienci</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/teams'), ENT_QUOTES, 'UTF-8') ?>">Zespoły</a>
                <a class="nav-item is-active" href="<?= htmlspecialchars(url('/admin/reports'), ENT_QUOTES, 'UTF-8') ?>">Raporty</a>
                <a class="nav-item" href="<?= htmlspecialchars(url('/admin/audit'), ENT_QUOTES, 'UTF-8') ?>">Audyt</a>
            </nav>

            <div class="sidebar-footer">
                <a class="link" href="<?= htmlspecialchars(url('/logout'), ENT_QUOTES, 'UTF-8') ?>">Wyloguj</a>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <div>
                    <div class="page-title">Raporty</div>
                    <div class="page-subtitle">Zestawienia i eksport</div>
                </div>
                <a class="btn-primary" href="#">Eksport</a>
            </header>

            <div class="panel">
                <div class="panel-body">Widok w przygotowaniu.</div>
            </div>
        </main>
    </div>
</body>
</html>
