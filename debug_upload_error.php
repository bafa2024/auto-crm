<?php
require_once 'config/database.php';
require_once 'services/EmailUploadService.php';

echo "=== Debug Upload Error ===\n\n";

// Test different file scenarios
$testFiles = [
    'test_contacts.csv',
    'Email marketing.xlsx',
    'test_sample.xlsx'
];

foreach ($testFiles as $file) {
    echo "Testing file: $file\n";
    
    if (file_exists($file)) {
        echo "  File exists: YES\n";
        echo "  File size: " . filesize($file) . " bytes\n";
        
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        echo "  Extension: '$extension'\n";
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);
        echo "  MIME type: '$mimeType'\n";
        
        // Test the validation function
        $database = (new Database())->getConnection();
        $uploadService = new EmailUploadService($database);
        $validationErrors = $uploadService->validateFile($file);
        echo "  Validation errors: " . count($validationErrors) . "\n";
        foreach ($validationErrors as $error) {
            echo "    - $error\n";
        }
        
        // Test the upload service directly
        $result = $uploadService->processUploadedFile($file, 1);
        echo "  Upload result: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
        if (!$result['success']) {
            echo "  Error message: " . $result['message'] . "\n";
        }
    } else {
        echo "  File exists: NO\n";
    }
    echo "\n";
}

echo "=== Testing Controller Logic ===\n";

// Simulate the controller's file type checking
$allowedTypes = ["text/csv", "application/vnd.ms-excel", "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"];

foreach ($testFiles as $file) {
    if (file_exists($file)) {
        echo "File: $file\n";
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file);
        finfo_close($finfo);
        
        echo "  MIME type: '$mimeType'\n";
        echo "  Allowed: " . (in_array($mimeType, $allowedTypes) ? 'YES' : 'NO') . "\n";
        
        // Check what the browser would send
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $browserMimeTypes = [
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel'
        ];
        
        if (isset($browserMimeTypes[$extension])) {
            echo "  Expected browser MIME: '" . $browserMimeTypes[$extension] . "'\n";
            echo "  Would pass controller check: " . (in_array($browserMimeTypes[$extension], $allowedTypes) ? 'YES' : 'NO') . "\n";
        }
        
        echo "\n";
    }
}