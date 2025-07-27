<?php
require_once "BaseController.php";

class EmailCampaignController extends BaseController {
    private $campaignModel;
    
    public function __construct($database) {
        parent::__construct($database);
        if ($database) {
            require_once __DIR__ . "/../models/EmailCampaign.php";
            $this->campaignModel = new EmailCampaign($database);
        }
    }
    
    public function createCampaign() {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        // Get JSON data
        $input = json_decode(file_get_contents("php://input"), true);
        
        // Map frontend fields to database fields
        $data = [
            "name" => $input["name"] ?? "",
            "subject" => $input["subject"] ?? "",
            "sender_name" => $input["from_name"] ?? "",
            "sender_email" => $input["from_email"] ?? "",
            "content" => $input["content"] ?? "",
            "created_by" => $_SESSION["user_id"],
            "status" => $input["status"] ?? "draft",
            "send_type" => $input["send_type"] ?? "immediate",
            "scheduled_at" => $input["scheduled_at"] ?? null,
            "target_type" => $input["target_type"] ?? "all",
            // Additional fields for compatibility
            "from_name" => $input["from_name"] ?? "",
            "from_email" => $input["from_email"] ?? "",
            "email_content" => $input["content"] ?? "",
            "user_id" => $_SESSION["user_id"]
        ];
        
        // Validate required fields
        if (empty($data["name"]) || empty($data["subject"]) || empty($data["sender_name"]) || empty($data["sender_email"]) || empty($data["content"])) {
            $this->sendError("Missing required fields", 400);
            return;
        }
        
        try {
            $campaign = $this->campaignModel->create($data);
            
            if ($campaign) {
                // Handle target recipients
                if ($input["target_type"] === "tags" && !empty($input["target_tags"])) {
                    // Store target tags (you may need to create a campaign_tags table)
                    // For now, we'll store in campaign metadata or handle in recipient selection
                }
                
                // Add recipients based on target type
                $this->addRecipientsToCampaign($campaign["id"], $input["target_type"], $input["target_tags"] ?? []);
                
                $this->sendSuccess($campaign, "Campaign created successfully");
            } else {
                $this->sendError("Failed to create campaign", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error creating campaign: " . $e->getMessage(), 500);
        }
    }
    
    public function updateCampaignStatus($campaignId) {
        if ($_SERVER["REQUEST_METHOD"] !== "PUT") {
            $this->sendError("Method not allowed", 405);
            return;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        $input = json_decode(file_get_contents("php://input"), true);
        $status = $input["status"] ?? "";
        
        if (!in_array($status, ["active", "paused", "completed", "draft"])) {
            $this->sendError("Invalid status", 400);
            return;
        }
        
        try {
            // Verify ownership
            $campaign = $this->campaignModel->findById($campaignId);
            if (!$campaign || $campaign["created_by"] != $_SESSION["user_id"]) {
                $this->sendError("Campaign not found or unauthorized", 404);
                return;
            }
            
            $updated = $this->campaignModel->update($campaignId, ["status" => $status]);
            
            if ($updated) {
                $this->sendSuccess(["id" => $campaignId, "status" => $status], "Campaign status updated");
            } else {
                $this->sendError("Failed to update campaign status", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error updating campaign: " . $e->getMessage(), 500);
        }
    }
    
    public function duplicateCampaign($campaignId) {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
            return;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        try {
            // Get original campaign
            $original = $this->campaignModel->findById($campaignId);
            if (!$original || $original["created_by"] != $_SESSION["user_id"]) {
                $this->sendError("Campaign not found or unauthorized", 404);
                return;
            }
            
            // Create duplicate
            $data = [
                "name" => $original["name"] . " (Copy)",
                "subject" => $original["subject"],
                "sender_name" => $original["sender_name"],
                "sender_email" => $original["sender_email"],
                "content" => $original["content"],
                "created_by" => $_SESSION["user_id"],
                "status" => "draft",
                "send_type" => $original["send_type"],
                "target_type" => $original["target_type"]
            ];
            
            $duplicate = $this->campaignModel->create($data);
            
            if ($duplicate) {
                // Copy recipients if needed
                $this->copyRecipients($campaignId, $duplicate["id"]);
                
                $this->sendSuccess($duplicate, "Campaign duplicated successfully");
            } else {
                $this->sendError("Failed to duplicate campaign", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error duplicating campaign: " . $e->getMessage(), 500);
        }
    }
    
    public function deleteCampaign($campaignId) {
        if ($_SERVER["REQUEST_METHOD"] !== "DELETE") {
            $this->sendError("Method not allowed", 405);
            return;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        try {
            // Verify ownership
            $campaign = $this->campaignModel->findById($campaignId);
            if (!$campaign || $campaign["created_by"] != $_SESSION["user_id"]) {
                $this->sendError("Campaign not found or unauthorized", 404);
                return;
            }
            
            // Only allow deletion of draft or paused campaigns
            if (!in_array($campaign["status"], ["draft", "paused"])) {
                $this->sendError("Cannot delete active or completed campaigns", 400);
                return;
            }
            
            $deleted = $this->campaignModel->delete($campaignId);
            
            if ($deleted) {
                $this->sendSuccess(["id" => $campaignId], "Campaign deleted successfully");
            } else {
                $this->sendError("Failed to delete campaign", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error deleting campaign: " . $e->getMessage(), 500);
        }
    }
    
    private function addRecipientsToCampaign($campaignId, $targetType, $targetTags = []) {
        $db = $this->db;
        
        if ($targetType === "all") {
            // Add all contacts
            $stmt = $db->prepare("
                INSERT INTO email_recipients (campaign_id, contact_id, email, status)
                SELECT ?, id, email, 'pending' FROM contacts
            ");
            $stmt->execute([$campaignId]);
        } elseif ($targetType === "tags" && !empty($targetTags)) {
            // Add contacts with specific tags
            $tagPlaceholders = str_repeat("?,", count($targetTags) - 1) . "?";
            $stmt = $db->prepare("
                INSERT INTO email_recipients (campaign_id, contact_id, email, status)
                SELECT DISTINCT ?, id, email, 'pending' 
                FROM contacts 
                WHERE " . implode(" OR ", array_fill(0, count($targetTags), "FIND_IN_SET(?, tags)"))
            );
            $params = [$campaignId];
            foreach ($targetTags as $tag) {
                $params[] = $tag;
            }
            $stmt->execute($params);
        }
    }
    
    private function copyRecipients($fromCampaignId, $toCampaignId) {
        $db = $this->db;
        $stmt = $db->prepare("
            INSERT INTO email_recipients (campaign_id, contact_id, email, status)
            SELECT ?, contact_id, email, 'pending' 
            FROM email_recipients 
            WHERE campaign_id = ?
        ");
        $stmt->execute([$toCampaignId, $fromCampaignId]);
    }
    
    public function sendCampaign($campaignId) {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
            return;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        // Check permission
        require_once __DIR__ . "/../models/EmployeePermission.php";
        $permissionModel = new EmployeePermission($this->db);
        $permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);
        
        if (!$permissions['can_send_campaigns']) {
            $this->sendError("You don't have permission to send campaigns", 403);
            return;
        }
        
        try {
            // Verify ownership
            $campaign = $this->campaignModel->findById($campaignId);
            if (!$campaign || $campaign["created_by"] != $_SESSION["user_id"]) {
                $this->sendError("Campaign not found or unauthorized", 404);
                return;
            }
            
            // Check if campaign can be sent
            if (!in_array($campaign["status"], ["draft", "scheduled", "paused"])) {
                $this->sendError("Campaign cannot be sent in current status", 400);
                return;
            }
            
            // Update status to active to trigger sending
            $updated = $this->campaignModel->update($campaignId, ["status" => "active"]);
            
            if ($updated) {
                // Process campaign immediately if requested
                $immediate = json_decode(file_get_contents("php://input"), true)['immediate'] ?? false;
                
                if ($immediate) {
                    require_once __DIR__ . "/../services/EmailService.php";
                    $emailService = new EmailService($this->db);
                    $result = $emailService->processCampaign($campaignId);
                    
                    $this->sendSuccess([
                        "id" => $campaignId,
                        "status" => "sending",
                        "result" => $result
                    ], "Campaign is being sent");
                } else {
                    $this->sendSuccess([
                        "id" => $campaignId,
                        "status" => "active"
                    ], "Campaign activated and will be processed by the scheduler");
                }
            } else {
                $this->sendError("Failed to activate campaign", 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error sending campaign: " . $e->getMessage(), 500);
        }
    }
    
    public function sendTestEmail() {
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
            return;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        $input = json_decode(file_get_contents("php://input"), true);
        
        // Validate input
        $to = $input["to"] ?? "";
        $subject = $input["subject"] ?? "";
        $content = $input["content"] ?? "";
        $fromName = $input["from_name"] ?? "";
        $fromEmail = $input["from_email"] ?? "";
        
        if (empty($to) || empty($subject) || empty($content)) {
            $this->sendError("Missing required fields", 400);
            return;
        }
        
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->sendError("Invalid email address", 400);
            return;
        }
        
        try {
            require_once __DIR__ . "/../services/EmailService.php";
            $emailService = new EmailService($this->db);
            
            // Add test disclaimer
            $content = '<div style="background-color: #f8d7da; color: #721c24; padding: 12px; margin-bottom: 20px; border: 1px solid #f5c6cb; border-radius: 4px;">
                <strong>TEST EMAIL</strong> - This is a test email from your campaign creation interface.
            </div>' . $content;
            
            // Send test email
            $result = $emailService->send($to, "[TEST] " . $subject, $content, [
                'from_email' => $fromEmail,
                'from_name' => $fromName
            ]);
            
            if ($result['success']) {
                $this->sendSuccess(['sent_to' => $to], "Test email sent successfully");
            } else {
                $this->sendError("Failed to send test email: " . ($result['error'] ?? 'Unknown error'), 500);
            }
        } catch (Exception $e) {
            $this->sendError("Error sending test email: " . $e->getMessage(), 500);
        }
    }
    
    public function getCampaignStats($campaignId) {
        if ($_SERVER["REQUEST_METHOD"] !== "GET") {
            $this->sendError("Method not allowed", 405);
            return;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION["user_id"])) {
            $this->sendError("Unauthorized", 401);
            return;
        }
        
        try {
            // Get campaign with stats
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    COUNT(DISTINCT r.id) as total_recipients,
                    COUNT(DISTINCT CASE WHEN r.status = 'sent' THEN r.id END) as sent_count,
                    COUNT(DISTINCT CASE WHEN r.opened_at IS NOT NULL THEN r.id END) as opened_count,
                    COUNT(DISTINCT CASE WHEN r.clicked_at IS NOT NULL THEN r.id END) as clicked_count,
                    COUNT(DISTINCT CASE WHEN r.bounced_at IS NOT NULL THEN r.id END) as bounced_count,
                    COUNT(DISTINCT CASE WHEN r.unsubscribed_at IS NOT NULL THEN r.id END) as unsubscribed_count,
                    COUNT(DISTINCT CASE WHEN r.status = 'failed' THEN r.id END) as failed_count
                FROM email_campaigns c
                LEFT JOIN email_recipients r ON c.id = r.campaign_id
                WHERE c.id = ? AND c.created_by = ?
                GROUP BY c.id
            ");
            $stmt->execute([$campaignId, $_SESSION["user_id"]]);
            $campaign = $stmt->fetch();
            
            if (!$campaign) {
                $this->sendError("Campaign not found or unauthorized", 404);
                return;
            }
            
            // Calculate rates
            $campaign['open_rate'] = $campaign['sent_count'] > 0 
                ? round(($campaign['opened_count'] / $campaign['sent_count']) * 100, 2) 
                : 0;
                
            $campaign['click_rate'] = $campaign['sent_count'] > 0 
                ? round(($campaign['clicked_count'] / $campaign['sent_count']) * 100, 2) 
                : 0;
                
            $campaign['bounce_rate'] = $campaign['sent_count'] > 0 
                ? round(($campaign['bounced_count'] / $campaign['sent_count']) * 100, 2) 
                : 0;
                
            $campaign['unsubscribe_rate'] = $campaign['sent_count'] > 0 
                ? round(($campaign['unsubscribed_count'] / $campaign['sent_count']) * 100, 2) 
                : 0;
            
            // Get recent activity
            $stmt = $this->db->prepare("
                SELECT 
                    'opened' as action,
                    r.email,
                    r.opened_at as action_time
                FROM email_recipients r
                WHERE r.campaign_id = ? AND r.opened_at IS NOT NULL
                UNION ALL
                SELECT 
                    'clicked' as action,
                    r.email,
                    r.clicked_at as action_time
                FROM email_recipients r
                WHERE r.campaign_id = ? AND r.clicked_at IS NOT NULL
                ORDER BY action_time DESC
                LIMIT 20
            ");
            $stmt->execute([$campaignId, $campaignId]);
            $campaign['recent_activity'] = $stmt->fetchAll();
            
            $this->sendSuccess($campaign);
            
        } catch (Exception $e) {
            $this->sendError("Error getting campaign stats: " . $e->getMessage(), 500);
        }
    }
    
    private function handleFileUpload($file, $campaignId = null) {
        require_once __DIR__ . "/../services/EmailUploadService.php";
        
        $allowedTypes = [
            "text/csv", 
            "application/csv",
            "text/comma-separated-values",
            "application/vnd.ms-excel", 
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "application/octet-stream" // Some browsers send this for Excel files
        ];
        
        // Also check file extension as fallback
        $extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
        $allowedExtensions = ["csv", "xlsx", "xls"];
        
        if (!in_array($file["type"], $allowedTypes) && !in_array($extension, $allowedExtensions)) {
            return ["success" => false, "message" => "Invalid file type. Please upload CSV or Excel file. (Detected: " . $file["type"] . ", Extension: " . $extension . ")"];
        }
        
        if ($file["size"] > 10 * 1024 * 1024) { // 10MB limit
            return ["success" => false, "message" => "File size exceeds 10MB limit."];
        }
        
        $uploadDir = __DIR__ . "/../uploads/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = uniqid() . "_" . basename($file["name"]);
        $uploadPath = $uploadDir . $fileName;
        
        if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
            // Process the file using EmailUploadService
            $uploadService = new EmailUploadService($this->db);
            
            // Validate file first
            $validationErrors = $uploadService->validateFile($uploadPath);
            if (!empty($validationErrors)) {
                // Clean up uploaded file
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                return ["success" => false, "message" => implode(", ", $validationErrors)];
            }
            
            $result = $uploadService->processUploadedFile($uploadPath, $campaignId, $file["name"]);
            
            // Clean up uploaded file
            if (file_exists($uploadPath)) {
                unlink($uploadPath);
            }
            
            return $result;
        }
        
        return ["success" => false, "message" => "Failed to upload file."];
    }
}