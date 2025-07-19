<?php
/**
 * Fix Foreign Key Constraint Issues
 * This script will properly remove foreign key constraints and recreate tables
 */

echo "🔧 Foreign Key Constraint Fix Script\n";
echo "===================================\n\n";

// Include database configuration
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Database connection failed\n");
    }
    
    echo "✅ Database connection successful\n\n";
    
    // Detect database type
    $dbType = 'unknown';
    try {
        $db->query("SELECT sqlite_version()");
        $dbType = 'sqlite';
        echo "📊 Database type: SQLite\n\n";
    } catch (Exception $e) {
        try {
            $db->query("SELECT version()");
            $dbType = 'mysql';
            echo "📊 Database type: MySQL\n\n";
        } catch (Exception $e2) {
            echo "⚠️  Could not determine database type\n\n";
        }
    }
    
    // Check if email_campaigns table exists
    echo "📋 Checking email_campaigns table...\n";
    
    if ($dbType === 'mysql') {
        $stmt = $db->query("SHOW TABLES LIKE 'email_campaigns'");
        $tableExists = $stmt->fetch();
    } else {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='email_campaigns'");
        $tableExists = $stmt->fetch();
    }
    
    if (!$tableExists) {
        echo "✅ email_campaigns table does not exist - will be created\n";
    } else {
        echo "⚠️  email_campaigns table exists with foreign key constraints\n";
        
        // Backup existing data
        echo "📦 Backing up existing campaign data...\n";
        $stmt = $db->query("SELECT * FROM email_campaigns");
        $existingCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "📊 Found " . count($existingCampaigns) . " existing campaigns\n";
        
        // For SQLite, we need to disable foreign key constraints
        if ($dbType === 'sqlite') {
            echo "🔓 Disabling foreign key constraints...\n";
            $db->exec("PRAGMA foreign_keys = OFF");
        }
        
        // Drop the table
        echo "🗑️  Dropping email_campaigns table...\n";
        $db->exec("DROP TABLE IF EXISTS email_campaigns");
        
        // Re-enable foreign key constraints for SQLite
        if ($dbType === 'sqlite') {
            echo "🔒 Re-enabling foreign key constraints...\n";
            $db->exec("PRAGMA foreign_keys = ON");
        }
    }
    
    // Create new email_campaigns table without foreign key constraints
    echo "🏗️  Creating new email_campaigns table without foreign keys...\n";
    
    if ($dbType === 'mysql') {
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
    } else {
        $createTableSQL = "
        CREATE TABLE email_campaigns (
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
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
        ";
    }
    
    $db->exec($createTableSQL);
    echo "✅ email_campaigns table created successfully\n";
    
    // Restore existing data if any
    if (isset($existingCampaigns) && !empty($existingCampaigns)) {
        echo "📥 Restoring existing campaign data...\n";
        
        $datetimeFunc = ($dbType === 'sqlite') ? "datetime('now')" : "NOW()";
        
        foreach ($existingCampaigns as $campaign) {
            $sql = "INSERT INTO email_campaigns (
                user_id, name, subject, email_content, from_name, from_email,
                status, scheduled_at, created_at, updated_at, sent_count, opened_count, clicked_count, total_recipients
            ) VALUES (
                :user_id, :name, :subject, :email_content, :from_name, :from_email,
                :status, :scheduled_at, :created_at, :updated_at, :sent_count, :opened_count, :clicked_count, :total_recipients
            )";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':user_id' => $campaign['user_id'],
                ':name' => $campaign['name'],
                ':subject' => $campaign['subject'],
                ':email_content' => $campaign['email_content'],
                ':from_name' => $campaign['from_name'],
                ':from_email' => $campaign['from_email'],
                ':status' => $campaign['status'],
                ':scheduled_at' => $campaign['scheduled_at'],
                ':created_at' => $campaign['created_at'],
                ':updated_at' => $campaign['updated_at'],
                ':sent_count' => $campaign['sent_count'],
                ':opened_count' => $campaign['opened_count'],
                ':clicked_count' => $campaign['clicked_count'],
                ':total_recipients' => $campaign['total_recipients']
            ]);
        }
        
        echo "✅ Restored " . count($existingCampaigns) . " campaigns\n";
    }
    
    // Test campaign creation
    echo "\n🧪 Testing campaign creation...\n";
    
    // Get a user for testing
    $stmt = $db->query("SELECT id, email, first_name, last_name FROM users ORDER BY id LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ No users found for testing!\n";
        exit(1);
    }
    
    echo "Using user: {$user['first_name']} {$user['last_name']} (ID: {$user['id']})\n";
    
    require_once 'services/EmailCampaignService.php';
    $campaignService = new EmailCampaignService($db);
    
    $testCampaignData = [
        'user_id' => $user['id'],
        'name' => 'Test Campaign - Foreign Key Fix',
        'subject' => 'Test Subject',
        'content' => 'This is a test campaign content.',
        'sender_name' => 'Test Sender',
        'sender_email' => 'test@example.com',
        'status' => 'draft'
    ];
    
    echo "Creating test campaign...\n";
    $result = $campaignService->createCampaign($testCampaignData);
    
    if ($result['success']) {
        echo "✅ Campaign created successfully!\n";
        echo "   Campaign ID: {$result['campaign_id']}\n";
        echo "   Message: {$result['message']}\n";
        
        // Verify the campaign
        $stmt = $db->prepare("SELECT * FROM email_campaigns WHERE id = ?");
        $stmt->execute([$result['campaign_id']]);
        $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($campaign) {
            echo "✅ Campaign verified in database:\n";
            echo "   ID: {$campaign['id']}\n";
            echo "   Name: {$campaign['name']}\n";
            echo "   User ID: {$campaign['user_id']}\n";
            echo "   Status: {$campaign['status']}\n";
        }
    } else {
        echo "❌ Campaign creation failed!\n";
        echo "   Error: {$result['message']}\n";
    }
    
    // Check final table structure
    echo "\n📋 Final table structure:\n";
    
    if ($dbType === 'mysql') {
        $stmt = $db->query("DESCRIBE email_campaigns");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            echo "  - {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
        }
        
        // Check for foreign keys
        $stmt = $db->query("
            SELECT COUNT(*) as count
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_NAME = 'email_campaigns' 
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $fkCount = $stmt->fetch()['count'];
        echo "  Foreign key constraints: $fkCount\n";
        
    } else {
        $stmt = $db->query("PRAGMA table_info(email_campaigns)");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($columns as $column) {
            echo "  - {$column['name']}: {$column['type']} (PK: {$column['pk']}, NotNull: {$column['notnull']})\n";
        }
        
        // Check for foreign keys
        $stmt = $db->query("PRAGMA foreign_key_list(email_campaigns)");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "  Foreign key constraints: " . count($foreignKeys) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n🎯 Foreign key fix completed!\n";
?> 