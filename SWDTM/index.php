<?php

declare(strict_types=1);

$script = $_SERVER['SCRIPT_NAME'] ?? '';
$script = is_string($script) ? $script : '';

$base = rtrim(str_replace('\\', '/', dirname($script)), '/');
$target = $base . '/public/index.php';

header('Location: ' . $target);
exit;
