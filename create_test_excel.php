<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

echo "Creating test Excel file...\n";

// Create new Spreadsheet object
$spreadsheet = new Spreadsheet();

// Set document properties
$spreadsheet->getProperties()->setCreator("ACRM Test")
    ->setLastModifiedBy("ACRM Test")
    ->setTitle("Test Email Contacts")
    ->setSubject("Test Excel Upload")
    ->setDescription("Test file for Excel upload functionality");

// Add header row
$spreadsheet->getActiveSheet()->setCellValue('A1', 'Email');
$spreadsheet->getActiveSheet()->setCellValue('B1', 'Name');
$spreadsheet->getActiveSheet()->setCellValue('C1', 'Company');
$spreadsheet->getActiveSheet()->setCellValue('D1', 'DOT');

// Add sample data
$sampleData = [
    ['john.doe@example.com', 'John Doe', 'Example Corp', '123456'],
    ['jane.smith@test.com', 'Jane Smith', 'Test Solutions', '789012'],
    ['bob.wilson@demo.com', 'Bob Wilson', 'Demo Company', '345678'],
    ['alice.johnson@sample.com', 'Alice Johnson', 'Sample Inc', '901234'],
    ['charlie.brown@test.org', 'Charlie Brown', 'Test Organization', '567890'],
    ['diana.prince@example.net', 'Diana Prince', 'Example Network', '234567'],
    ['bruce.wayne@test.biz', 'Bruce Wayne', 'Test Business', '890123'],
    ['clark.kent@demo.org', 'Clark Kent', 'Demo Organization', '456789'],
    ['peter.parker@sample.com', 'Peter Parker', 'Sample Corp', '012345'],
    ['tony.stark@test.com', 'Tony Stark', 'Test Industries', '678901']
];

$row = 2;
foreach ($sampleData as $data) {
    $spreadsheet->getActiveSheet()->setCellValue('A' . $row, $data[0]);
    $spreadsheet->getActiveSheet()->setCellValue('B' . $row, $data[1]);
    $spreadsheet->getActiveSheet()->setCellValue('C' . $row, $data[2]);
    $spreadsheet->getActiveSheet()->setCellValue('D' . $row, $data[3]);
    $row++;
}

// Auto-size columns
foreach (range('A', 'D') as $col) {
    $spreadsheet->getActiveSheet()->getColumnDimension($col)->setAutoSize(true);
}

// Save file
$writer = new Xlsx($spreadsheet);
$filename = 'test_contacts.xlsx';
$writer->save($filename);

echo "Test Excel file created: $filename\n";
echo "File size: " . filesize($filename) . " bytes\n";
echo "Contains " . count($sampleData) . " sample contacts\n";