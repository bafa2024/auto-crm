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

// Get campaign ID from query parameters
$campaignId = isset($_GET['campaign_id']) ? intval($_GET['campaign_id']) : 0;

if (!$campaignId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Campaign ID is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get total recipients
    $sql = "SELECT COUNT(*) as total FROM email_recipients WHERE campaign_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$campaignId]);
    $totalRecipients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get unique email count (case-insensitive)
    $sql = "SELECT COUNT(DISTINCT LOWER(email)) as unique_emails FROM email_recipients WHERE campaign_id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$campaignId]);
    $uniqueEmails = $stmt->fetch(PDO::FETCH_ASSOC)['unique_emails'];
    
    // Get already sent count
    $sql = "SELECT COUNT(DISTINCT LOWER(recipient_email)) as sent_emails 
            FROM campaign_sends 
            WHERE campaign_id = ? AND status = 'sent'";
    $stmt = $db->prepare($sql);
    $stmt->execute([$campaignId]);
    $sentEmails = $stmt->fetch(PDO::FETCH_ASSOC)['sent_emails'];
    
    // Get fresh unique emails count
    $sql = "SELECT COUNT(DISTINCT LOWER(r.email)) as fresh_emails
            FROM email_recipients r
            LEFT JOIN campaign_sends cs ON LOWER(r.email) = LOWER(cs.recipient_email) 
                AND cs.campaign_id = ? AND cs.status = 'sent'
            WHERE r.campaign_id = ? AND cs.id IS NULL";
    $stmt = $db->prepare($sql);
    $stmt->execute([$campaignId, $campaignId]);
    $freshEmails = $stmt->fetch(PDO::FETCH_ASSOC)['fresh_emails'];
    
    // Get duplicate email examples
    $sql = "SELECT email, COUNT(*) as count 
            FROM email_recipients 
            WHERE campaign_id = ? 
            GROUP BY LOWER(email) 
            HAVING COUNT(*) > 1 
            ORDER BY count DESC 
            LIMIT 5";
    $stmt = $db->prepare($sql);
    $stmt->execute([$campaignId]);
    $duplicateExamples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_recipients' => $totalRecipients,
            'unique_emails' => $uniqueEmails,
            'duplicate_count' => $totalRecipients - $uniqueEmails,
            'sent_emails' => $sentEmails,
            'fresh_unique_emails' => $freshEmails,
            'duplicate_examples' => $duplicateExamples
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