<?php
require_once 'vendor/autoload.php';
require_once 'config/database.php';
require_once 'services/EmailUploadService.php';

// Initialize database connection
$database = (new Database())->getConnection();
$uploadService = new EmailUploadService($database);

echo "=== XLSX Upload Test ===\n\n";

// Test XLSX file upload
$xlsxFile = 'Email marketing.xlsx';
if (file_exists($xlsxFile)) {
    echo "Testing XLSX file: $xlsxFile\n";
    
    // Test with campaign ID = 1
    $result = $uploadService->processUploadedFile($xlsxFile, 1);
    
    echo "Result:\n";
    echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
    
    if ($result['success']) {
        echo "Total rows: " . $result['total_rows'] . "\n";
        echo "Imported: " . $result['imported'] . "\n";
        echo "Failed: " . $result['failed'] . "\n";
        
        if (!empty($result['errors'])) {
            echo "Errors (first 10):\n";
            foreach (array_slice($result['errors'], 0, 10) as $error) {
                echo "  - $error\n";
            }
            if (count($result['errors']) > 10) {
                echo "  ... and " . (count($result['errors']) - 10) . " more errors\n";
            }
        }
    } else {
        echo "Error: " . $result['message'] . "\n";
    }
} else {
    echo "XLSX test file not found: $xlsxFile\n";
}

// Test creating a sample XLSX file for testing
echo "\n=== Creating Sample XLSX File ===\n";
try {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Add headers
    $sheet->setCellValue('A1', 'Email');
    $sheet->setCellValue('B1', 'Name');
    $sheet->setCellValue('C1', 'Company');
    
    // Add sample data
    $sampleData = [
        ['test1@example.com', 'Test User 1', 'Test Company 1'],
        ['test2@example.com', 'Test User 2', 'Test Company 2'],
        ['test3@example.com', 'Test User 3', 'Test Company 3'],
        ['test4@example.com', 'Test User 4', 'Test Company 4'],
        ['test5@example.com', 'Test User 5', 'Test Company 5']
    ];
    
    $row = 2;
    foreach ($sampleData as $data) {
        $sheet->setCellValue('A' . $row, $data[0]);
        $sheet->setCellValue('B' . $row, $data[1]);
        $sheet->setCellValue('C' . $row, $data[2]);
        $row++;
    }
    
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $testFile = 'test_sample.xlsx';
    $writer->save($testFile);
    
    echo "Sample XLSX file created: $testFile\n";
    
    // Test the sample file
    echo "\n=== Testing Sample XLSX File ===\n";
    $result = $uploadService->processUploadedFile($testFile, 1);
    
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
    
} catch (Exception $e) {
    echo "Error creating/testing sample XLSX file: " . $e->getMessage() . "\n";
}