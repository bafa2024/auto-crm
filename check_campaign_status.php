<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    echo "📊 Current Campaign Status:\n";
    $stmt = $db->query("SELECT id, name, status FROM email_campaigns ORDER BY id");
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  • Campaign ID: {$row['id']}, Name: {$row['name']}, Status: {$row['status']}\n";
    }
    
    // Check what statuses are blocking edits
    echo "\n🔍 Campaigns with blocking statuses (completed/sending):\n";
    $stmt = $db->prepare("SELECT id, name, status FROM email_campaigns WHERE status IN ('completed', 'sending')");
    $stmt->execute();
    
    $blockedCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($blockedCampaigns) > 0) {
        foreach ($blockedCampaigns as $campaign) {
            echo "  ❌ ID: {$campaign['id']}, Name: {$campaign['name']}, Status: {$campaign['status']}\n";
        }
        echo "\n💡 These campaigns cannot be edited due to their status.\n";
    } else {
        echo "  ✅ No campaigns are currently blocked from editing.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
