#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Service Reminder Cron Job - CLI Entry Point
 * 
 * This script should be run daily (e.g., via cron job at 8:00 AM):
 * 0 8 * * * /usr/bin/php /path/to/cron/send_service_reminders.php
 * 
 * For web-based execution, use trigger.php instead:
 * curl "https://your-domain.com/cron/trigger.php?key=YOUR_SECRET_KEY"
 */

// Security: Only allow CLI execution
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from command line. Use trigger.php for web-based execution.');
}

require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/reminder_functions.php';

// Run the reminder check
try {
    run_service_reminders();
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
