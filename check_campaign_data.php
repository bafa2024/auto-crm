<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();

echo "=== Database Status Check ===\n";

// Check campaigns
$stmt = $db->query("SELECT COUNT(*) as count FROM email_campaigns");
$campaignCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "Email Campaigns: $campaignCount\n";

// Check recipients
$stmt = $db->query("SELECT COUNT(*) as count FROM email_recipients");
$recipientCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
echo "Email Recipients: $recipientCount\n";

if ($campaignCount > 0) {
    echo "\nCampaigns:\n";
    $stmt = $db->query("SELECT id, name, status FROM email_campaigns LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - ID: {$row['id']}, Name: {$row['name']}, Status: {$row['status']}\n";
    }
}

if ($recipientCount > 0) {
    echo "\nRecipients by Campaign:\n";
    $stmt = $db->query("SELECT campaign_id, COUNT(*) as count FROM email_recipients GROUP BY campaign_id LIMIT 5");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - Campaign {$row['campaign_id']}: {$row['count']} recipients\n";
    }
}

// If we have campaigns but no proper test data, let's create some
if ($campaignCount > 0 && $recipientCount == 0) {
    echo "\n=== Creating Test Recipients ===\n";
    
    // Get first campaign
    $stmt = $db->query("SELECT id FROM email_campaigns LIMIT 1");
    $campaign = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($campaign) {
        $campaignId = $campaign['id'];
        
        // Add test recipients
        $testRecipients = [
            ['name' => 'Test User 1', 'email' => 'test1@example.com'],
            ['name' => 'Test User 2', 'email' => 'test2@example.com'],
            ['name' => 'Test User 3', 'email' => 'test3@example.com'],
        ];
        
        foreach ($testRecipients as $recipient) {
            $sql = "INSERT INTO email_recipients (campaign_id, name, email, status) VALUES (?, ?, ?, 'pending')";
            $stmt = $db->prepare($sql);
            $stmt->execute([$campaignId, $recipient['name'], $recipient['email']]);
        }
        
        echo "✅ Added " . count($testRecipients) . " test recipients to campaign $campaignId\n";
    }
}

echo "\n=== End Status Check ===\n";
?>
