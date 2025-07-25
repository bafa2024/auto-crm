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
                $currentTime = date('Y-m-d H:i:s');
                
                $stmt = $this->db->prepare("INSERT INTO users (first_name, last_name, email, password, role, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute(['Admin', 'User', 'admin@autocrm.com', $hashedPassword, 'admin', 'active', $currentTime, $currentTime]);
                $userId = $this->db->lastInsertId();
            }
            
            // Check table structure and add missing columns if needed
            $this->ensureTableStructure();
            
            // Use current timestamp for datetime values
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO email_campaigns (
                user_id, name, subject, email_content, from_name, from_email, 
                status, created_at, updated_at
            ) VALUES (
                :user_id, :name, :subject, :content, :sender_name, :sender_email,
                :status, :created_at, :updated_at
            )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':name' => $campaignData['name'],
                ':subject' => $campaignData['subject'],
                ':content' => $campaignData['content'],
                ':sender_name' => $campaignData['sender_name'],
                ':sender_email' => $campaignData['sender_email'],
                ':status' => $campaignData['status'],
                ':created_at' => $currentTime,
                ':updated_at' => $currentTime
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
     * Ensure table has all required columns
     */
    private function ensureTableStructure() {
        try {
            // Get current table structure
            if ($this->dbType === 'mysql') {
                $stmt = $this->db->query("DESCRIBE email_campaigns");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $existingColumns = array_column($columns, 'Field');
            } else {
                $stmt = $this->db->query("PRAGMA table_info(email_campaigns)");
                $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $existingColumns = array_column($columns, 'name');
            }
            
            // Define required columns
            $requiredColumns = [
                'user_id' => $this->dbType === 'mysql' ? 'INT NOT NULL' : 'INTEGER NOT NULL',
                'schedule_type' => $this->dbType === 'mysql' ? 'VARCHAR(50) DEFAULT "immediate"' : 'TEXT DEFAULT "immediate"',
                'schedule_date' => $this->dbType === 'mysql' ? 'DATETIME NULL' : 'DATETIME',
                'frequency' => $this->dbType === 'mysql' ? 'VARCHAR(50) NULL' : 'TEXT',
                'total_recipients' => $this->dbType === 'mysql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
                'sent_count' => $this->dbType === 'mysql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
                'opened_count' => $this->dbType === 'mysql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
                'clicked_count' => $this->dbType === 'mysql' ? 'INT DEFAULT 0' : 'INTEGER DEFAULT 0',
                'updated_at' => $this->dbType === 'mysql' ? 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' : 'DATETIME DEFAULT CURRENT_TIMESTAMP'
            ];
            
            // Add missing columns
            foreach ($requiredColumns as $columnName => $columnDef) {
                if (!in_array($columnName, $existingColumns)) {
                    try {
                        if ($this->dbType === 'mysql') {
                            $sql = "ALTER TABLE email_campaigns ADD COLUMN $columnName $columnDef";
                        } else {
                            // SQLite doesn't support adding columns with default values easily
                            // We'll skip this for SQLite as the table should be created correctly
                            continue;
                        }
                        $this->db->exec($sql);
                        error_log("Added missing column: $columnName to email_campaigns table");
                    } catch (Exception $e) {
                        error_log("Failed to add column $columnName: " . $e->getMessage());
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("Error ensuring table structure: " . $e->getMessage());
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
            
            // Batch processing: split recipients into chunks of 50
            $recipientChunks = array_chunk($recipients, 50);
            foreach ($recipientChunks as $batchIndex => $batch) {
                error_log("Processing batch " . ($batchIndex + 1) . " of " . count($recipientChunks));
                foreach ($batch as $recipient) {
                    try {
                        $personalizedContent = $this->personalizeContent($campaign['email_content'], $recipient);
                        $emailSent = $this->sendEmail(
                            $recipient['email'],
                            $campaign['subject'],
                            $personalizedContent,
                            $campaign['from_name'],
                            $campaign['from_email'],
                            $recipient
                        );
                        
                        // Record the send
                        $currentTime = date('Y-m-d H:i:s');
                        $sql = "INSERT INTO campaign_sends (
                            campaign_id, recipient_id, recipient_email, status, sent_at
                        ) VALUES (
                            :campaign_id, :recipient_id, :recipient_email, :status, :sent_at
                        )";
                        
                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':campaign_id' => $campaignId,
                            ':recipient_id' => $recipient['id'],
                            ':recipient_email' => $recipient['email'],
                            ':status' => $emailSent ? 'sent' : 'failed',
                            ':sent_at' => $currentTime
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
                // Optional: sleep 1 second between batches to avoid rate limits
                if ($batchIndex < count($recipientChunks) - 1) {
                    sleep(1);
                }
            }
            
            // Update campaign status
            $this->updateCampaignStatus($campaignId, 'active');
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'total_recipients' => count($recipients),
                'errors' => $errors,
                'message' => "Campaign sent successfully!"
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
    private function sendEmail($to, $subject, $content, $senderName, $senderEmail, $recipient = null) {
        // Prepare email headers
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $senderName . ' <' . $senderEmail . '>',
            'Reply-To: ' . $senderEmail,
            'X-Mailer: ACRM Email Campaign System'
        ];
        
        // Create HTML email
        $htmlContent = $this->createHtmlEmail($content, $senderName, $recipient);
        
        // Send email
        return mail($to, $subject, $htmlContent, implode("\r\n", $headers));
    }
    
    /**
     * Create HTML email template
     */
    private function createHtmlEmail($content, $senderName, $recipient = null) {
        $greeting = '';
        if ($recipient && !empty($recipient['name'])) {
            $firstName = $this->getFirstName($recipient['name']);
            $greeting = '<p>Hi ' . htmlspecialchars($firstName) . ',</p>';
        }
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email</title>
            <style>
                body { font-family: Arial, sans-serif; background: #fff; color: #222; margin: 0; padding: 0; }
                .email-body { max-width: 600px; margin: 0 auto; padding: 24px; background: #fff; border: 1px solid #eee; border-radius: 8px; }
                .footer { font-size: 12px; color: #888; margin-top: 32px; text-align: left; }
            </style>
        </head>
        <body>
            <div class="email-body">
                ' . $greeting . '
                <div>' . nl2br($content) . '</div>
                <div class="footer">
                    <br>This message was sent by ' . htmlspecialchars($senderName) . '.
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
            
            // Use current timestamp for datetime values
            $currentTime = date('Y-m-d H:i:s');
            
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
                updated_at = :updated_at
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
                ':updated_at' => $currentTime,
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

    /**
     * Get all recipients and their send status for a campaign
     */
    public function getCampaignRecipientsWithStatus($campaignId) {
        try {
            $sql = "SELECT r.id, r.email, r.name, r.company, cs.status, cs.sent_at, cs.opened_at, cs.clicked_at
                    FROM email_recipients r
                    LEFT JOIN campaign_sends cs ON r.id = cs.recipient_id AND cs.campaign_id = ?
                    WHERE r.campaign_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId, $campaignId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Get all recipients for a campaign (for sending to all)
     */
    public function getAllCampaignRecipients($campaignId) {
        try {
            $sql = "SELECT id, email, name, company FROM email_recipients WHERE campaign_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
?> 