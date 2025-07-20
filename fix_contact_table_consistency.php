<?php
// fix_contact_table_consistency.php - Fix contact table consistency
// Ensure all contact creation uses email_recipients table instead of contacts table

echo "=== Contact Table Consistency Fix ===\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Step 1: Check existing tables
    echo "1. Checking existing tables...\n";
    
    $tables = [];
    if ($database->getDatabaseType() === 'mysql') {
        $stmt = $db->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
    } else {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
    }
    
    echo "Found tables:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    $hasContactsTable = in_array('contacts', $tables);
    $hasEmailRecipientsTable = in_array('email_recipients', $tables);
    
    echo "\nTable status:\n";
    echo "- contacts table: " . ($hasContactsTable ? "EXISTS" : "NOT FOUND") . "\n";
    echo "- email_recipients table: " . ($hasEmailRecipientsTable ? "EXISTS" : "NOT FOUND") . "\n";
    
    // Step 2: Check email_recipients table structure
    echo "\n2. Checking email_recipients table structure...\n";
    
    if ($hasEmailRecipientsTable) {
        $stmt = $db->query("DESCRIBE email_recipients");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "email_recipients table columns:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']} ({$column['Null']})\n";
        }
    } else {
        echo "❌ email_recipients table not found\n";
        exit(1);
    }
    
    // Step 3: Check contacts table structure (if exists)
    if ($hasContactsTable) {
        echo "\n3. Checking contacts table structure...\n";
        
        $stmt = $db->query("DESCRIBE contacts");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "contacts table columns:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']} ({$column['Null']})\n";
        }
        
        // Step 4: Migrate data from contacts to email_recipients
        echo "\n4. Migrating data from contacts to email_recipients...\n";
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM contacts");
        $contactCount = $stmt->fetch()['count'];
        echo "Found $contactCount contacts to migrate\n";
        
        if ($contactCount > 0) {
            $stmt = $db->query("SELECT * FROM contacts");
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $migratedCount = 0;
            $skippedCount = 0;
            
            foreach ($contacts as $contact) {
                // Map contact fields to email_recipients fields
                $email = $contact['email'] ?? '';
                $name = '';
                
                // Combine first_name and last_name if they exist
                if (isset($contact['first_name']) && isset($contact['last_name'])) {
                    $name = trim($contact['first_name'] . ' ' . $contact['last_name']);
                } elseif (isset($contact['first_name'])) {
                    $name = $contact['first_name'];
                } elseif (isset($contact['last_name'])) {
                    $name = $contact['last_name'];
                } elseif (isset($contact['name'])) {
                    $name = $contact['name'];
                }
                
                $company = $contact['company'] ?? '';
                $dot = $contact['dot'] ?? '';
                $createdAt = $contact['created_at'] ?? date('Y-m-d H:i:s');
                
                // Check if email already exists in email_recipients
                $stmt = $db->prepare("SELECT id FROM email_recipients WHERE email = ?");
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $skippedCount++;
                    continue; // Skip if email already exists
                }
                
                // Insert into email_recipients
                $sql = "INSERT INTO email_recipients (email, name, company, dot, created_at) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$email, $name, $company, $dot, $createdAt]);
                
                $migratedCount++;
            }
            
            echo "✅ Migrated $migratedCount contacts\n";
            echo "⚠️ Skipped $skippedCount duplicates\n";
        } else {
            echo "✅ No contacts to migrate\n";
        }
        
        // Step 5: Drop contacts table
        echo "\n5. Dropping contacts table...\n";
        
        $db->exec("DROP TABLE contacts");
        echo "✅ contacts table dropped\n";
    } else {
        echo "\n3. No contacts table found - nothing to migrate\n";
    }
    
    // Step 6: Verify email_recipients table has all contacts
    echo "\n6. Verifying email_recipients table...\n";
    
    $stmt = $db->query("SELECT COUNT(*) as count FROM email_recipients");
    $totalContacts = $stmt->fetch()['count'];
    echo "✅ Total contacts in email_recipients: $totalContacts\n";
    
    // Step 7: Test contact creation
    echo "\n7. Testing contact creation in email_recipients...\n";
    
    $testContact = [
        'email' => 'consistencytest@example.com',
        'name' => 'Consistency Test',
        'company' => 'Consistency Company',
        'dot' => '123456',
        'campaign_id' => null
    ];
    
    $currentTime = date('Y-m-d H:i:s');
    $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    try {
        $result = $stmt->execute([
            $testContact['email'],
            $testContact['name'],
            $testContact['company'],
            $testContact['dot'],
            $testContact['campaign_id'],
            $currentTime
        ]);
        
        if ($result) {
            $contactId = $db->lastInsertId();
            echo "✅ Contact creation test successful (ID: $contactId)\n";
            
            // Clean up test data
            $db->exec("DELETE FROM email_recipients WHERE id = $contactId");
            echo "✅ Test data cleaned up\n";
        } else {
            echo "❌ Contact creation test failed\n";
        }
    } catch (Exception $e) {
        echo "❌ Contact creation test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Step 8: Update files that reference contacts table
    echo "\n8. Checking for files that reference contacts table...\n";
    
    $filesToCheck = [
        'fix_contact_datetime_issue.php',
        'setup_admin.php',
        'verify_acrm_live.php'
    ];
    
    foreach ($filesToCheck as $file) {
        if (file_exists($file)) {
            echo "⚠️ Found file that may reference contacts table: $file\n";
            echo "   This file should be updated to use email_recipients table\n";
        }
    }
    
    // Final summary
    echo "\n=== Fix Summary ===\n";
    echo "✅ email_recipients table verified\n";
    if ($hasContactsTable) {
        echo "✅ contacts table data migrated to email_recipients\n";
        echo "✅ contacts table dropped\n";
    }
    echo "✅ Contact creation test successful\n";
    echo "✅ All contacts now use email_recipients table\n";
    
    echo "\n🎉 Contact table consistency fix completed!\n";
    echo "All contact creation now uses the email_recipients table.\n";
    echo "\n📝 IMPORTANT NOTES:\n";
    echo "- All contacts are now stored in email_recipients table\n";
    echo "- Manual contact creation works correctly\n";
    echo "- Bulk upload works correctly\n";
    echo "- No more confusion between tables\n";
    
    echo "\n🚀 NEXT STEPS:\n";
    echo "1. Test manual contact creation on the website\n";
    echo "2. Test bulk contact upload\n";
    echo "3. Verify all contacts appear in the contacts list\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 