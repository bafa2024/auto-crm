<?php
/**
 * Create deleted_email_recipients table to store soft-deleted email recipients
 * This table acts as an archive for deleted contacts
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Creating deleted_email_recipients table...\n";
    
    // Create the deleted_email_recipients table with the same structure as email_recipients
    // plus additional fields for deletion tracking
    $sql = "CREATE TABLE IF NOT EXISTS deleted_email_recipients (
        id INTEGER PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        name VARCHAR(255),
        company VARCHAR(255),
        dot VARCHAR(50),
        campaign_id INTEGER,
        created_at DATETIME,
        updated_at DATETIME,
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        deleted_by INTEGER,
        deletion_reason VARCHAR(255),
        original_id INTEGER,
        FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    $db->exec($sql);
    echo "✓ deleted_email_recipients table created successfully\n";
    
    // Create indexes for better performance
    $db->exec("CREATE INDEX IF NOT EXISTS idx_deleted_email ON deleted_email_recipients(email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_deleted_at ON deleted_email_recipients(deleted_at)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_original_id ON deleted_email_recipients(original_id)");
    echo "✓ Indexes created successfully\n";
    
    // Create a similar table for deleted campaign_sends records
    $sql = "CREATE TABLE IF NOT EXISTS deleted_campaign_sends (
        id INTEGER PRIMARY KEY,
        campaign_id INTEGER,
        recipient_id INTEGER,
        recipient_email TEXT NOT NULL,
        status TEXT DEFAULT 'pending',
        sent_at DATETIME,
        opened_at DATETIME,
        clicked_at DATETIME,
        tracking_id TEXT,
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        original_id INTEGER
    )";
    
    $db->exec($sql);
    echo "✓ deleted_campaign_sends table created successfully\n";
    
    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_deleted_campaign_id ON deleted_campaign_sends(campaign_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_deleted_recipient_id ON deleted_campaign_sends(recipient_id)");
    echo "✓ Indexes created for deleted_campaign_sends\n";
    
    echo "\n✅ All tables created successfully!\n";
    
} catch (Exception $e) {
    echo "Error creating tables: " . $e->getMessage() . "\n";
    exit(1);
}
?>