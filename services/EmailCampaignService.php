<?php

class EmailCampaignService {
    private $db;
    private $dbType;
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        $this->db = $database->getConnection();
        $this->dbType = $database->getDatabaseType();
    }
    
    /**
     * Get database type from the database configuration
     */
    private function getDatabaseType() {
        return $this->dbType;
    }
    
    /**
     * Get appropriate datetime function based on database type
     */
    private function getDateTimeFunction() {
        return ($this->dbType === 'sqlite') ? "datetime('now')" : "NOW()";
    }
    
    /**
     * Create a new email campaign
     */
    public function createCampaign($campaignData) {
        try {
            // Create email_campaigns table if it doesn't exist
            $this->createCampaignsTable();
            
            // Verify user exists before creating campaign
            $userId = $campaignData['user_id'] ?? 1;
            $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                // Create default admin user if it doesn't exist
                $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $datetimeFunc = $this->getDateTimeFunction();
                
                $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, $datetimeFunc, $datetimeFunc)");
                $stmt->execute(['Admin', 'User', 'admin@autocrm.com', $hashedPassword, 'admin', 'active']);
                $userId = $this->db->lastInsertId();
            }
            
            // Use appropriate datetime function based on database type
            $datetimeFunc = $this->getDateTimeFunction();
            
            $sql = "INSERT INTO email_campaigns (
                user_id, name, subject, email_content, from_name, from_email, 
                status, created_at, updated_at
            ) VALUES (
                :user_id, :name, :subject, :content, :sender_name, :sender_email,
                :status, $datetimeFunc, $datetimeFunc
            )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $campaignData['name'],
                ':subject' => $campaignData['subject'],
                ':content' => $campaignData['content'],
                ':sender_name' => $campaignData['sender_name'],
                ':sender_email' => $campaignData['sender_email'],
                ':status' => $campaignData['status']
            ]);
            
            $campaignId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'campaign_id' => $campaignId,
                'message' => 'Campaign created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create campaign: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send campaign to selected recipients
     */
    public function sendCampaign($campaignId, $recipientIds) {
        try {
            // Debug logging
            error_log("sendCampaign called with campaignId: $campaignId, recipientIds: " . json_encode($recipientIds));
            
            // Get campaign details
            $campaign = $this->getCampaign($campaignId);
            if (!$campaign) {
                error_log("Campaign not found: $campaignId");
                return [
                    'success' => false,
                    'message' => 'Campaign not found'
                ];
            }
            
            error_log("Campaign found: " . json_encode($campaign));
            
            // Get recipients
            $recipients = $this->getRecipients($recipientIds);
            error_log("Recipients found: " . count($recipients));
            
            if (empty($recipients)) {
                error_log("No recipients found for IDs: " . json_encode($recipientIds));
                return [
                    'success' => false,
                    'message' => 'No recipients selected or found'
                ];
            }
            
            // Create campaign_sends table if it doesn't exist
            $this->createCampaignSendsTable();
            
            $sentCount = 0;
            $errors = [];
            
            foreach ($recipients as $recipient) {
                try {
                    // Send email
                    $emailSent = $this->sendEmail(
                        $recipient['email'],
                        $campaign['subject'],
                        $this->personalizeContent($campaign['email_content'], $recipient),
                        $campaign['from_name'],
                        $campaign['from_email']
                    );
                    
                    // Record the send
                    $datetimeFunc = $this->getDateTimeFunction();
                    $sql = "INSERT INTO campaign_sends (
                        campaign_id, recipient_id, recipient_email, status, sent_at
                    ) VALUES (
                        :campaign_id, :recipient_id, :recipient_email, :status, $datetimeFunc
                    )";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':campaign_id' => $campaignId,
                        ':recipient_id' => $recipient['id'],
                        ':recipient_email' => $recipient['email'],
                        ':status' => $emailSent ? 'sent' : 'failed'
                    ]);
                    
                    if ($emailSent) {
                        $sentCount++;
                    } else {
                        $errors[] = "Failed to send to {$recipient['email']}";
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "Error sending to {$recipient['email']}: " . $e->getMessage();
                }
            }
            
            // Update campaign status
            $this->updateCampaignStatus($campaignId, 'active');
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'total_recipients' => count($recipients),
                'errors' => $errors,
                'message' => "Sent to $sentCount out of " . count($recipients) . " recipients"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to send campaign: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get campaign by ID
     */
    private function getCampaign($campaignId) {
        $sql = "SELECT * FROM email_campaigns WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $campaignId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recipients by IDs
     */
    private function getRecipients($recipientIds) {
        if (empty($recipientIds)) {
            return [];
        }
        
        $placeholders = str_repeat('?,', count($recipientIds) - 1) . '?';
        $sql = "SELECT id, email, name, company FROM email_recipients WHERE id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($recipientIds);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Personalize email content with recipient data
     */
    private function personalizeContent($content, $recipient) {
        $replacements = [
            '{{name}}' => $recipient['name'] ?? 'there',
            '{{email}}' => $recipient['email'],
            '{{company}}' => $recipient['company'] ?? '',
            '{{first_name}}' => $this->getFirstName($recipient['name'] ?? ''),
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
    
    /**
     * Extract first name from full name
     */
    private function getFirstName($fullName) {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? '';
    }
    
    /**
     * Send email using PHP mail() function
     */
    private function sendEmail($to, $subject, $content, $senderName, $senderEmail) {
        // Prepare email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $senderName . ' <' . $senderEmail . '>',
            'Reply-To: ' . $senderEmail,
            'X-Mailer: ACRM Email Campaign System'
        ];
        
        // Create HTML email
        $htmlContent = $this->createHtmlEmail($content, $senderName);
        
        // Send email
        return mail($to, $subject, $htmlContent, implode("\r\n", $headers));
    }
    
    /**
     * Create HTML email template
     */
    private function createHtmlEmail($content, $senderName) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Campaign</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . htmlspecialchars($senderName) . '</h2>
                </div>
                <div class="content">
                    ' . nl2br(htmlspecialchars($content)) . '
                </div>
                <div class="footer">
                    <p>This email was sent by ACRM Email Campaign System</p>
                    <p>To unsubscribe, please contact the sender</p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    /**
     * Update campaign status
     */
    private function updateCampaignStatus($campaignId, $status) {
        $sql = "UPDATE email_campaigns SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status, ':id' => $campaignId]);
    }
    
    /**
     * Create email_campaigns table
     */
    private function createCampaignsTable() {
        $datetimeFunc = $this->getDateTimeFunction();
        if ($this->dbType === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS email_campaigns (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                subject TEXT NOT NULL,
                email_content TEXT NOT NULL,
                from_name TEXT NOT NULL,
                from_email TEXT NOT NULL,
                schedule_type TEXT DEFAULT 'immediate',
                schedule_date DATETIME,
                frequency TEXT,
                status TEXT DEFAULT 'draft',
                total_recipients INTEGER DEFAULT 0,
                sent_count INTEGER DEFAULT 0,
                opened_count INTEGER DEFAULT 0,
                clicked_count INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            // MySQL syntax
            $sql = "CREATE TABLE IF NOT EXISTS email_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                email_content TEXT NOT NULL,
                from_name VARCHAR(100) NOT NULL,
                from_email VARCHAR(255) NOT NULL,
                schedule_type VARCHAR(50) DEFAULT 'immediate',
                schedule_date DATETIME,
                frequency VARCHAR(50),
                status VARCHAR(50) DEFAULT 'draft',
                total_recipients INT DEFAULT 0,
                sent_count INT DEFAULT 0,
                opened_count INT DEFAULT 0,
                clicked_count INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user_id (user_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $this->db->exec($sql);
    }
    
    /**
     * Create campaign_sends table
     */
    private function createCampaignSendsTable() {
        $datetimeFunc = $this->getDateTimeFunction();
        if ($this->dbType === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS campaign_sends (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                campaign_id INTEGER NOT NULL,
                recipient_id INTEGER NOT NULL,
                recipient_email TEXT NOT NULL,
                status TEXT DEFAULT 'pending',
                sent_at DATETIME,
                opened_at DATETIME,
                clicked_at DATETIME,
                tracking_id TEXT UNIQUE
            )";
        } else {
            // MySQL syntax
            $sql = "CREATE TABLE IF NOT EXISTS campaign_sends (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT NOT NULL,
                recipient_id INT NOT NULL,
                recipient_email VARCHAR(255) NOT NULL,
                status VARCHAR(50) DEFAULT 'pending',
                sent_at DATETIME,
                opened_at DATETIME,
                clicked_at DATETIME,
                tracking_id VARCHAR(64) UNIQUE,
                INDEX idx_campaign_id (campaign_id),
                INDEX idx_recipient_id (recipient_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }
        
        $this->db->exec($sql);
    }
    
    /**
     * Get campaign statistics
     */
    public function getCampaignStats($campaignId) {
        try {
            $sql = "SELECT 
                COUNT(*) as total_sends,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened_count
                FROM campaign_sends 
                WHERE campaign_id = :campaign_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':campaign_id' => $campaignId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get all campaigns with statistics
     */
    public function getAllCampaigns() {
        try {
            $sql = "SELECT 
                c.*,
                COUNT(cs.id) as total_sends,
                SUM(CASE WHEN cs.status = 'sent' THEN 1 ELSE 0 END) as sent_count
                FROM email_campaigns c
                LEFT JOIN campaign_sends cs ON c.id = cs.campaign_id
                GROUP BY c.id
                ORDER BY c.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get campaign by ID for editing
     */
    public function getCampaignById($campaignId) {
        try {
            $sql = "SELECT * FROM email_campaigns WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $campaignId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Edit/Update an existing campaign
     */
    public function editCampaign($campaignId, $campaignData) {
        try {
            // First check if campaign exists
            $existingCampaign = $this->getCampaignById($campaignId);
            if (!$existingCampaign) {
                return [
                    'success' => false,
                    'message' => 'Campaign not found'
                ];
            }
            
            // Check if campaign can be edited (not sent/completed)
            if (in_array($existingCampaign['status'], ['completed', 'sending'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot edit campaign that is already sent or in progress'
                ];
            }
            
            // Use appropriate datetime function based on database type
            $datetimeFunc = $this->getDateTimeFunction();
            
            $sql = "UPDATE email_campaigns SET 
                name = :name,
                subject = :subject,
                email_content = :content,
                from_name = :sender_name,
                from_email = :sender_email,
                schedule_type = :schedule_type,
                schedule_date = :schedule_date,
                frequency = :frequency,
                status = :status,
                updated_at = $datetimeFunc
                WHERE id = :id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':name' => $campaignData['name'],
                ':subject' => $campaignData['subject'],
                ':content' => $campaignData['content'],
                ':sender_name' => $campaignData['sender_name'],
                ':sender_email' => $campaignData['sender_email'],
                ':schedule_type' => $campaignData['schedule_type'] ?? 'immediate',
                ':schedule_date' => $campaignData['schedule_date'] ?? null,
                ':frequency' => $campaignData['frequency'] ?? null,
                ':status' => $campaignData['status'] ?? 'draft',
                ':id' => $campaignId
            ]);
            
            if ($result) {
                return [
                    'success' => true,
                    'campaign_id' => $campaignId,
                    'message' => 'Campaign updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update campaign'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to update campaign: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a campaign
     */
    public function deleteCampaign($campaignId) {
        try {
            // First check if campaign exists
            $existingCampaign = $this->getCampaignById($campaignId);
            if (!$existingCampaign) {
                return [
                    'success' => false,
                    'message' => 'Campaign not found'
                ];
            }
            
            // Check if campaign can be deleted (not sent/completed)
            if (in_array($existingCampaign['status'], ['completed', 'sending'])) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete campaign that is already sent or in progress'
                ];
            }
            
            // Delete campaign sends first (due to foreign key)
            $stmt = $this->db->prepare("DELETE FROM campaign_sends WHERE campaign_id = ?");
            $stmt->execute([$campaignId]);
            
            // Delete the campaign
            $stmt = $this->db->prepare("DELETE FROM email_campaigns WHERE id = ?");
            $result = $stmt->execute([$campaignId]);
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Campaign deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to delete campaign'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to delete campaign: ' . $e->getMessage()
            ];
        }
    }
}
?> 