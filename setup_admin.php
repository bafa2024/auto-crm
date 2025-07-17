<?php
// setup_admin.php - Setup Admin User and Required Data

echo "AutoDial Pro CRM - Admin Setup\n";
echo "==============================\n\n";

// Load environment variables
$env = [];
if (file_exists(__DIR__ . '/.env')) {
    $envFile = file_get_contents(__DIR__ . '/.env');
    $envLines = explode("\n", $envFile);
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\'');
        }
    }
    echo "✓ Environment file loaded\n";
} else {
    die("❌ .env file not found. Please run check_env.php first\n");
}

// Database connection
try {
    $dsn = "mysql:host=" . $env['DB_HOST'] . 
           ";port=" . ($env['DB_PORT'] ?? '3306') . 
           ";dbname=" . $env['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Database connection successful\n\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Create tables if they don't exist
echo "=== Creating Database Tables ===\n";

// Users table
$createUsersTable = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(255),
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'agent') DEFAULT 'agent',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

try {
    $pdo->exec($createUsersTable);
    echo "✓ Users table ready\n";
} catch (Exception $e) {
    echo "⚠️  Users table error: " . $e->getMessage() . "\n";
}

// Contacts table
$createContactsTable = "
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createContactsTable);
    echo "✓ Contacts table ready\n";
} catch (Exception $e) {
    echo "⚠️  Contacts table error: " . $e->getMessage() . "\n";
}

// Email campaigns table
$createCampaignsTable = "
CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createCampaignsTable);
    echo "✓ Email campaigns table ready\n";
} catch (Exception $e) {
    echo "⚠️  Email campaigns table error: " . $e->getMessage() . "\n";
}

// Email templates table
$createTemplatesTable = "
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    template_type VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createTemplatesTable);
    echo "✓ Email templates table ready\n";
} catch (Exception $e) {
    echo "⚠️  Email templates table error: " . $e->getMessage() . "\n";
}

// Check if admin user exists
echo "\n=== Creating Admin User ===\n";
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
$stmt->execute(['admin@autocrm.com']);
$result = $stmt->fetch();

if ($result['count'] > 0) {
    echo "✓ Admin user already exists\n";
    
    // Update password to ensure it's correct
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$hashedPassword, 'admin@autocrm.com']);
    echo "✓ Admin password reset to: admin123\n";
} else {
    echo "Creating admin user...\n";
    
    // Create admin user
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("
        INSERT INTO users (first_name, last_name, company_name, email, password, role, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    try {
        $stmt->execute([
            'Admin',
            'User',
            'AutoDial Pro CRM',
            'admin@autocrm.com',
            $hashedPassword,
            'admin',
            'active'
        ]);
        
        echo "✓ Admin user created successfully\n";
    } catch (Exception $e) {
        echo "❌ Failed to create admin user: " . $e->getMessage() . "\n";
    }
}

// Create sample contacts
echo "\n=== Creating Sample Data ===\n";
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM contacts");
$stmt->execute();
$contactCount = $stmt->fetch()['count'];

if ($contactCount == 0) {
    $sampleContacts = [
        ['John', 'Doe', 'john.doe@example.com', '+1234567890', 'ABC Company'],
        ['Jane', 'Smith', 'jane.smith@example.com', '+1234567891', 'XYZ Corp'],
        ['Mike', 'Johnson', 'mike.johnson@example.com', '+1234567892', 'Tech Solutions'],
        ['Sarah', 'Williams', 'sarah.williams@example.com', '+1234567893', 'Marketing Pro'],
        ['David', 'Brown', 'david.brown@example.com', '+1234567894', 'Sales Team']
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO contacts (first_name, last_name, email, phone, company, created_by) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    
    foreach ($sampleContacts as $contact) {
        try {
            $stmt->execute($contact);
        } catch (Exception $e) {
            // Ignore duplicate errors
        }
    }
    
    echo "✓ Sample contacts created\n";
} else {
    echo "✓ Contacts already exist ($contactCount found)\n";
}

// Create sample email templates
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM email_templates");
$stmt->execute();
$templateCount = $stmt->fetch()['count'];

if ($templateCount == 0) {
    $sampleTemplates = [
        [
            'Welcome Email',
            'Welcome to {{company_name}}!',
            '<h2>Welcome {{first_name}}!</h2><p>Thank you for joining us. We\'re excited to have you on board.</p>'
        ],
        [
            'Follow-up Email',
            'Following up on our conversation',
            '<p>Hi {{first_name}},</p><p>I wanted to follow up on our recent conversation. Do you have any questions?</p>'
        ]
    ];
    
    $stmt = $pdo->prepare("
        INSERT INTO email_templates (name, subject, content, created_by) 
        VALUES (?, ?, ?, 1)
    ");
    
    foreach ($sampleTemplates as $template) {
        try {
            $stmt->execute($template);
        } catch (Exception $e) {
            // Ignore errors
        }
    }
    
    echo "✓ Sample email templates created\n";
} else {
    echo "✓ Email templates already exist ($templateCount found)\n";
}

// Verify admin user
echo "\n=== Verification ===\n";
$stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, status FROM users WHERE email = ?");
$stmt->execute(['admin@autocrm.com']);
$adminUser = $stmt->fetch();

if ($adminUser) {
    echo "✓ Admin user verified:\n";
    echo "   ID: " . $adminUser['id'] . "\n";
    echo "   Name: " . $adminUser['first_name'] . " " . $adminUser['last_name'] . "\n";
    echo "   Email: " . $adminUser['email'] . "\n";
    echo "   Role: " . $adminUser['role'] . "\n";
    echo "   Status: " . $adminUser['status'] . "\n";
}

echo "\n✅ Setup completed successfully!\n";
echo "\n📋 Summary:\n";
echo "   Admin Email: admin@autocrm.com\n";
echo "   Admin Password: admin123\n";
echo "   Application URL: " . ($env['APP_URL'] ?? 'https://autocrm.regrowup.ca') . "\n";
echo "\n⚠️  IMPORTANT: Change the admin password after first login!\n";