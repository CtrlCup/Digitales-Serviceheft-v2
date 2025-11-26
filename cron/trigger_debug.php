<?php
// Debug version - shows detailed errors
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "Step 1: Script started\n";

try {
    echo "Step 2: Loading config...\n";
    require_once __DIR__ . '/../src/config.php';
    echo "Step 3: Config loaded\n";
    
    echo "Step 4: Checking CRON_SECRET_KEY...\n";
    if (!defined('CRON_SECRET_KEY')) {
        die("ERROR: CRON_SECRET_KEY is not defined in config.php\n");
    }
    echo "Step 5: CRON_SECRET_KEY is defined\n";
    
    $providedKey = $_GET['key'] ?? '';
    echo "Step 6: Provided key length: " . strlen($providedKey) . "\n";
    echo "Step 7: Expected key length: " . strlen(CRON_SECRET_KEY) . "\n";
    
    if ($providedKey === '') {
        die("ERROR: No key provided in URL\n");
    }
    
    if (!hash_equals(CRON_SECRET_KEY, $providedKey)) {
        die("ERROR: Key mismatch\n");
    }
    
    echo "Step 8: Key validated successfully\n";
    
    echo "Step 9: Checking if send_service_reminders.php exists...\n";
    $scriptPath = __DIR__ . '/send_service_reminders.php';
    if (!file_exists($scriptPath)) {
        die("ERROR: send_service_reminders.php not found at: $scriptPath\n");
    }
    echo "Step 10: Script file exists\n";
    
    echo "Step 11: Defining CRON_TRIGGER_AUTHORIZED...\n";
    define('CRON_TRIGGER_AUTHORIZED', true);
    
    echo "Step 12: Including send_service_reminders.php...\n";
    require $scriptPath;
    
    echo "Step 13: Script completed successfully\n";
    
} catch (Throwable $e) {
    echo "\n=== ERROR ===\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
