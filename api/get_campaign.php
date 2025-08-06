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
        // Get all recipients for the campaign
        $db = $database->getConnection();
        
        // Get recipients who haven't been sent any email yet (no entry in campaign_sends or status != 'sent')
        $sql = "SELECT DISTINCT r.id, r.email, r.name, r.company, r.created_at,
                CASE 
                    WHEN cs.id IS NULL THEN 'never_sent'
                    WHEN cs.status = 'failed' THEN 'failed'
                    WHEN cs.status = 'sent' THEN 'sent'
                    ELSE 'pending'
                END as send_status,
                cs.sent_at
                FROM email_recipients r
                LEFT JOIN campaign_sends cs ON r.id = cs.recipient_id AND cs.campaign_id = ?
                WHERE (cs.id IS NULL OR cs.status != 'sent')
                ORDER BY r.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$campaignId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also get count of unique unsent emails
        $countSql = "SELECT COUNT(DISTINCT LOWER(r.email)) as unique_unsent
                     FROM email_recipients r
                     LEFT JOIN campaign_sends cs ON r.id = cs.recipient_id AND cs.campaign_id = ? AND cs.status = 'sent'
                     WHERE cs.id IS NULL";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute([$campaignId]);
        $uniqueUnsentCount = $countStmt->fetch(PDO::FETCH_ASSOC)['unique_unsent'];
    }
    
    // If recipients=all is requested, return all contacts
    if (isset($_GET['recipients']) && $_GET['recipients'] === 'all') {
        $db = $database->getConnection();
        
        // Get all contacts from email_recipients table
        $sql = "SELECT DISTINCT r.id, r.email, r.name, r.company, r.created_at,
                CASE 
                    WHEN cs.id IS NULL THEN 'never_sent'
                    WHEN cs.status = 'failed' THEN 'failed'
                    WHEN cs.status = 'sent' THEN 'sent'
                    ELSE 'pending'
                END as send_status,
                cs.sent_at
                FROM email_recipients r
                LEFT JOIN campaign_sends cs ON r.id = cs.recipient_id AND cs.campaign_id = ?
                ORDER BY r.created_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$campaignId]);
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total_contacts FROM email_recipients";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute();
        $totalContacts = $countStmt->fetch(PDO::FETCH_ASSOC)['total_contacts'];
        
        // Get count of unique emails for this campaign that haven't been sent
        $unsentCountSql = "SELECT COUNT(DISTINCT LOWER(r.email)) as unique_unsent
                          FROM email_recipients r
                          LEFT JOIN campaign_sends cs ON r.id = cs.recipient_id AND cs.campaign_id = ? AND cs.status = 'sent'
                          WHERE cs.id IS NULL";
        $unsentCountStmt = $db->prepare($unsentCountSql);
        $unsentCountStmt->execute([$campaignId]);
        $uniqueUnsentCount = $unsentCountStmt->fetch(PDO::FETCH_ASSOC)['unique_unsent'];
    }

    if ($campaign) {
        $response = [
            'success' => true,
            'campaign' => $campaign
        ];
        if (isset($_GET['recipients']) && $_GET['recipients'] === 'unsent') {
            $response['recipients'] = $recipients;
            $response['unique_unsent_count'] = $uniqueUnsentCount ?? 0;
            $response['total_unsent'] = count($recipients);
        }
        if (isset($_GET['recipients']) && $_GET['recipients'] === 'all') {
            $response['recipients'] = $recipients;
            $response['unique_unsent_count'] = $uniqueUnsentCount ?? 0;
            $response['total_contacts'] = count($recipients);
            $response['total_unsent'] = count(array_filter($recipients, function($r) { return $r['send_status'] !== 'sent'; }));
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