<?php
// switch_to_sqlite.php - Switch to SQLite for local testing

echo "Switching to SQLite Database for Local Testing\n";
echo "==============================================\n\n";

$originalDbFile = 'config/database.php';
$sqliteDbFile = 'config/database_sqlite.php';
$backupDbFile = 'config/database_mysql_backup.php';

try {
    // 1. Backup original MySQL database config
    if (file_exists($originalDbFile)) {
        if (!file_exists($backupDbFile)) {
            copy($originalDbFile, $backupDbFile);
            echo "✓ Backed up MySQL database config\n";
        } else {
            echo "✓ MySQL database config backup already exists\n";
        }
    }
    
    // 2. Replace with SQLite config
    if (file_exists($sqliteDbFile)) {
        copy($sqliteDbFile, $originalDbFile);
        echo "✓ Switched to SQLite database configuration\n";
    } else {
        throw new Exception("SQLite database configuration not found");
    }
    
    // 3. Test the new configuration
    echo "\n2. Testing SQLite connection...\n";
    require_once $originalDbFile;
    
    $database = new Database();
    if ($database->testConnection()) {
        echo "✓ SQLite database connection successful\n";
        
        // Show database info
        $info = $database->getDatabaseInfo();
        echo "\nDatabase Information:\n";
        echo "- Type: " . $info['type'] . "\n";
        echo "- Path: " . $info['path'] . "\n";
        echo "- Size: " . number_format($info['size']) . " bytes\n";
        echo "\nTables and row counts:\n";
        foreach ($info['tables'] as $table => $count) {
            echo "- $table: $count rows\n";
        }
        
    } else {
        throw new Exception("SQLite database connection failed");
    }
    
    echo "\n✅ Successfully switched to SQLite!\n";
    echo "\nLogin credentials:\n";
    echo "- Admin: admin@autocrm.com / admin123\n";
    echo "- Test User: john@example.com / test123\n";
    
    echo "\nTo switch back to MySQL later, run:\n";
    echo "php switch_to_mysql.php\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>