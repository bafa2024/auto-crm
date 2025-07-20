<?php
// process_scheduled_campaigns.php - Cron job for processing scheduled campaigns
// This script should be run every minute via cron job

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set time limit for long-running processes
set_time_limit(300); // 5 minutes

echo "=== Scheduled Campaigns Processor ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // Include required files
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../services/ScheduledCampaignService.php';
    require_once __DIR__ . '/../services/EmailService.php';
    
    // Initialize database
    $database = new Database();
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    // Initialize scheduled campaign service
    $scheduledService = new ScheduledCampaignService($database);
    
    // Process scheduled campaigns
    echo "Processing scheduled campaigns...\n";
    $result = $scheduledService->processScheduledCampaigns();
    
    if ($result['success']) {
        echo "✅ Processing completed successfully!\n";
        echo "Campaigns processed: {$result['processed']}\n";
        echo "Emails sent: {$result['sent']}\n";
        
        if (!empty($result['errors'])) {
            echo "Errors encountered:\n";
            foreach ($result['errors'] as $error) {
                echo "- $error\n";
            }
        }
    } else {
        echo "❌ Processing failed: {$result['message']}\n";
    }
    
    // Get scheduled campaigns for display
    echo "\nUpcoming scheduled campaigns:\n";
    $scheduledCampaigns = $scheduledService->getScheduledCampaigns();
    
    if (empty($scheduledCampaigns)) {
        echo "No scheduled campaigns found.\n";
    } else {
        foreach ($scheduledCampaigns as $campaign) {
            $scheduleDate = new DateTime($campaign['schedule_date']);
            $now = new DateTime();
            $timeUntil = $scheduleDate->diff($now);
            
            echo "- Campaign: {$campaign['name']}\n";
            echo "  Schedule: {$campaign['schedule_date']}\n";
            echo "  Type: {$campaign['schedule_type']}\n";
            if ($campaign['frequency']) {
                echo "  Frequency: {$campaign['frequency']}\n";
            }
            echo "  Time until: {$timeUntil->format('%d days, %h hours, %i minutes')}\n\n";
        }
    }
    
    echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
    echo "=== End of Processing ===\n";
    
} catch (Exception $e) {
    echo "❌ Critical error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
?> 