<?php
require_once 'config/database.php';

echo "=== Fix Recipient Counts ===\n\n";

// Initialize database connection
$database = (new Database())->getConnection();

try {
    // Get all campaigns
    $stmt = $database->query("SELECT id, name FROM email_campaigns");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($campaigns) . " campaigns to update\n\n";
    
    foreach ($campaigns as $campaign) {
        echo "Updating campaign: " . $campaign['name'] . " (ID: " . $campaign['id'] . ")\n";
        
        // Count actual recipients
        $countStmt = $database->prepare("SELECT COUNT(*) as count FROM email_recipients WHERE campaign_id = ?");
        $countStmt->execute([$campaign['id']]);
        $actualCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Update the campaign
        $updateStmt = $database->prepare("UPDATE email_campaigns SET total_recipients = ? WHERE id = ?");
        $updateStmt->execute([$actualCount, $campaign['id']]);
        
        echo "  Updated recipient count to: $actualCount\n";
    }
    
    echo "\n=== Verification ===\n";
    
    // Verify the updates
    $stmt = $database->query("
        SELECT 
            ec.id,
            ec.name,
            ec.total_recipients,
            COUNT(er.id) as actual_recipients
        FROM email_campaigns ec
        LEFT JOIN email_recipients er ON ec.id = er.campaign_id
        GROUP BY ec.id, ec.name, ec.total_recipients
        ORDER BY ec.created_at DESC
    ");
    $verification = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($verification as $campaign) {
        echo "Campaign: " . $campaign['name'] . "\n";
        echo "  Stored count: " . $campaign['total_recipients'] . "\n";
        echo "  Actual count: " . $campaign['actual_recipients'] . "\n";
        echo "  Status: " . ($campaign['total_recipients'] == $campaign['actual_recipients'] ? 'SYNCED' : 'MISMATCH') . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}