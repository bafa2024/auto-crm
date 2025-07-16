<?php
// verify_setup.php - Verify that the setup is working correctly

echo "AutoDial Pro CRM - Setup Verification\n";
echo "====================================\n\n";

// Load environment variables
if (file_exists(__DIR__ . '/.env')) {
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
} else {
    echo "❌ .env file not found\n";
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
    exit(1);
}

// Check if tables exist
echo "\nChecking database tables...\n";
$requiredTables = ['users', 'contacts', 'email_campaigns', 'email_templates'];

foreach ($requiredTables as $table) {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute([$table]);
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ $table table exists\n";
    } else {
        echo "❌ $table table missing\n";
    }
}

// Check if admin user exists
echo "\nChecking admin user...\n";
$stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, status FROM users WHERE email = ?");
$stmt->execute(['admin@autocrm.com']);
$adminUser = $stmt->fetch();

if ($adminUser) {
    echo "✓ Admin user exists:\n";
    echo "  - Email: " . $adminUser['email'] . "\n";
    echo "  - Name: " . $adminUser['first_name'] . " " . $adminUser['last_name'] . "\n";
    echo "  - Role: " . $adminUser['role'] . "\n";
    echo "  - Status: " . $adminUser['status'] . "\n";
} else {
    echo "❌ Admin user not found\n";
}

// Check if company_name column exists
echo "\nChecking users table structure...\n";
$stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'company_name'");
$stmt->execute();
$columnExists = $stmt->fetch();

if ($columnExists) {
    echo "✓ company_name column exists in users table\n";
} else {
    echo "❌ company_name column missing from users table\n";
}

// Test API endpoints
echo "\nTesting API endpoints...\n";

// Test signup endpoint
$testData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'company_name' => 'Test Company',
    'email' => 'test@example.com',
    'password' => 'testpassword123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $_ENV['APP_URL'] ?? 'http://localhost' . '/api/auth/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200 || $httpCode == 409) { // 409 means email already exists
    echo "✓ API endpoint /api/auth/register is accessible\n";
} else {
    echo "⚠️  API endpoint /api/auth/register returned HTTP $httpCode\n";
}

// Check file permissions
echo "\nChecking file permissions...\n";
$directories = ['uploads', 'logs', 'temp', 'backups', 'cache', 'sessions'];

foreach ($directories as $dir) {
    if (is_dir(__DIR__ . '/' . $dir)) {
        if (is_writable(__DIR__ . '/' . $dir)) {
            echo "✓ $dir directory is writable\n";
        } else {
            echo "⚠️  $dir directory is not writable\n";
        }
    } else {
        echo "❌ $dir directory missing\n";
    }
}

echo "\n✅ Setup verification completed!\n";
echo "\nYour AutoDial Pro CRM application should be ready to use.\n";
echo "Access URL: " . ($_ENV['APP_URL'] ?? 'http://localhost') . "\n";
echo "Admin Login: admin@autocrm.com / admin123\n"; 