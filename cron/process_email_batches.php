<?php
// This script processes email batches
// Can be run via cron job or scheduled task

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailCampaignService.php';
require_once __DIR__ . '/../services/BatchService.php';

// Set execution time limit
set_time_limit(0);

try {
    $database = new Database();
    $emailCampaignService = new EmailCampaignService($database);
    $batchService = new BatchService($database);
    $db = $database->getConnection();
    
    // Get all campaigns with pending batches
    $sql = "SELECT DISTINCT campaign_id FROM email_batches WHERE status = 'pending' ORDER BY created_at ASC";
    $stmt = $db->query($sql);
    $campaigns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($campaigns) . " campaigns with pending batches\n";
    
    foreach ($campaigns as $campaignId) {
        echo "Processing campaign $campaignId...\n";
        
        // Get campaign details
        $sql = "SELECT * FROM email_campaigns WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$campaign) {
            echo "Campaign $campaignId not found, skipping...\n";
            continue;
        }
        
        // Process all pending batches for this campaign
        while ($batch = $batchService->getNextPendingBatch($campaignId)) {
            echo "Processing batch {$batch['id']} (batch {$batch['batch_number']})...\n";
            
            $result = $emailCampaignService->processBatch($batch['id'], $campaign, $batchService);
            
            if ($result['success']) {
                echo "Batch {$batch['id']} completed: {$result['sent_count']} sent, {$result['failed_count']} failed\n";
            } else {
                echo "Batch {$batch['id']} failed: {$result['message']}\n";
            }
            
            // Add delay between batches
            sleep(2);
        }
        
        echo "Completed all batches for campaign $campaignId\n\n";
    }
    
    echo "Batch processing completed\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("Batch processing error: " . $e->getMessage());
}
?>