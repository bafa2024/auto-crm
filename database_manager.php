<?php
// database_manager.php - Easy database management for AutoDial Pro

function showUsage() {
    echo "AutoDial Pro Database Manager\n";
    echo "=============================\n\n";
    echo "Usage: php database_manager.php [command]\n\n";
    echo "Commands:\n";
    echo "  create-sqlite    Create SQLite database with sample data\n";
    echo "  switch-sqlite    Switch to SQLite for local testing\n";
    echo "  switch-mysql     Switch back to MySQL\n";
    echo "  test-sqlite      Test SQLite database and dashboard\n";
    echo "  info            Show current database information\n";
    echo "  reset-sqlite    Reset SQLite database (recreate with fresh data)\n";
    echo "  help            Show this help message\n\n";
    echo "Examples:\n";
    echo "  php database_manager.php create-sqlite\n";
    echo "  php database_manager.php switch-sqlite\n";
    echo "  php database_manager.php test-sqlite\n";
}

function getDatabaseInfo() {
    try {
        require_once 'config/database.php';
        $database = new Database();
        
        if (method_exists($database, 'getDatabaseInfo')) {
            return $database->getDatabaseInfo();
        } else {
            // Fallback for MySQL
            $conn = $database->getConnection();
            return [
                'type' => 'MySQL',
                'status' => $conn ? 'Connected' : 'Failed'
            ];
        }
    } catch (Exception $e) {
        return [
            'type' => 'Unknown',
            'status' => 'Error: ' . $e->getMessage()
        ];
    }
}

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'create-sqlite':
        echo "Creating SQLite database...\n";
        include 'database/create_sqlite.php';
        break;
        
    case 'switch-sqlite':
        echo "Switching to SQLite...\n";
        include 'switch_to_sqlite.php';
        break;
        
    case 'switch-mysql':
        echo "Switching to MySQL...\n";
        include 'switch_to_mysql.php';
        break;
        
    case 'test-sqlite':
        echo "Testing SQLite database...\n";
        include 'test_sqlite_dashboard.php';
        break;
        
    case 'reset-sqlite':
        echo "Resetting SQLite database...\n";
        $dbPath = __DIR__ . '/database/autocrm_local.db';
        if (file_exists($dbPath)) {
            unlink($dbPath);
            echo "✓ Removed existing database\n";
        }
        include 'database/create_sqlite.php';
        break;
        
    case 'info':
        echo "Current Database Information\n";
        echo "===========================\n\n";
        $info = getDatabaseInfo();
        
        if (isset($info['type'])) {
            echo "Database Type: " . $info['type'] . "\n";
            
            if ($info['type'] === 'SQLite') {
                echo "Database Path: " . $info['path'] . "\n";
                echo "Database Size: " . number_format($info['size']) . " bytes\n";
                
                if (!empty($info['tables'])) {
                    echo "\nTables:\n";
                    foreach ($info['tables'] as $table => $count) {
                        if ($table !== 'sqlite_sequence') {
                            echo "- $table: $count rows\n";
                        }
                    }
                }
            } else {
                echo "Status: " . $info['status'] . "\n";
            }
        }
        echo "\n";
        break;
        
    case 'help':
    default:
        showUsage();
        break;
}
?>