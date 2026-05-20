<?php

declare(strict_types=1);

require __DIR__ . '/../src/auth.php';

logout();
header('Location: ' . url('/login'));
exit;
