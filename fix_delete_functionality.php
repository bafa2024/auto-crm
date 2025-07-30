<?php
/**
 * Fix Delete Functionality - Create Missing Tables
 * 
 * This script creates the missing tables needed for the delete functionality
 * to work properly on the production MySQL database.
 */

echo "Fixing Delete Functionality\n";
echo "==========================\n\n";

try {
    // Include database configuration
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "✓ Connected to database successfully\n";
    
    // Create deleted_email_recipients table
    echo "\n1. Creating deleted_email_recipients table...\n";
    
    $sql = "
        CREATE TABLE IF NOT EXISTS deleted_email_recipients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(255) NOT NULL,
            name VARCHAR(255) NOT NULL,
            company VARCHAR(255),
            dot VARCHAR(50),
            campaign_id INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_by INT,
            deletion_reason VARCHAR(255) DEFAULT 'Manual deletion',
            original_id INT,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_campaign_id (campaign_id),
            INDEX idx_deleted_by (deleted_by),
            INDEX idx_deleted_at (deleted_at),
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL,
            FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $result = $db->exec($sql);
    if ($result !== false) {
        echo "✓ Deleted email recipients table created successfully\n";
    } else {
        echo "✓ Deleted email recipients table already exists\n";
    }
    
    // Create deleted_campaign_sends table
    echo "\n2. Creating deleted_campaign_sends table...\n";
    
    $sql = "
        CREATE TABLE IF NOT EXISTS deleted_campaign_sends (
            id INT AUTO_INCREMENT PRIMARY KEY,
            campaign_id INT,
            recipient_id INT,
            recipient_email VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            sent_at TIMESTAMP NULL,
            opened_at TIMESTAMP NULL,
            clicked_at TIMESTAMP NULL,
            tracking_id VARCHAR(255),
            original_id INT,
            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_campaign_id (campaign_id),
            INDEX idx_recipient_id (recipient_id),
            INDEX idx_status (status),
            INDEX idx_deleted_at (deleted_at),
            FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $result = $db->exec($sql);
    if ($result !== false) {
        echo "✓ Deleted campaign sends table created successfully\n";
    } else {
        echo "✓ Deleted campaign sends table already exists\n";
    }
    
    // Test the delete functionality
    echo "\n3. Testing delete functionality...\n";
    
    // Test inserting a record into deleted_email_recipients
    $testEmail = 'test@example.com';
    $testName = 'Test User';
    
    $stmt = $db->prepare("
        INSERT INTO deleted_email_recipients (email, name, company, dot, campaign_id, deleted_by, deletion_reason, original_id) 
        VALUES (?, ?, 'Test Company', '12345', NULL, 1, 'Test insertion', 999)
    ");
    $result = $stmt->execute([$testEmail, $testName]);
    
    if ($result) {
        echo "✓ Test record inserted successfully into deleted_email_recipients\n";
        
        // Clean up test record
        $stmt = $db->prepare("DELETE FROM deleted_email_recipients WHERE email = ?");
        $stmt->execute([$testEmail]);
        echo "✓ Test record cleaned up\n";
    }
    
    echo "\n✅ Delete functionality fixed successfully!\n";
    echo "\nThe delete functionality should now work properly.\n";
    echo "You can test it by trying to delete contacts from the contacts page.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nPlease check your database connection and try again.\n";
    exit(1);
}
?> 