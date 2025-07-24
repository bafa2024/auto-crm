<?php
header('Content-Type: application/json');
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if campaign ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid campaign ID']);
    exit;
}

$campaignId = (int)$_GET['id'];

try {
    require_once '../config/database.php';
    require_once '../services/EmailCampaignService.php';
    
    $database = new Database();
    $campaignService = new EmailCampaignService($database);
    
    // Get campaign data
    $campaign = $campaignService->getCampaignById($campaignId);
    
    // If recipients=unsent is requested, return unsent recipients as well
    $recipients = [];
    if (isset($_GET['recipients']) && $_GET['recipients'] === 'unsent') {
        $allRecipients = $campaignService->getCampaignRecipientsWithStatus($campaignId);
        $recipients = array_filter($allRecipients, function($r) {
            return empty($r['status']) || $r['status'] !== 'sent';
        });
        // Re-index array for JSON
        $recipients = array_values($recipients);
    }

    if ($campaign) {
        $response = [
            'success' => true,
            'campaign' => $campaign
        ];
        if (!empty($recipients)) {
            $response['recipients'] = $recipients;
        }
        echo json_encode($response);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Campaign not found'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?> 