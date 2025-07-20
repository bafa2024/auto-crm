<?php
// fix_live_schema.php - Fix live server database schema

echo "=== Live Server Database Schema Fix ===\n\n";

try {
    // Simulate live server environment
    $_SERVER['SERVER_NAME'] = 'autocrm.regrowup.ca';
    $_SERVER['HTTP_HOST'] = 'autocrm.regrowup.ca';
    $_SERVER['DOCUMENT_ROOT'] = '/home/u946493694/domains/autocrm.regrowup.ca/public_html';
    
    require_once 'config/database.php';
    $database = new Database();
    
    echo "1. Environment Detection:\n";
    echo "- Environment: " . $database->getEnvironment() . "\n";
    echo "- Database Type: " . $database->getDatabaseType() . "\n";
    
    if ($database->getDatabaseType() === 'mysql') {
        echo "✓ MySQL database detected\n\n";
        
        $db = $database->getConnection();
        
        // Check if email_campaigns table exists
        echo "2. Checking email_campaigns table...\n";
        $stmt = $db->query("SHOW TABLES LIKE 'email_campaigns'");
        $tableExists = $stmt->fetch();
        
        if ($tableExists) {
            echo "✓ email_campaigns table exists\n\n";
            
            // Get current table structure
            echo "3. Current table structure:\n";
            $stmt = $db->query("DESCRIBE email_campaigns");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $existingColumns = array_column($columns, 'Field');
            echo "Existing columns: " . implode(', ', $existingColumns) . "\n\n";
            
            // Define required columns with their definitions
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
            
            // Check which columns are missing
            $missingColumns = array_diff_key($requiredColumns, array_flip($existingColumns));
            
            if (empty($missingColumns)) {
                echo "✓ All required columns already exist\n";
            } else {
                echo "4. Adding missing columns:\n";
                
                foreach ($missingColumns as $columnName => $columnDef) {
                    try {
                        $sql = "ALTER TABLE email_campaigns ADD COLUMN $columnName $columnDef";
                        $db->exec($sql);
                        echo "✓ Added column: $columnName\n";
                    } catch (Exception $e) {
                        echo "❌ Failed to add column $columnName: " . $e->getMessage() . "\n";
                    }
                }
                
                echo "\n5. Updated table structure:\n";
                $stmt = $db->query("DESCRIBE email_campaigns");
                $updatedColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($updatedColumns as $column) {
                    echo "- {$column['Field']}: {$column['Type']} {$column['Null']} {$column['Key']}\n";
                }
            }
            
            // Test campaign creation
            echo "\n6. Testing campaign creation...\n";
            require_once 'services/EmailCampaignService.php';
            $campaignService = new EmailCampaignService($database);
            
            $testCampaignData = [
                'user_id' => 1,
                'name' => 'Schema Fix Test Campaign - ' . date('Y-m-d H:i:s'),
                'subject' => 'Schema Fix Test',
                'content' => 'This is a test campaign to verify the schema fix.',
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
            echo "✓ email_campaigns table created successfully\n";
        }
        
    } else {
        echo "❌ Not using MySQL database\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Schema Fix Complete ===\n";
?> 