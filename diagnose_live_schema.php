<?php
// diagnose_live_schema.php - Diagnose live server database schema
// Run this script directly on the live server

echo "=== Live Server Database Schema Diagnosis ===\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Check all tables
    echo "1. All tables in database:\n";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "❌ No tables found in database\n";
    } else {
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }
    
    echo "\n";
    
    // Check email_campaigns table specifically
    echo "2. email_campaigns table analysis:\n";
    
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
        $requiredColumns = [
            'id', 'user_id', 'name', 'subject', 'email_content', 'from_name', 
            'from_email', 'schedule_type', 'schedule_date', 'frequency', 
            'status', 'total_recipients', 'sent_count', 'opened_count', 
            'clicked_count', 'created_at', 'updated_at'
        ];
        
        echo "\nRequired columns analysis:\n";
        foreach ($requiredColumns as $column) {
            if (in_array($column, $existingColumns)) {
                echo "✓ $column\n";
            } else {
                echo "❌ $column (MISSING)\n";
            }
        }
        
        // Check for unexpected columns
        $unexpectedColumns = array_diff($existingColumns, $requiredColumns);
        if (!empty($unexpectedColumns)) {
            echo "\nUnexpected columns:\n";
            foreach ($unexpectedColumns as $column) {
                echo "⚠️ $column (not expected)\n";
            }
        }
        
        // Check sample data
        echo "\n3. Sample data check:\n";
        $stmt = $db->query("SELECT COUNT(*) as count FROM email_campaigns");
        $count = $stmt->fetch()['count'];
        echo "- Total campaigns: $count\n";
        
        if ($count > 0) {
            $stmt = $db->query("SELECT * FROM email_campaigns LIMIT 1");
            $sample = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "- Sample campaign data:\n";
            foreach ($sample as $key => $value) {
                $displayValue = is_string($value) && strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value;
                echo "  $key: $displayValue\n";
            }
        }
        
    } else {
        echo "❌ email_campaigns table does not exist\n";
    }
    
    // Check users table
    echo "\n4. users table analysis:\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->fetch();
    
    if ($usersTableExists) {
        echo "✓ users table exists\n";
        
        $stmt = $db->query("DESCRIBE users");
        $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Users table columns:\n";
        foreach ($userColumns as $column) {
            echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
        }
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM users");
        $userCount = $stmt->fetch()['count'];
        echo "- Total users: $userCount\n";
        
    } else {
        echo "❌ users table does not exist\n";
    }
    
    // Check email_recipients table
    echo "\n5. email_recipients table analysis:\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'email_recipients'");
    $recipientsTableExists = $stmt->fetch();
    
    if ($recipientsTableExists) {
        echo "✓ email_recipients table exists\n";
        
        $stmt = $db->query("DESCRIBE email_recipients");
        $recipientColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Recipients table columns:\n";
        foreach ($recipientColumns as $column) {
            echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
        }
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM email_recipients");
        $recipientCount = $stmt->fetch()['count'];
        echo "- Total recipients: $recipientCount\n";
        
    } else {
        echo "❌ email_recipients table does not exist\n";
    }
    
    echo "\n=== Diagnosis Summary ===\n";
    
    if ($tableExists) {
        $missingCount = count(array_diff($requiredColumns, $existingColumns));
        if ($missingCount > 0) {
            echo "❌ $missingCount required columns are missing from email_campaigns table\n";
            echo "🔧 Run migrate_live_database.php to fix the schema\n";
        } else {
            echo "✅ All required columns exist in email_campaigns table\n";
        }
    } else {
        echo "❌ email_campaigns table does not exist\n";
        echo "🔧 Run migrate_live_database.php to create the table\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Diagnosis Complete ===\n";
?> 