<?php
/**
 * Simple Campaign Schedule API
 * Handle campaign scheduling requests
 */

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

require_once '../config/database.php';
require_once '../services/SimpleCampaignScheduler.php';

try {
    $database = new Database();
    $scheduler = new SimpleCampaignScheduler($database);
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'POST') {
        // Schedule a campaign
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (!isset($input['campaign_id'])) {
            echo json_encode(['success' => false, 'message' => 'Campaign ID is required']);
            exit;
        }
        
        $campaignId = (int)$input['campaign_id'];
        
        // Use ISO date if available (includes timezone), otherwise fall back to schedule_date
        $scheduleDate = $input['schedule_date_iso'] ?? $input['schedule_date'] ?? null;
        
        $scheduleData = [
            'schedule_type' => $input['schedule_type'] ?? 'immediate',
            'schedule_date' => $scheduleDate,
            'frequency' => $input['frequency'] ?? 'once',
            'recipient_ids' => $input['recipient_ids'] ?? [],
            'client_timezone' => $input['client_timezone'] ?? null,
            'client_offset' => $input['client_offset'] ?? null
        ];
        
        // Validate recipient IDs
        if (empty($scheduleData['recipient_ids'])) {
            echo json_encode(['success' => false, 'message' => 'At least one recipient must be selected']);
            exit;
        }
        
        $result = $scheduler->scheduleCampaign($campaignId, $scheduleData);
        echo json_encode($result);
        
    } elseif ($method === 'GET') {
        // Get scheduled campaigns or process pending
        if (isset($_GET['action'])) {
            if ($_GET['action'] === 'process') {
                // Process pending campaigns
                $result = $scheduler->processPendingCampaigns();
                echo json_encode($result);
            } elseif ($_GET['action'] === 'list') {
                // Get scheduled campaigns list
                $campaigns = $scheduler->getScheduledCampaigns();
                echo json_encode(['success' => true, 'campaigns' => $campaigns]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Action parameter required']);
        }
        
    } elseif ($method === 'DELETE') {
        // Cancel a scheduled campaign
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['scheduled_id'])) {
            echo json_encode(['success' => false, 'message' => 'Scheduled ID is required']);
            exit;
        }
        
        $result = $scheduler->cancelScheduledCampaign((int)$input['scheduled_id']);
        echo json_encode($result);
        
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
?>
