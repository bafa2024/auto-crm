<?php
// Migration script to create employee permissions table

require_once __DIR__ . "/../config/database.php";

$database = new Database();
$db = $database->getConnection();

echo "=== Creating Employee Permissions Table ===\n\n";
echo "Database Type: " . $database->getDatabaseType() . "\n";
echo "Environment: " . $database->getEnvironment() . "\n\n";

try {
    if ($database->getDatabaseType() === 'sqlite') {
        // SQLite version
        $sql = "CREATE TABLE IF NOT EXISTS employee_permissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL UNIQUE,
            can_upload_contacts INTEGER DEFAULT 1,
            can_create_campaigns INTEGER DEFAULT 1,
            can_send_campaigns INTEGER DEFAULT 1,
            can_edit_campaigns INTEGER DEFAULT 1,
            can_delete_campaigns INTEGER DEFAULT 0,
            can_export_contacts INTEGER DEFAULT 0,
            can_view_all_campaigns INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )";
        
        $db->exec($sql);
        
        // Create index
        $db->exec("CREATE INDEX IF NOT EXISTS idx_emp_perm_user ON employee_permissions(user_id)");
        
    } else {
        // MySQL version
        $sql = "CREATE TABLE IF NOT EXISTS employee_permissions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL UNIQUE,
            can_upload_contacts BOOLEAN DEFAULT TRUE,
            can_create_campaigns BOOLEAN DEFAULT TRUE,
            can_send_campaigns BOOLEAN DEFAULT TRUE,
            can_edit_campaigns BOOLEAN DEFAULT TRUE,
            can_delete_campaigns BOOLEAN DEFAULT FALSE,
            can_export_contacts BOOLEAN DEFAULT FALSE,
            can_view_all_campaigns BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
    }
    
    echo "✅ Employee permissions table created successfully!\n";
    
    // Verify table creation
    if ($database->getDatabaseType() === 'sqlite') {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='employee_permissions'");
    } else {
        $stmt = $db->query("SHOW TABLES LIKE 'employee_permissions'");
    }
    
    if ($stmt->fetch()) {
        echo "✅ Table 'employee_permissions' verified!\n\n";
        
        // Create default permissions for existing employees
        echo "Creating default permissions for existing employees...\n";
        
        $stmt = $db->query("SELECT id FROM users WHERE role IN ('agent', 'manager')");
        $employees = $stmt->fetchAll();
        
        foreach ($employees as $employee) {
            try {
                $checkStmt = $db->prepare("SELECT id FROM employee_permissions WHERE user_id = ?");
                $checkStmt->execute([$employee['id']]);
                
                if (!$checkStmt->fetch()) {
                    $insertStmt = $db->prepare("INSERT INTO employee_permissions (user_id) VALUES (?)");
                    $insertStmt->execute([$employee['id']]);
                    echo "✅ Created permissions for user ID: " . $employee['id'] . "\n";
                }
            } catch (Exception $e) {
                echo "⚠️  Skipped user ID " . $employee['id'] . " (may already exist)\n";
            }
        }
        
        echo "\n✅ Default permissions created for all employees!\n";
    } else {
        echo "❌ Table 'employee_permissions' not found!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating employee permissions table: " . $e->getMessage() . "\n";
}

echo "\n⚠️  This script can be run multiple times safely (CREATE IF NOT EXISTS)\n";