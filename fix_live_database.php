<?php
// fix_live_database.php - Comprehensive live server database fix
// Run this script directly on the live server

echo "=== Live Server Database Fix ===\n\n";

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
    
    // 1. Check and fix email_campaigns table
    echo "1. Fixing email_campaigns table...\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'email_campaigns'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
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
    } else {
        echo "✓ email_campaigns table exists\n";
        
        // Check and add missing columns
        $stmt = $db->query("DESCRIBE email_campaigns");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'Field');
        
        $requiredColumns = [
            'user_id' => 'INT NOT NULL',
            'schedule_type' => 'VARCHAR(50) DEFAULT "immediate"',
            'schedule_date' => 'DATETIME NULL',
            'frequency' => 'VARCHAR(50) NULL',
            'total_recipients' => 'INT DEFAULT 0',
            'sent_count' => 'INT DEFAULT 0',
            'opened_count' => 'INT DEFAULT 0',
            'clicked_count' => 'INT DEFAULT 0',
            'updated_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
        ];
        
        foreach ($requiredColumns as $columnName => $columnDef) {
            if (!in_array($columnName, $existingColumns)) {
                try {
                    $sql = "ALTER TABLE email_campaigns ADD COLUMN $columnName $columnDef";
                    $db->exec($sql);
                    echo "✓ Added column: $columnName\n";
                } catch (Exception $e) {
                    echo "❌ Failed to add column $columnName: " . $e->getMessage() . "\n";
                }
            } else {
                echo "✓ Column $columnName already exists\n";
            }
        }
    }
    
    // 2. Check and fix users table
    echo "\n2. Checking users table...\n";
    
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
    
    // 3. Check and fix email_recipients table
    echo "\n3. Checking email_recipients table...\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'email_recipients'");
    $recipientsTableExists = $stmt->fetch();
    
    if (!$recipientsTableExists) {
        echo "Creating email_recipients table...\n";
        
        $createRecipientsSQL = "
        CREATE TABLE email_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            company VARCHAR(255),
            status VARCHAR(50) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($createRecipientsSQL);
        echo "✓ email_recipients table created\n";
    } else {
        echo "✓ email_recipients table exists\n";
    }
    
    // 4. Check and fix campaign_sends table
    echo "\n4. Checking campaign_sends table...\n";
    
    $stmt = $db->query("SHOW TABLES LIKE 'campaign_sends'");
    $sendsTableExists = $stmt->fetch();
    
    if (!$sendsTableExists) {
        echo "Creating campaign_sends table...\n";
        
        $createSendsSQL = "
        CREATE TABLE campaign_sends (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT NOT NULL,
            recipient_id INT NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            sent_at DATETIME,
            opened_at DATETIME,
            clicked_at DATETIME,
            tracking_id VARCHAR(64) UNIQUE,
            INDEX idx_campaign_id (campaign_id),
            INDEX idx_recipient_id (recipient_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        
        $db->exec($createSendsSQL);
        echo "✓ campaign_sends table created\n";
    } else {
        echo "✓ campaign_sends table exists\n";
    }
    
    // 5. Test campaign creation
    echo "\n5. Testing campaign creation...\n";
    
    require_once 'services/EmailCampaignService.php';
    $campaignService = new EmailCampaignService($database);
    
    $testCampaignData = [
        'user_id' => 1,
        'name' => 'Database Fix Test Campaign - ' . date('Y-m-d H:i:s'),
        'subject' => 'Database Fix Test',
        'content' => 'This is a test campaign to verify the database fix.',
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
    
    echo "\n=== Database Fix Complete ===\n";
    echo "✅ All database tables and columns are now properly configured\n";
    echo "✅ Campaign creation should work correctly\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 