<?php
/**
 * Check database tables and structure
 */

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Database Type: " . $database->getDatabaseType() . "\n";
    echo "Environment: " . $database->getEnvironment() . "\n\n";
    
    echo "=== All Tables in Database ===\n";
    
    if ($database->getDatabaseType() === 'sqlite') {
        // SQLite
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            echo "- $table\n";
            
            // Get table structure
            $stmt = $db->query("PRAGMA table_info($table)");
            $columns = $stmt->fetchAll();
            foreach ($columns as $col) {
                echo "  └─ {$col['name']} ({$col['type']})\n";
            }
            echo "\n";
        }
    } else {
        // MySQL
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            echo "- $table\n";
            
            // Get table structure
            $stmt = $db->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll();
            foreach ($columns as $col) {
                echo "  └─ {$col['Field']} ({$col['Type']})\n";
            }
            echo "\n";
        }
    }
    
    // Check specifically for deleted_ tables
    $deletedTables = array_filter($tables, function($table) {
        return strpos($table, 'deleted_') === 0;
    });
    
    if (empty($deletedTables)) {
        echo "\n⚠️  No archive tables found!\n";
        echo "Need to run: php database/create_deleted_recipients_table.php\n";
    } else {
        echo "\n✓ Archive tables found: " . implode(', ', $deletedTables) . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>