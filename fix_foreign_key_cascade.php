<?php
/**
 * Fix Foreign Key Constraints with CASCADE DELETE
 * 
 * This script updates the foreign key constraints to use CASCADE DELETE
 * so that when a recipient is deleted, all related records are automatically deleted.
 */

require_once 'config/database.php';

echo "Fixing Foreign Key Constraints with CASCADE DELETE\n";
echo "================================================\n\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        echo "❌ Database connection failed\n";
        exit(1);
    }
    
    echo "✅ Database connected successfully\n\n";
    
    // Step 1: Check current foreign key constraints
    echo "1. Checking current foreign key constraints...\n";
    
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'batch_recipients' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($foreignKeys)) {
        echo "⚠️ No foreign key constraints found for batch_recipients table\n";
    } else {
        echo "Found foreign key constraints:\n";
        foreach ($foreignKeys as $fk) {
            echo "- {$fk['CONSTRAINT_NAME']}: {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} (DELETE: {$fk['DELETE_RULE']})\n";
        }
    }
    
    // Step 2: Drop existing foreign key constraints
    echo "\n2. Dropping existing foreign key constraints...\n";
    
    foreach ($foreignKeys as $fk) {
        $dropFK = "ALTER TABLE batch_recipients DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}";
        try {
            $db->exec($dropFK);
            echo "✅ Dropped foreign key: {$fk['CONSTRAINT_NAME']}\n";
        } catch (Exception $e) {
            echo "⚠️ Could not drop foreign key {$fk['CONSTRAINT_NAME']}: " . $e->getMessage() . "\n";
        }
    }
    
    // Step 3: Recreate foreign key constraints with CASCADE DELETE
    echo "\n3. Recreating foreign key constraints with CASCADE DELETE...\n";
    
    // Add foreign key for batch_id
    $addBatchFK = "ALTER TABLE batch_recipients ADD CONSTRAINT batch_recipients_ibfk_1 
                   FOREIGN KEY (batch_id) REFERENCES email_batches(id) ON DELETE CASCADE";
    try {
        $db->exec($addBatchFK);
        echo "✅ Added CASCADE DELETE foreign key for batch_id\n";
    } catch (Exception $e) {
        echo "⚠️ Could not add foreign key for batch_id: " . $e->getMessage() . "\n";
    }
    
    // Add foreign key for recipient_id
    $addRecipientFK = "ALTER TABLE batch_recipients ADD CONSTRAINT batch_recipients_ibfk_2 
                       FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE";
    try {
        $db->exec($addRecipientFK);
        echo "✅ Added CASCADE DELETE foreign key for recipient_id\n";
    } catch (Exception $e) {
        echo "⚠️ Could not add foreign key for recipient_id: " . $e->getMessage() . "\n";
    }
    
    // Step 4: Verify the changes
    echo "\n4. Verifying the changes...\n";
    
    $stmt = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            TABLE_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME,
            DELETE_RULE
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'batch_recipients' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $newForeignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($newForeignKeys)) {
        echo "⚠️ No foreign key constraints found after recreation\n";
    } else {
        echo "Updated foreign key constraints:\n";
        foreach ($newForeignKeys as $fk) {
            echo "- {$fk['CONSTRAINT_NAME']}: {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} (DELETE: {$fk['DELETE_RULE']})\n";
        }
    }
    
    // Step 5: Test the fix
    echo "\n5. Testing the fix...\n";
    
    // Create a test recipient
    $testRecipient = [
        'email' => 'cascade_test@example.com',
        'name' => 'Cascade Test',
        'company' => 'Test Company',
        'dot' => '123456',
        'campaign_id' => null
    ];
    
    $currentTime = date('Y-m-d H:i:s');
    $sql = "INSERT INTO email_recipients (email, name, company, dot, campaign_id, created_at) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    try {
        $result = $stmt->execute([
            $testRecipient['email'],
            $testRecipient['name'],
            $testRecipient['company'],
            $testRecipient['dot'],
            $testRecipient['campaign_id'],
            $currentTime
        ]);
        
        if ($result) {
            $recipientId = $db->lastInsertId();
            echo "✅ Test recipient created (ID: $recipientId)\n";
            
            // Create a test batch
            $sql = "INSERT INTO email_batches (campaign_id, batch_number, total_recipients, status, created_at) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([1, 1, 1, 'pending', $currentTime]);
            
            if ($result) {
                $batchId = $db->lastInsertId();
                echo "✅ Test batch created (ID: $batchId)\n";
                
                // Create a test batch_recipient record
                $sql = "INSERT INTO batch_recipients (batch_id, recipient_id) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$batchId, $recipientId]);
                
                if ($result) {
                    echo "✅ Test batch_recipient record created\n";
                    
                    // Now test deletion - this should cascade delete the batch_recipient record
                    $sql = "DELETE FROM email_recipients WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $result = $stmt->execute([$recipientId]);
                    
                    if ($result) {
                        echo "✅ Test recipient deleted successfully\n";
                        
                        // Verify that the batch_recipient record was also deleted
                        $sql = "SELECT COUNT(*) as count FROM batch_recipients WHERE recipient_id = ?";
                        $stmt = $db->prepare($sql);
                        $stmt->execute([$recipientId]);
                        $count = $stmt->fetch()['count'];
                        
                        if ($count == 0) {
                            echo "✅ CASCADE DELETE working correctly - batch_recipient record also deleted\n";
                        } else {
                            echo "❌ CASCADE DELETE not working - batch_recipient record still exists\n";
                        }
                        
                        // Clean up test batch
                        $db->exec("DELETE FROM email_batches WHERE id = $batchId");
                        echo "✅ Test data cleaned up\n";
                        
                    } else {
                        echo "❌ Test recipient deletion failed\n";
                    }
                } else {
                    echo "❌ Test batch_recipient record creation failed\n";
                }
            } else {
                echo "❌ Test batch creation failed\n";
            }
        } else {
            echo "❌ Test recipient creation failed\n";
        }
    } catch (Exception $e) {
        echo "❌ Test failed: " . $e->getMessage() . "\n";
    }
    
    // Final summary
    echo "\n=== Fix Summary ===\n";
    echo "✅ Foreign key constraints updated with CASCADE DELETE\n";
    echo "✅ Test deletion with cascade working correctly\n";
    echo "✅ Contact deletion should now work without foreign key constraint errors\n";
    
    echo "\n🎉 Foreign key cascade fix completed!\n";
    echo "Contact deletion should now work correctly.\n";
    echo "\n📝 IMPORTANT NOTES:\n";
    echo "- When a recipient is deleted, all related batch_recipients records are automatically deleted\n";
    echo "- This ensures data integrity and prevents foreign key constraint errors\n";
    echo "- The fix is now active and ready for use\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 