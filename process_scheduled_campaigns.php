<?php
/**
 * Simple Campaign Scheduler Processor
 * Run this script via cron job to process scheduled campaigns
 * 
 * Cron examples:
 * Every minute: * * * * * php /path/to/process_scheduled_campaigns.php
 * Every 5 minutes: */5 * * * * php /path/to/process_scheduled_campaigns.php
 */

// Set time limit for long-running process
set_time_limit(300); // 5 minutes

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'config/database.php';
require_once 'services/SimpleCampaignScheduler.php';

echo "=== Simple Campaign Scheduler Processor ===\n";
echo "Started at: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $database = new Database();
    $scheduler = new SimpleCampaignScheduler($database);
    
    echo "🔍 Checking for pending scheduled campaigns...\n";
    
    // Process all pending campaigns
    $result = $scheduler->processPendingCampaigns();
    
    if ($result['success']) {
        echo "✅ Processing completed!\n";
        echo "📊 Processed: {$result['processed']} campaigns\n";
        
        if ($result['processed'] > 0) {
            echo "\n📋 Results:\n";
            foreach ($result['results'] as $index => $campaignResult) {
                $num = $index + 1;
                if ($campaignResult['success']) {
                    echo "  $num. ✅ {$campaignResult['message']}\n";
                } else {
                    echo "  $num. ❌ {$campaignResult['message']}\n";
                }
            }
        } else {
            echo "💤 No campaigns were due for processing.\n";
        }
        
    } else {
        echo "❌ Processing failed: {$result['message']}\n";
    }
    
    // Show upcoming scheduled campaigns
    echo "\n📅 Upcoming scheduled campaigns:\n";
    $db = $database->getConnection();
    $stmt = $db->query("
        SELECT sc.id, ec.name, sc.schedule_type, sc.next_send_at, sc.status
        FROM scheduled_campaigns sc
        JOIN email_campaigns ec ON sc.campaign_id = ec.id
        WHERE sc.status = 'pending' AND sc.next_send_at > NOW()
        ORDER BY sc.next_send_at ASC
        LIMIT 5
    ");
    
    $upcoming = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($upcoming) > 0) {
        foreach ($upcoming as $campaign) {
            echo "  • {$campaign['name']} - {$campaign['next_send_at']} ({$campaign['schedule_type']})\n";
        }
    } else {
        echo "  No upcoming scheduled campaigns.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    error_log("Scheduler processor error: " . $e->getMessage());
}

echo "\nCompleted at: " . date('Y-m-d H:i:s') . "\n";
echo "====================================\n";
?>
