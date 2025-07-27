<?php
// Migration to add email tracking tables

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    // Add missing columns to email_recipients
    if ($driver === 'sqlite') {
        // Check existing columns
        $stmt = $db->query("PRAGMA table_info(email_recipients)");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
        
        $newColumns = [
            'error_message' => "ALTER TABLE email_recipients ADD COLUMN error_message TEXT",
            'open_count' => "ALTER TABLE email_recipients ADD COLUMN open_count INTEGER DEFAULT 0",
            'click_count' => "ALTER TABLE email_recipients ADD COLUMN click_count INTEGER DEFAULT 0"
        ];
        
        foreach ($newColumns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $db->exec($sql);
                echo "Added $column column to email_recipients\n";
            }
        }
        
        // Create email_clicks table
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_clicks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipient_id INTEGER NOT NULL,
                campaign_id INTEGER NOT NULL,
                url TEXT NOT NULL,
                clicked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
            )
        ");
        
        // Create unsubscribed_emails table
        $db->exec("
            CREATE TABLE IF NOT EXISTS unsubscribed_emails (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                campaign_id INTEGER,
                unsubscribed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL
            )
        ");
        
    } else {
        // MySQL
        $stmt = $db->query("SHOW COLUMNS FROM email_recipients");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        
        $newColumns = [
            'error_message' => "ALTER TABLE email_recipients ADD COLUMN error_message TEXT",
            'open_count' => "ALTER TABLE email_recipients ADD COLUMN open_count INT DEFAULT 0",
            'click_count' => "ALTER TABLE email_recipients ADD COLUMN click_count INT DEFAULT 0"
        ];
        
        foreach ($newColumns as $column => $sql) {
            if (!in_array($column, $columns)) {
                $db->exec($sql);
                echo "Added $column column to email_recipients\n";
            }
        }
        
        // Create email_clicks table
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_clicks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                recipient_id INT NOT NULL,
                campaign_id INT NOT NULL,
                url TEXT NOT NULL,
                clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
                INDEX idx_recipient (recipient_id),
                INDEX idx_campaign (campaign_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Create unsubscribed_emails table
        $db->exec("
            CREATE TABLE IF NOT EXISTS unsubscribed_emails (
                id INT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                campaign_id INT,
                unsubscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL,
                INDEX idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    echo "Email clicks table created\n";
    echo "Unsubscribed emails table created\n";
    
    // Add status enum values if MySQL
    if ($driver === 'mysql') {
        try {
            $db->exec("ALTER TABLE email_campaigns MODIFY COLUMN status ENUM('draft', 'scheduled', 'active', 'sending', 'completed', 'completed_with_errors', 'paused', 'cancelled') DEFAULT 'draft'");
            echo "Updated email_campaigns status enum\n";
        } catch (Exception $e) {
            echo "Status enum already updated or error: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}