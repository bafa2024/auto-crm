<?php
// quick_test.php - Quick API test with current database credentials

echo "AutoDial Pro CRM - Quick API Test\n";
echo "=================================\n\n";

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
echo "\n=== Database Connection Test ===\n";
try {
    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . 
           ";port=" . ($_ENV['DB_PORT'] ?? '3306') . 
           ";dbname=" . $_ENV['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Database connection successful\n";
    
    // Test if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Users table exists\n";
        
        // Check if admin user exists
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE email = ?");
        $stmt->execute(['admin@autocrm.com']);
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            echo "✓ Admin user exists\n";
        } else {
            echo "⚠️  Admin user not found\n";
        }
    } else {
        echo "❌ Users table not found\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test API endpoint directly
echo "\n=== Direct API Test ===\n";

// Load required files
require_once 'autoload.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo "❌ Database connection failed in Database class\n";
        exit(1);
    }
    
    echo "✓ Database class working\n";
    
    // Test AuthController
    $controller = new AuthController($db);
    echo "✓ AuthController loaded\n";
    
    // Test with sample data
    $testData = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'company_name' => 'Test Company',
        'email' => 'test' . time() . '@example.com',
        'password' => 'testpassword123'
    ];
    
    echo "Testing with email: " . $testData['email'] . "\n";
    
    // Create a simple request object
    $request = new stdClass();
    $request->body = $testData;
    
    // Capture output
    ob_start();
    
    try {
        $controller->register($request);
        $output = ob_get_clean();
        
        echo "✓ API call completed\n";
        echo "Response: " . $output . "\n";
        
    } catch (Exception $e) {
        ob_end_clean();
        echo "❌ API call failed: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Test web API endpoint
echo "\n=== Web API Test ===\n";

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
if (substr($baseUrl, -1) === '/') {
    $baseUrl = substr($baseUrl, 0, -1);
}

echo "Testing URL: $baseUrl/api.php/api/auth/register\n";

$testData = [
    'first_name' => 'WebTest',
    'last_name' => 'User',
    'company_name' => 'Web Test Company',
    'email' => 'webtest' . time() . '@example.com',
    'password' => 'testpassword123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api.php/api/auth/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: $error\n";
} else {
    echo "HTTP Code: $httpCode\n";
    echo "Response: $response\n";
    
    if ($httpCode == 200) {
        echo "✓ Web API endpoint working!\n";
    } else {
        echo "❌ Web API endpoint failed\n";
    }
}

echo "\n✅ Quick test completed!\n"; 