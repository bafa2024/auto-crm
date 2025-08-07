<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check campaigns
    $stmt = $db->query("SELECT COUNT(*) as count FROM email_campaigns");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 Total campaigns: " . $result['count'] . "\n";
    
    if ($result['count'] == 0) {
        echo "🔧 Creating a test campaign...\n";
        
        $insertStmt = $db->prepare("INSERT INTO email_campaigns (name, subject, content, content_type, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $insertStmt->execute([
            'Test Campaign',
            'Welcome to ACRM - Test Email',
            '<h1>Welcome!</h1><p>This is a test email campaign from ACRM system.</p><p>Best regards,<br>ACRM Team</p>',
            'html',
            'draft'
        ]);
        
        echo "✅ Test campaign created!\n";
    }
    
    // Show existing campaigns
    echo "\n📋 Existing campaigns:\n";
    $stmt = $db->query("SELECT id, name, subject, status FROM email_campaigns LIMIT 5");
    while ($campaign = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  • ID: {$campaign['id']}, Name: {$campaign['name']}, Status: {$campaign['status']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
