<?php
require_once __DIR__ . "/../models/BaseModel.php";
require_once __DIR__ . "/../models/Contact.php";
require_once __DIR__ . "/../models/EmployeePermission.php";
require_once __DIR__ . "/../services/EmailService.php";
require_once __DIR__ . "/BaseController.php";

class InstantEmailController extends BaseController {
    protected $db;
    private $contactModel;
    private $permissionModel;
    private $emailService;
    
    public function __construct($db) {
        $this->db = $db;
        $this->contactModel = new Contact($db);
        $this->permissionModel = new EmployeePermission($db);
        $this->emailService = new EmailService($db);
    }
    
    /**
     * Check if user has permission to send instant emails
     */
    private function checkPermission() {
        if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
            return false;
        }
        
        return $this->permissionModel->hasPermission($_SESSION["user_id"], 'can_send_instant_emails');
    }
    
    /**
     * Send instant email
     */
    public function sendInstantEmail() {
        if (!$this->checkPermission()) {
            return $this->sendError("You don't have permission to send instant emails", 403);
        }
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $required = ['to', 'subject', 'message'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    return $this->sendError("$field is required");
                }
            }
            
            // Validate email format
            $emails = $this->parseEmailRecipients($input['to']);
            if (empty($emails)) {
                return $this->sendError("Please provide valid email addresses");
            }
            
            $subject = trim($input['subject']);
            $message = $input['message'];
            $cc = isset($input['cc']) ? $this->parseEmailRecipients($input['cc']) : [];
            $bcc = isset($input['bcc']) ? $this->parseEmailRecipients($input['bcc']) : [];
            
            // Track sending results
            $results = [
                'successful' => [],
                'failed' => [],
                'total' => count($emails)
            ];
            
            // Send emails
            foreach ($emails as $email) {
                try {
                    $result = $this->emailService->sendInstantEmail([
                        'to' => $email,
                        'cc' => $cc,
                        'bcc' => $bcc,
                        'subject' => $subject,
                        'message' => $message,
                        'sender_id' => $_SESSION["user_id"],
                        'sender_name' => $_SESSION["user_name"]
                    ]);
                    
                    if ($result) {
                        $results['successful'][] = $email;
                        
                        // Log the activity
                        $this->logEmailActivity($email, $subject, 'sent');
                    } else {
                        $results['failed'][] = $email;
                    }
                } catch (Exception $e) {
                    $results['failed'][] = $email;
                    error_log("Failed to send instant email to $email: " . $e->getMessage());
                }
            }
            
            // Prepare response message
            $successCount = count($results['successful']);
            $failCount = count($results['failed']);
            
            if ($successCount > 0 && $failCount == 0) {
                $message = "All emails sent successfully ($successCount sent)";
                return $this->sendSuccess($results, $message);
            } elseif ($successCount > 0 && $failCount > 0) {
                $message = "Partially successful: $successCount sent, $failCount failed";
                return $this->sendSuccess($results, $message);
            } else {
                return $this->sendError("Failed to send any emails", 500, $results);
            }
            
        } catch (Exception $e) {
            error_log("Instant email error: " . $e->getMessage());
            return $this->sendError("Failed to send email: " . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get email templates for quick compose
     */
    public function getEmailTemplates() {
        if (!$this->checkPermission()) {
            return $this->sendError("You don't have permission to access email templates", 403);
        }
        
        try {
            $templates = [
                [
                    'id' => 'follow_up',
                    'name' => 'Follow Up',
                    'subject' => 'Following up on our conversation',
                    'message' => "Hi there,\n\nI wanted to follow up on our recent conversation. Please let me know if you have any questions or if there's anything I can help you with.\n\nBest regards,\n{sender_name}"
                ],
                [
                    'id' => 'introduction',
                    'name' => 'Introduction',
                    'subject' => 'Introduction - {company_name}',
                    'message' => "Hello,\n\nI hope this email finds you well. I'm {sender_name} from {company_name}, and I wanted to reach out to introduce our services.\n\nI'd love to schedule a brief call to discuss how we can help your business.\n\nBest regards,\n{sender_name}"
                ],
                [
                    'id' => 'thank_you',
                    'name' => 'Thank You',
                    'subject' => 'Thank you for your time',
                    'message' => "Dear {contact_name},\n\nThank you for taking the time to speak with me today. I really appreciate the opportunity to learn more about your needs.\n\nAs discussed, I'll follow up with the information you requested.\n\nBest regards,\n{sender_name}"
                ],
                [
                    'id' => 'meeting_request',
                    'name' => 'Meeting Request',
                    'subject' => 'Meeting Request - {topic}',
                    'message' => "Hello {contact_name},\n\nI hope this email finds you well. I would like to schedule a meeting to discuss {topic}.\n\nPlease let me know your availability for the next week, and I'll send over a calendar invitation.\n\nThank you for your time.\n\nBest regards,\n{sender_name}"
                ]
            ];
            
            return $this->sendSuccess($templates);
            
        } catch (Exception $e) {
            error_log("Email templates error: " . $e->getMessage());
            return $this->sendError("Failed to load email templates", 500);
        }
    }
    
    /**
     * Get contact suggestions for email composer
     */
    public function getContactSuggestions() {
        if (!$this->checkPermission()) {
            return $this->sendError("You don't have permission to access contacts", 403);
        }
        
        try {
            $query = $_GET['q'] ?? '';
            $limit = min(intval($_GET['limit'] ?? 10), 50);
            
            if (strlen($query) < 2) {
                return $this->sendSuccess([]);
            }
            
            $contacts = $this->contactModel->searchForEmailComposer($query, $limit);
            
            return $this->sendSuccess($contacts);
            
        } catch (Exception $e) {
            error_log("Contact suggestions error: " . $e->getMessage());
            return $this->sendError("Failed to load contact suggestions", 500);
        }
    }
    
    /**
     * Get sent email history
     */
    public function getSentHistory() {
        if (!$this->checkPermission()) {
            return $this->sendError("You don't have permission to view email history", 403);
        }
        
        try {
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(intval($_GET['limit'] ?? 20), 100);
            $offset = ($page - 1) * $limit;
            
            $sql = "SELECT id, recipient_email, subject, sent_at, status 
                    FROM instant_email_log 
                    WHERE sender_id = ? 
                    ORDER BY sent_at DESC 
                    LIMIT ? OFFSET ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$_SESSION["user_id"], $limit, $offset]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM instant_email_log WHERE sender_id = ?";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$_SESSION["user_id"]]);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return $this->sendSuccess([
                'emails' => $emails,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Email history error: " . $e->getMessage());
            return $this->sendError("Failed to load email history", 500);
        }
    }
    
    /**
     * Parse email recipients from input string
     */
    private function parseEmailRecipients($input) {
        if (empty($input)) {
            return [];
        }
        
        $emails = [];
        $recipients = preg_split('/[;,\n\r]+/', $input);
        
        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if (filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $emails[] = $recipient;
            }
        }
        
        return array_unique($emails);
    }
    
    /**
     * Log email activity
     */
    private function logEmailActivity($email, $subject, $status) {
        try {
            $sql = "INSERT INTO instant_email_log (sender_id, recipient_email, subject, status, sent_at) 
                    VALUES (?, ?, ?, ?, NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$_SESSION["user_id"], $email, $subject, $status]);
        } catch (Exception $e) {
            error_log("Failed to log email activity: " . $e->getMessage());
        }
    }
}
