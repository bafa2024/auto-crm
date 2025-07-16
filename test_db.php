<?php
// test_db.php - Test database connection

echo "Testing Database Connection\n";
echo "==========================\n\n";

// Test basic PDO connection
try {
    $dsn = "mysql:host=localhost;dbname=autocrm;charset=utf8mb4";
    $pdo = new PDO($dsn, 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Basic PDO connection successful\n";
    
    // Test if database exists
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $result = $stmt->fetch();
    echo "✓ Current database: " . ($result['current_db'] ?? 'None') . "\n";
    
    // Test if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "✓ Users table exists\n";
        
        // Check table structure
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        
        echo "Users table columns:\n";
        foreach ($columns as $column) {
            echo "  - " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    } else {
        echo "⚠️  Users table does not exist\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting tips:\n";
    echo "1. Make sure XAMPP MySQL service is running\n";
    echo "2. Check if the 'autocrm' database exists\n";
    echo "3. Verify MySQL credentials (default: root with no password)\n";
}

echo "\nTesting CloudConfig...\n";
echo "=====================\n";

require_once __DIR__ . '/config/cloud.php';

try {
    $config = CloudConfig::getDatabaseConfig();
    echo "✓ CloudConfig loaded successfully\n";
    echo "Database config:\n";
    echo "  Host: " . $config['host'] . "\n";
    echo "  Database: " . $config['database'] . "\n";
    echo "  Username: " . $config['username'] . "\n";
    echo "  Password: " . (empty($config['password']) ? '(empty)' : '(set)') . "\n";
    
} catch (Exception $e) {
    echo "❌ CloudConfig error: " . $e->getMessage() . "\n";
} 