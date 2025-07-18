<?php
// Create a simple test to mimic actual form submission
session_start();
require_once 'config/database.php';

echo "=== Actual Form Upload Test ===\n\n";

// Create test file
$csvData = [
    ['Email', 'Name', 'Company'],
    ['actualtest1@example.com', 'Actual Test 1', 'Actual Company 1'],
    ['actualtest2@example.com', 'Actual Test 2', 'Actual Company 2']
];

$csvFile = 'test_actual_upload.csv';
$handle = fopen($csvFile, 'w');
foreach ($csvData as $row) {
    fputcsv($handle, $row);
}
fclose($handle);

// Test 1: Direct service call (this should work)
echo "1. Testing direct service call...\n";
require_once 'services/EmailUploadService.php';
$database = (new Database())->getConnection();
$uploadService = new EmailUploadService($database);
$result = $uploadService->processUploadedFile($csvFile, 1);
echo "   Direct service result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if ($result['success']) {
    echo "   Imported: " . $result['imported'] . "\n";
} else {
    echo "   Error: " . $result['message'] . "\n";
}

// Test 2: Test the form processing logic from test_email_upload_form.php
echo "\n2. Testing form processing logic...\n";

// Simulate POST request and file upload
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['campaign_id'] = '1';

// Create a temporary file in the system temp directory to simulate uploaded file
$tempFile = tempnam(sys_get_temp_dir(), 'upload_test_');
copy($csvFile, $tempFile);

$_FILES['email_file'] = [
    'name' => 'test_actual_upload.csv',
    'type' => 'text/csv',
    'tmp_name' => $tempFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tempFile)
];

echo "   Simulated $_FILES array:\n";
echo "   Name: " . $_FILES['email_file']['name'] . "\n";
echo "   Type: " . $_FILES['email_file']['type'] . "\n";
echo "   Size: " . $_FILES['email_file']['size'] . "\n";
echo "   Error: " . $_FILES['email_file']['error'] . "\n";
echo "   Temp file exists: " . (file_exists($_FILES['email_file']['tmp_name']) ? 'YES' : 'NO') . "\n";

// Now test the upload form logic
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['email_file'])) {
    $uploadService = new EmailUploadService($database);
    
    $file = $_FILES['email_file'];
    $campaignId = $_POST['campaign_id'] ?? null;
    
    echo "   Processing file upload...\n";
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = 'File upload failed: ' . $file['error'];
        $messageType = 'danger';
    } else {
        // Check file extension only (simplified version)
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            $message = 'Invalid file type. Please upload CSV or Excel file.';
            $messageType = 'danger';
        } else {
            // Process the file directly from tmp_name
            $result = $uploadService->processUploadedFile($file['tmp_name'], $campaignId, $file['name']);
            
            if ($result['success']) {
                $message = "Upload successful! Imported: {$result['imported']} contacts";
                if ($result['failed'] > 0) {
                    $message .= ", Failed: {$result['failed']}";
                }
                $messageType = 'success';
            } else {
                $message = 'Upload failed: ' . $result['message'];
                $messageType = 'danger';
            }
        }
    }
}

echo "   Form processing result: $messageType\n";
echo "   Message: $message\n";

// Clean up
unlink($csvFile);
unlink($tempFile);

echo "\n=== Test Complete ===\n";
echo "If you're still getting 'Unsupported file format' error,\n";
echo "it might be because your browser is sending a different MIME type.\n";
echo "Try uploading a file and check what MIME type it reports.\n";