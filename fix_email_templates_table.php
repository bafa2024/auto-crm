<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Fixing email_templates table structure...\n\n";
    
    // Define the expected columns based on the EmailTemplate model
    $expectedColumns = [
        'id' => 'int(11) AUTO_INCREMENT PRIMARY KEY',
        'name' => 'varchar(255)',
        'category' => 'varchar(100)',
        'subject' => 'varchar(255)',
        'content' => 'text',
        'thumbnail' => 'varchar(255)',
        'variables' => 'text',
        'created_by' => 'int(11)',
        'is_public' => 'tinyint(1) DEFAULT 1',
        'created_at' => 'timestamp DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];
    
    // Get current table structure
    $stmt = $db->query("DESCRIBE email_templates");
    $existingColumns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existingColumns[$row['Field']] = $row['Type'];
    }
    
    echo "Current columns: " . implode(', ', array_keys($existingColumns)) . "\n\n";
    
    // Add missing columns
    $addedColumns = [];
    foreach ($expectedColumns as $columnName => $columnDef) {
        if (!array_key_exists($columnName, $existingColumns)) {
            try {
                // Handle different column types
                if (strpos($columnDef, 'AUTO_INCREMENT') !== false) {
                    // Skip primary key if it already exists
                    continue;
                }
                
                $sql = "ALTER TABLE email_templates ADD COLUMN `$columnName` $columnDef";
                $db->exec($sql);
                $addedColumns[] = $columnName;
                echo "✓ Added column: $columnName\n";
            } catch (Exception $e) {
                echo "✗ Failed to add column $columnName: " . $e->getMessage() . "\n";
            }
        } else {
            echo "- Column $columnName already exists\n";
        }
    }
    
    if (empty($addedColumns)) {
        echo "\n✓ All required columns are already present!\n";
    } else {
        echo "\n✓ Successfully added columns: " . implode(', ', $addedColumns) . "\n";
    }
    
    // Update existing records to have is_public = 1 if the column was just added
    if (in_array('is_public', $addedColumns)) {
        try {
            $db->exec("UPDATE email_templates SET is_public = 1 WHERE is_public IS NULL");
            echo "✓ Updated existing records to be public by default\n";
        } catch (Exception $e) {
            echo "✗ Failed to update existing records: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Email templates table structure is now fixed!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 