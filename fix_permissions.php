<?php
/**
 * Quick Migration Fix for Employee Permissions
 * URL: /fix_permissions.php
 * 
 * Fixes the "Column not found: can_send_instant_emails" error
 */

// Simple security check
$secretKey = 'fix_permissions_2025';
if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
    http_response_code(403);
    die('Access denied. Use: /fix_permissions.php?key=' . $secretKey);
}

require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ACRM Permissions Fix</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: #28a745; background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .error { color: #dc3545; background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0; }
        .info { color: #17a2b8; background: #d1ecf1; padding: 10px; border-radius: 5px; margin: 10px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔧 ACRM Permissions Fix</h1>
    
    <?php
    try {
        $db = (new Database())->getConnection();
        echo "<div class='success'>✅ Database connected successfully</div>";
        
        // Check current table structure
        echo "<h3>Checking employee_permissions table...</h3>";
        $stmt = $db->query("DESCRIBE employee_permissions");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "<div class='info'>Current columns: " . implode(', ', $columns) . "</div>";
        
        // Check if can_send_instant_emails exists
        if (in_array('can_send_instant_emails', $columns)) {
            echo "<div class='success'>✅ can_send_instant_emails column already exists!</div>";
            echo "<div class='info'>The permissions error should be fixed now.</div>";
        } else {
            echo "<div class='error'>❌ can_send_instant_emails column is missing</div>";
            echo "<div class='info'>Adding the missing column...</div>";
            
            // Add the missing column
            $sql = "ALTER TABLE employee_permissions 
                    ADD COLUMN can_send_instant_emails TINYINT(1) DEFAULT 1 
                    AFTER can_view_all_campaigns";
            
            $db->exec($sql);
            echo "<div class='success'>✅ SUCCESS! can_send_instant_emails column added</div>";
            
            // Verify the addition
            $stmt = $db->query("DESCRIBE employee_permissions");
            $newColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array('can_send_instant_emails', $newColumns)) {
                echo "<div class='success'>✅ VERIFIED: Column successfully added</div>";
            } else {
                echo "<div class='error'>❌ FAILED: Column addition verification failed</div>";
            }
        }
        
        // Show final table structure
        echo "<h3>Final Table Structure:</h3>";
        $stmt = $db->query("DESCRIBE employee_permissions");
        echo "<pre>";
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $highlight = $row['Field'] === 'can_send_instant_emails' ? ' ⭐' : '';
            echo $row['Field'] . ' (' . $row['Type'] . ')' . $highlight . "\n";
        }
        echo "</pre>";
        
        // Update all existing users to have instant email permission
        echo "<h3>Setting Default Permissions...</h3>";
        $stmt = $db->prepare("UPDATE employee_permissions SET can_send_instant_emails = 1 WHERE can_send_instant_emails IS NULL");
        $affected = $stmt->rowCount();
        if ($stmt->execute()) {
            echo "<div class='success'>✅ Updated permissions for all users</div>";
        }
        
        echo "<div class='success'>";
        echo "<h3>🎉 Fix Complete!</h3>";
        echo "<p>The permissions error should now be resolved.</p>";
        echo "<p><strong>Next steps:</strong></p>";
        echo "<ul>";
        echo "<li>Test saving employee permissions in the admin panel</li>";
        echo "<li>If it works, you can delete this file: /fix_permissions.php</li>";
        echo "</ul>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<div class='error'>❌ ERROR: " . $e->getMessage() . "</div>";
        echo "<div class='info'>Error details: " . $e->getFile() . " line " . $e->getLine() . "</div>";
    }
    ?>
    
    <hr>
    <p style="text-align: center; color: #666;">
        Generated: <?php echo date('Y-m-d H:i:s'); ?> | 
        <a href="/dashboard.php">← Back to Dashboard</a>
    </p>
</body>
</html>
