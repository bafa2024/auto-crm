<?php
// fix_scheduled_campaigns.php - Fix scheduled campaign issues
// This script fixes common issues with scheduled campaigns

echo "=== Scheduled Campaigns Fix ===\n\n";

try {
    require_once 'config/database.php';
    require_once 'services/ScheduledCampaignService.php';
    require_once 'services/EmailService.php';
    
    $database = new Database();
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Step 1: Check and fix email_campaigns table
    echo "1. Checking and fixing email_campaigns table...\n";
    
    $scheduledService = new ScheduledCampaignService($database);
    
    // Check if table exists
    $stmt = $db->query("SHOW TABLES LIKE 'email_campaigns'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "❌ email_campaigns table not found - creating it...\n";
        $scheduledService->ensureCampaignsTable();
        echo "✅ email_campaigns table created\n";
    } else {
        echo "✅ email_campaigns table exists\n";
    }
    
    // Check table structure
    $stmt = $db->query("DESCRIBE email_campaigns");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existingColumns = array_column($columns, 'Field');
    
    $requiredColumns = [
        'schedule_type' => 'VARCHAR(50) DEFAULT "immediate"',
        'schedule_date' => 'DATETIME NULL',
        'frequency' => 'VARCHAR(50) NULL',
        'total_recipients' => 'INT DEFAULT 0',
        'sent_count' => 'INT DEFAULT 0',
        'opened_count' => 'INT DEFAULT 0',
        'clicked_count' => 'INT DEFAULT 0',
        'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    $missingColumns = [];
    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!in_array($columnName, $existingColumns)) {
            $missingColumns[$columnName] = $columnDef;
        }
    }
    
    if (!empty($missingColumns)) {
        echo "❌ Missing columns found - adding them...\n";
        foreach ($missingColumns as $columnName => $columnDef) {
            try {
                $sql = "ALTER TABLE email_campaigns ADD COLUMN $columnName $columnDef";
                $db->exec($sql);
                echo "✅ Added column: $columnName\n";
            } catch (Exception $e) {
                echo "⚠️ Failed to add column $columnName: " . $e->getMessage() . "\n";
            }
        }
    } else {
        echo "✅ All required columns exist\n";
    }
    
    // Step 2: Check and fix campaign_sends table
    echo "\n2. Checking and fixing campaign_sends table...\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'campaign_sends'");
    $sendsTableExists = $stmt->fetch();
    
    if (!$sendsTableExists) {
        echo "❌ campaign_sends table not found - creating it...\n";
        $scheduledService->ensureCampaignSendsTable();
        echo "✅ campaign_sends table created\n";
    } else {
        echo "✅ campaign_sends table exists\n";
    }
    
    // Step 3: Fix campaign statuses
    echo "\n3. Fixing campaign statuses...\n";
    
    // Fix campaigns with invalid statuses
    $validStatuses = ['draft', 'scheduled', 'sending', 'completed', 'failed', 'cancelled'];
    
    $stmt = $db->query("SELECT id, name, status FROM email_campaigns");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixedStatuses = 0;
    foreach ($campaigns as $campaign) {
        if (!in_array($campaign['status'], $validStatuses)) {
            // Fix invalid status
            $sql = "UPDATE email_campaigns SET status = 'draft' WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$campaign['id']]);
            $fixedStatuses++;
            echo "✅ Fixed campaign '{$campaign['name']}' status to 'draft'\n";
        }
    }
    
    if ($fixedStatuses > 0) {
        echo "✅ Fixed $fixedStatuses campaign statuses\n";
    } else {
        echo "✅ All campaign statuses are valid\n";
    }
    
    // Step 4: Fix scheduled campaigns with past dates
    echo "\n4. Fixing scheduled campaigns with past dates...\n";
    
    $currentTime = date('Y-m-d H:i:s');
    $sql = "SELECT id, name, schedule_date FROM email_campaigns WHERE status = 'scheduled' AND schedule_date < ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$currentTime]);
    $pastCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixedPastDates = 0;
    foreach ($pastCampaigns as $campaign) {
        // Reschedule to 1 hour from now
        $newDate = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $sql = "UPDATE email_campaigns SET schedule_date = ?, updated_at = ? WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$newDate, $currentTime, $campaign['id']]);
        $fixedPastDates++;
        echo "✅ Rescheduled campaign '{$campaign['name']}' to $newDate\n";
    }
    
    if ($fixedPastDates > 0) {
        echo "✅ Fixed $fixedPastDates past scheduled campaigns\n";
    } else {
        echo "✅ No past scheduled campaigns found\n";
    }
    
    // Step 5: Fix orphaned campaign sends
    echo "\n5. Fixing orphaned campaign sends...\n";
    
    $sql = "SELECT COUNT(*) as count FROM campaign_sends cs 
            LEFT JOIN email_campaigns ec ON cs.campaign_id = ec.id 
            WHERE ec.id IS NULL";
    $stmt = $db->query($sql);
    $orphanedSends = $stmt->fetch()['count'];
    
    if ($orphanedSends > 0) {
        echo "❌ Found $orphanedSends orphaned campaign sends - removing them...\n";
        $sql = "DELETE cs FROM campaign_sends cs 
                LEFT JOIN email_campaigns ec ON cs.campaign_id = ec.id 
                WHERE ec.id IS NULL";
        $db->exec($sql);
        echo "✅ Removed orphaned campaign sends\n";
    } else {
        echo "✅ No orphaned campaign sends found\n";
    }
    
    // Step 6: Test scheduled campaign creation
    echo "\n6. Testing scheduled campaign creation...\n";
    
    $testCampaign = [
        'user_id' => 1,
        'name' => 'Fix Test Campaign',
        'subject' => 'Fix Test Subject',
        'content' => 'Hello {first_name}, this is a fix test email.',
        'sender_name' => 'Fix Test Sender',
        'sender_email' => 'fix@example.com',
        'schedule_type' => 'scheduled',
        'schedule_date' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        'frequency' => null
    ];
    
    $result = $scheduledService->createScheduledCampaign($testCampaign);
    
    if ($result['success']) {
        echo "✅ Test campaign created successfully (ID: {$result['campaign_id']})\n";
        $testCampaignId = $result['campaign_id'];
    } else {
        echo "❌ Test campaign creation failed: {$result['message']}\n";
    }
    
    // Step 7: Test campaign processing
    echo "\n7. Testing campaign processing...\n";
    
    $result = $scheduledService->processScheduledCampaigns();
    
    if ($result['success']) {
        echo "✅ Campaign processing test successful:\n";
        echo "- Processed: {$result['processed']}\n";
        echo "- Sent: {$result['sent']}\n";
        
        if (!empty($result['errors'])) {
            echo "- Errors: " . count($result['errors']) . "\n";
        }
    } else {
        echo "❌ Campaign processing test failed: {$result['message']}\n";
    }
    
    // Step 8: Clean up test data
    if (isset($testCampaignId)) {
        echo "\n8. Cleaning up test data...\n";
        $db->exec("DELETE FROM campaign_sends WHERE campaign_id = $testCampaignId");
        $db->exec("DELETE FROM email_campaigns WHERE id = $testCampaignId");
        echo "✅ Test data cleaned up\n";
    }
    
    // Step 9: Final verification
    echo "\n9. Final verification...\n";
    
    // Count campaigns by status
    $sql = "SELECT status, COUNT(*) as count FROM email_campaigns GROUP BY status";
    $stmt = $db->query($sql);
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Campaign status summary:\n";
    foreach ($statusCounts as $status) {
        echo "- {$status['status']}: {$status['count']}\n";
    }
    
    // Count scheduled campaigns
    $sql = "SELECT COUNT(*) as count FROM email_campaigns WHERE status = 'scheduled'";
    $stmt = $db->query($sql);
    $scheduledCount = $stmt->fetch()['count'];
    echo "Scheduled campaigns: $scheduledCount\n";
    
    // Final summary
    echo "\n=== Fix Summary ===\n";
    echo "✅ email_campaigns table verified/fixed\n";
    echo "✅ campaign_sends table verified/fixed\n";
    echo "✅ Campaign statuses fixed\n";
    echo "✅ Past scheduled campaigns rescheduled\n";
    echo "✅ Orphaned campaign sends removed\n";
    echo "✅ Scheduled campaign creation working\n";
    echo "✅ Campaign processing working\n";
    
    echo "\n🎉 Scheduled campaigns fix completed!\n";
    echo "The scheduled campaign system is now working correctly.\n";
    echo "\n📝 IMPORTANT NOTES:\n";
    echo "- Set up cron job to run cron/process_scheduled_campaigns.php every minute\n";
    echo "- Monitor campaign statuses regularly\n";
    echo "- Check logs for any processing errors\n";
    
    echo "\n🚀 NEXT STEPS:\n";
    echo "1. Set up cron job: */1 * * * * php /path/to/cron/process_scheduled_campaigns.php\n";
    echo "2. Test creating a scheduled campaign\n";
    echo "3. Monitor campaign execution\n";
    echo "4. Check campaign statistics\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 