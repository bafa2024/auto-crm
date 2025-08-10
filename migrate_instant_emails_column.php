<?php
/**
 * Migration script to add missing can_send_instant_emails column
 * Run this on live server if the column is missing
 */

require_once 'config/database.php';

try {
    $db = (new Database())->getConnection();
    
    echo "Checking employee_permissions table structure...\n";
    
    // Check if column exists
    $stmt = $db->query("DESCRIBE employee_permissions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('can_send_instant_emails', $columns)) {
        echo "Adding missing can_send_instant_emails column...\n";
        
        $sql = "ALTER TABLE employee_permissions 
                ADD COLUMN can_send_instant_emails TINYINT(1) DEFAULT 1 
                AFTER can_view_all_campaigns";
        
        $db->exec($sql);
        echo "✅ SUCCESS: can_send_instant_emails column added successfully\n";
    } else {
        echo "✅ Column can_send_instant_emails already exists\n";
    }
    
    // Verify the change
    echo "\nCurrent table structure:\n";
    $stmt = $db->query("DESCRIBE employee_permissions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- {$row['Field']} ({$row['Type']}) DEFAULT: {$row['Default']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\nMigration complete.\n";
?>
