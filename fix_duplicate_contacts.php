<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->getConnection();

echo "<h1>Fix Duplicate Contacts</h1>";

// Start transaction
$conn->beginTransaction();

try {
    // Step 1: Count current duplicates
    $stmt = $conn->query("
        SELECT COUNT(*) as total_records,
               COUNT(DISTINCT LOWER(email)) as unique_emails,
               (COUNT(*) - COUNT(DISTINCT LOWER(email))) as duplicates
        FROM email_recipients
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Status:</h2>";
    echo "<p>Total Records: " . $stats['total_records'] . "</p>";
    echo "<p>Unique Emails: " . $stats['unique_emails'] . "</p>";
    echo "<p>Duplicate Records: " . $stats['duplicates'] . "</p>";
    
    if ($stats['duplicates'] > 0) {
        echo "<h2>Removing Duplicates...</h2>";
        
        // Step 2: Create temporary table with unique records (keeping the oldest record for each email)
        $conn->exec("CREATE TEMPORARY TABLE temp_unique_recipients AS
            SELECT * FROM email_recipients r1
            WHERE r1.id = (
                SELECT MIN(r2.id)
                FROM email_recipients r2
                WHERE LOWER(r2.email) = LOWER(r1.email)
            )");
        
        // Step 3: Get count of records to be kept
        $stmt = $conn->query("SELECT COUNT(*) as kept_records FROM temp_unique_recipients");
        $kept = $stmt->fetch(PDO::FETCH_ASSOC)['kept_records'];
        echo "<p>Records to keep: $kept</p>";
        
        // Step 4: Update campaign_sends to use the kept recipient IDs
        echo "<p>Updating campaign_sends references...</p>";
        
        // For each duplicate email, update campaign_sends to use the ID we're keeping
        $stmt = $conn->query("
            SELECT LOWER(email) as email_lower, GROUP_CONCAT(id) as ids
            FROM email_recipients
            GROUP BY LOWER(email)
            HAVING COUNT(*) > 1
        ");
        $duplicateGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($duplicateGroups as $group) {
            $ids = explode(',', $group['ids']);
            $keepId = min($ids); // Keep the lowest ID (oldest record)
            
            // Update campaign_sends to use the kept ID
            foreach ($ids as $id) {
                if ($id != $keepId) {
                    $updateStmt = $conn->prepare("
                        UPDATE campaign_sends 
                        SET recipient_id = ? 
                        WHERE recipient_id = ?
                    ");
                    $updateStmt->execute([$keepId, $id]);
                }
            }
        }
        
        // Step 5: Update batch_recipients to use the kept recipient IDs
        echo "<p>Updating batch_recipients references...</p>";
        
        foreach ($duplicateGroups as $group) {
            $ids = explode(',', $group['ids']);
            $keepId = min($ids); // Keep the lowest ID (oldest record)
            
            // Update batch_recipients to use the kept ID
            foreach ($ids as $id) {
                if ($id != $keepId) {
                    // Delete duplicate entries first
                    $deleteStmt = $conn->prepare("
                        DELETE FROM batch_recipients 
                        WHERE recipient_id = ? 
                        AND batch_id IN (
                            SELECT batch_id FROM (
                                SELECT batch_id FROM batch_recipients WHERE recipient_id = ?
                            ) as temp
                        )
                    ");
                    $deleteStmt->execute([$id, $keepId]);
                    
                    // Then update remaining
                    $updateStmt = $conn->prepare("
                        UPDATE batch_recipients 
                        SET recipient_id = ? 
                        WHERE recipient_id = ?
                    ");
                    $updateStmt->execute([$keepId, $id]);
                }
            }
        }
        
        // Step 6: Delete all records from email_recipients
        $conn->exec("DELETE FROM email_recipients");
        
        // Step 7: Insert unique records back
        $conn->exec("INSERT INTO email_recipients SELECT * FROM temp_unique_recipients");
        
        // Step 8: Drop temporary table
        $conn->exec("DROP TEMPORARY TABLE temp_unique_recipients");
        
        echo "<p><strong>Duplicates removed successfully!</strong></p>";
        
        // Step 9: Add unique index on email column to prevent future duplicates
        echo "<h2>Adding Unique Constraint...</h2>";
        
        // Check if index already exists
        $stmt = $conn->query("SHOW INDEX FROM email_recipients WHERE Column_name = 'email' AND Non_unique = 0");
        if ($stmt->rowCount() == 0) {
            try {
                $conn->exec("ALTER TABLE email_recipients ADD UNIQUE INDEX idx_unique_email (email)");
                echo "<p><strong>Unique constraint added on email column!</strong></p>";
            } catch (Exception $e) {
                echo "<p>Note: Could not add unique constraint (may already exist): " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>Unique constraint already exists on email column.</p>";
        }
    } else {
        echo "<h2>No duplicates found!</h2>";
        
        // Still try to add unique constraint if it doesn't exist
        $stmt = $conn->query("SHOW INDEX FROM email_recipients WHERE Column_name = 'email' AND Non_unique = 0");
        if ($stmt->rowCount() == 0) {
            try {
                $conn->exec("ALTER TABLE email_recipients ADD UNIQUE INDEX idx_unique_email (email)");
                echo "<p><strong>Unique constraint added on email column as prevention!</strong></p>";
            } catch (Exception $e) {
                echo "<p>Note: Could not add unique constraint: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Final count
    echo "<h2>Final Status:</h2>";
    $stmt = $conn->query("SELECT COUNT(*) as total FROM email_recipients");
    $finalCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    echo "<p>Total Records: $finalCount</p>";
    
    echo "<h2>Complete!</h2>";
    echo "<p>The duplicate contacts have been removed and a unique constraint has been added to prevent future duplicates.</p>";
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    echo "<h2>Error:</h2>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
    echo "<p>Transaction rolled back - no changes were made.</p>";
}
?>