<?php
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    $spreadsheet = IOFactory::load('Email marketing.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();

    echo "Columns (First Row):\n";
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        echo $col . ': ' . $worksheet->getCell($col . '1')->getValue() . "\n";
    }

    echo "\nSample data (First 5 rows):\n";
    for ($row = 1; $row <= min(5, $highestRow); $row++) {
        for ($col = 'A'; $col <= $highestColumn; $col++) {
            echo $worksheet->getCell($col . $row)->getValue() . "\t";
        }
        echo "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}