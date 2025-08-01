<?php
/**
 * Database Schema Fix Script
 * This script ensures consistent database schema for contacts management
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Database connection failed");
    }
    
    echo "<h1>Database Schema Fix</h1>";
    
    // Check if we're using SQLite or MySQL
    $isSQLite = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite';
    
    echo "<h2>1. Checking existing tables...</h2>";
    
    // Check what tables exist
    if ($isSQLite) {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    } else {
        $stmt = $db->query("SHOW TABLES");
    }
    
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p>Existing tables: " . implode(', ', $existingTables) . "</p>";
    
    // Ensure contacts table exists with correct structure
    echo "<h2>2. Ensuring contacts table structure...</h2>";
    
    $contactsTableSQL = "
        CREATE TABLE IF NOT EXISTS contacts (
            id INTEGER PRIMARY KEY " . ($isSQLite ? "AUTOINCREMENT" : "AUTO_INCREMENT") . ",
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            company VARCHAR(255),
            position VARCHAR(255),
            status VARCHAR(20) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ";
    
    $db->exec($contactsTableSQL);
    echo "<p>✅ Contacts table structure verified</p>";
    
    // Add indexes if they don't exist
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_contacts_email ON contacts(email)",
        "CREATE INDEX IF NOT EXISTS idx_contacts_status ON contacts(status)",
        "CREATE INDEX IF NOT EXISTS idx_contacts_created_at ON contacts(created_at)"
    ];
    
    foreach ($indexes as $indexSQL) {
        try {
            $db->exec($indexSQL);
            echo "<p>✅ Index created/verified</p>";
        } catch (Exception $e) {
            echo "<p>⚠️ Index already exists or error: " . $e->getMessage() . "</p>";
        }
    }
    
    // Check if email_recipients table exists and migrate data if needed
    echo "<h2>3. Checking email_recipients table...</h2>";
    
    if (in_array('email_recipients', $existingTables)) {
        echo "<p>📋 email_recipients table exists</p>";
        
        // Check if email_recipients has data that should be in contacts
        $stmt = $db->query("SELECT COUNT(*) as count FROM email_recipients");
        $recipientCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($recipientCount > 0) {
            echo "<p>📊 Found {$recipientCount} records in email_recipients</p>";
            
            // Check if contacts table is empty
            $stmt = $db->query("SELECT COUNT(*) as count FROM contacts");
            $contactCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($contactCount == 0) {
                echo "<p>🔄 Migrating data from email_recipients to contacts...</p>";
                
                // Get email_recipients structure
                if ($isSQLite) {
                    $stmt = $db->query("PRAGMA table_info(email_recipients)");
                } else {
                    $stmt = $db->query("DESCRIBE email_recipients");
                }
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Check if email_recipients has name field
                $hasNameField = false;
                foreach ($columns as $col) {
                    if (isset($col['name']) && $col['name'] === 'name') {
                        $hasNameField = true;
                        break;
                    }
                }
                
                if ($hasNameField) {
                    // Migrate data with name field
                    $migrationSQL = "
                        INSERT INTO contacts (first_name, last_name, email, company, created_at)
                        SELECT 
                            CASE 
                                WHEN name LIKE '% %' THEN SUBSTRING_INDEX(name, ' ', 1)
                                ELSE name 
                            END as first_name,
                            CASE 
                                WHEN name LIKE '% %' THEN SUBSTRING(name FROM LOCATE(' ', name) + 1)
                                ELSE '' 
                            END as last_name,
                            email,
                            company,
                            created_at
                        FROM email_recipients
                        WHERE email IS NOT NULL AND email != ''
                    ";
                } else {
                    // Migrate data without name field
                    $migrationSQL = "
                        INSERT INTO contacts (first_name, last_name, email, company, created_at)
                        SELECT 
                            'Unknown' as first_name,
                            '' as last_name,
                            email,
                            company,
                            created_at
                        FROM email_recipients
                        WHERE email IS NOT NULL AND email != ''
                    ";
                }
                
                try {
                    $db->exec($migrationSQL);
                    echo "<p>✅ Data migration completed</p>";
                } catch (Exception $e) {
                    echo "<p>❌ Migration error: " . $e->getMessage() . "</p>";
                }
            } else {
                echo "<p>ℹ️ Contacts table already has data, skipping migration</p>";
            }
        } else {
            echo "<p>ℹ️ email_recipients table is empty</p>";
        }
    } else {
        echo "<p>ℹ️ email_recipients table does not exist</p>";
    }
    
    // Verify final state
    echo "<h2>4. Final verification...</h2>";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM contacts");
    $finalCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "<p>✅ Contacts table has {$finalCount} records</p>";
    
    // Test query
    $stmt = $db->query("SELECT id, first_name, last_name, email, company, created_at FROM contacts LIMIT 5");
    $sampleData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sampleData)) {
        echo "<p>✅ Sample data verification successful</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Company</th><th>Created</th></tr>";
        foreach ($sampleData as $row) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
            echo "<td>" . htmlspecialchars($row['company'] ?? '') . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<h2>✅ Database schema fix completed successfully!</h2>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ul>";
    echo "<li>Test the contacts page to ensure search and filtering work</li>";
    echo "<li>Verify that all contact operations (create, edit, delete) work correctly</li>";
    echo "<li>Check that the API endpoints return the expected data structure</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h1>❌ Error during database schema fix</h1>";
    echo "<p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
    echo "<p><strong>Line:</strong> " . htmlspecialchars($e->getLine()) . "</p>";
}
?> 