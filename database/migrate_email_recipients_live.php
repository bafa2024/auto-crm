<?php
// Database migration for live server - email_recipients table
// This script handles foreign key constraints properly

require_once '../config/config.php';

echo "<h1>Live Server Database Migration - email_recipients</h1>";

try {
    // Connect to live MySQL database
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color: green;'>✓ Connected to live MySQL database</p>";
    
    // Check if email_campaigns table exists first
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_campaigns'");
    $campaignsTableExists = $stmt->fetch();
    
    if (!$campaignsTableExists) {
        echo "<p style='color: orange;'>⚠ email_campaigns table does not exist. Creating it first...</p>";
        
        // Create email_campaigns table
        $sql = "CREATE TABLE email_campaigns (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            sender_name VARCHAR(100),
            sender_email VARCHAR(255),
            reply_to_email VARCHAR(255),
            campaign_type VARCHAR(50),
            status ENUM('draft', 'scheduled', 'sending', 'completed', 'paused') DEFAULT 'draft',
            scheduled_at DATETIME,
            total_recipients INT DEFAULT 0,
            sent_count INT DEFAULT 0,
            opened_count INT DEFAULT 0,
            clicked_count INT DEFAULT 0,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ email_campaigns table created successfully</p>";
        
        // Insert a default campaign
        $stmt = $pdo->prepare("INSERT INTO email_campaigns (name, subject, content, status) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Default Campaign', 'Welcome Email', 'Welcome to our platform!', 'draft']);
        echo "<p style='color: green;'>✓ Default campaign created</p>";
    } else {
        echo "<p style='color: green;'>✓ email_campaigns table exists</p>";
    }
    
    // Check if email_recipients table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'email_recipients'");
    $recipientsTableExists = $stmt->fetch();
    
    if ($recipientsTableExists) {
        echo "<p style='color: green;'>✓ email_recipients table already exists</p>";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE email_recipients");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columnNames = array_column($columns, 'Field');
        
        // Check if campaign_id column exists
        if (!in_array('campaign_id', $columnNames)) {
            echo "<p>Adding campaign_id column...</p>";
            $pdo->exec("ALTER TABLE email_recipients ADD COLUMN campaign_id INT");
            echo "<p style='color: green;'>✓ campaign_id column added</p>";
        }
        
        // Check if foreign key constraint exists
        $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
                             WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
                             AND TABLE_NAME = 'email_recipients' 
                             AND COLUMN_NAME = 'campaign_id' 
                             AND REFERENCED_TABLE_NAME = 'email_campaigns'");
        $fkExists = $stmt->fetch();
        
        if (!$fkExists) {
            echo "<p>Adding foreign key constraint...</p>";
            $pdo->exec("ALTER TABLE email_recipients 
                       ADD CONSTRAINT email_recipients_ibfk_1 
                       FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) 
                       ON DELETE CASCADE");
            echo "<p style='color: green;'>✓ Foreign key constraint added</p>";
        } else {
            echo "<p style='color: green;'>✓ Foreign key constraint already exists</p>";
        }
        
        // Get row count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients");
        $count = $stmt->fetch()['count'];
        echo "<p>Table has $count records</p>";
        
    } else {
        echo "<p>Creating email_recipients table...</p>";
        
        // Get default campaign ID
        $stmt = $pdo->query("SELECT id FROM email_campaigns LIMIT 1");
        $defaultCampaign = $stmt->fetch();
        $defaultCampaignId = $defaultCampaign ? $defaultCampaign['id'] : 1;
        
        // Create the table with proper foreign key constraint
        $sql = "CREATE TABLE email_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255),
            company VARCHAR(255),
            dot VARCHAR(100),
            custom_data TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            error_message TEXT,
            open_count INT DEFAULT 0,
            click_count INT DEFAULT 0,
            campaign_id INT DEFAULT $defaultCampaignId,
            tracking_id VARCHAR(255),
            status VARCHAR(50) DEFAULT 'active',
            INDEX idx_email (email),
            INDEX idx_campaign_id (campaign_id),
            INDEX idx_created_at (created_at),
            CONSTRAINT email_recipients_ibfk_1 
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) 
            ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        echo "<p style='color: green;'>✓ email_recipients table created successfully</p>";
        
        // Insert sample data with proper campaign_id
        echo "<p>Inserting sample data...</p>";
        
        $sampleData = [
            ['John Doe', 'john.doe@example.com', 'Example Corp', '123456'],
            ['Jane Smith', 'jane.smith@test.com', 'Test Solutions', '789012'],
            ['Bob Wilson', 'bob.wilson@demo.com', 'Demo Company', '345678'],
            ['Alice Johnson', 'alice.johnson@sample.com', 'Sample Inc', '901234'],
            ['Charlie Brown', 'charlie.brown@test.org', 'Test Organization', '567890']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO email_recipients (name, email, company, dot, campaign_id) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($sampleData as $data) {
            $data[] = $defaultCampaignId; // Add campaign_id
            $stmt->execute($data);
        }
        
        echo "<p style='color: green;'>✓ Sample data inserted successfully</p>";
        
        // Verify the data
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM email_recipients");
        $count = $stmt->fetch()['count'];
        echo "<p>Table now has $count records</p>";
    }
    
    // Show final table structure
    echo "<h2>Final Table Structure:</h2>";
    $stmt = $pdo->query("DESCRIBE email_recipients");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . $column['Field'] . "</td>";
        echo "<td>" . $column['Type'] . "</td>";
        echo "<td>" . $column['Null'] . "</td>";
        echo "<td>" . $column['Key'] . "</td>";
        echo "<td>" . $column['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Show sample data
    echo "<h2>Sample Data:</h2>";
    $stmt = $pdo->query("SELECT er.*, ec.name as campaign_name 
                         FROM email_recipients er 
                         LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id 
                         LIMIT 5");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Company</th><th>DOT</th><th>Campaign</th><th>Created At</th></tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . ($row['name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['email'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['company'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['dot'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['campaign_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($row['created_at'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p style='color: green;'>✓ Migration completed successfully!</p>";
    echo "<p>The contacts page should now work properly with the live server database.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Stack trace:</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
?> 