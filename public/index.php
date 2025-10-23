<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}
header('Location: /login.php');
exit;
