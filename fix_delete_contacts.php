<?php
require_once 'autoload.php';
require_once 'config/database.php';

echo "=== Fixing Delete Contacts Functionality ===\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if deleted_email_recipients table exists
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='deleted_email_recipients'");
    $result = $stmt->fetch();
    
    if (!$result) {
        echo "Creating deleted_email_recipients table...\n";
        
        $createTableSQL = "
        CREATE TABLE deleted_email_recipients (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            name TEXT,
            company TEXT,
            dot TEXT,
            campaign_id INTEGER,
            created_at DATETIME,
            updated_at DATETIME,
            deleted_by INTEGER,
            deletion_reason TEXT,
            original_id INTEGER,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($createTableSQL);
        echo "✅ deleted_email_recipients table created successfully\n";
    } else {
        echo "✅ deleted_email_recipients table already exists\n";
    }
    
    // Check if deleted_campaign_sends table exists
    $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='deleted_campaign_sends'");
    $result = $stmt->fetch();
    
    if (!$result) {
        echo "Creating deleted_campaign_sends table...\n";
        
        $createTableSQL = "
        CREATE TABLE deleted_campaign_sends (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            campaign_id INTEGER,
            recipient_id INTEGER,
            recipient_email TEXT,
            status TEXT,
            sent_at DATETIME,
            opened_at DATETIME,
            clicked_at DATETIME,
            tracking_id TEXT,
            original_id INTEGER,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($createTableSQL);
        echo "✅ deleted_campaign_sends table created successfully\n";
    } else {
        echo "✅ deleted_campaign_sends table already exists\n";
    }
    
    // Test the delete functionality
    echo "\n=== Testing Delete Functionality ===\n";
    
    // First, let's create a test contact
    $testEmail = 'test.delete@example.com';
    $testName = 'Test Delete Contact';
    $testCompany = 'Test Company';
    $testDot = '123456';
    
    // Check if test contact already exists
    $stmt = $db->prepare("SELECT id FROM email_recipients WHERE email = ?");
    $stmt->execute([$testEmail]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        echo "Test contact already exists, using existing ID: " . $existing['id'] . "\n";
        $testContactId = $existing['id'];
    } else {
        // Create test contact
        $sql = "INSERT INTO email_recipients (email, name, company, dot, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$testEmail, $testName, $testCompany, $testDot, date('Y-m-d H:i:s')]);
        $testContactId = $db->lastInsertId();
        echo "Created test contact with ID: $testContactId\n";
    }
    
    // Now test the delete functionality
    echo "Testing delete for contact ID: $testContactId\n";
    
    // Simulate the delete process
    try {
        $db->beginTransaction();
        
        // Get the recipient data
        $sql = "SELECT * FROM email_recipients WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$testContactId]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$recipient) {
            throw new Exception("Test contact not found");
        }
        
        echo "Found recipient: " . $recipient['email'] . "\n";
        
        // Move to deleted_email_recipients
        $sql = "INSERT INTO deleted_email_recipients (email, name, company, dot, campaign_id, created_at, updated_at, deleted_by, deletion_reason, original_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            $recipient['email'],
            $recipient['name'],
            $recipient['company'],
            $recipient['dot'],
            $recipient['campaign_id'],
            $recipient['created_at'],
            $recipient['updated_at'],
            1, // deleted_by
            'Test deletion',
            $testContactId
        ]);
        
        echo "✅ Moved to deleted_email_recipients\n";
        
        // Delete from email_recipients
        $sql = "DELETE FROM email_recipients WHERE id = ?";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([$testContactId]);
        
        if ($result) {
            $db->commit();
            echo "✅ Successfully deleted test contact\n";
            
            // Verify it's in the deleted table
            $sql = "SELECT * FROM deleted_email_recipients WHERE original_id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$testContactId]);
            $deleted = $stmt->fetch();
            
            if ($deleted) {
                echo "✅ Contact successfully archived in deleted_email_recipients\n";
            } else {
                echo "❌ Contact not found in deleted_email_recipients\n";
            }
        } else {
            throw new Exception("Failed to delete from email_recipients");
        }
        
    } catch (Exception $e) {
        $db->rollBack();
        echo "❌ Error during delete test: " . $e->getMessage() . "\n";
    }
    
    echo "\n=== Delete Functionality Test Complete ===\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}