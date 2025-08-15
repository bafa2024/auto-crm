<?php
/**
 * Email Queue Processor
 * Run this script periodically (e.g., every minute via cron/scheduled task)
 * to process pending emails in the queue
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from command line");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailQueueService.php';

echo "Email Queue Processor - " . date('Y-m-d H:i:s') . "\n";
echo "=========================================\n";

try {
    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    // Initialize queue service
    $queueService = new EmailQueueService($db);
    
    // Get queue statistics
    $stats = $queueService->getQueueStats();
    if ($stats) {
        echo "Queue Status:\n";
        echo "  Pending: " . $stats['pending'] . "\n";
        echo "  Sending: " . $stats['sending'] . "\n";
        echo "  Sent: " . $stats['sent'] . "\n";
        echo "  Failed: " . $stats['failed'] . "\n";
        echo "\n";
    }
    
    // Process queue
    if ($stats['pending'] > 0) {
        echo "Processing email queue...\n";
        $result = $queueService->processQueue(20); // Process up to 20 emails
        
        echo "Results:\n";
        echo "  Processed: " . $result['processed'] . "\n";
        echo "  Sent: " . $result['sent'] . "\n";
        echo "  Failed: " . $result['failed'] . "\n";
        
        if (!empty($result['details'])) {
            echo "\nDetails:\n";
            foreach ($result['details'] as $detail) {
                echo "  " . $detail . "\n";
            }
        }
    } else {
        echo "No pending emails in queue.\n";
    }
    
    // Retry failed emails (once per hour)
    if (date('i') == '00') {
        echo "\nRetrying failed emails...\n";
        $retried = $queueService->retryFailed();
        echo "  Retried: " . $retried . " emails\n";
    }
    
    // Clean old sent emails (once per day at midnight)
    if (date('H:i') == '00:00') {
        echo "\nCleaning old sent emails...\n";
        $cleaned = $queueService->clearOldSentEmails(7); // Keep for 7 days
        echo "  Cleaned: " . $cleaned . " old emails\n";
    }
    
    echo "\nProcess completed.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    error_log("Email queue processor error: " . $e->getMessage());
    exit(1);
}
?>