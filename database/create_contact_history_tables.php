<?php
// Create contact history and upload tracking tables
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Database connection failed");
    }
    
    echo "<h1>Creating Contact History Tables</h1>";
    
    // 1. Contact History Table
    echo "<h2>1. Creating contact_history table...</h2>";
    $sql = "CREATE TABLE IF NOT EXISTS contact_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        contact_id INT NOT NULL,
        action ENUM('created', 'updated', 'deleted', 'bulk_uploaded', 'bulk_deleted') NOT NULL,
        old_data JSON NULL,
        new_data JSON NULL,
        performed_by INT NULL,
        performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        batch_id VARCHAR(50) NULL,
        notes TEXT NULL,
        INDEX idx_contact_id (contact_id),
        INDEX idx_action (action),
        INDEX idx_performed_at (performed_at),
        INDEX idx_batch_id (batch_id),
        FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "<p>✅ contact_history table created successfully</p>";
    
    // 2. Upload Sessions Table
    echo "<h2>2. Creating upload_sessions table...</h2>";
    $sql = "CREATE TABLE IF NOT EXISTS upload_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_name VARCHAR(255) NOT NULL,
        filename VARCHAR(255) NOT NULL,
        total_records INT DEFAULT 0,
        successful_uploads INT DEFAULT 0,
        failed_uploads INT DEFAULT 0,
        uploaded_by INT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('processing', 'completed', 'failed', 'cancelled') DEFAULT 'processing',
        file_size BIGINT DEFAULT 0,
        file_type VARCHAR(10) DEFAULT 'csv',
        notes TEXT NULL,
        INDEX idx_uploaded_at (uploaded_at),
        INDEX idx_status (status),
        INDEX idx_uploaded_by (uploaded_by)
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "<p>✅ upload_sessions table created successfully</p>";
    
    // 3. Contact Batches Table
    echo "<h2>3. Creating contact_batches table...</h2>";
    $sql = "CREATE TABLE IF NOT EXISTS contact_batches (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_name VARCHAR(255) NOT NULL,
        upload_session_id INT NULL,
        total_contacts INT DEFAULT 0,
        active_contacts INT DEFAULT 0,
        deleted_contacts INT DEFAULT 0,
        created_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'archived', 'deleted') DEFAULT 'active',
        retention_days INT DEFAULT 30,
        notes TEXT NULL,
        INDEX idx_created_at (created_at),
        INDEX idx_status (status),
        INDEX idx_upload_session_id (upload_session_id),
        FOREIGN KEY (upload_session_id) REFERENCES upload_sessions(id) ON DELETE SET NULL
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "<p>✅ contact_batches table created successfully</p>";
    
    // 4. Add batch_id column to contacts table
    echo "<h2>4. Adding batch_id to contacts table...</h2>";
    $sql = "ALTER TABLE contacts ADD COLUMN IF NOT EXISTS batch_id VARCHAR(50) NULL";
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "<p>✅ batch_id column added to contacts table</p>";
    
    // 5. Add indexes for better performance
    echo "<h2>5. Adding performance indexes...</h2>";
    $indexes = [
        "ALTER TABLE contacts ADD INDEX IF NOT EXISTS idx_batch_id (batch_id)",
        "ALTER TABLE contacts ADD INDEX IF NOT EXISTS idx_created_at (created_at)",
        "ALTER TABLE contact_history ADD INDEX IF NOT EXISTS idx_contact_action (contact_id, action)",
        "ALTER TABLE upload_sessions ADD INDEX IF NOT EXISTS idx_uploaded_status (uploaded_at, status)"
    ];
    
    foreach ($indexes as $index) {
        $stmt = $db->prepare($index);
        $stmt->execute();
    }
    echo "<p>✅ Performance indexes added</p>";
    
    // 6. Create views for easy querying
    echo "<h2>6. Creating database views...</h2>";
    
    // Recent uploads view
    $sql = "CREATE OR REPLACE VIEW recent_uploads AS
            SELECT 
                us.id,
                us.session_name,
                us.filename,
                us.total_records,
                us.successful_uploads,
                us.failed_uploads,
                us.uploaded_at,
                us.status,
                u.email as uploaded_by_email,
                DATEDIFF(NOW(), us.uploaded_at) as days_ago
            FROM upload_sessions us
            LEFT JOIN users u ON us.uploaded_by = u.id
            ORDER BY us.uploaded_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "<p>✅ recent_uploads view created</p>";
    
    // Contact statistics view
    $sql = "CREATE OR REPLACE VIEW contact_statistics AS
            SELECT 
                DATE(created_at) as upload_date,
                COUNT(*) as total_contacts,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_contacts,
                COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_contacts,
                COUNT(CASE WHEN batch_id IS NOT NULL THEN 1 END) as batch_contacts
            FROM contacts
            GROUP BY DATE(created_at)
            ORDER BY upload_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute();
    echo "<p>✅ contact_statistics view created</p>";
    
    echo "<h2>✅ All tables and views created successfully!</h2>";
    
    // Show sample data structure
    echo "<h3>Sample Data Structure:</h3>";
    echo "<ul>";
    echo "<li><strong>contact_history</strong> - Tracks all contact changes</li>";
    echo "<li><strong>upload_sessions</strong> - Tracks file uploads</li>";
    echo "<li><strong>contact_batches</strong> - Groups contacts by upload batch</li>";
    echo "<li><strong>recent_uploads</strong> - View for recent upload statistics</li>";
    echo "<li><strong>contact_statistics</strong> - View for daily contact statistics</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?> 