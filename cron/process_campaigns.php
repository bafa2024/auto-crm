<?php
// Cron job to process scheduled email campaigns
// Run this every minute: * * * * * php /path/to/acrm/cron/process_campaigns.php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailService.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check for scheduled campaigns that should be activated
    $stmt = $db->prepare("
        UPDATE email_campaigns 
        SET status = 'active' 
        WHERE status = 'scheduled' 
        AND scheduled_at <= NOW()
        AND scheduled_at IS NOT NULL
    ");
    $stmt->execute();
    
    // Get all active campaigns
    $stmt = $db->prepare("
        SELECT id FROM email_campaigns 
        WHERE status = 'active'
    ");
    $stmt->execute();
    $campaigns = $stmt->fetchAll();
    
    if (empty($campaigns)) {
        echo "No active campaigns to process.\n";
        exit(0);
    }
    
    $emailService = new EmailService($db);
    
    foreach ($campaigns as $campaign) {
        echo "Processing campaign ID: " . $campaign['id'] . "\n";
        
        $result = $emailService->processCampaign($campaign['id']);
        
        if ($result['success']) {
            echo "Campaign processed successfully. Sent: {$result['sent']}, Failed: {$result['failed']}\n";
        } else {
            echo "Error processing campaign: " . $result['error'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Cron job error: " . $e->getMessage() . "\n";
    error_log("Campaign cron error: " . $e->getMessage());
}