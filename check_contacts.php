<?php
// Quick script to check if contacts exist in the database

require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if email_recipients table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'email_recipients'");
    if ($tableCheck->rowCount() == 0) {
        echo "❌ email_recipients table does not exist!\n";
        exit;
    }
    
    // Check contacts count
    $stmt = $db->query("SELECT COUNT(*) as count FROM email_recipients");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    
    echo "📊 Total contacts in database: " . $count . "\n";
    
    if ($count == 0) {
        echo "❌ No contacts found in database!\n";
        echo "🔧 Would you like to add some test contacts? (y/n): ";
        
        // Add some test contacts if database is empty
        echo "\n📝 Adding test contacts...\n";
        
        $testContacts = [
            ['John Doe', 'john.doe@example.com', 'ACRM Company'],
            ['Jane Smith', 'jane.smith@company.com', 'Tech Solutions'],
            ['Mike Johnson', 'mike.johnson@business.com', 'Digital Agency'],
            ['Sarah Wilson', 'sarah.wilson@startup.com', 'Innovation Labs'],
            ['Tom Brown', 'tom.brown@enterprise.com', 'Global Corp']
        ];
        
        $insertStmt = $db->prepare("INSERT INTO email_recipients (name, email, company, created_at) VALUES (?, ?, ?, NOW())");
        
        $added = 0;
        foreach ($testContacts as $contact) {
            try {
                $insertStmt->execute($contact);
                $added++;
                echo "✅ Added: " . $contact[0] . " (" . $contact[1] . ")\n";
            } catch (Exception $e) {
                echo "❌ Failed to add " . $contact[0] . ": " . $e->getMessage() . "\n";
            }
        }
        
        echo "\n🎉 Successfully added $added test contacts!\n";
        
    } else {
        // Show some sample contacts
        echo "📋 Sample contacts:\n";
        $sampleStmt = $db->query("SELECT id, name, email, company FROM email_recipients LIMIT 5");
        while ($contact = $sampleStmt->fetch(PDO::FETCH_ASSOC)) {
            echo "  • " . $contact['name'] . " (" . $contact['email'] . ") - " . $contact['company'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}
?>
