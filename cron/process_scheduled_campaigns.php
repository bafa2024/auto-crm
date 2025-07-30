<?php
/**
 * Cron job script to process scheduled email campaigns
 * Run this every minute: * * * * * /usr/bin/php /path/to/cron/process_scheduled_campaigns.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/cron_errors.log');

// Start execution time tracking
$startTime = microtime(true);

// Log start
error_log("[" . date('Y-m-d H:i:s') . "] Starting scheduled campaigns processing");

try {
    // Include required files
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../services/EmailCampaignService.php';
    require_once __DIR__ . '/../services/ScheduledCampaignService.php';
    
    // Initialize database
    $database = new Database();
    $db = $database->getConnection();
    
    // Initialize services
    $emailCampaignService = new EmailCampaignService($database);
    $scheduledCampaignService = new ScheduledCampaignService($database);
    
    // Get campaigns that are scheduled and ready to send
    $scheduledCampaigns = $scheduledCampaignService->getReadyToSendCampaigns();
    
    if (empty($scheduledCampaigns)) {
        error_log("[" . date('Y-m-d H:i:s') . "] No campaigns ready to send");
        exit(0);
    }
    
    error_log("[" . date('Y-m-d H:i:s') . "] Found " . count($scheduledCampaigns) . " campaigns ready to send");
    
    $processedCount = 0;
    $errorCount = 0;
    
    foreach ($scheduledCampaigns as $campaign) {
        try {
            error_log("[" . date('Y-m-d H:i:s') . "] Processing campaign ID: {$campaign['id']} - {$campaign['name']}");
            
            // Update campaign status to sending
            $scheduledCampaignService->updateCampaignStatus($campaign['id'], 'sending');
            
            // Get all recipients for this campaign
            $recipients = $emailCampaignService->getAllCampaignRecipients($campaign['id']);
            
            if (empty($recipients)) {
                error_log("[" . date('Y-m-d H:i:s') . "] No recipients found for campaign {$campaign['id']}");
                $scheduledCampaignService->updateCampaignStatus($campaign['id'], 'completed');
                continue;
            }
            
            error_log("[" . date('Y-m-d H:i:s') . "] Found " . count($recipients) . " recipients for campaign {$campaign['id']}");
            
            // Send campaign to all recipients
            $result = $emailCampaignService->sendCampaignToAll($campaign['id']);
            
            if ($result['success']) {
                error_log("[" . date('Y-m-d H:i:s') . "] Successfully initiated sending for campaign {$campaign['id']}");
                $processedCount++;
            } else {
                error_log("[" . date('Y-m-d H:i:s') . "] Failed to send campaign {$campaign['id']}: " . $result['message']);
                $scheduledCampaignService->updateCampaignStatus($campaign['id'], 'failed');
                $errorCount++;
            }
            
        } catch (Exception $e) {
            error_log("[" . date('Y-m-d H:i:s') . "] Error processing campaign {$campaign['id']}: " . $e->getMessage());
            $scheduledCampaignService->updateCampaignStatus($campaign['id'], 'failed');
            $errorCount++;
        }
    }
    
    $executionTime = round(microtime(true) - $startTime, 2);
    error_log("[" . date('Y-m-d H:i:s') . "] Completed processing. Processed: $processedCount, Errors: $errorCount, Time: {$executionTime}s");
    
} catch (Exception $e) {
    error_log("[" . date('Y-m-d H:i:s') . "] Critical error in scheduled campaigns processing: " . $e->getMessage());
    exit(1);
}

exit(0);
?> 