<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    // Read the Email marketing.xlsx template
    $inputFileName = 'Email marketing.xlsx';
    $spreadsheet = IOFactory::load($inputFileName);
    $worksheet = $spreadsheet->getActiveSheet();
    
    echo "=== Email Marketing Template Analysis ===\n\n";
    
    // Get dimensions
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
    
    echo "Total rows: $highestRow\n";
    echo "Total columns: $highestColumn ($highestColumnIndex)\n\n";
    
    // Read headers (first row)
    echo "Column Headers:\n";
    $headers = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $cellValue = $worksheet->getCellByColumnAndRow($col, 1)->getValue();
        $headers[$col] = $cellValue;
        echo "Column " . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . ": $cellValue\n";
    }
    
    echo "\nSample Data (rows 2-5):\n";
    echo str_repeat("-", 80) . "\n";
    
    // Read sample data
    for ($row = 2; $row <= min(5, $highestRow); $row++) {
        echo "Row $row:\n";
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            echo "  " . $headers[$col] . ": $cellValue\n";
        }
        echo "\n";
    }
    
    // Analyze data types and patterns
    echo "\nData Analysis:\n";
    echo str_repeat("-", 80) . "\n";
    
    $emailColumn = null;
    $nameColumn = null;
    $companyColumn = null;
    
    // Find specific columns
    foreach ($headers as $col => $header) {
        $headerLower = strtolower($header);
        if (strpos($headerLower, 'email') !== false) {
            $emailColumn = $col;
            echo "Email column found: Column " . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . " ('$header')\n";
        }
        if (strpos($headerLower, 'name') !== false && strpos($headerLower, 'email') === false) {
            $nameColumn = $col;
            echo "Name column found: Column " . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . " ('$header')\n";
        }
        if (strpos($headerLower, 'company') !== false || strpos($headerLower, 'organization') !== false) {
            $companyColumn = $col;
            echo "Company column found: Column " . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . " ('$header')\n";
        }
    }
    
    // Count total data rows (excluding header)
    $dataRowCount = 0;
    for ($row = 2; $row <= $highestRow; $row++) {
        $hasData = false;
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
            if (!empty($cellValue)) {
                $hasData = true;
                break;
            }
        }
        if ($hasData) {
            $dataRowCount++;
        }
    }
    
    echo "\nTotal data rows: $dataRowCount\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}