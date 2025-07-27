<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/EmailCampaignService.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get campaign ID from query parameters
$campaignId = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

if (!$campaignId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campaign ID is required']);
    exit;
}

try {
    $database = new Database();
    $emailCampaignService = new EmailCampaignService($database);
    
    // Get batch progress
    $result = $emailCampaignService->getCampaignBatchProgress($campaignId);
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'progress' => $result['progress']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Failed to get batch progress'
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