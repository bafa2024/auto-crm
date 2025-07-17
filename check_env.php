<?php
// check_env.php - Check and fix environment configuration

echo "AutoDial Pro CRM - Environment Check\n";
echo "====================================\n\n";

// Check if .env file exists
if (!file_exists(__DIR__ . '/.env')) {
    echo "❌ .env file not found. Creating one...\n";
    
    $envContent = <<<ENV
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u946493694_autocrm
DB_USER=u946493694_autocrmu
DB_PASS=CDExzsawq123@#$

# Application Configuration
APP_URL=https://autocrm.regrowup.ca
APP_ENV=production
APP_DEBUG=false

# Security
JWT_SECRET=change_this_to_a_random_string_$(uniqid())
SESSION_LIFETIME=3600

# Email Configuration (optional)
SMTP_HOST=localhost
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=tls
ENV;
    
    if (file_put_contents(__DIR__ . '/.env', $envContent)) {
        echo "✓ Created .env file\n";
        echo "⚠️  Please update the database credentials in .env file\n";
    } else {
        echo "❌ Failed to create .env file\n";
        exit(1);
    }
} else {
    echo "✓ .env file exists\n";
}

// Load and display current environment variables
echo "\n=== Current Environment Variables ===\n";
$envFile = file_get_contents(__DIR__ . '/.env');
$envLines = explode("\n", $envFile);
$env = [];

foreach ($envLines as $line) {
    $line = trim($line);
    if (!empty($line) && strpos($line, '=') !== false && !str_starts_with($line, '#')) {
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
        echo "$key = " . (strpos($key, 'PASS') !== false ? '***' : $value) . "\n";
    }
}

// Test database connection with current settings
echo "\n=== Database Connection Test ===\n";
try {
    $dsn = "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . 
           ";port=" . ($env['DB_PORT'] ?? '3306') . 
           ";dbname=" . ($env['DB_NAME'] ?? 'autocrm') . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env['DB_USER'] ?? 'root', $env['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Database connection successful\n";
    
    // Test if database exists
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "✓ Connected to database: " . ($result['current_db'] ?? 'None') . "\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nPossible solutions:\n";
    echo "1. Check if MySQL service is running\n";
    echo "2. Verify database credentials in .env file\n";
    echo "3. Ensure database 'autocrm' exists\n";
    echo "4. Check if user has proper permissions\n";
}

// Check if we're on HTTPS
echo "\n=== HTTPS Check ===\n";
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    echo "✓ Running on HTTPS\n";
} else {
    echo "⚠️  Running on HTTP (consider enabling HTTPS)\n";
}

// Check server configuration
echo "\n=== Server Configuration ===\n";
echo "Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'Unknown') . "\n";

// Check if .htaccess is being processed
if (isset($_SERVER['REDIRECT_STATUS'])) {
    echo "✓ .htaccess is being processed (REDIRECT_STATUS: " . $_SERVER['REDIRECT_STATUS'] . ")\n";
} else {
    echo "⚠️  .htaccess might not be processed\n";
}

echo "\n✅ Environment check completed!\n";
echo "\nIf database connection failed, please:\n";
echo "1. Update the .env file with correct database credentials\n";
echo "2. Ensure the 'autocrm' database exists\n";
echo "3. Run this script again to verify\n";