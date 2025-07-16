<?php
// deploy.php - One-click deployment script for live servers

echo "AutoDial Pro CRM - Deployment Script\n";
echo "====================================\n\n";

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    echo "❌ This script should be run from command line\n";
    echo "Usage: php deploy.php\n";
    exit(1);
}

// Check PHP version
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die("❌ PHP 7.4 or higher is required. You have " . PHP_VERSION . "\n");
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
    exit(1);
}

// Check if .env file exists
if (!file_exists(__DIR__ . '/.env')) {
    echo "\n❌ .env file not found. Creating template...\n";
    
    $envTemplate = <<<ENV
# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=autocrm
DB_USER=your_database_username
DB_PASS=your_database_password

# Application Configuration
APP_URL=https://yourdomain.com
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
    
    file_put_contents(__DIR__ . '/.env', $envTemplate);
    echo "✓ Created .env template file\n";
    echo "⚠️  Please edit .env file with your actual database credentials\n";
    echo "   Then run this script again.\n";
    exit(1);
}

// Load environment variables
$envFile = file_get_contents(__DIR__ . '/.env');
$envLines = explode("\n", $envFile);
$env = [];

foreach ($envLines as $line) {
    $line = trim($line);
    if (!empty($line) && strpos($line, '=') !== false && !str_starts_with($line, '#')) {
        list($key, $value) = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
}

// Set environment variables
foreach ($env as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

echo "✓ Environment variables loaded\n";

// Validate database configuration
$required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
$missing = [];

foreach ($required as $var) {
    if (empty($_ENV[$var])) {
        $missing[] = $var;
    }
}

if (!empty($missing)) {
    echo "❌ Missing required environment variables: " . implode(', ', $missing) . "\n";
    echo "Please update your .env file with the required database configuration.\n";
    exit(1);
}

// Test database connection
echo "\nTesting database connection...\n";
try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . 
           ";port=" . ($_ENV['DB_PORT'] ?? '3306') . 
           ";dbname=" . $_ENV['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Database connection successful\n";
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your database credentials in .env file.\n";
    exit(1);
}

// Create necessary directories
echo "\nCreating directories...\n";
$directories = ['uploads', 'logs', 'temp', 'backups', 'cache', 'sessions', 'database'];

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

// Apply database schema
echo "\nApplying database schema...\n";
$schemaFile = __DIR__ . '/database/schema.sql';
if (file_exists($schemaFile)) {
    $schema = file_get_contents($schemaFile);
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^(USE|CREATE DATABASE)/i', $statement)) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Ignore "table already exists" errors
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠️  Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    echo "✓ Database schema applied\n";
} else {
    echo "❌ Schema file not found: $schemaFile\n";
    exit(1);
}

// Run migrations
echo "\nRunning migrations...\n";
if (file_exists(__DIR__ . '/database/migrate.php')) {
    require_once __DIR__ . '/database/migrate.php';
    echo "✓ Migrations completed\n";
} else {
    echo "⚠️  Migration file not found\n";
}

// Set file permissions
echo "\nSetting file permissions...\n";
$directories = ['uploads', 'logs', 'temp', 'backups', 'cache', 'sessions'];
foreach ($directories as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        chmod(__DIR__ . '/' . $dir, 0755);
        echo "✓ Set permissions for $dir\n";
    }
}

// Create .htaccess if it doesn't exist
if (!file_exists(__DIR__ . '/.htaccess')) {
    $htaccessContent = "RewriteEngine On\n\n";
    $htaccessContent .= "# Handle Authorization Header\n";
    $htaccessContent .= "RewriteCond %{HTTP:Authorization} .\n";
    $htaccessContent .= "RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]\n\n";
    $htaccessContent .= "# Redirect to index.php\n";
    $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
    $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
    $htaccessContent .= "RewriteRule ^(.*)$ index.php [QSA,L]\n";
    
    file_put_contents(__DIR__ . '/.htaccess', $htaccessContent);
    echo "✓ Created .htaccess file\n";
}

// Security check
echo "\nSecurity check...\n";
if (strpos($_ENV['JWT_SECRET'] ?? '', 'change_this_to_a_random_string') !== false) {
    echo "⚠️  WARNING: Please change the JWT_SECRET in your .env file\n";
}

if ($_ENV['APP_DEBUG'] === 'true') {
    echo "⚠️  WARNING: APP_DEBUG is enabled. Disable in production.\n";
}

echo "\n✅ Deployment completed successfully!\n";
echo "\nYour application is ready to use.\n";
echo "Default admin credentials:\n";
echo "   Email: admin@autocrm.com\n";
echo "   Password: admin123\n";
echo "\n⚠️  IMPORTANT:\n";
echo "   1. Change the default admin password after first login\n";
echo "   2. Update JWT_SECRET in .env file\n";
echo "   3. Set APP_DEBUG=false in production\n";
echo "   4. Enable HTTPS for security\n"; 