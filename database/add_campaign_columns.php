<?php
// Migration to add send_type and target_type columns to email_campaigns table

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if we're using SQLite or MySQL
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'sqlite') {
        // SQLite - need to recreate table since ALTER TABLE ADD COLUMN is limited
        echo "Adding columns to SQLite database...\n";
        
        // Check if columns already exist
        $stmt = $db->query("PRAGMA table_info(email_campaigns)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        
        if (!in_array('send_type', $columns)) {
            // Add columns one by one (SQLite supports this)
            $db->exec("ALTER TABLE email_campaigns ADD COLUMN send_type VARCHAR(50) DEFAULT 'immediate'");
            echo "Added send_type column\n";
        } else {
            echo "send_type column already exists\n";
        }
        
        if (!in_array('target_type', $columns)) {
            $db->exec("ALTER TABLE email_campaigns ADD COLUMN target_type VARCHAR(50) DEFAULT 'all'");
            echo "Added target_type column\n";
        } else {
            echo "target_type column already exists\n";
        }
        
    } else {
        // MySQL
        echo "Adding columns to MySQL database...\n";
        
        // Check if columns exist
        $stmt = $db->query("SHOW COLUMNS FROM email_campaigns");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        if (!in_array('send_type', $columns)) {
            $db->exec("ALTER TABLE email_campaigns ADD COLUMN send_type VARCHAR(50) DEFAULT 'immediate' AFTER status");
            echo "Added send_type column\n";
        } else {
            echo "send_type column already exists\n";
        }
        
        if (!in_array('target_type', $columns)) {
            $db->exec("ALTER TABLE email_campaigns ADD COLUMN target_type VARCHAR(50) DEFAULT 'all' AFTER send_type");
            echo "Added target_type column\n";
        } else {
            echo "target_type column already exists\n";
        }
    }
    
    // Also ensure email_recipients table exists
    if ($driver === 'sqlite') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_recipients (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                contact_id INTEGER,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                company VARCHAR(255),
                custom_data TEXT,
                tracking_id VARCHAR(100),
                status VARCHAR(50) DEFAULT 'pending',
                sent_at DATETIME,
                opened_at DATETIME,
                clicked_at DATETIME,
                bounced_at DATETIME,
                unsubscribed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL
            )
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_recipients (
                id INT PRIMARY KEY AUTO_INCREMENT,
                campaign_id INT NOT NULL,
                contact_id INT,
                email VARCHAR(255) NOT NULL,
                name VARCHAR(255),
                company VARCHAR(255),
                custom_data TEXT,
                tracking_id VARCHAR(100),
                status ENUM('pending', 'sent', 'opened', 'clicked', 'bounced', 'unsubscribed') DEFAULT 'pending',
                sent_at DATETIME,
                opened_at DATETIME,
                clicked_at DATETIME,
                bounced_at DATETIME,
                unsubscribed_at DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
                INDEX idx_campaign_status (campaign_id, status),
                UNIQUE KEY unique_campaign_email (campaign_id, email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    echo "Email recipients table ensured\n";
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}