<?php
require_once 'config/database.php';

echo "Setting up Email Tables for SQLite\n";
echo "==================================\n\n";

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "✓ Database connection established\n\n";
    
    // Create email_campaigns table
    echo "Creating email_campaigns table...\n";
    $createCampaignsTable = "
    CREATE TABLE IF NOT EXISTS email_campaigns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        sender_name VARCHAR(100),
        sender_email VARCHAR(255),
        reply_to_email VARCHAR(255),
        campaign_type VARCHAR(50) DEFAULT 'bulk',
        status VARCHAR(20) DEFAULT 'draft',
        scheduled_at DATETIME,
        total_recipients INTEGER DEFAULT 0,
        sent_count INTEGER DEFAULT 0,
        opened_count INTEGER DEFAULT 0,
        clicked_count INTEGER DEFAULT 0,
        bounced_count INTEGER DEFAULT 0,
        unsubscribed_count INTEGER DEFAULT 0,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($createCampaignsTable);
    echo "✓ email_campaigns table created\n";
    
    // Create email_recipients table
    echo "Creating email_recipients table...\n";
    $createRecipientsTable = "
    CREATE TABLE IF NOT EXISTS email_recipients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER,
        email VARCHAR(255) NOT NULL,
        name VARCHAR(255),
        company VARCHAR(255),
        custom_data TEXT,
        status VARCHAR(20) DEFAULT 'pending',
        sent_at DATETIME,
        opened_at DATETIME,
        clicked_at DATETIME,
        bounced_at DATETIME,
        unsubscribed_at DATETIME,
        tracking_id VARCHAR(64),
        error_message TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($createRecipientsTable);
    echo "✓ email_recipients table created\n";
    
    // Create email_templates table
    echo "Creating email_templates table...\n";
    $createTemplatesTable = "
    CREATE TABLE IF NOT EXISTS email_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        template_type VARCHAR(50) DEFAULT 'custom',
        variables TEXT,
        is_active BOOLEAN DEFAULT 1,
        created_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($createTemplatesTable);
    echo "✓ email_templates table created\n";
    
    // Create email_uploads table
    echo "Creating email_uploads table...\n";
    $createUploadsTable = "
    CREATE TABLE IF NOT EXISTS email_uploads (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        campaign_id INTEGER,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_size INTEGER NOT NULL,
        total_records INTEGER DEFAULT 0,
        imported_records INTEGER DEFAULT 0,
        failed_records INTEGER DEFAULT 0,
        status VARCHAR(20) DEFAULT 'pending',
        error_log TEXT,
        uploaded_by INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    
    $conn->exec($createUploadsTable);
    echo "✓ email_uploads table created\n";
    
    // Create indexes for better performance
    echo "\nCreating indexes...\n";
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_email_recipients_campaign ON email_recipients(campaign_id)",
        "CREATE INDEX IF NOT EXISTS idx_email_recipients_email ON email_recipients(email)",
        "CREATE INDEX IF NOT EXISTS idx_email_recipients_status ON email_recipients(status)",
        "CREATE INDEX IF NOT EXISTS idx_email_campaigns_status ON email_campaigns(status)",
        "CREATE INDEX IF NOT EXISTS idx_email_campaigns_created_by ON email_campaigns(created_by)"
    ];
    
    foreach ($indexes as $index) {
        $conn->exec($index);
        echo "✓ Index created\n";
    }
    
    // Check table status
    echo "\nTable Status:\n";
    $tables = ['email_campaigns', 'email_recipients', 'email_templates', 'email_uploads'];
    foreach ($tables as $table) {
        $stmt = $conn->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "- $table: $count records\n";
    }
    
    echo "\n✅ Email tables setup complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>