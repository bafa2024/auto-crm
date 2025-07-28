<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== Live Server Contacts Table Backup ===\n\n";
    echo "Database Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    // Check if contacts table exists
    $stmt = $db->query("SHOW TABLES LIKE 'contacts'");
    $tableExists = $stmt->fetch();
    
    if (!$tableExists) {
        echo "❌ Contacts table does not exist. No backup needed.\n";
        exit(0);
    }
    
    echo "✅ Contacts table exists\n";
    
    // Count existing records
    $stmt = $db->query("SELECT COUNT(*) as count FROM contacts");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "📊 Found $count existing contacts\n";
    
    // Create backup table
    $backupTableName = 'contacts_backup_' . date('Y_m_d_H_i_s');
    echo "\n🔄 Creating backup table: $backupTableName\n";
    
    // Get table structure
    $stmt = $db->query("SHOW CREATE TABLE contacts");
    $createTable = $stmt->fetch(PDO::FETCH_ASSOC)['Create Table'];
    
    // Create backup table with same structure
    $backupCreateTable = str_replace('CREATE TABLE `contacts`', "CREATE TABLE `$backupTableName`", $createTable);
    $db->exec($backupCreateTable);
    
    // Copy data to backup table
    $db->exec("INSERT INTO $backupTableName SELECT * FROM contacts");
    
    // Verify backup
    $stmt = $db->query("SELECT COUNT(*) as count FROM $backupTableName");
    $backupCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "✅ Backup completed successfully\n";
    echo "📊 Original records: $count\n";
    echo "📊 Backup records: $backupCount\n";
    
    if ($count == $backupCount) {
        echo "✅ Backup verification successful - all records copied\n";
        echo "\n💾 Backup table name: $backupTableName\n";
        echo "📝 You can restore from this backup if needed using:\n";
        echo "   INSERT INTO contacts SELECT * FROM $backupTableName;\n";
    } else {
        echo "❌ Backup verification failed - record counts don't match\n";
    }
    
    echo "\n🎉 Backup process completed!\n";
    
} catch (Exception $e) {
    echo "❌ Backup failed: " . $e->getMessage() . "\n";
    echo "\nPlease check your database permissions and try again.\n";
} 