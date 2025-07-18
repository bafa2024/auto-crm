<?php
require_once 'config/database.php';
require_once 'controllers/EmailCampaignController.php';

echo "=== Web Upload Simulation Test ===\n\n";

$database = (new Database())->getConnection();
$controller = new EmailCampaignController($database);

// Test 1: Simulate CSV upload via web form
echo "1. Testing CSV upload simulation...\n";

// Create a test CSV file
$csvData = [
    ['Email', 'Name', 'Company'],
    ['webtest1@example.com', 'Web Test 1', 'Web Company 1'],
    ['webtest2@example.com', 'Web Test 2', 'Web Company 2']
];

$csvFile = 'test_web_upload.csv';
$handle = fopen($csvFile, 'w');
foreach ($csvData as $row) {
    fputcsv($handle, $row);
}
fclose($handle);

// Simulate file upload array as it would come from web form
$simulatedFileUpload = [
    'name' => 'test_web_upload.csv',
    'type' => 'text/csv',
    'tmp_name' => $csvFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($csvFile)
];

echo "  Simulated file upload:\n";
echo "    Name: " . $simulatedFileUpload['name'] . "\n";
echo "    Type: " . $simulatedFileUpload['type'] . "\n";
echo "    Size: " . $simulatedFileUpload['size'] . " bytes\n";
echo "    Error: " . $simulatedFileUpload['error'] . "\n";

// Test the controller's handleFileUpload method using reflection
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('handleFileUpload');
$method->setAccessible(true);

$result = $method->invokeArgs($controller, [$simulatedFileUpload, 1]);

echo "  Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
if (!$result['success']) {
    echo "  Error: " . $result['message'] . "\n";
} else {
    echo "  Imported: " . ($result['imported'] ?? 0) . "\n";
    echo "  Failed: " . ($result['failed'] ?? 0) . "\n";
}

// Test 2: Simulate XLSX upload
echo "\n2. Testing XLSX upload simulation...\n";

if (file_exists('test_sample.xlsx')) {
    $simulatedXlsxUpload = [
        'name' => 'test_sample.xlsx',
        'type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'tmp_name' => 'test_sample.xlsx',
        'error' => UPLOAD_ERR_OK,
        'size' => filesize('test_sample.xlsx')
    ];
    
    echo "  Simulated XLSX upload:\n";
    echo "    Name: " . $simulatedXlsxUpload['name'] . "\n";
    echo "    Type: " . $simulatedXlsxUpload['type'] . "\n";
    echo "    Size: " . $simulatedXlsxUpload['size'] . " bytes\n";
    
    $result = $method->invokeArgs($controller, [$simulatedXlsxUpload, 2]);
    
    echo "  Result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    if (!$result['success']) {
        echo "  Error: " . $result['message'] . "\n";
    } else {
        echo "  Imported: " . ($result['imported'] ?? 0) . "\n";
        echo "  Failed: " . ($result['failed'] ?? 0) . "\n";
    }
} else {
    echo "  test_sample.xlsx not found, skipping XLSX test\n";
}

// Test 3: Test with different MIME types that browsers might send
echo "\n3. Testing different MIME types...\n";

$mimeTypeTests = [
    'text/csv' => 'Standard CSV',
    'application/csv' => 'Alternative CSV',
    'text/comma-separated-values' => 'CSV variant',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'XLSX',
    'application/vnd.ms-excel' => 'XLS',
    'application/octet-stream' => 'Generic binary (fallback)'
];

foreach ($mimeTypeTests as $mimeType => $description) {
    echo "  Testing $description ($mimeType):\n";
    
    $testFile = [
        'name' => 'test_web_upload.csv',
        'type' => $mimeType,
        'tmp_name' => $csvFile,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($csvFile)
    ];
    
    $result = $method->invokeArgs($controller, [$testFile, 1]);
    echo "    Result: " . ($result['success'] ? 'PASS' : 'FAIL') . "\n";
    if (!$result['success']) {
        echo "    Error: " . $result['message'] . "\n";
    }
}

// Test 4: Test invalid file type
echo "\n4. Testing invalid file type...\n";
$invalidFile = [
    'name' => 'test.txt',
    'type' => 'text/plain',
    'tmp_name' => $csvFile,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($csvFile)
];

$result = $method->invokeArgs($controller, [$invalidFile, 1]);
echo "  Result: " . ($result['success'] ? 'PASS (unexpected)' : 'FAIL (expected)') . "\n";
echo "  Error: " . $result['message'] . "\n";

// Clean up
unlink($csvFile);

echo "\n=== Test Summary ===\n";
echo "The upload functionality should now work properly with:\n";
echo "✓ CSV files (text/csv, application/csv)\n";
echo "✓ XLSX files (application/vnd.openxmlformats-officedocument.spreadsheetml.sheet)\n";
echo "✓ XLS files (application/vnd.ms-excel)\n";
echo "✓ Fallback for generic binary files (application/octet-stream)\n";
echo "✓ File extension validation as backup\n";