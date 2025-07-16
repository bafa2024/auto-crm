<?php
// setup_live.php - Live server setup

echo "AutoDial Pro CRM - Live Server Setup\n";
echo "====================================\n\n";

// Check if .env file exists
if (!file_exists(__DIR__ . '/.env')) {
    echo "❌ .env file not found. Please create one with your database credentials.\n";
    echo "\nExample .env file:\n";
    echo "DB_HOST=localhost\n";
    echo "DB_PORT=3306\n";
    echo "DB_NAME=autocrm\n";
    echo "DB_USER=your_username\n";
    echo "DB_PASS=your_password\n";
    echo "APP_URL=https://yourdomain.com\n";
    echo "JWT_SECRET=your_random_secret_key\n";
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

// Validate required environment variables
$required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
$missing = [];

foreach ($required as $var) {
    if (empty($_ENV[$var])) {
        $missing[] = $var;
    }
}

if (!empty($missing)) {
    echo "❌ Missing required environment variables: " . implode(', ', $missing) . "\n";
    exit(1);
}

echo "✓ Required environment variables validated\n\n";

// Test database connection
echo "Testing database connection...\n";
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

// Run schema
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
require_once __DIR__ . '/database/migrate.php';

// Set proper permissions
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

echo "\n✅ Live server setup completed successfully!\n";
echo "\nYour application is ready to use.\n";
echo "Default admin credentials:\n";
echo "   Email: admin@autocrm.com\n";
echo "   Password: admin123\n";
echo "\n⚠️  IMPORTANT: Change the default admin password after first login!\n"; 