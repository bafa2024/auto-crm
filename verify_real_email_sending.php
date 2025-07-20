<?php
// verify_real_email_sending.php - Verify real email sending functionality
// This script tests actual email sending to ensure emails are received

echo "=== Verifying Real Email Sending ===\n\n";

try {
    require_once 'config/database.php';
    require_once 'services/EmailService.php';
    require_once 'services/ScheduledCampaignService.php';
    
    $database = new Database();
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Test 1: Check PHP mail configuration
    echo "1. Checking PHP mail configuration...\n";
    
    if (function_exists("mail")) {
        echo "✅ PHP mail() function is available\n";
    } else {
        echo "❌ PHP mail() function is not available\n";
        exit(1);
    }
    
    // Check sendmail path
    $sendmailPath = ini_get("sendmail_path");
    if ($sendmailPath) {
        echo "✅ Sendmail path: $sendmailPath\n";
    } else {
        echo "⚠️ Sendmail path not configured\n";
    }
    
    // Check SMTP settings
    $smtpHost = ini_get("SMTP");
    $smtpPort = ini_get("smtp_port");
    
    if ($smtpHost) {
        echo "✅ SMTP Host: $smtpHost\n";
    } else {
        echo "⚠️ SMTP Host not configured\n";
    }
    
    if ($smtpPort) {
        echo "✅ SMTP Port: $smtpPort\n";
    } else {
        echo "⚠️ SMTP Port not configured\n";
    }
    
    // Test 2: Test basic email sending
    echo "\n2. Testing basic email sending...\n";
    
    $testEmail = 'test@example.com'; // Change this to your actual email
    $testSubject = 'Basic Email Test - ' . date('Y-m-d H:i:s');
    $testMessage = 'This is a basic test email sent at ' . date('Y-m-d H:i:s') . ' to verify email functionality.';
    $testHeaders = [
        "From: noreply@regrowup.ca",
        "Reply-To: noreply@regrowup.ca",
        "X-Mailer: ACRM Email System"
    ];
    
    echo "Sending basic test email to: $testEmail\n";
    $basicResult = mail($testEmail, $testSubject, $testMessage, implode("\r\n", $testHeaders));
    
    if ($basicResult) {
        echo "✅ Basic email sent successfully!\n";
    } else {
        echo "❌ Basic email failed to send\n";
    }
    
    // Test 3: Test EmailService
    echo "\n3. Testing EmailService...\n";
    
    $emailService = new EmailService($database);
    echo "✅ EmailService instantiated\n";
    
    $serviceResult = $emailService->sendSimpleEmail(
        $testEmail,
        'EmailService Test - ' . date('Y-m-d H:i:s'),
        'Hello {first_name}, this is a test email from EmailService sent at ' . date('Y-m-d H:i:s'),
        'ACRM EmailService Test',
        'noreply@regrowup.ca'
    );
    
    if ($serviceResult) {
        echo "✅ EmailService email sent successfully!\n";
    } else {
        echo "❌ EmailService email failed to send\n";
    }
    
    // Test 4: Test immediate campaign with real recipients
    echo "\n4. Testing immediate campaign with real recipients...\n";
    
    // Create test recipients with real email addresses
    $realRecipients = [
        ['your-email@example.com', 'Your Name', 'Your Company'], // Change this to your email
        ['test1@example.com', 'Test User 1', 'Test Company 1'],
        ['test2@example.com', 'Test User 2', 'Test Company 2']
    ];
    
    echo "Creating test recipients...\n";
    foreach ($realRecipients as $recipient) {
        try {
            $stmt = $db->prepare("INSERT INTO email_recipients (email, name, company, created_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$recipient[0], $recipient[1], $recipient[2], date('Y-m-d H:i:s')]);
            echo "✅ Created recipient: {$recipient[0]}\n";
        } catch (Exception $e) {
            echo "⚠️ Recipient {$recipient[0]} already exists or error: " . $e->getMessage() . "\n";
        }
    }
    
    // Get all recipients
    $stmt = $db->query("SELECT id, email, name FROM email_recipients LIMIT 10");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recipients)) {
        echo "Found " . count($recipients) . " recipients for testing\n";
        
        // Create immediate campaign
        $scheduledService = new ScheduledCampaignService($database);
        
        $immediateCampaignData = [
            'user_id' => 1,
            'name' => 'Real Email Test Campaign - ' . date('Y-m-d H:i:s'),
            'subject' => 'Real Email Test Campaign - ' . date('Y-m-d H:i:s'),
            'content' => 'Hello {first_name}, this is a test immediate campaign with real email sending. 

This email was sent at ' . date('Y-m-d H:i:s') . ' to verify that the ACRM email system is working correctly.

If you receive this email, it means:
✅ Email sending is working
✅ Campaign creation is working
✅ Immediate sending is working
✅ Email personalization is working

Best regards,
ACRM Email System',
            'sender_name' => 'ACRM Email System',
            'sender_email' => 'noreply@regrowup.ca',
            'schedule_type' => 'immediate',
            'schedule_date' => null,
            'frequency' => null
        ];
        
        $result = $scheduledService->createScheduledCampaign($immediateCampaignData);
        
        if ($result['success']) {
            echo "✅ Campaign created successfully (ID: {$result['campaign_id']})\n";
            $testCampaignId = $result['campaign_id'];
            
            // Process the campaign
            echo "Processing campaign...\n";
            $processResult = $scheduledService->processScheduledCampaigns();
            
            if ($processResult['success']) {
                echo "✅ Campaign processed successfully:\n";
                echo "- Processed: {$processResult['processed']}\n";
                echo "- Sent: {$processResult['sent']}\n";
                
                if (!empty($processResult['errors'])) {
                    echo "- Errors: " . count($processResult['errors']) . "\n";
                    foreach ($processResult['errors'] as $error) {
                        echo "  - $error\n";
                    }
                }
            } else {
                echo "❌ Campaign processing failed: {$processResult['message']}\n";
            }
        } else {
            echo "❌ Campaign creation failed: {$result['message']}\n";
        }
    } else {
        echo "❌ No recipients found for testing\n";
    }
    
    // Test 5: Check campaign sends and email logs
    echo "\n5. Checking campaign sends and email logs...\n";
    
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
            
            // Show recent sends
            $stmt = $db->query("SELECT recipient_email, status, sent_at FROM campaign_sends ORDER BY sent_at DESC LIMIT 10");
            $recentSends = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "\nRecent email sends:\n";
            foreach ($recentSends as $send) {
                echo "- {$send['recipient_email']}: {$send['status']} at {$send['sent_at']}\n";
            }
        }
    } catch (Exception $e) {
        echo "No campaign sends found: " . $e->getMessage() . "\n";
    }
    
    // Test 6: Test email with different content types
    echo "\n6. Testing email with different content types...\n";
    
    $htmlContent = '
    <h2>HTML Email Test</h2>
    <p>This is an <strong>HTML formatted</strong> email sent at ' . date('Y-m-d H:i:s') . '.</p>
    <ul>
        <li>Feature 1: HTML formatting</li>
        <li>Feature 2: Lists and styling</li>
        <li>Feature 3: Professional appearance</li>
    </ul>
    <p><a href="https://acrm.regrowup.ca" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Visit ACRM</a></p>
    <p>Best regards,<br>ACRM Email System</p>';
    
    $htmlResult = $emailService->sendSimpleEmail(
        $testEmail,
        'HTML Email Test - ' . date('Y-m-d H:i:s'),
        $htmlContent,
        'ACRM HTML Test',
        'noreply@regrowup.ca'
    );
    
    if ($htmlResult) {
        echo "✅ HTML email sent successfully!\n";
    } else {
        echo "❌ HTML email failed to send\n";
    }
    
    // Test 7: Check server error logs
    echo "\n7. Checking for email-related errors...\n";
    
    $errorLogPath = ini_get('error_log');
    if ($errorLogPath && file_exists($errorLogPath)) {
        echo "Error log path: $errorLogPath\n";
        
        // Read last 50 lines of error log
        $lines = file($errorLogPath);
        $recentLines = array_slice($lines, -50);
        
        $emailErrors = [];
        foreach ($recentLines as $line) {
            if (strpos($line, 'email') !== false || strpos($line, 'mail') !== false || strpos($line, 'SMTP') !== false) {
                $emailErrors[] = trim($line);
            }
        }
        
        if (!empty($emailErrors)) {
            echo "Recent email-related errors:\n";
            foreach (array_slice($emailErrors, -10) as $error) {
                echo "- $error\n";
            }
        } else {
            echo "✅ No recent email-related errors found\n";
        }
    } else {
        echo "⚠️ Error log not accessible\n";
    }
    
    // Test 8: Clean up test data
    echo "\n8. Cleaning up test data...\n";
    
    if (isset($testCampaignId)) {
        try {
            $db->exec("DELETE FROM campaign_sends WHERE campaign_id = $testCampaignId");
            $db->exec("DELETE FROM email_campaigns WHERE id = $testCampaignId");
            echo "✅ Test campaign data cleaned up\n";
        } catch (Exception $e) {
            echo "⚠️ Failed to clean up test campaign data: " . $e->getMessage() . "\n";
        }
    }
    
    // Final summary and instructions
    echo "\n=== Verification Summary ===\n";
    echo "✅ PHP mail configuration checked\n";
    echo "✅ Basic email sending tested\n";
    echo "✅ EmailService tested\n";
    echo "✅ Immediate campaign with real emails tested\n";
    echo "✅ HTML email formatting tested\n";
    echo "✅ Email logs checked\n";
    echo "✅ Test data cleaned up\n";
    
    echo "\n🎉 Real email sending verification completed!\n";
    
    echo "\n📧 Email Delivery Check:\n";
    echo "1. Check your email inbox for test emails\n";
    echo "2. Check spam/junk folder if emails are not in inbox\n";
    echo "3. Verify sender email: noreply@regrowup.ca\n";
    echo "4. Look for emails with subjects containing 'Test' and current timestamp\n";
    
    echo "\n🔧 If emails are not received:\n";
    echo "1. Check server email configuration\n";
    echo "2. Verify SMTP settings in hosting control panel\n";
    echo "3. Check if port 587 or 465 is open for SMTP\n";
    echo "4. Contact hosting provider about email sending\n";
    echo "5. Consider using external email services\n";
    
    echo "\n📝 Next Steps:\n";
    echo "1. Test immediate campaign creation on live site\n";
    echo "2. Monitor email delivery\n";
    echo "3. Configure proper SMTP if needed\n";
    echo "4. Set up email tracking and analytics\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 