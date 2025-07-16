<?php
// database/migrate.php - Database migration script

require_once __DIR__ . '/../config/database.php';

echo "AutoDial Pro CRM Database Migration\n";
echo "==================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Database connection failed\n");
    }
    
    echo "✓ Database connection successful\n\n";
    
    // Check if company_name column exists in users table
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'company_name'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "Adding company_name column to users table...\n";
        
        $sql = "ALTER TABLE users ADD COLUMN company_name VARCHAR(255) AFTER last_name";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute()) {
            echo "✓ company_name column added successfully\n";
        } else {
            echo "❌ Failed to add company_name column\n";
        }
    } else {
        echo "✓ company_name column already exists\n";
    }
    
    // Check if tables exist, create them if they don't
    $tables = ['contacts', 'email_campaigns', 'email_templates'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            echo "Creating $table table...\n";
            
            // Read schema file and execute table creation
            $schemaFile = __DIR__ . '/schema.sql';
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                
                // Extract table creation for this specific table
                if (preg_match("/CREATE TABLE IF NOT EXISTS $table \(.*?\);/s", $schema, $matches)) {
                    $createTableSql = $matches[0];
                    $stmt = $db->prepare($createTableSql);
                    
                    if ($stmt->execute()) {
                        echo "✓ $table table created successfully\n";
                    } else {
                        echo "❌ Failed to create $table table\n";
                    }
                }
            }
        } else {
            echo "✓ $table table already exists\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
} 