<?php
// fix_schema.php - Fix missing schema file on live server

echo "AutoDial Pro CRM - Schema Fix\n";
echo "=============================\n\n";

// Create database directory if it doesn't exist
$dbDir = __DIR__ . '/database';
if (!is_dir($dbDir)) {
    if (mkdir($dbDir, 0755, true)) {
        echo "✓ Created database directory\n";
    } else {
        echo "❌ Failed to create database directory\n";
        exit(1);
    }
}

// Create schema file
$schemaFile = $dbDir . '/schema.sql';
$schemaContent = <<<'SQL'
-- AutoDial Pro CRM Database Schema

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(255),
    role ENUM('admin', 'manager', 'agent') DEFAULT 'agent',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contacts table
CREATE TABLE IF NOT EXISTS contacts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email campaigns table
CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT PRIMARY KEY AUTO_INCREMENT,
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
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    template_type VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default admin user (password: admin123)
INSERT INTO users (email, password, first_name, last_name, company_name, role, status) 
VALUES ('admin@autocrm.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'AutoDial Pro', 'admin', 'active')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;
SQL;

if (file_put_contents($schemaFile, $schemaContent)) {
    echo "✓ Created schema file: $schemaFile\n";
} else {
    echo "❌ Failed to create schema file\n";
    exit(1);
}

// Create migration file
$migrationFile = $dbDir . '/migrate.php';
$migrationContent = <<<'PHP'
<?php
// database/migrate.php - Database migration script

require_once __DIR__ . '/../config/database.php';

echo "AutoDial Pro CRM Database Migration\n";
echo "==================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("❌ Database connection failed\n");
    }
    
    echo "✓ Database connection successful\n\n";
    
    // Check if company_name column exists in users table
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'company_name'");
    $stmt->execute();
    $columnExists = $stmt->fetch();
    
    if (!$columnExists) {
        echo "Adding company_name column to users table...\n";
        
        $sql = "ALTER TABLE users ADD COLUMN company_name VARCHAR(255) AFTER last_name";
        $stmt = $db->prepare($sql);
        
        if ($stmt->execute()) {
            echo "✓ company_name column added successfully\n";
        } else {
            echo "❌ Failed to add company_name column\n";
        }
    } else {
        echo "✓ company_name column already exists\n";
    }
    
    // Check if tables exist, create them if they don't
    $tables = ['contacts', 'email_campaigns', 'email_templates'];
    
    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            echo "Creating $table table...\n";
            
            // Read schema file and execute table creation
            $schemaFile = __DIR__ . '/schema.sql';
            if (file_exists($schemaFile)) {
                $schema = file_get_contents($schemaFile);
                
                // Extract table creation for this specific table
                if (preg_match("/CREATE TABLE IF NOT EXISTS $table \(.*?\);/s", $schema, $matches)) {
                    $createTableSql = $matches[0];
                    $stmt = $db->prepare($createTableSql);
                    
                    if ($stmt->execute()) {
                        echo "✓ $table table created successfully\n";
                    } else {
                        echo "❌ Failed to create $table table\n";
                    }
                }
            }
        } else {
            echo "✓ $table table already exists\n";
        }
    }
    
    echo "\n✅ Migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
}
PHP;

if (file_put_contents($migrationFile, $migrationContent)) {
    echo "✓ Created migration file: $migrationFile\n";
} else {
    echo "❌ Failed to create migration file\n";
    exit(1);
}

echo "\n✅ Schema files created successfully!\n";
echo "\nNow you can run the setup script again:\n";
echo "php setup_live.php\n"; 