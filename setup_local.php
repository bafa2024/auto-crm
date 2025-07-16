<?php
// setup_local.php - Local development setup for XAMPP

echo "AutoDial Pro CRM - Local Setup (XAMPP)\n";
echo "======================================\n\n";

// Check if XAMPP is running
echo "Checking XAMPP services...\n";

// Check Apache
$apacheRunning = false;
$ch = curl_init('http://localhost');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode == 200) {
    echo "✓ Apache is running\n";
    $apacheRunning = true;
} else {
    echo "❌ Apache is not running. Please start XAMPP Apache service.\n";
}

// Check MySQL
$mysqlRunning = false;
try {
    $pdo = new PDO("mysql:host=localhost", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5
    ]);
    echo "✓ MySQL is running\n";
    $mysqlRunning = true;
} catch (PDOException $e) {
    echo "❌ MySQL is not running. Please start XAMPP MySQL service.\n";
    echo "   Error: " . $e->getMessage() . "\n";
}

if (!$apacheRunning || !$mysqlRunning) {
    echo "\n⚠️  Please start the required XAMPP services and run this script again.\n";
    echo "   You can start them from XAMPP Control Panel.\n";
    exit(1);
}

echo "\n✓ All required services are running\n\n";

// Create database if it doesn't exist
echo "Setting up database...\n";
try {
    $pdo = new PDO("mysql:host=localhost", 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS autocrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database 'autocrm' created/verified\n";
    
    // Use the database
    $pdo->exec("USE autocrm");
    
    // Run schema
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (file_exists($schemaFile)) {
        $schema = file_get_contents($schemaFile);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        foreach ($statements as $statement) {
            if (!empty($statement) && !preg_match('/^(USE|CREATE DATABASE)/i', $statement)) {
                $pdo->exec($statement);
            }
        }
        
        echo "✓ Database schema applied successfully\n";
    } else {
        echo "❌ Schema file not found: $schemaFile\n";
    }
    
    // Run migration
    echo "\nRunning migrations...\n";
    require_once __DIR__ . '/database/migrate.php';
    
} catch (PDOException $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Local setup completed successfully!\n";
echo "\nYou can now access the application at:\n";
echo "   http://localhost/autocrm\n";
echo "\nDefault admin credentials:\n";
echo "   Email: admin@autocrm.com\n";
echo "   Password: admin123\n"; 