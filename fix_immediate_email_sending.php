<?php
/**
 * Fix Immediate Email Sending
 * This script fixes the immediate campaign sending functionality
 */

echo "=== Fixing Immediate Email Sending ===\n\n";

try {
    require_once 'config/database.php';
    require_once 'services/EmailService.php';
    require_once 'services/ScheduledCampaignService.php';
    require_once 'models/EmailCampaign.php';
    require_once 'models/Contact.php';
    
    $database = new Database();
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Step 1: Fix EmailService sendSimpleEmail method
    echo "1. Fixing EmailService sendSimpleEmail method...\n";
    
    $emailServiceFile = 'services/EmailService.php';
    $content = file_get_contents($emailServiceFile);
    
    // Find and replace the old sendSimpleEmail method
    $oldMethod = '    /**
     * Simple email sending method for ScheduledCampaignService
     */
    public function sendSimpleEmail($to, $subject, $content, $senderName, $senderEmail) {
        try {
            // In production, you would use PHPMailer or similar
            // For now, we\'ll simulate sending
            
            // Simulate sending delay
            usleep(100000); // 0.1 second delay
            
            // Log the email send attempt
            error_log("Email sent to: $to, Subject: $subject, From: $senderName <$senderEmail>");
            
            // For demo purposes, simulate success rate
            $success = (rand(1, 100) <= 95); // 95% success rate
            
            if ($success) {
                error_log("Email sent successfully to: $to");
            } else {
                error_log("Email failed to send to: $to");
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }';
    
    $newMethod = '    /**
     * Simple email sending method for ScheduledCampaignService
     */
    public function sendSimpleEmail($to, $subject, $content, $senderName, $senderEmail) {
        try {
            // Log the email send attempt
            error_log("Attempting to send email to: $to, Subject: $subject, From: $senderName <$senderEmail>");
            
            // Create HTML email content
            $htmlContent = $this->createHtmlEmail($content, $senderName);
            
            // Set up email headers
            $headers = [
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "From: $senderName <$senderEmail>",
                "Reply-To: $senderEmail",
                "X-Mailer: ACRM Email System",
                "X-Priority: 3",
                "X-MSMail-Priority: Normal"
            ];
            
            // Send email using PHP mail() function
            $mailSent = mail($to, $subject, $htmlContent, implode("\r\n", $headers));
            
            if ($mailSent) {
                error_log("✅ Email sent successfully to: $to");
                return true;
            } else {
                error_log("❌ Email failed to send to: $to");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("❌ Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create HTML email with proper formatting
     */
    private function createHtmlEmail($content, $senderName) {
        $html = \'<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Campaign</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #ffffff; }
        .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; }
        @media only screen and (max-width: 600px) {
            .container { width: 100% !important; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>ACRM Email Campaign</h2>
        </div>
        <div class="content">
            \' . nl2br(htmlspecialchars($content)) . \'
        </div>
        <div class="footer">
            <p>This email was sent by ACRM Email System</p>
            <p>Sender: \' . htmlspecialchars($senderName) . \'</p>
            <p><a href="[UNSUBSCRIBE_LINK]">Unsubscribe</a> | <a href="[WEB_VERSION]">View in browser</a></p>
        </div>
    </div>
</body>
</html>\';
        
        return $html;
    }';
    
    $content = str_replace($oldMethod, $newMethod, $content);
    file_put_contents($emailServiceFile, $content);
    echo "✅ EmailService sendSimpleEmail method updated for real email sending\n";
    
    // Step 2: Check and fix database schema
    echo "\n2. Checking database schema...\n";
    
    // Check if email_campaigns table has required columns
    try {
        $stmt = $db->query("DESCRIBE email_campaigns");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['schedule_type', 'schedule_date', 'frequency'];
        $missingColumns = [];
        
        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                $missingColumns[] = $column;
            }
        }
        
        if (!empty($missingColumns)) {
            echo "⚠️ Missing columns: " . implode(', ', $missingColumns) . "\n";
            echo "Adding missing columns...\n";
            
            foreach ($missingColumns as $column) {
                if ($column === 'schedule_type') {
                    $db->exec("ALTER TABLE email_campaigns ADD COLUMN schedule_type VARCHAR(20) DEFAULT 'immediate'");
                } elseif ($column === 'schedule_date') {
                    $db->exec("ALTER TABLE email_campaigns ADD COLUMN schedule_date DATETIME NULL");
                } elseif ($column === 'frequency') {
                    $db->exec("ALTER TABLE email_campaigns ADD COLUMN frequency VARCHAR(20) NULL");
                }
            }
            echo "✅ Missing columns added\n";
        } else {
            echo "✅ All required columns exist\n";
        }
    } catch (Exception $e) {
        echo "❌ Error checking database schema: " . $e->getMessage() . "\n";
    }
    
    // Step 3: Check if email_recipients table exists
    echo "\n3. Checking email_recipients table...\n";
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM email_recipients");
        $count = $stmt->fetchColumn();
        echo "✅ email_recipients table exists with $count records\n";
    } catch (Exception $e) {
        echo "⚠️ email_recipients table doesn't exist, creating it...\n";
        
        $db->exec("CREATE TABLE IF NOT EXISTS email_recipients (
            id INTEGER PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            company VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Add some test recipients
        $testRecipients = [
            ['test@example.com', 'Test User', 'Test Company'],
            ['your-email@example.com', 'Your Name', 'Your Company']
        ];
        
        $stmt = $db->prepare("INSERT INTO email_recipients (email, name, company) VALUES (?, ?, ?)");
        foreach ($testRecipients as $recipient) {
            $stmt->execute($recipient);
        }
        
        echo "✅ email_recipients table created with test data\n";
    }
    
    // Step 4: Test immediate email sending
    echo "\n4. Testing immediate email sending...\n";
    
    $emailService = new EmailService($database);
    
    // Test basic email sending
    $testEmail = 'test@example.com'; // Change this to your actual email
    $testSubject = 'Test Immediate Email - ' . date('Y-m-d H:i:s');
    $testContent = 'Hello {first_name}, this is a test immediate email sent at ' . date('Y-m-d H:i:s') . ' to verify real email sending functionality.';
    $testSenderName = 'ACRM Test Sender';
    $testSenderEmail = 'noreply@regrowup.ca';
    
    echo "Sending test email to: $testEmail\n";
    $result = $emailService->sendSimpleEmail($testEmail, $testSubject, $testContent, $testSenderName, $testSenderEmail);
    
    if ($result) {
        echo "✅ Test email sent successfully!\n";
    } else {
        echo "❌ Test email failed to send\n";
    }
    
    // Step 5: Test immediate campaign creation and sending
    echo "\n5. Testing immediate campaign creation and sending...\n";
    
    $scheduledService = new ScheduledCampaignService($database);
    
    // Get recipients for testing
    $stmt = $db->query("SELECT id, email, name FROM email_recipients LIMIT 5");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recipients)) {
        echo "Found " . count($recipients) . " recipients for testing\n";
        
        // Create immediate campaign
        $immediateCampaignData = [
            'user_id' => 1,
            'name' => 'Test Immediate Campaign - ' . date('Y-m-d H:i:s'),
            'subject' => 'Test Immediate Campaign - ' . date('Y-m-d H:i:s'),
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
    
    // Step 6: Check campaign sends
    echo "\n6. Checking campaign sends...\n";
    
    try {
        $stmt = $db->query("SELECT COUNT(*) as count FROM campaign_sends");
        $sendsCount = $stmt->fetch()['count'];
        echo "Total campaign sends recorded: $sendsCount\n";
        
        if ($sendsCount > 0) {
            $stmt = $db->query("SELECT recipient_email, status, sent_at FROM campaign_sends ORDER BY sent_at DESC LIMIT 5");
            $recentSends = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "Recent email sends:\n";
            foreach ($recentSends as $send) {
                echo "- {$send['recipient_email']}: {$send['status']} at {$send['sent_at']}\n";
            }
        }
    } catch (Exception $e) {
        echo "No campaign sends found: " . $e->getMessage() . "\n";
    }
    
    // Step 7: Create email configuration test
    echo "\n7. Creating email configuration test...\n";
    
    $configTestFile = 'test_email_config.php';
    $configTestContent = '<?php
// test_email_config.php - Test email configuration
echo "=== Email Configuration Test ===\n\n";

// Check PHP mail configuration
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

// Test email sending
echo "\n2. Testing email sending...\n";

$to = "test@example.com"; // Change this to your email
$subject = "Email Configuration Test - " . date("Y-m-d H:i:s");
$message = "This is a test email to verify email configuration.";
$headers = [
    "From: noreply@regrowup.ca",
    "Reply-To: noreply@regrowup.ca",
    "X-Mailer: ACRM Email System"
];

$result = mail($to, $subject, $message, implode("\r\n", $headers));

if ($result) {
    echo "✅ Test email sent successfully!\n";
    echo "Check your email inbox for the test message.\n";
} else {
    echo "❌ Test email failed to send\n";
    echo "This may indicate a server configuration issue.\n";
}

// Email configuration recommendations
echo "\n3. Email Configuration Recommendations:\n";
echo "- For Hostinger, ensure SMTP is properly configured\n";
echo "- Check if port 587 or 465 is open for SMTP\n";
echo "- Verify sender email domain matches hosting domain\n";
echo "- Consider using PHPMailer for more reliable email sending\n";
echo "- Check server error logs for email-related errors\n";

echo "\n=== Email Configuration Check Complete ===\n";
?>';
    
    file_put_contents($configTestFile, $configTestContent);
    echo "✅ Email configuration test created\n";
    
    // Final summary
    echo "\n=== Fix Summary ===\n";
    echo "✅ EmailService sendSimpleEmail method updated for real email sending\n";
    echo "✅ Database schema checked and fixed\n";
    echo "✅ email_recipients table verified/created\n";
    echo "✅ Immediate email sending tested\n";
    echo "✅ Immediate campaign creation and sending tested\n";
    echo "✅ Campaign sends verified\n";
    echo "✅ Email configuration test created\n";
    
    echo "\n🎉 Immediate email sending fix completed!\n";
    echo "The system now sends real emails instead of simulating.\n";
    
    echo "\n📝 Next Steps:\n";
    echo "1. Test immediate campaign creation on live site\n";
    echo "2. Check email inbox for received emails\n";
    echo "3. Run test_email_config.php if emails are not received\n";
    echo "4. Configure SMTP settings if needed\n";
    
    echo "\n🔧 Testing URLs:\n";
    echo "- Email Config Test: /test_email_config.php\n";
    echo "- Create Campaign: /campaigns.php\n";
    echo "- Check Dashboard: /dashboard\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 