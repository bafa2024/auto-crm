<?php
// switch_to_mysql.php - Switch back to MySQL database

echo "Switching back to MySQL Database\n";
echo "================================\n\n";

$originalDbFile = 'config/database.php';
$backupDbFile = 'config/database_mysql_backup.php';

try {
    // Check if backup exists
    if (!file_exists($backupDbFile)) {
        throw new Exception("MySQL database backup not found. Cannot restore.");
    }
    
    // Restore MySQL configuration
    copy($backupDbFile, $originalDbFile);
    echo "✓ Restored MySQL database configuration\n";
    
    // Test MySQL connection (optional - might fail if MySQL not available)
    echo "\n2. Testing MySQL connection...\n";
    try {
        require_once $originalDbFile;
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "✓ MySQL database connection successful\n";
        }
    } catch (Exception $e) {
        echo "⚠ MySQL connection test failed: " . $e->getMessage() . "\n";
        echo "  This is normal if MySQL server is not running or configured\n";
    }
    
    echo "\n✅ Successfully switched back to MySQL!\n";
    echo "\nNote: Make sure your MySQL server is running and configured\n";
    echo "To switch back to SQLite for testing, run: php switch_to_sqlite.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>