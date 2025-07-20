<?php
header('Content-Type: application/json');
session_start();

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
    
    if ($campaign) {
        echo json_encode([
            'success' => true,
            'campaign' => $campaign
        ]);
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