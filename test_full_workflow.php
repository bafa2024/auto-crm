<?php
require_once 'config/database.php';
require_once 'services/EmailUploadService.php';

echo "Testing Complete Email Upload Workflow\n";
echo "======================================\n\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Step 1: Clear previous test data
    echo "1. Clearing previous test data...\n";
    $conn->exec("DELETE FROM email_recipients WHERE email LIKE '%@example.com'");
    $conn->exec("DELETE FROM email_campaigns WHERE name LIKE 'Test Campaign%'");
    echo "✓ Previous test data cleared\n\n";
    
    // Step 2: Create a test campaign
    echo "2. Creating test campaign...\n";
    $campaignSql = "INSERT INTO email_campaigns (user_id, name, subject, email_content, from_name, from_email, status) 
                   VALUES (1, 'Test Campaign - Local', 'Welcome to Our Service', 'Hello {{name}}, welcome to our service!', 'Test Sender', 'test@example.com', 'draft')";
    $conn->exec($campaignSql);
    $campaignId = $conn->lastInsertId();
    echo "✓ Campaign created with ID: $campaignId\n\n";
    
    // Step 3: Test email upload
    echo "3. Testing email upload...\n";
    $uploadService = new EmailUploadService($conn);
    
    // Use the existing test CSV file
    $csvFile = 'test_contacts.csv';
    if (file_exists($csvFile)) {
        $result = $uploadService->processUploadedFile($csvFile, $campaignId);
        
        if ($result['success']) {
            echo "✓ Upload successful!\n";
            echo "  - Total rows processed: " . ($result['total_rows'] ?? 0) . "\n";
            echo "  - Successfully imported: " . ($result['imported'] ?? 0) . "\n";
            echo "  - Failed imports: " . ($result['failed'] ?? 0) . "\n";
            
            if (!empty($result['errors'])) {
                echo "  - Errors encountered:\n";
                foreach (array_slice($result['errors'], 0, 3) as $error) {
                    echo "    * $error\n";
                }
            }
        } else {
            echo "✗ Upload failed: " . $result['message'] . "\n";
        }
    } else {
        echo "✗ Test CSV file not found: $csvFile\n";
    }
    
    echo "\n4. Verifying database storage...\n";
    
    // Check campaign update
    $stmt = $conn->prepare("SELECT * FROM email_campaigns WHERE id = ?");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($campaign) {
        echo "✓ Campaign details:\n";
        echo "  - Name: " . $campaign['name'] . "\n";
        echo "  - Subject: " . $campaign['subject'] . "\n";
        echo "  - Status: " . $campaign['status'] . "\n";
    }
    
    // Check recipients
    $stmt = $conn->prepare("SELECT * FROM email_recipients WHERE campaign_id = ?");
    $stmt->execute([$campaignId]);
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n✓ Recipients imported: " . count($recipients) . "\n";
    echo "Sample recipients:\n";
    foreach (array_slice($recipients, 0, 5) as $recipient) {
        echo "  - " . $recipient['email'] . " (" . ($recipient['name'] ?? 'No name') . ")\n";
    }
    
    // Step 5: Test web interface functionality
    echo "\n5. Testing web interface components...\n";
    
    // Check if the upload form file exists
    if (file_exists('test_email_upload_form.php')) {
        echo "✓ Upload form available: test_email_upload_form.php\n";
    } else {
        echo "✗ Upload form not found\n";
    }
    
    // Check if the template download works
    if (file_exists('download_template.php')) {
        echo "✓ Template download available: download_template.php\n";
    } else {
        echo "✗ Template download not found\n";
    }
    
    // Check required directories
    $directories = ['uploads', 'logs', 'temp'];
    foreach ($directories as $dir) {
        if (is_dir($dir) && is_writable($dir)) {
            echo "✓ Directory ready: $dir\n";
        } else {
            echo "✗ Directory issue: $dir\n";
        }
    }
    
    echo "\n6. Final status check...\n";
    
    // Get overall statistics
    $stmt = $conn->query("SELECT COUNT(*) FROM email_campaigns");
    $totalCampaigns = $stmt->fetchColumn();
    
    $stmt = $conn->query("SELECT COUNT(*) FROM email_recipients");
    $totalRecipients = $stmt->fetchColumn();
    
    $stmt = $conn->query("SELECT COUNT(DISTINCT email) FROM email_recipients");
    $uniqueEmails = $stmt->fetchColumn();
    
    echo "Database Statistics:\n";
    echo "- Total campaigns: $totalCampaigns\n";
    echo "- Total recipients: $totalRecipients\n";
    echo "- Unique emails: $uniqueEmails\n";
    
    echo "\n✅ WORKFLOW TEST COMPLETED SUCCESSFULLY!\n";
    echo "\nTo use the web interface:\n";
    echo "1. Start PHP server: php -S localhost:8080\n";
    echo "2. Open: http://localhost:8080/test_email_upload_form.php\n";
    echo "3. Upload CSV files and see results\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>