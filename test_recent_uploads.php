<?php
require_once 'config/database.php';

echo "=== Recent Uploads Display Test ===\n\n";

// Initialize database connection
$database = (new Database())->getConnection();

// Get recent uploads
echo "Testing Recent Uploads Query...\n";
try {
    $stmt = $database->query("
        SELECT 
            er.campaign_id,
            ec.name as campaign_name,
            COUNT(er.id) as recipient_count,
            MIN(er.created_at) as upload_date,
            MAX(er.created_at) as last_upload
        FROM email_recipients er
        LEFT JOIN email_campaigns ec ON er.campaign_id = ec.id
        GROUP BY er.campaign_id, ec.name
        ORDER BY upload_date DESC
        LIMIT 10
    ");
    $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Recent uploads found: " . count($recentUploads) . "\n\n";
    
    if (!empty($recentUploads)) {
        echo "Upload Details:\n";
        echo "================\n";
        foreach ($recentUploads as $upload) {
            echo "Campaign ID: " . $upload['campaign_id'] . "\n";
            echo "Campaign Name: " . ($upload['campaign_name'] ?? 'No Campaign') . "\n";
            echo "Recipients: " . $upload['recipient_count'] . "\n";
            echo "First Upload: " . $upload['upload_date'] . "\n";
            echo "Last Upload: " . $upload['last_upload'] . "\n";
            echo "---\n";
        }
        
        // Test HTML display format
        echo "\nHTML Table Format:\n";
        echo "==================\n";
        echo "<table class='table'>\n";
        echo "<thead><tr><th>Campaign</th><th>Recipients</th><th>Upload Date</th></tr></thead>\n";
        echo "<tbody>\n";
        
        foreach ($recentUploads as $upload) {
            $campaignName = htmlspecialchars($upload['campaign_name'] ?? 'No Campaign');
            $recipients = $upload['recipient_count'];
            $uploadDate = date('Y-m-d H:i', strtotime($upload['upload_date']));
            
            echo "<tr>\n";
            echo "  <td>$campaignName</td>\n";
            echo "  <td>$recipients</td>\n";
            echo "  <td>$uploadDate</td>\n";
            echo "</tr>\n";
        }
        
        echo "</tbody>\n";
        echo "</table>\n";
    }
    
} catch (Exception $e) {
    echo "Error fetching recent uploads: " . $e->getMessage() . "\n";
}

// Test campaign stats
echo "\n=== Campaign Statistics ===\n";
try {
    $stmt = $database->query("
        SELECT 
            ec.id,
            ec.name,
            ec.total_recipients,
            COUNT(er.id) as actual_recipients,
            ec.created_at
        FROM email_campaigns ec
        LEFT JOIN email_recipients er ON ec.id = er.campaign_id
        GROUP BY ec.id, ec.name, ec.total_recipients, ec.created_at
        ORDER BY ec.created_at DESC
    ");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Campaigns found: " . count($campaigns) . "\n\n";
    foreach ($campaigns as $campaign) {
        echo "Campaign: " . $campaign['name'] . "\n";
        echo "  ID: " . $campaign['id'] . "\n";
        echo "  Total Recipients (stored): " . ($campaign['total_recipients'] ?? 'NULL') . "\n";
        echo "  Actual Recipients (count): " . $campaign['actual_recipients'] . "\n";
        echo "  Created: " . $campaign['created_at'] . "\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "Error fetching campaign stats: " . $e->getMessage() . "\n";
}

// Test table structure
echo "\n=== Table Structure Check ===\n";
try {
    $stmt = $database->query("PRAGMA table_info(email_campaigns)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "email_campaigns table columns:\n";
    foreach ($columns as $column) {
        echo "  " . $column['name'] . " (" . $column['type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo "Error checking table structure: " . $e->getMessage() . "\n";
}