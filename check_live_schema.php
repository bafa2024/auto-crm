<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Checking live server database schema...\n";
    echo "Database Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    // Check if contacts table exists
    $stmt = $db->query("SHOW TABLES LIKE 'contacts'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "❌ Contacts table does not exist on live server!\n";
        exit(1);
    }
    
    echo "✅ Contacts table exists\n";
    
    // Get table schema
    $stmt = $db->query("DESCRIBE contacts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nContacts table columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})" . ($col['Null'] === 'NO' ? ' NOT NULL' : '') . "\n";
    }
    
    // Check for user_id and created_by columns
    $hasUserId = false;
    $hasCreatedBy = false;
    
    foreach ($columns as $col) {
        if ($col['Field'] === 'user_id') {
            $hasUserId = true;
        }
        if ($col['Field'] === 'created_by') {
            $hasCreatedBy = true;
        }
    }
    
    echo "\nColumn availability:\n";
    echo "- user_id: " . ($hasUserId ? '✅' : '❌') . "\n";
    echo "- created_by: " . ($hasCreatedBy ? '✅' : '❌') . "\n";
    
    if ($hasCreatedBy && !$hasUserId) {
        echo "\n✅ Live server schema is correct (has created_by, no user_id)\n";
    } else {
        echo "\n⚠️  Live server schema may need attention\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
} 