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

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from session
    $userId = $_SESSION['user_id'];
    
    // Get query parameters
    $status = $_GET['status'] ?? null;
    $limit = $_GET['limit'] ?? null;
    
    // Build WHERE clause
    $whereConditions = [];
    $params = [];
    
    if ($_SESSION['user_role'] !== 'admin') {
        $whereConditions[] = "c.user_id = ?";
        $params[] = $userId;
    }
    
    if ($status) {
        $whereConditions[] = "c.status = ?";
        $params[] = $status;
    }
    
    $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
    $limitClause = $limit ? "LIMIT " . (int)$limit : "";
    
    // Build SQL query
    $sql = "SELECT c.*, 
            COUNT(DISTINCT cs.id) as total_sends,
            COUNT(DISTINCT CASE WHEN cs.status = 'sent' THEN cs.id END) as sent_count,
            COUNT(DISTINCT CASE WHEN cs.opened_at IS NOT NULL THEN cs.id END) as opened_count,
            COUNT(DISTINCT CASE WHEN cs.clicked_at IS NOT NULL THEN cs.id END) as clicked_count
            FROM email_campaigns c
            LEFT JOIN campaign_sends cs ON c.id = cs.campaign_id
            $whereClause
            GROUP BY c.id
            ORDER BY c.created_at DESC
            $limitClause";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $db->query($sql);
    }
    
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total recipients count
    $recipientsSql = "SELECT COUNT(*) as total FROM email_recipients";
    $recipientsStmt = $db->query($recipientsSql);
    $totalRecipients = $recipientsStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    echo json_encode([
        'success' => true,
        'campaigns' => $campaigns,
        'total_recipients' => $totalRecipients
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>