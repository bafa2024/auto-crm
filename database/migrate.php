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
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $tableExists = $stmt->fetch();
        
        if (!$tableExists) {
            echo "Creating $table table...\n";
            
            // Define table creation SQL directly instead of parsing from schema file
            $tableSql = '';
            
            switch ($table) {
                case 'contacts':
                    $tableSql = "CREATE TABLE IF NOT EXISTS contacts (
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
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    break;
                    
                case 'email_campaigns':
                    $tableSql = "CREATE TABLE IF NOT EXISTS email_campaigns (
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
                        INDEX idx_status (status)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    break;
                    
                case 'email_templates':
                    $tableSql = "CREATE TABLE IF NOT EXISTS email_templates (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        name VARCHAR(255) NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        content TEXT NOT NULL,
                        template_type VARCHAR(50),
                        is_active BOOLEAN DEFAULT TRUE,
                        created_by INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    break;
            }
            
            if ($tableSql) {
                $stmt = $db->prepare($tableSql);
                
                if ($stmt->execute()) {
                    echo "✓ $table table created successfully\n";
                } else {
                    echo "❌ Failed to create $table table\n";
                }
            }
        } else {
            echo "✓ $table table already exists\n";
        }
    }
    
    // Add new tables for teams and privileges
    $newTables = ['teams', 'team_members', 'worker_privileges'];
    foreach ($newTables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $tableExists = $stmt->fetch();
        if (!$tableExists) {
            echo "Creating $table table...\n";
            $tableSql = '';
            switch ($table) {
                case 'teams':
                    $tableSql = "CREATE TABLE IF NOT EXISTS teams (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        name VARCHAR(255) NOT NULL,
                        description TEXT,
                        created_by INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    break;
                case 'team_members':
                    $tableSql = "CREATE TABLE IF NOT EXISTS team_members (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        team_id INT NOT NULL,
                        user_id INT NOT NULL,
                        role ENUM('owner','worker') DEFAULT 'worker',
                        status ENUM('active','inactive') DEFAULT 'active',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    break;
                case 'worker_privileges':
                    $tableSql = "CREATE TABLE IF NOT EXISTS worker_privileges (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        team_id INT NOT NULL,
                        user_id INT NOT NULL,
                        privilege VARCHAR(100) NOT NULL,
                        allowed BOOLEAN DEFAULT TRUE,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
                    break;
            }
            if ($tableSql) {
                $stmt = $db->prepare($tableSql);
                if ($stmt->execute()) {
                    echo "✓ $table table created successfully\n";
                } else {
                    echo "❌ Failed to create $table table\n";
                }
            }
        } else {
            echo "✓ $table table already exists\n";
        }
    }
    
    // Check if admin user exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute(['admin@autocrm.com']);
    $adminExists = $stmt->fetch();
    
    if (!$adminExists) {
        echo "Creating default admin user...\n";
        
        $adminSql = "INSERT INTO users (email, password, first_name, last_name, company_name, role, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($adminSql);
        
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        
        if ($stmt->execute(['admin@autocrm.com', $hashedPassword, 'Admin', 'User', 'AutoDial Pro', 'admin', 'active'])) {
            echo "✓ Default admin user created successfully\n";
        } else {
            echo "❌ Failed to create default admin user\n";
        }
    } else {
        echo "✓ Default admin user already exists\n";
    }
    
    // Migration: Add timezone columns to users and email_campaigns
    $dbType = $database->getDatabaseType();

    function addColumnIfNotExists($db, $table, $column, $definition) {
        $exists = false;
        if ($GLOBALS['dbType'] === 'mysql') {
            // Use direct string interpolation for SHOW COLUMNS
            $stmt = $db->query("SHOW COLUMNS FROM `$table` LIKE '" . $column . "'");
            $exists = $stmt->fetch() ? true : false;
        } else {
            $stmt = $db->prepare("PRAGMA table_info($table)");
            $stmt->execute();
            foreach ($stmt->fetchAll() as $col) {
                if ($col['name'] === $column) {
                    $exists = true;
                    break;
                }
            }
        }
        if (!$exists) {
            if ($GLOBALS['dbType'] === 'mysql') {
                // Use direct string interpolation for ALTER TABLE
                $db->exec("ALTER TABLE `$table` ADD COLUMN `$column`