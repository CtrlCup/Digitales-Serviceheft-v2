<?php
declare(strict_types=1);

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

/**
 * Separate connection for vehicles database.
 * Uses VEH_DB_* constants when available; otherwise falls back to primary DB settings.
 */
function vehicle_db(): PDO {
    static $vpdo = null;
    if ($vpdo instanceof PDO) {
        return $vpdo;
    }

    $host = defined('VEH_DB_HOST') ? VEH_DB_HOST : (defined('DB_HOST') ? DB_HOST : 'localhost');
    $port = defined('VEH_DB_PORT') ? VEH_DB_PORT : (defined('DB_PORT') ? DB_PORT : '3306');
    $name = defined('VEH_DB_NAME') ? VEH_DB_NAME : (defined('DB_NAME') ? DB_NAME : '');
    $user = defined('VEH_DB_USER') ? VEH_DB_USER : (defined('DB_USER') ? DB_USER : '');
    $pass = defined('VEH_DB_PASS') ? VEH_DB_PASS : (defined('DB_PASS') ? DB_PASS : '');

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $vpdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $vpdo;
}

/**
 * Separate connection for service entries database.
 * Prefers SRV_DB_* constants; falls back to VEH_DB_*; then to primary DB_*.
 */
function service_db(): PDO {
    static $spdo = null;
    if ($spdo instanceof PDO) {
        return $spdo;
    }

    // Prefer explicit service DB constants if defined
    $host = defined('SRV_DB_HOST') ? SRV_DB_HOST : (defined('VEH_DB_HOST') ? VEH_DB_HOST : (defined('DB_HOST') ? DB_HOST : 'localhost'));
    $port = defined('SRV_DB_PORT') ? SRV_DB_PORT : (defined('VEH_DB_PORT') ? VEH_DB_PORT : (defined('DB_PORT') ? DB_PORT : '3306'));
    $name = defined('SRV_DB_NAME') ? SRV_DB_NAME : (defined('VEH_DB_NAME') ? VEH_DB_NAME : (defined('DB_NAME') ? DB_NAME : ''));
    $user = defined('SRV_DB_USER') ? SRV_DB_USER : (defined('VEH_DB_USER') ? VEH_DB_USER : (defined('DB_USER') ? DB_USER : ''));
    $pass = defined('SRV_DB_PASS') ? SRV_DB_PASS : (defined('VEH_DB_PASS') ? VEH_DB_PASS : (defined('DB_PASS') ? DB_PASS : ''));

    $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8mb4';
    $spdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $spdo;
}
