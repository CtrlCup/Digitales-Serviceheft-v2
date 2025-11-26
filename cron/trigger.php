<?php
declare(strict_types=1);

/**
 * Web-based Cron Trigger for Service Reminders
 * 
 * This file allows running the service reminder cron job via HTTP request
 * for webhosts that don't support CLI cron jobs.
 * 
 * SECURITY:
 * - Uses a secret key defined in config.php
 * - Optional IP whitelist
 * - Prevents concurrent execution
 * 
 * SETUP:
 * 1. Add to src/config.php:
 *    define('CRON_SECRET_KEY', 'your-random-secret-key-here');
 * 
 * 2. Setup external cron service (e.g., cron-job.org, easycron.com):
 *    URL: https://your-domain.com/cron/trigger.php?key=your-random-secret-key-here
 *    Schedule: Daily at 8:00 AM
 * 
 * 3. Or use your webhost's cron panel:
 *    Command: curl -s "https://your-domain.com/cron/trigger.php?key=your-secret"
 *    Or: wget -q -O- "https://your-domain.com/cron/trigger.php?key=your-secret"
 */

require_once __DIR__ . '/../src/config.php';

// Security: Check if CRON_SECRET_KEY is defined
if (!defined('CRON_SECRET_KEY') || CRON_SECRET_KEY === '') {
    http_response_code(500);
    die('ERROR: CRON_SECRET_KEY not configured. Please add it to src/config.php');
}

// Security: Validate secret key
$providedKey = $_GET['key'] ?? '';
if (!hash_equals(CRON_SECRET_KEY, $providedKey)) {
    http_response_code(403);
    die('Forbidden: Invalid key');
}

// Optional: IP whitelist (uncomment and configure if needed)
/*
$allowedIPs = [
    '127.0.0.1',
    '::1',
    // Add IPs of your cron service provider here
];
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIP, $allowedIPs, true)) {
    http_response_code(403);
    die('Forbidden: IP not whitelisted');
}
*/

// Prevent concurrent execution
$lockFile = __DIR__ . '/reminder.lock';
$fp = fopen($lockFile, 'c+');
if (!$fp || !flock($fp, LOCK_EX | LOCK_NB)) {
    http_response_code(429);
    die('Cron job already running');
}

// Set execution time limit (5 minutes max)
set_time_limit(300);

// Capture output
ob_start();

try {
    // Load bootstrap and reminder functions
    require_once __DIR__ . '/../src/bootstrap.php';
    require_once __DIR__ . '/reminder_functions.php';
    
    // Execute the reminder check
    run_service_reminders();
    
    $output = ob_get_clean();
    
    // Log to file
    $logFile = __DIR__ . '/reminder.log';
    file_put_contents($logFile, $output, FILE_APPEND);
    
    // Return output
    header('Content-Type: text/plain; charset=utf-8');
    echo $output;
    
} catch (Throwable $e) {
    $output = ob_get_clean();
    $error = "ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
    
    // Log error
    $logFile = __DIR__ . '/reminder.log';
    file_put_contents($logFile, "[ERROR] " . date('Y-m-d H:i:s') . "\n" . $error . "\n\n", FILE_APPEND);
    
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $error;
}

// Release lock
flock($fp, LOCK_UN);
fclose($fp);
@unlink($lockFile);
