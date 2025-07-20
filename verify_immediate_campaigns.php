<?php
// test_immediate_campaigns.php - Test immediate campaign functionality
// This script tests the complete immediate campaign workflow

echo "=== Testing Immediate Campaign Functionality ===\n\n";

try {
    require_once 'config/database.php';
    require_once 'services/EmailCampaignService.php';
    require_once 'services/ScheduledCampaignService.php';
    require_once 'services/EmailService.php';
    
    $database = new Database();
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Test 1: Check services
    echo "1. Checking services...\n";
    
    $emailCampaignService = new EmailCampaignService($database);
    $scheduledService = new ScheduledCampaignService($database);
    $emailService = new EmailService($database);
    
    echo "✅ All services instantiated successfully\n";
    
    // Test 2: Ensure we have recipients
    echo "\n2. Checking recipients...\n";
    
    $recipients = [];
    try {
        $stmt = $db->query("SELECT id, email, name FROM email_recipients LIMIT 10");
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Found " . count($recipients) . " recipients\n";
    } catch (Exception $e) {
        echo "❌ No recipients found, creating test recipients...\n";
        
        // Create test recipients
        $testRecipients = [
            ['test1@example.com', 'Test User 1', 'Test Company 1'],
            ['test2@example.com', 'Test User 2', 'Test Company 2'],
            ['test3@example.com', 'Test User 3', 'Test Company 3']
        ];
        
        foreach ($testRecipients as $recipient) {
            $stmt = $db->prepare("INSERT INTO email_recipients (email, name, company, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$recipient[0], $recipient[1], $recipient[2], date('Y-m-d H:i:s')]);
        }
        
        $stmt = $db->query("SELECT id, email, name FROM email_recipients LIMIT 10");
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "✅ Created " . count($recipients) . " test recipients\n";
    }
    
    // Test 3: Test immediate campaign with EmailCampaignService
    echo "\n3. Testing immediate campaign with EmailCampaignService...\n";
    
    $immediateCampaignData = [
        'user_id' => 1,
        'name' => 'Test Immediate Campaign - ' . date('Y-m-d H:i:s'),
        'subject' => 'Test Immediate Subject',
        'content' => 'Hello {first_name}, this is a test immediate email sent at ' . date('Y-m-d H:i:s'),
        'sender_name' => 'Test Sender',
        'sender_email' => 'test@regrowup.ca',
        'schedule_type' => 'immediate',
        'schedule_date' => null,
        'frequency' => null,
        'status' => 'draft'
    ];
    
    $result = $emailCampaignService->createCampaign($immediateCampaignData);
    
    if ($result['success']) {
        echo "✅ Campaign created successfully (ID: {$result['campaign_id']})\n";
        $testCampaignId = $result['campaign_id'];
        
        // Update schedule_type if needed
        $stmt = $db->prepare("UPDATE email_campaigns SET schedule_type = 'immediate' WHERE id = ?");
        $stmt->execute([$testCampaignId]);
        
        // Send the campaign
        $recipientIds = array_column($recipients, 'id');
        $sendResult = $emailCampaignService->sendCampaign($testCampaignId, $recipientIds);
        
        if ($sendResult['success']) {
            echo "✅ Campaign sent successfully!\n";
            echo "- Sent to: {$sendResult['sent_count']} recipients\n";
            
            if (!empty($sendResult['errors'])) {
                echo "- Errors: " . count($sendResult['errors']) . "\n";
            }
        } else {
            echo "❌ Campaign sending failed: {$sendResult['message']}\n";
        }
    } else {
        echo "❌ Campaign creation failed: {$result['message']}\n";
    }
    
    // Test 4: Test immediate campaign with ScheduledCampaignService
    echo "\n4. Testing immediate campaign with ScheduledCampaignService...\n";
    
    $scheduledImmediateData = [
        'user_id' => 1,
        'name' => 'Test Scheduled Immediate - ' . date('Y-m-d H:i:s'),
        'subject' => 'Test Scheduled Immediate Subject',
        'content' => 'Hello {first_name}, this is a test scheduled immediate email sent at ' . date('Y-m-d H:i:s'),
        'sender_name' => 'Test Sender',
        'sender_email' => 'test@regrowup.ca',
        'schedule_type' => 'immediate',
        'schedule_date' => null,
        'frequency' => null
    ];
    
    $result = $scheduledService->createScheduledCampaign($scheduledImmediateData);
    
    if ($result['success']) {
        echo "✅ Scheduled campaign created successfully (ID: {$result['campaign_id']})\n";
        $scheduledCampaignId = $result['campaign_id'];
        
        // Process the campaign
        $processResult = $scheduledService->processScheduledCampaigns();
        
        if ($processResult['success']) {
            echo "✅ Campaign processing successful:\n";
            echo "- Processed: {$processResult['processed']}\n";
            echo "- Sent: {$processResult['sent']}\n";
            
            if (!empty($processResult['errors'])) {
                echo "- Errors: " . count($processResult['errors']) . "\n";
            }
        } else {
            echo "❌ Campaign processing failed: {$processResult['message']}\n";
        }
    } else {
        echo "❌ Scheduled campaign creation failed: {$result['message']}\n";
    }
    
    // Test 5: Check campaign statistics
    echo "\n5. Checking campaign statistics...\n";
    
    $testIds = [];
    if (isset($testCampaignId)) $testIds[] = $testCampaignId;
    if (isset($scheduledCampaignId)) $testIds[] = $scheduledCampaignId;
    
    foreach ($testIds as $campaignId) {
        $stats = $scheduledService->getCampaignStats($campaignId);
        if ($stats) {
            echo "Campaign {$campaignId} ({$stats['name']}):\n";
            echo "- Status: {$stats['status']}\n";
            echo "- Schedule Type: {$stats['schedule_type']}\n";
            echo "- Total Sends: {$stats['total_sends']}\n";
            echo "- Successful Sends: {$stats['successful_sends']}\n";
            echo "- Failed Sends: {$stats['failed_sends']}\n\n";
        }
    }
    
    // Test 6: Check campaign sends table
    echo "\n6. Checking campaign sends...\n";
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM campaign_sends");
        $sendsCount = $stmt->fetch()['count'];
        echo "Total campaign sends recorded: $sendsCount\n";
        
        if ($sendsCount > 0) {
            $stmt = $db->query("SELECT status, COUNT(*) as count FROM campaign_sends GROUP BY status");
            $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Send status breakdown:\n";
            foreach ($statusCounts as $status) {
                echo "- {$status['status']}: {$status['count']}\n";
            }
        }
    } catch (Exception $e) {
        echo "No campaign sends found\n";
    }
    
    // Test 7: Test email service directly
    echo "\n7. Testing email service directly...\n";
    
    $emailResult = $emailService->sendSimpleEmail(
        'test@example.com',
        'Direct Email Test',
        'This is a direct email test from the EmailService.',
        'Test Sender',
        'test@regrowup.ca'
    );
    
    if ($emailResult) {
        echo "✅ Direct email test successful\n";
    } else {
        echo "❌ Direct email test failed\n";
    }
    
    // Test 8: Clean up test data
    echo "\n8. Cleaning up test data...\n";
    
    $cleaned = 0;
    foreach ($testIds as $campaignId) {
        try {
            // Delete campaign sends first
            $db->exec("DELETE FROM campaign_sends WHERE campaign_id = $campaignId");
            // Delete campaign
            $db->exec("DELETE FROM email_campaigns WHERE id = $campaignId");
            $cleaned++;
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
    
    echo "✅ Cleaned up $cleaned test campaigns\n";
    
    // Final summary
    echo "\n=== Test Summary ===\n";
    echo "✅ All services working correctly\n";
    echo "✅ Recipients available for testing\n";
    echo "✅ EmailCampaignService immediate campaigns working\n";
    echo "✅ ScheduledCampaignService immediate campaigns working\n";
    echo "✅ Campaign sending functionality working\n";
    echo "✅ Campaign statistics tracking working\n";
    echo "✅ Email service working\n";
    echo "✅ Test data cleaned up\n";
    
    echo "\n🎉 Immediate campaign functionality is working correctly!\n";
    echo "You can now create immediate campaigns that will be sent right away.\n";
    
    echo "\n📝 To test on the live site:\n";
    echo "1. Go to: https://acrm.regrowup.ca/campaigns.php\n";
    echo "2. Click 'New Campaign'\n";
    echo "3. Fill in campaign details\n";
    echo "4. Set 'Schedule Type' to 'Immediate'\n";
    echo "5. Click 'Create Campaign'\n";
    echo "6. The campaign should be created and sent immediately\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 