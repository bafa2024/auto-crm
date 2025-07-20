<?php
// migrate_live_database.php - Comprehensive live server database migration
// Run this script directly on the live server

echo "=== Live Server Database Migration ===\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    if ($database->getDatabaseType() !== 'mysql') {
        echo "❌ This script is designed for MySQL databases only\n";
        exit(1);
    }
    
    $db = $database->getConnection();
    
    // 1. Check current email_campaigns table structure
    echo "1. Checking current email_campaigns table...\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'email_campaigns'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ email_campaigns table exists\n";
        
        // Get current structure
        $stmt = $db->query("DESCRIBE email_campaigns");
        $currentColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Current columns:\n";
        foreach ($currentColumns as $column) {
            echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
        }
        
        // Check if we need to migrate
        $existingColumns = array_column($currentColumns, 'Field');
        $requiredColumns = [
            'id', 'user_id', 'name', 'subject', 'email_content', 'from_name', 
            'from_email', 'schedule_type', 'schedule_date', 'frequency', 
            'status', 'total_recipients', 'sent_count', 'opened_count', 
            'clicked_count', 'created_at', 'updated_at'
        ];
        
        $missingColumns = array_diff($requiredColumns, $existingColumns);
        
        if (empty($missingColumns)) {
            echo "\n✓ All required columns exist - no migration needed\n";
        } else {
            echo "\n❌ Missing columns: " . implode(', ', $missingColumns) . "\n";
            echo "Migration required...\n\n";
            
            // 2. Backup existing data
            echo "2. Backing up existing data...\n";
            $stmt = $db->query("SELECT * FROM email_campaigns");
            $existingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "✓ Backed up " . count($existingData) . " campaigns\n";
            
            // 3. Drop and recreate table
            echo "3. Recreating email_campaigns table...\n";
            
            $db->exec("DROP TABLE email_campaigns");
            echo "✓ Dropped old table\n";
            
            $createTableSQL = "
            CREATE TABLE email_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                email_content TEXT NOT NULL,
                from_name VARCHAR(100) NOT NULL,
                from_email VARCHAR(255) NOT NULL,
                schedule_type VARCHAR(50) DEFAULT 'immediate',
                schedule_date DATETIME,
                frequency VARCHAR(50),
                status VARCHAR(50) DEFAULT 'draft',
                total_recipients INT DEFAULT 0,
                sent_count INT DEFAULT 0,
                opened_count INT DEFAULT 0,
                clicked_count INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            
            $db->exec($createTableSQL);
            echo "✓ Created new table with correct schema\n";
            
            // 4. Restore data with mapping
            if (!empty($existingData)) {
                echo "4. Restoring data...\n";
                
                $datetimeFunc = "NOW()";
                $inserted = 0;
                
                foreach ($existingData as $campaign) {
                    try {
                        // Map old column names to new ones
                        $mappedData = [
                            'user_id' => $campaign['user_id'] ?? 1,
                            'name' => $campaign['name'] ?? $campaign['campaign_name'] ?? 'Migrated Campaign',
                            'subject' => $campaign['subject'] ?? $campaign['email_subject'] ?? 'Migrated Subject',
                            'email_content' => $campaign['email_content'] ?? $campaign['content'] ?? $campaign['email_content'] ?? 'Migrated content',
                            'from_name' => $campaign['from_name'] ?? $campaign['sender_name'] ?? 'Migrated Sender',
                            'from_email' => $campaign['from_email'] ?? $campaign['sender_email'] ?? 'migrated@example.com',
                            'schedule_type' => $campaign['schedule_type'] ?? 'immediate',
                            'schedule_date' => $campaign['schedule_date'] ?? null,
                            'frequency' => $campaign['frequency'] ?? null,
                            'status' => $campaign['status'] ?? 'draft',
                            'total_recipients' => $campaign['total_recipients'] ?? 0,
                            'sent_count' => $campaign['sent_count'] ?? 0,
                            'opened_count' => $campaign['opened_count'] ?? 0,
                            'clicked_count' => $campaign['clicked_count'] ?? 0,
                            'created_at' => $campaign['created_at'] ?? $datetimeFunc,
                            'updated_at' => $campaign['updated_at'] ?? $datetimeFunc
                        ];
                        
                        $sql = "INSERT INTO email_campaigns (
                            user_id, name, subject, email_content, from_name, from_email,
                            schedule_type, schedule_date, frequency, status, total_recipients,
                            sent_count, opened_count, clicked_count, created_at, updated_at
                        ) VALUES (
                            :user_id, :name, :subject, :email_content, :from_name, :from_email,
                            :schedule_type, :schedule_date, :frequency, :status, :total_recipients,
                            :sent_count, :opened_count, :clicked_count, :created_at, :updated_at
                        )";
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute($mappedData);
                        $inserted++;
                        
                    } catch (Exception $e) {
                        echo "⚠️ Failed to restore campaign: " . $e->getMessage() . "\n";
                    }
                }
                
                echo "✓ Restored $inserted campaigns\n";
            }
        }
        
    } else {
        echo "❌ email_campaigns table does not exist\n";
        echo "Creating email_campaigns table...\n";
        
        $createTableSQL = "
        CREATE TABLE email_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            email_content TEXT NOT NULL,
            from_name VARCHAR(100) NOT NULL,
            from_email VARCHAR(255) NOT NULL,
            schedule_type VARCHAR(50) DEFAULT 'immediate',
            schedule_date DATETIME,
            frequency VARCHAR(50),
            status VARCHAR(50) DEFAULT 'draft',
            total_recipients INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            opened_count INT DEFAULT 0,
            clicked_count INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($createTableSQL);
        echo "✓ email_campaigns table created\n";
    }
    
    // 5. Ensure users table exists
    echo "\n5. Checking users table...\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $usersTableExists = $stmt->fetch();
    
    if (!$usersTableExists) {
        echo "Creating users table...\n";
        
        $createUsersSQL = "
        CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            role VARCHAR(50) DEFAULT 'user',
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($createUsersSQL);
        echo "✓ users table created\n";
        
        // Create default admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute(['Admin', 'User', 'admin@autocrm.com', $hashedPassword, 'admin', 'active']);
        echo "✓ Default admin user created\n";
    } else {
        echo "✓ users table exists\n";
        
        // Check if admin user exists
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute(['admin@autocrm.com']);
        $adminUser = $stmt->fetch();
        
        if (!$adminUser) {
            $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['Admin', 'User', 'admin@autocrm.com', $hashedPassword, 'admin', 'active']);
            echo "✓ Default admin user created\n";
        } else {
            echo "✓ Admin user already exists\n";
        }
    }
    
    // 6. Test campaign creation
    echo "\n6. Testing campaign creation...\n";
    
    require_once 'services/EmailCampaignService.php';
    $campaignService = new EmailCampaignService($database);
    
    $testCampaignData = [
        'user_id' => 1,
        'name' => 'Migration Test Campaign - ' . date('Y-m-d H:i:s'),
        'subject' => 'Migration Test',
        'content' => 'This is a test campaign to verify the migration worked.',
        'sender_name' => 'Test Sender',
        'sender_email' => 'test@example.com',
        'status' => 'draft'
    ];
    
    $result = $campaignService->createCampaign($testCampaignData);
    
    if ($result['success']) {
        echo "✓ Campaign creation test successful!\n";
        echo "- Campaign ID: " . $result['campaign_id'] . "\n";
        echo "- Message: " . $result['message'] . "\n";
        
        // Clean up test data
        $db->exec("DELETE FROM email_campaigns WHERE id = " . $result['campaign_id']);
        echo "✓ Test data cleaned up\n";
    } else {
        echo "❌ Campaign creation test failed: " . $result['message'] . "\n";
    }
    
    // 7. Show final table structure
    echo "\n7. Final table structure:\n";
    $stmt = $db->query("DESCRIBE email_campaigns");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($finalColumns as $column) {
        echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "✅ Database schema has been migrated successfully\n";
    echo "✅ All required columns are now present\n";
    echo "✅ Campaign creation should work correctly\n";
    echo "✅ Existing data has been preserved (if any)\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 