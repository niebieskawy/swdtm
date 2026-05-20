<?php

declare(strict_types=1);

require __DIR__ . '/../src/auth.php';

if (is_logged_in()) {
    redirect_after_login();
}

header('Location: ' . url('/login'));
exit;
