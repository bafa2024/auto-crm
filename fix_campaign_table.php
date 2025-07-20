<?php
// fix_campaign_table.php - Fix email_campaigns table structure

echo "=== Fixing Email Campaigns Table ===\n\n";

// Use local SQLite database
$dbPath = __DIR__ . '/database/autocrm_local.db';

try {
    // Create SQLite database connection
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✓ Connected to local SQLite database\n\n";
    
    // Check current table structure
    echo "1. Checking current table structure...\n";
    $stmt = $db->prepare("PRAGMA table_info(email_campaigns)");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "Current columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Check for missing columns
    $requiredColumns = [
        'schedule_type' => 'TEXT DEFAULT "immediate"',
        'schedule_date' => 'DATETIME',
        'frequency' => 'TEXT'
    ];
    
    $existingColumns = array_column($columns, 'name');
    $missingColumns = [];
    
    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!in_array($columnName, $existingColumns)) {
            $missingColumns[$columnName] = $columnDef;
        }
    }
    
    if (!empty($missingColumns)) {
        echo "\n2. Adding missing columns...\n";
        
        // Backup existing data
        $stmt = $db->prepare("SELECT * FROM email_campaigns");
        $stmt->execute();
        $existingData = $stmt->fetchAll();
        
        echo "Backed up " . count($existingData) . " existing records\n";
        
        // Drop and recreate table with proper structure
        $db->exec("DROP TABLE IF EXISTS email_campaigns");
        
        $createCampaignsTable = "CREATE TABLE email_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            email_content TEXT NOT NULL,
            from_name TEXT NOT NULL,
            from_email TEXT NOT NULL,
            schedule_type TEXT DEFAULT 'immediate',
            schedule_date DATETIME,
            frequency TEXT,
            status TEXT DEFAULT 'draft',
            total_recipients INTEGER DEFAULT 0,
            sent_count INTEGER DEFAULT 0,
            opened_count INTEGER DEFAULT 0,
            clicked_count INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        
        $db->exec($createCampaignsTable);
        echo "✓ Table recreated with proper structure\n";
        
        // Restore data if any
        if (!empty($existingData)) {
            echo "Restoring existing data...\n";
            $stmt = $db->prepare("INSERT INTO email_campaigns (user_id, name, subject, email_content, from_name, from_email, status, total_recipients, sent_count, opened_count, clicked_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $restoredCount = 0;
            foreach ($existingData as $row) {
                $stmt->execute([
                    $row['user_id'],
                    $row['name'],
                    $row['subject'],
                    $row['email_content'],
                    $row['from_name'],
                    $row['from_email'],
                    $row['status'],
                    $row['total_recipients'] ?? 0,
                    $row['sent_count'] ?? 0,
                    $row['opened_count'] ?? 0,
                    $row['clicked_count'] ?? 0,
                    $row['created_at'] ?? date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s') // updated_at
                ]);
                $restoredCount++;
            }
            echo "✓ Restored $restoredCount records\n";
        }
    } else {
        echo "\n2. All required columns already exist\n";
    }
    
    // Verify the fix
    echo "\n3. Verifying table structure...\n";
    $stmt = $db->prepare("PRAGMA table_info(email_campaigns)");
    $stmt->execute();
    $columns = $stmt->fetchAll();
    
    echo "Updated columns:\n";
    foreach ($columns as $column) {
        echo "- " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
    // Test inserting a campaign
    echo "\n4. Testing campaign insertion...\n";
    $stmt = $db->prepare("INSERT INTO email_campaigns (user_id, name, subject, email_content, from_name, from_email, schedule_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'))");
    $result = $stmt->execute([1, 'Test Campaign', 'Test Subject', 'Test content', 'Test Sender', 'test@example.com', 'immediate', 'draft']);
    
    if ($result) {
        echo "✓ Campaign insertion test successful\n";
        
        // Clean up test data
        $db->exec("DELETE FROM email_campaigns WHERE name = 'Test Campaign'");
        echo "✓ Test data cleaned up\n";
    } else {
        echo "❌ Campaign insertion test failed\n";
    }
    
    echo "\n=== Fix Complete ===\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 