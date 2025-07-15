<?php
// setup.php - Run this script to set up the project

echo "AutoDial Pro CRM Setup\n";
echo "=====================\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die("Error: PHP 7.4 or higher is required. You have " . PHP_VERSION . "\n");
}

echo "✓ PHP version " . PHP_VERSION . " is compatible\n";

// Check required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    } else {
        echo "✓ PHP extension '$ext' is installed\n";
    }
}

if (!empty($missingExtensions)) {
    echo "\n❌ Missing required PHP extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "Please install these extensions before continuing.\n";
    exit(1);
}

// Create necessary directories
$directories = [
    'uploads',
    'logs',
    'temp',
    'backups',
    'cache',
    'sessions'
];

echo "\nCreating directories...\n";
foreach ($directories as $dir) {
    if (!is_dir(__DIR__ . '/' . $dir)) {
        if (mkdir(__DIR__ . '/' . $dir, 0755, true)) {
            echo "✓ Created directory: $dir\n";
        } else {
            echo "❌ Failed to create directory: $dir\n";
        }
    } else {
        echo "✓ Directory exists: $dir\n";
    }
}

// Create .env file if it doesn't exist
if (!file_exists(__DIR__ . '/.env')) {
    if (file_exists(__DIR__ . '/.env.example')) {
        copy(__DIR__ . '/.env.example', __DIR__ . '/.env');
        echo "\n✓ Created .env file from .env.example\n";
        echo "⚠️  Please update .env with your configuration\n";
    } else {
        // Create a basic .env file
        $envContent = <<<ENV
# Application
APP_NAME="AutoDial Pro CRM"
APP_ENV=development
APP_URL=http://localhost
APP_DEBUG=true

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=autocrm
DB_USER=root
DB_PASS=

# Security
JWT_SECRET=change_this_to_a_random_string
SESSION_LIFETIME=3600

# Email
SMTP_HOST=localhost
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
ENV;
        file_put_contents(__DIR__ . '/.env', $envContent);
        echo "\n✓ Created default .env file\n";
        echo "⚠️  Please update .env with your configuration\n";
    }
}

// Create .htaccess if it doesn't exist
if (!file_exists(__DIR__ . '/.htaccess')) {
    $htaccessContent = file_get_contents(__DIR__ . '/.htaccess.example') ?? 
'RewriteEngine On

# Handle Authorization Header
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# Redirect to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]';
    
    file_put_contents(__DIR__ . '/.htaccess', $htaccessContent);
    echo "✓ Created .htaccess file\n";
}

// Check if Composer is installed
echo "\nChecking Composer...\n";
exec('composer --version 2>&1', $output, $returnCode);

if ($returnCode === 0) {
    echo "✓ Composer is installed\n";
    echo "\nInstalling dependencies...\n";
    system('composer install');
} else {
    echo "⚠️  Composer is not installed\n";
    echo "The project will use the custom autoloader.\n";
    echo "For better performance and to use external packages, install Composer:\n";
    echo "https://getcomposer.org/download/\n";
}

// Test database connection
echo "\nTesting database connection...\n";
require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "✓ Database connection successful\n";
        
        // Ask if user wants to initialize database
        echo "\nDo you want to initialize the database schema? (y/n): ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        
        if (trim($line) === 'y' || trim($line) === 'yes') {
            echo "Initializing database schema...\n";
            
            // Check if schema.sql exists
            $schemaFile = __DIR__ . '/database/schema.sql';
            if (!file_exists($schemaFile)) {
                // Create schema file
                $schemaDir = __DIR__ . '/database';
                if (!is_dir($schemaDir)) {
                    mkdir($schemaDir, 0755, true);
                }
                
                $schema = file_get_contents(__DIR__ . '/setup/schema.sql') ?? createDefaultSchema();
                file_put_contents($schemaFile, $schema);
            }
            
            try {
                $database->initializeSchema();
                echo "✓ Database schema initialized successfully\n";
            } catch (Exception $e) {
                echo "❌ Failed to initialize database: " . $e->getMessage() . "\n";
            }
        }
        
        fclose($handle);
    }
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in .env\n";
}

echo "\n✅ Setup complete!\n";
echo "\nNext steps:\n";
echo "1. Update .env with your configuration\n";
echo "2. Ensure your web server points to the project root\n";
echo "3. Access the application at your configured URL\n";

// Helper function to create default schema
function createDefaultSchema() {
    return <<<SQL
-- AutoDial Pro CRM Database Schema

CREATE DATABASE IF NOT EXISTS autocrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE autocrm;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
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

-- Insert default admin user (password: admin123)
INSERT INTO users (email, password, first_name, last_name, role, status) 
VALUES ('admin@autocrm.com', '\$2y\$10\$YourHashedPasswordHere', 'Admin', 'User', 'admin', 'active')
ON DUPLICATE KEY UPDATE id=id;
SQL;
}
?>