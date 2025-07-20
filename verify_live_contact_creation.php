<?php
// test_live_contact_creation.php - Test contact creation on live server
// This script tests the exact contact creation functionality

echo "=== Live Server Contact Creation Test ===\n\n";

try {
    require_once 'config/database.php';
    $database = new Database();
    
    echo "Environment: " . $database->getEnvironment() . "\n";
    echo "Database Type: " . $database->getDatabaseType() . "\n\n";
    
    $db = $database->getConnection();
    
    // Test 1: Check campaign_id column
    echo "1. Checking campaign_id column...\n";
    
    $stmt = $db->query("DESCRIBE email_recipients");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $campaignIdColumn = null;
    foreach ($columns as $column) {
        if ($column['Field'] === 'campaign_id') {
            $campaignIdColumn = $column;
            break;
        }
    }
    
    if ($campaignIdColumn) {
        echo "campaign_id column:\n";
        echo "- Field: {$campaignIdColumn['Field']}\n";
        echo "- Type: {$campaignIdColumn['Type']}\n";
        echo "- Null: {$campaignIdColumn['Null']}\n";
        
        if ($campaignIdColumn['Null'] === 'YES') {
            echo "✅ campaign_id is nullable - this is correct\n";
        } else {
            echo "❌ campaign_id is NOT NULL - run fix_live_campaign_id_issue.php first\n";
            exit(1);
        }
    } else {
        echo "❌ campaign_id column not found\n";
        exit(1);
    }
    
    // Test 2: Test contact creation with NULL campaign_id
    echo "\n2. Testing contact creation with NULL campaign_id...\n";
    
    $testEmail = 'livetest' . time() . '@example.com';
    $testContact = [
        'email' => $testEmail,
        'name' => 'Live Test Contact',
        'company' => 'Live Test Company',
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
            echo "✅ Contact creation successful (ID: $contactId)\n";
            
            // Verify the contact
            $stmt = $db->prepare("SELECT * FROM email_recipients WHERE id = ?");
            $stmt->execute([$contactId]);
            $contact = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contact) {
                echo "✅ Contact verified:\n";
                echo "- Email: {$contact['email']}\n";
                echo "- Name: {$contact['name']}\n";
                echo "- Campaign ID: " . ($contact['campaign_id'] === null ? 'NULL' : $contact['campaign_id']) . "\n";
            }
            
            // Clean up
            $db->exec("DELETE FROM email_recipients WHERE id = $contactId");
            echo "✅ Test data cleaned up\n";
        } else {
            echo "❌ Contact creation failed\n";
        }
    } catch (Exception $e) {
        echo "❌ Contact creation failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Test 3: Test with empty string campaign_id (like form submission)
    echo "\n3. Testing with empty string campaign_id...\n";
    
    $testEmail2 = 'liveemptytest' . time() . '@example.com';
    $campaignId = ''; // Empty string from form
    
    // Process like contacts.php does
    if (empty($campaignId) || $campaignId === '' || $campaignId === '0') {
        $campaignId = null;
    }
    
    echo "Processing empty string: '$campaignId' -> " . ($campaignId === null ? 'NULL' : $campaignId) . "\n";
    
    $currentTime = date('Y-m-d H:i:s');
    $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    try {
        $result = $stmt->execute([
            $testEmail2,
            'Empty String Test',
            'Empty String Company',
            '654321',
            $campaignId,
            $currentTime
        ]);
        
        if ($result) {
            $contactId = $db->lastInsertId();
            echo "✅ Empty string test successful (ID: $contactId)\n";
            
            // Clean up
            $db->exec("DELETE FROM email_recipients WHERE id = $contactId");
            echo "✅ Test data cleaned up\n";
        } else {
            echo "❌ Empty string test failed\n";
        }
    } catch (Exception $e) {
        echo "❌ Empty string test failed: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    echo "\n🎉 All tests passed!\n";
    echo "Contact creation is working correctly on the live server.\n";
    echo "You can now create contacts with or without campaign selection.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 