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
    
    // Get search parameter if provided
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Get all recipients who haven't been sent ANY campaign
    $sql = "SELECT DISTINCT r.id, r.email, r.name, r.company, r.created_at,
            r.campaign_id,
            c.name as campaign_name,
            CASE 
                WHEN NOT EXISTS (
                    SELECT 1 FROM campaign_sends cs 
                    WHERE cs.recipient_email = r.email 
                    AND cs.status = 'sent'
                ) THEN 'never_sent_any'
                ELSE 'sent_other_campaigns'
            END as global_status,
            (
                SELECT COUNT(DISTINCT cs2.campaign_id) 
                FROM campaign_sends cs2 
                WHERE LOWER(cs2.recipient_email) = LOWER(r.email) 
                AND cs2.status = 'sent'
            ) as campaigns_received_count
            FROM email_recipients r
            LEFT JOIN email_campaigns c ON r.campaign_id = c.id
            WHERE NOT EXISTS (
                SELECT 1 FROM campaign_sends cs3 
                WHERE cs3.recipient_id = r.id 
                AND cs3.campaign_id = r.campaign_id 
                AND cs3.status = 'sent'
            )";
    
    // Add search filter if provided
    if ($search) {
        $sql .= " AND (
            LOWER(r.email) LIKE LOWER(:search) OR 
            LOWER(r.name) LIKE LOWER(:search) OR 
            LOWER(r.company) LIKE LOWER(:search)
        )";
    }
    
    $sql .= " ORDER BY r.created_at DESC LIMIT 500";
    
    $stmt = $db->prepare($sql);
    if ($search) {
        $searchParam = '%' . $search . '%';
        $stmt->bindParam(':search', $searchParam);
    }
    $stmt->execute();
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total counts
    $countSql = "SELECT 
                COUNT(DISTINCT r.id) as total_unsent_recipients,
                COUNT(DISTINCT LOWER(r.email)) as unique_unsent_emails
                FROM email_recipients r
                WHERE NOT EXISTS (
                    SELECT 1 FROM campaign_sends cs 
                    WHERE cs.recipient_id = r.id 
                    AND cs.campaign_id = r.campaign_id 
                    AND cs.status = 'sent'
                )";
    
    $countStmt = $db->query($countSql);
    $counts = $countStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get recipients who have never received any campaign
    $neverSentSql = "SELECT COUNT(DISTINCT LOWER(email)) as never_sent_count
                     FROM email_recipients r
                     WHERE NOT EXISTS (
                         SELECT 1 FROM campaign_sends cs 
                         WHERE LOWER(cs.recipient_email) = LOWER(r.email) 
                         AND cs.status = 'sent'
                     )";
    
    $neverSentStmt = $db->query($neverSentSql);
    $neverSentCount = $neverSentStmt->fetch(PDO::FETCH_ASSOC)['never_sent_count'];
    
    echo json_encode([
        'success' => true,
        'recipients' => $recipients,
        'total_unsent_recipients' => $counts['total_unsent_recipients'],
        'unique_unsent_emails' => $counts['unique_unsent_emails'],
        'never_sent_any_campaign' => $neverSentCount,
        'showing_limit' => count($recipients) >= 500
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
?>