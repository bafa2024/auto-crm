<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from session
    $userId = $_SESSION['user_id'];
    
    // Get total contacts count
    $stmt = $db->query("SELECT COUNT(*) as total FROM contacts");
    $totalContacts = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get calls today (placeholder - would need call_logs table)
    $callsToday = 0;
    try {
        $stmt = $db->query("SELECT COUNT(*) as total FROM call_logs WHERE DATE(created_at) = CURDATE()");
        $callsToday = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    } catch (Exception $e) {
        // Table might not exist, use default value
    }
    
    // Get active campaigns count
    $stmt = $db->query("SELECT COUNT(*) as total FROM email_campaigns WHERE status IN ('draft', 'scheduled', 'sending')");
    $activeCampaigns = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get connection rate (placeholder - would need actual call data)
    $connectionRate = "23.5%";
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'totalContacts' => (int)$totalContacts,
            'callsToday' => (int)$callsToday,
            'activeCampaigns' => (int)$activeCampaigns,
            'connectionRate' => $connectionRate
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 