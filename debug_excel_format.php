<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

echo "=== Debug Excel Format ===\n\n";

$xlsxFile = 'Email marketing.xlsx';
if (file_exists($xlsxFile)) {
    try {
        $spreadsheet = IOFactory::load($xlsxFile);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        
        echo "Total rows found: " . count($rows) . "\n";
        
        if (count($rows) > 0) {
            echo "\nFirst 5 rows structure:\n";
            echo "==============================\n";
            
            for ($i = 1; $i <= min(5, count($rows)); $i++) {
                echo "Row $i:\n";
                if (isset($rows[$i])) {
                    foreach ($rows[$i] as $col => $value) {
                        echo "  [$col] => '" . trim($value) . "'\n";
                    }
                }
                echo "\n";
            }
            
            // Check headers specifically
            echo "Headers analysis:\n";
            echo "=================\n";
            if (isset($rows[1])) {
                $headers = array_map('trim', array_values($rows[1]));
                foreach ($headers as $index => $header) {
                    echo "Index $index: '$header'\n";
                    $headerLower = strtolower($header);
                    if (strpos($headerLower, 'email') !== false) {
                        echo "  -> EMAIL COLUMN FOUND!\n";
                    }
                }
            }
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "File not found: $xlsxFile\n";
}