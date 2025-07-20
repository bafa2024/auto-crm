<?php
// check_live_schema.php - Check live server database schema
// Run this script directly on the live server

echo "=== Live Server Database Schema Check ===\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Check email_campaigns table
    echo "Checking email_campaigns table...\n";
    $stmt = $db->query("SHOW TABLES LIKE 'email_campaigns'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ email_campaigns table exists\n\n";
        
        // Get table structure
        $stmt = $db->query("DESCRIBE email_campaigns");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Current columns:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
        }
        
        // Check for required columns
        $existingColumns = array_column($columns, 'Field');
        $requiredColumns = ['id', 'user_id', 'name', 'subject', 'email_content', 'from_name', 'from_email', 'status', 'created_at'];
        
        echo "\nChecking required columns:\n";
        foreach ($requiredColumns as $column) {
            if (in_array($column, $existingColumns)) {
                echo "✓ $column\n";
            } else {
                echo "❌ $column (MISSING)\n";
            }
        }
        
        // Check for optional columns
        $optionalColumns = ['schedule_type', 'schedule_date', 'frequency', 'total_recipients', 'sent_count', 'opened_count', 'clicked_count', 'updated_at'];
        
        echo "\nChecking optional columns:\n";
        foreach ($optionalColumns as $column) {
            if (in_array($column, $existingColumns)) {
                echo "✓ $column\n";
            } else {
                echo "⚠️ $column (missing but optional)\n";
            }
        }
        
    } else {
        echo "❌ email_campaigns table does not exist\n";
    }
    
    // Check other related tables
    echo "\nChecking related tables:\n";
    $tables = ['users', 'email_recipients', 'campaign_sends'];
    
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch();
        if ($exists) {
            echo "✓ $table table exists\n";
        } else {
            echo "❌ $table table missing\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Check Complete ===\n";
?> 