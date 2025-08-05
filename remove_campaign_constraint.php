<?php
// Script to remove foreign key constraint on campaign_id
$host = 'localhost';
$dbname = 'autocrm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Connected to database successfully.\n";
    
    // First, let's check the current foreign key constraints
    echo "Checking current foreign key constraints...\n";
    $stmt = $pdo->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'autocrm' 
        AND TABLE_NAME = 'email_recipients' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($constraints)) {
        echo "No foreign key constraints found on email_recipients table.\n";
    } else {
        echo "Found foreign key constraints:\n";
        foreach ($constraints as $constraint) {
            echo "- {$constraint['CONSTRAINT_NAME']}: {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
        }
        
        // Remove the foreign key constraint
        foreach ($constraints as $constraint) {
            if ($constraint['COLUMN_NAME'] === 'campaign_id') {
                echo "Removing foreign key constraint: {$constraint['CONSTRAINT_NAME']}\n";
                $pdo->exec("ALTER TABLE email_recipients DROP FOREIGN KEY {$constraint['CONSTRAINT_NAME']}");
                echo "Foreign key constraint removed successfully.\n";
            }
        }
    }
    
    // Now let's modify the campaign_id column to allow NULL values
    echo "Modifying campaign_id column to allow NULL values...\n";
    $pdo->exec("ALTER TABLE email_recipients MODIFY COLUMN campaign_id INT NULL");
    echo "campaign_id column modified successfully.\n";
    
    echo "Database modification completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?> 