<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== Live Server Contacts Table Migration ===\n\n";
    echo "Database Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    // Check if contacts table exists
    $stmt = $db->query("SHOW TABLES LIKE 'contacts'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "❌ Contacts table does not exist. Creating it...\n";
        
        $db->exec("
            CREATE TABLE contacts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100),
                email VARCHAR(255),
                phone VARCHAR(50),
                company VARCHAR(255),
                job_title VARCHAR(255),
                lead_source VARCHAR(100),
                interest_level ENUM('hot', 'warm', 'cold') DEFAULT 'warm',
                status ENUM('new', 'contacted', 'qualified', 'converted', 'lost') DEFAULT 'new',
                notes TEXT,
                assigned_agent_id INT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        echo "✅ Contacts table created successfully\n";
        exit(0);
    }
    
    echo "✅ Contacts table exists\n";
    
    // Get current table structure
    $stmt = $db->query("DESCRIBE contacts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nCurrent table structure:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
    }
    
    // Check for user_id column
    $hasUserId = false;
    $hasCreatedBy = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'user_id') {
            $hasUserId = true;
        }
        if ($col['Field'] === 'created_by') {
            $hasCreatedBy = true;
        }
    }
    
    echo "\nColumn analysis:\n";
    echo "- user_id: " . ($hasUserId ? '❌ (needs removal)' : '✅ (not present)') . "\n";
    echo "- created_by: " . ($hasCreatedBy ? '✅ (present)' : '❌ (missing)') . "\n";
    
    // If user_id exists, remove it
    if ($hasUserId) {
        echo "\n🔄 Removing user_id column...\n";
        
        // Check for foreign key constraints
        $stmt = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'contacts' 
            AND COLUMN_NAME = 'user_id'
        ");
        $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Drop foreign key constraints first
        foreach ($constraints as $constraint) {
            $constraintName = $constraint['CONSTRAINT_NAME'];
            echo "Dropping constraint: $constraintName\n";
            $db->exec("ALTER TABLE contacts DROP FOREIGN KEY `$constraintName`");
        }
        
        // Drop the column
        $db->exec("ALTER TABLE contacts DROP COLUMN user_id");
        echo "✅ user_id column removed successfully\n";
    }
    
    // If created_by doesn't exist, add it
    if (!$hasCreatedBy) {
        echo "\n🔄 Adding created_by column...\n";
        $db->exec("
            ALTER TABLE contacts 
            ADD COLUMN created_by INT,
            ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ");
        echo "✅ created_by column added successfully\n";
    }
    
    // Verify final structure
    echo "\n🔄 Verifying final table structure...\n";
    $stmt = $db->query("DESCRIBE contacts");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nFinal table structure:\n";
    foreach ($finalColumns as $col) {
        echo "- {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
    }
    
    // Final verification
    $hasUserIdFinal = false;
    $hasCreatedByFinal = false;
    
    foreach ($finalColumns as $col) {
        if ($col['Field'] === 'user_id') {
            $hasUserIdFinal = true;
        }
        if ($col['Field'] === 'created_by') {
            $hasCreatedByFinal = true;
        }
    }
    
    echo "\nFinal verification:\n";
    echo "- user_id: " . ($hasUserIdFinal ? '❌ (still present)' : '✅ (removed)') . "\n";
    echo "- created_by: " . ($hasCreatedByFinal ? '✅ (present)' : '❌ (missing)') . "\n";
    
    if (!$hasUserIdFinal && $hasCreatedByFinal) {
        echo "\n🎉 Migration completed successfully!\n";
        echo "✅ Contacts table is now properly configured for live server.\n";
    } else {
        echo "\n⚠️  Migration may have issues. Please check the table structure manually.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your database permissions and try again.\n";
} 