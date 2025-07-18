<?php
require_once 'config/database.php';
require_once 'services/EmailUploadService.php';

echo "=== Fresh Upload Test ===\n\n";

// Initialize database connection
$database = (new Database())->getConnection();
$uploadService = new EmailUploadService($database);

// Create fresh CSV data
$freshCsvData = [
    ['Email', 'Name', 'Company'],
    ['fresh1@example.com', 'Fresh User 1', 'Fresh Company 1'],
    ['fresh2@example.com', 'Fresh User 2', 'Fresh Company 2'],
    ['fresh3@example.com', 'Fresh User 3', 'Fresh Company 3'],
    ['fresh4@example.com', 'Fresh User 4', 'Fresh Company 4'],
    ['fresh5@example.com', 'Fresh User 5', 'Fresh Company 5']
];

// Create fresh CSV file
$csvFile = 'test_fresh_contacts.csv';
$handle = fopen($csvFile, 'w');
foreach ($freshCsvData as $row) {
    fputcsv($handle, $row);
}
fclose($handle);

echo "Created fresh CSV file: $csvFile\n";

// Test upload
echo "\n=== Testing Fresh CSV Upload ===\n";
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

// Test the web form upload workflow
echo "\n=== Testing Web Form Upload Workflow ===\n";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['campaign_id'] = '1';

// Simulate file upload
$_FILES['email_file'] = [
    'name' => 'test_fresh_contacts.csv',
    'tmp_name' => $csvFile,
    'size' => filesize($csvFile),
    'type' => 'text/csv',
    'error' => UPLOAD_ERR_OK
];

// Test the controller logic
require_once 'controllers/EmailCampaignController.php';
$controller = new EmailCampaignController($database);

echo "Testing controller file upload handling...\n";

// Test the private method by using reflection
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('handleFileUpload');
$method->setAccessible(true);

$uploadResult = $method->invokeArgs($controller, [$_FILES['email_file'], 1]);

echo "Upload result:\n";
echo "Success: " . ($uploadResult['success'] ? 'Yes' : 'No') . "\n";
echo "Message: " . ($uploadResult['message'] ?? 'No message') . "\n";

if (isset($uploadResult['imported'])) {
    echo "Imported: " . $uploadResult['imported'] . "\n";
}
if (isset($uploadResult['failed'])) {
    echo "Failed: " . $uploadResult['failed'] . "\n";
}

// Clean up
unlink($csvFile);