<?php
// fix_recipients_table.php - Fix email_recipients table structure

echo "=== Fixing Email Recipients Table ===\n\n";

// Use local SQLite database
$dbPath = __DIR__ . '/database/autocrm_local.db';

try {
    // Create SQLite database connection
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to local SQLite database\n\n";
    
    // Check current table structure
    echo "1. Checking current table structure...\n";
    $stmt = $db->prepare("PRAGMA table_info(email_recipients)");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "Current columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Check if updated_at column exists
    $hasUpdatedAt = false;
    foreach ($columns as $column) {
        if ($column['name'] === 'updated_at') {
            $hasUpdatedAt = true;
            break;
        }
    }
    
    if (!$hasUpdatedAt) {
        echo "\n2. Recreating table with correct structure...\n";
        
        // Backup existing data
        $stmt = $db->prepare("SELECT * FROM email_recipients");
        $stmt->execute();
        $existingData = $stmt->fetchAll();
        
        echo "Backed up " . count($existingData) . " existing records\n";
        
        // Drop and recreate table
        $db->exec("DROP TABLE IF EXISTS email_recipients");
        
        $createRecipientsTable = "CREATE TABLE email_recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            name TEXT,
            company TEXT,
            dot TEXT,
            custom_data TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($createRecipientsTable);
        echo "✓ Table recreated with correct structure\n";
        
        // Restore data if any
        if (!empty($existingData)) {
            echo "Restoring existing data...\n";
            $stmt = $db->prepare("INSERT INTO email_recipients (email, name, company, dot, custom_data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $restoredCount = 0;
            foreach ($existingData as $row) {
                $stmt->execute([
                    $row['email'],
                    $row['name'],
                    $row['company'],
                    $row['dot'] ?? null,
                    $row['custom_data'] ?? null,
                    $row['created_at'] ?? date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s') // updated_at
                ]);
                $restoredCount++;
            }
            echo "✓ Restored $restoredCount records\n";
        }
    } else {
        echo "\n2. updated_at column already exists\n";
    }
    
    // Verify the fix
    echo "\n3. Verifying table structure...\n";
    $stmt = $db->prepare("PRAGMA table_info(email_recipients)");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "Updated columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Test inserting a recipient
    echo "\n4. Testing recipient insertion...\n";
    $stmt = $db->prepare("INSERT INTO email_recipients (email, name, company, created_at, updated_at) VALUES (?, ?, ?, datetime('now'), datetime('now'))");
    $result = $stmt->execute(['test@example.com', 'Test User', 'Test Company']);
    
    if ($result) {
        echo "✓ Recipient insertion test successful\n";
        
        // Clean up test data
        $db->exec("DELETE FROM email_recipients WHERE email = 'test@example.com'");
        echo "✓ Test data cleaned up\n";
    } else {
        echo "❌ Recipient insertion test failed\n";
    }
    
    echo "\n=== Fix Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 