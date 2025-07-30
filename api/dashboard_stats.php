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
    
    // Get emails sent count
    $stmt = $db->query("SELECT SUM(sent_count) as total FROM email_campaigns WHERE status IN ('sending', 'completed')");
    $emailsSent = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get active campaigns count
    $stmt = $db->query("SELECT COUNT(*) as total FROM email_campaigns WHERE status IN ('draft', 'scheduled', 'sending')");
    $activeCampaigns = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate email performance metrics
    $totalSent = $emailsSent;
    $totalOpened = 0;
    $totalClicked = 0;
    $totalBounced = 0;
    
    // Get opened count
    $stmt = $db->query("SELECT SUM(opened_count) as total FROM email_campaigns WHERE status IN ('sending', 'completed')");
    $totalOpened = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Get clicked count
    $stmt = $db->query("SELECT SUM(clicked_count) as total FROM email_campaigns WHERE status IN ('sending', 'completed')");
    $totalClicked = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Calculate rates
    $deliveryRate = $totalSent > 0 ? round((($totalSent - $totalBounced) / $totalSent) * 100, 1) : 0;
    $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;
    $clickRate = $totalSent > 0 ? round(($totalClicked / $totalSent) * 100, 1) : 0;
    $bounceRate = $totalSent > 0 ? round(($totalBounced / $totalSent) * 100, 1) : 0;
    
    // Format rates with % symbol
    $deliveryRateFormatted = $deliveryRate . '%';
    $openRateFormatted = $openRate . '%';
    $clickRateFormatted = $clickRate . '%';
    $bounceRateFormatted = $bounceRate . '%';
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'totalContacts' => (int)$totalContacts,
            'emailsSent' => (int)$emailsSent,
            'activeCampaigns' => (int)$activeCampaigns,
            'openRate' => $openRateFormatted,
            'deliveryRate' => $deliveryRateFormatted,
            'clickRate' => $clickRateFormatted,
            'bounceRate' => $bounceRateFormatted
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