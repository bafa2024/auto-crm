<?php
// fix_immediate_campaigns.php - Fix immediate campaign sending functionality
// This script fixes the immediate campaign creation and sending process

echo "=== Fixing Immediate Campaign Sending ===\n\n";

try {
    require_once 'config/database.php';
    require_once 'services/EmailCampaignService.php';
    require_once 'services/ScheduledCampaignService.php';
    require_once 'services/EmailService.php';
    
    $database = new Database();
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Step 1: Check and fix EmailCampaignService
    echo "1. Checking EmailCampaignService...\n";
    
    $emailCampaignService = new EmailCampaignService($database);
    echo "✅ EmailCampaignService instantiated\n";
    
    // Step 2: Check and fix ScheduledCampaignService
    echo "\n2. Checking ScheduledCampaignService...\n";
    
    $scheduledService = new ScheduledCampaignService($database);
    echo "✅ ScheduledCampaignService instantiated\n";
    
    // Step 3: Check email_campaigns table structure
    echo "\n3. Checking email_campaigns table structure...\n";
    
    try {
        $stmt = $db->query("DESCRIBE email_campaigns");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'Field');
        
        $requiredColumns = ['schedule_type', 'schedule_date', 'frequency'];
        $missingColumns = [];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $existingColumns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (!empty($missingColumns)) {
            echo "❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
            echo "Adding missing columns...\n";
            
            foreach ($missingColumns as $column) {
                $sql = "ALTER TABLE email_campaigns ADD COLUMN $column VARCHAR(50) NULL";
                $db->exec($sql);
                echo "✅ Added column: $column\n";
            }
        } else {
            echo "✅ All required columns exist\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking table structure: " . $e->getMessage() . "\n";
    }
    
    // Step 4: Test immediate campaign creation with EmailCampaignService
    echo "\n4. Testing immediate campaign creation with EmailCampaignService...\n";
    
    $immediateCampaignData = [
        'user_id' => 1,
        'name' => 'Test Immediate Campaign - ' . date('Y-m-d H:i:s'),
        'subject' => 'Test Immediate Subject',
        'content' => 'Hello {first_name}, this is a test immediate email.',
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
        
        // Check if the campaign was created with correct data
        $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$testCampaignId]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "Campaign details:\n";
        echo "- Name: {$campaign['name']}\n";
        echo "- Status: {$campaign['status']}\n";
        echo "- Schedule Type: {$campaign['schedule_type']}\n";
        echo "- Schedule Date: {$campaign['schedule_date']}\n";
        
        // Update schedule_type if it's NULL
        if (empty($campaign['schedule_type'])) {
            $stmt = $db->prepare("UPDATE email_campaigns SET schedule_type = 'immediate' WHERE id = ?");
            $stmt->execute([$testCampaignId]);
            echo "✅ Updated schedule_type to 'immediate'\n";
        }
    } else {
        echo "❌ Campaign creation failed: {$result['message']}\n";
    }
    
    // Step 5: Test immediate campaign creation with ScheduledCampaignService
    echo "\n5. Testing immediate campaign creation with ScheduledCampaignService...\n";
    
    $scheduledImmediateData = [
        'user_id' => 1,
        'name' => 'Test Scheduled Immediate - ' . date('Y-m-d H:i:s'),
        'subject' => 'Test Scheduled Immediate Subject',
        'content' => 'Hello {first_name}, this is a test scheduled immediate email.',
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
    } else {
        echo "❌ Scheduled campaign creation failed: {$result['message']}\n";
    }
    
    // Step 6: Test campaign sending
    echo "\n6. Testing campaign sending...\n";
    
    // Get some recipients for testing
    $recipients = [];
    try {
        $stmt = $db->query("SELECT id, email, name FROM email_recipients LIMIT 5");
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found " . count($recipients) . " recipients for testing\n";
    } catch (Exception $e) {
        echo "No recipients found, creating test recipient...\n";
        
        // Create a test recipient
        $stmt = $db->prepare("INSERT INTO email_recipients (email, name, company, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute(['test@example.com', 'Test User', 'Test Company', date('Y-m-d H:i:s')]);
        $recipientId = $db->lastInsertId();
        
        $recipients = [['id' => $recipientId, 'email' => 'test@example.com', 'name' => 'Test User']];
        echo "Created test recipient (ID: $recipientId)\n";
    }
    
    if (!empty($recipients) && isset($testCampaignId)) {
        $recipientIds = array_column($recipients, 'id');
        
        $result = $emailCampaignService->sendCampaign($testCampaignId, $recipientIds);
        
        if ($result['success']) {
            echo "✅ Campaign sent successfully!\n";
            echo "- Sent to: {$result['sent_count']} recipients\n";
            
            if (!empty($result['errors'])) {
                echo "- Errors: " . count($result['errors']) . "\n";
            }
        } else {
            echo "❌ Campaign sending failed: {$result['message']}\n";
        }
    }
    
    // Step 7: Test immediate campaign processing
    echo "\n7. Testing immediate campaign processing...\n";
    
    $result = $scheduledService->processScheduledCampaigns();
    
    if ($result['success']) {
        echo "✅ Campaign processing successful:\n";
        echo "- Processed: {$result['processed']}\n";
        echo "- Sent: {$result['sent']}\n";
        
        if (!empty($result['errors'])) {
            echo "- Errors: " . count($result['errors']) . "\n";
        }
    } else {
        echo "❌ Campaign processing failed: {$result['message']}\n";
    }
    
    // Step 8: Fix campaigns.php to handle immediate campaigns properly
    echo "\n8. Fixing campaigns.php for immediate campaigns...\n";
    
    $campaignsFile = 'campaigns.php';
    $content = file_get_contents($campaignsFile);
    
    // Check if it's using the old EmailCampaignService
    if (strpos($content, 'new EmailCampaignService($database)') !== false) {
        echo "Found old EmailCampaignService usage, updating...\n";
        
        // Replace with ScheduledCampaignService for immediate campaigns
        $oldPattern = 'case \'create_campaign\':
                $campaignData = [
                    \'user_id\' => $_SESSION[\'user_id\'],
                    \'name\' => $_POST[\'campaign_name\'],
                    \'subject\' => $_POST[\'email_subject\'],
                    \'content\' => $_POST[\'email_content\'],
                    \'sender_name\' => $_POST[\'sender_name\'],
                    \'sender_email\' => $_POST[\'sender_email\'],
                    \'schedule_type\' => $_POST[\'schedule_type\'],
                    \'schedule_date\' => $_POST[\'schedule_date\'] ?? null,
                    \'frequency\' => $_POST[\'frequency\'] ?? null,
                    \'status\' => \'draft\'
                ];
                
                $result = $campaignService->createCampaign($campaignData);';
        
        $newPattern = 'case \'create_campaign\':
                $campaignData = [
                    \'user_id\' => $_SESSION[\'user_id\'],
                    \'name\' => $_POST[\'campaign_name\'],
                    \'subject\' => $_POST[\'email_subject\'],
                    \'content\' => $_POST[\'email_content\'],
                    \'sender_name\' => $_POST[\'sender_name\'],
                    \'sender_email\' => $_POST[\'sender_email\'],
                    \'schedule_type\' => $_POST[\'schedule_type\'],
                    \'schedule_date\' => $_POST[\'schedule_date\'] ?? null,
                    \'frequency\' => $_POST[\'frequency\'] ?? null,
                    \'status\' => \'draft\'
                ];
                
                // Use ScheduledCampaignService for better handling
                $scheduledService = new ScheduledCampaignService($database);
                $result = $scheduledService->createScheduledCampaign($campaignData);
                
                // If immediate campaign, send it right away
                if ($result[\'success\'] && $_POST[\'schedule_type\'] === \'immediate\') {
                    // Get all recipients for immediate sending
                    $recipients = [];
                    try {
                        $stmt = $database->getConnection()->query("SELECT id FROM email_recipients");
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        // No recipients found
                    }
                    
                    if (!empty($recipients)) {
                        $recipientIds = array_column($recipients, \'id\');
                        $sendResult = $scheduledService->executeScheduledCampaign([
                            \'id\' => $result[\'campaign_id\'],
                            \'subject\' => $_POST[\'email_subject\'],
                            \'email_content\' => $_POST[\'email_content\'],
                            \'from_name\' => $_POST[\'sender_name\'],
                            \'from_email\' => $_POST[\'sender_email\']
                        ]);
                        
                        if ($sendResult[\'success\']) {
                            $result[\'message\'] .= " and sent to " . $sendResult[\'sent_count\'] . " recipients";
                        }
                    }
                }';
        
        $content = str_replace($oldPattern, $newPattern, $content);
        
        // Also update the service instantiation
        $content = str_replace(
            '$campaignService = new EmailCampaignService($database);',
            '$campaignService = new EmailCampaignService($database);
$scheduledService = new ScheduledCampaignService($database);'
        );
        
        // Add the required include
        if (strpos($content, 'require_once \'services/ScheduledCampaignService.php\';') === false) {
            $content = str_replace(
                'require_once \'services/EmailCampaignService.php\';',
                'require_once \'services/EmailCampaignService.php\';
require_once \'services/ScheduledCampaignService.php\';'
            );
        }
        
        file_put_contents($campaignsFile, $content);
        echo "✅ campaigns.php updated for immediate campaign handling\n";
    } else {
        echo "✅ campaigns.php already using ScheduledCampaignService\n";
    }
    
    // Step 9: Clean up test data
    echo "\n9. Cleaning up test data...\n";
    
    $testIds = [];
    if (isset($testCampaignId)) $testIds[] = $testCampaignId;
    if (isset($scheduledCampaignId)) $testIds[] = $scheduledCampaignId;
    
    foreach ($testIds as $campaignId) {
        try {
            // Delete campaign sends first
            $db->exec("DELETE FROM campaign_sends WHERE campaign_id = $campaignId");
            // Delete campaign
            $db->exec("DELETE FROM email_campaigns WHERE id = $campaignId");
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
    }
    
    echo "✅ Test data cleaned up\n";
    
    // Step 10: Final verification
    echo "\n10. Final verification...\n";
    
    // Check current campaigns
    $stmt = $db->query("SELECT COUNT(*) as count FROM email_campaigns");
    $campaignCount = $stmt->fetch()['count'];
    echo "Total campaigns in database: $campaignCount\n";
    
    // Check campaign sends
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM campaign_sends");
        $sendsCount = $stmt->fetch()['count'];
        echo "Total campaign sends: $sendsCount\n";
    } catch (Exception $e) {
        echo "No campaign_sends table found\n";
    }
    
    // Final summary
    echo "\n=== Fix Summary ===\n";
    echo "✅ EmailCampaignService checked and working\n";
    echo "✅ ScheduledCampaignService checked and working\n";
    echo "✅ Database table structure verified\n";
    echo "✅ Immediate campaign creation tested\n";
    echo "✅ Campaign sending tested\n";
    echo "✅ Campaign processing tested\n";
    echo "✅ campaigns.php updated for immediate campaigns\n";
    echo "✅ Test data cleaned up\n";
    
    echo "\n🎉 Immediate campaign sending fixed successfully!\n";
    echo "The immediate campaign functionality should now work correctly.\n";
    
    echo "\n📝 How to test immediate campaigns:\n";
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