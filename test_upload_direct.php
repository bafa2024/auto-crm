<?php
require_once 'config/database.php';
require_once 'services/EmailUploadService.php';

echo "Testing Email Upload Service\n";
echo "============================\n\n";

try {
    // Test database connection
    echo "1. Testing database connection...\n";
    $db = new Database();
    $database = $db->getConnection();
    $stmt = $database->query("SELECT 1");
    echo "✓ Database connection successful\n\n";
    
    // Test EmailUploadService
    echo "2. Testing EmailUploadService...\n";
    $uploadService = new EmailUploadService($database);
    echo "✓ EmailUploadService created\n\n";
    
    // Test CSV parsing
    echo "3. Testing CSV file parsing...\n";
    $csvFile = 'test_contacts.csv';
    
    if (file_exists($csvFile)) {
        $result = $uploadService->processUploadedFile($csvFile, null);
        
        if ($result['success']) {
            echo "✓ CSV processed successfully\n";
            echo "  - Total rows: " . ($result['total_rows'] ?? 0) . "\n";
            echo "  - Imported: " . ($result['imported'] ?? 0) . "\n";
            echo "  - Failed: " . ($result['failed'] ?? 0) . "\n";
            
            if (!empty($result['errors'])) {
                echo "  - Errors:\n";
                foreach (array_slice($result['errors'], 0, 5) as $error) {
                    echo "    * $error\n";
                }
            }
        } else {
            echo "✗ CSV processing failed: " . $result['message'] . "\n";
        }
    } else {
        echo "✗ Test CSV file not found: $csvFile\n";
    }
    
    echo "\n4. Testing database queries...\n";
    
    // Check if email_recipients table exists
    try {
        $stmt = $database->query("SELECT COUNT(*) FROM email_recipients");
        $count = $stmt->fetchColumn();
        echo "✓ Found $count recipients in database\n";
        
        // Show sample data
        $stmt = $database->query("SELECT * FROM email_recipients LIMIT 5");
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($recipients)) {
            echo "\nSample recipients:\n";
            foreach ($recipients as $recipient) {
                echo "  - " . $recipient['email'] . " (" . ($recipient['name'] ?? 'No name') . ")\n";
            }
        }
        
    } catch (Exception $e) {
        echo "✗ email_recipients table not found or error: " . $e->getMessage() . "\n";
        echo "  Creating table...\n";
        
        // Create table
        $createTable = "
        CREATE TABLE IF NOT EXISTS email_recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            company VARCHAR(255),
            status VARCHAR(50) DEFAULT 'pending',
            tracking_id VARCHAR(64),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $database->exec($createTable);
        echo "✓ email_recipients table created\n";
        
        // Retry the upload
        echo "\n5. Retrying CSV upload...\n";
        $result = $uploadService->processUploadedFile($csvFile, null);
        
        if ($result['success']) {
            echo "✓ CSV processed successfully after table creation\n";
            echo "  - Imported: " . ($result['imported'] ?? 0) . "\n";
            echo "  - Failed: " . ($result['failed'] ?? 0) . "\n";
        } else {
            echo "✗ CSV processing still failed: " . $result['message'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
?>