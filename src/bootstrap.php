<?php
declare(strict_types=1);

session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'cookie_samesite' => 'Lax',
]);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/helpers.php';

// Load default locale first
load_locale(APP_LOCALE);

// If a user is logged in and has a locale preference, override
try {
    if (!empty($_SESSION['user_id'])) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT locale FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([ (int)$_SESSION['user_id'] ]);
        $row = $stmt->fetch();
        if ($row && !empty($row['locale'])) {
            load_locale((string)$row['locale']);
        }
    }
} catch (Throwable $e) {
    // fallback silently to default
}
