<?php
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load('Email marketing.xlsx');
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray();
    
    echo "Headers:\n";
    print_r($rows[0]);
    echo "\nFirst 5 rows:\n";
    for ($i = 1; $i <= 5 && $i < count($rows); $i++) {
        print_r($rows[$i]);
    }
} catch (Exception $e) {
    echo "Error reading Excel file: " . $e->getMessage();
} 