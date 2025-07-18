<?php
require_once 'config/database.php';
require_once 'services/EmailUploadService.php';

// Initialize database connection
$database = (new Database())->getConnection();
$uploadService = new EmailUploadService($database);

echo "=== CSV Upload Test ===\n\n";

// Test CSV file upload
$csvFile = 'test_contacts.csv';
if (file_exists($csvFile)) {
    echo "Testing CSV file: $csvFile\n";
    
    // Test with campaign ID = 2
    $result = $uploadService->processUploadedFile($csvFile, 2);
    
    echo "Result:\n";
    echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    
    if ($result['success']) {
        echo "Total rows: " . $result['total_rows'] . "\n";
        echo "Imported: " . $result['imported'] . "\n";
        echo "Failed: " . $result['failed'] . "\n";
        
        if (!empty($result['errors'])) {
            echo "Errors:\n";
            foreach ($result['errors'] as $error) {
                echo "  - $error\n";
            }
        }
    } else {
        echo "Error: " . $result['message'] . "\n";
    }
} else {
    echo "CSV test file not found: $csvFile\n";
}

// Test getting recent uploads
echo "\n=== Recent Uploads Test ===\n";
try {
    $stmt = $database->query("
        SELECT 
            er.campaign_id,
            ec.name as campaign_name,
            COUNT(er.id) as recipient_count,
            MIN(er.created_at) as upload_date
        FROM email_recipients er
        LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
        GROUP BY er.campaign_id, ec.name
        ORDER BY upload_date DESC
        LIMIT 10
    ");
    $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent uploads found: " . count($recentUploads) . "\n";
    foreach ($recentUploads as $upload) {
        echo "  - Campaign: " . ($upload['campaign_name'] ?? 'No Campaign') . 
             ", Recipients: " . $upload['recipient_count'] . 
             ", Date: " . $upload['upload_date'] . "\n";
    }
} catch (Exception $e) {
    echo "Error fetching recent uploads: " . $e->getMessage() . "\n";
}

echo "\n=== Database Table Check ===\n";
try {
    $stmt = $database->query("SELECT COUNT(*) as total FROM email_recipients");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total email recipients in database: " . $result['total'] . "\n";
} catch (Exception $e) {
    echo "Error checking database: " . $e->getMessage() . "\n";
}