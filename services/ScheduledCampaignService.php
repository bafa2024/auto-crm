<?php
// ScheduledCampaignService.php - Handle scheduled email campaigns
// This service manages campaign scheduling, execution, and status updates

class ScheduledCampaignService {
    private $db;
    private $dbType;
    private $database;
    private $emailService;
    
    public function __construct($database) {
        $this->database = $database;
        $this->db = $database->getConnection();
        $this->dbType = $database->getDatabaseType();
        $this->emailService = new EmailService($database);
    }
    
    /**
     * Get database type
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
     * Create a scheduled campaign
     */
    public function createScheduledCampaign($campaignData) {
        try {
            // Ensure campaigns table exists
            $this->ensureCampaignsTable();
            
            // Validate schedule data
            $validation = $this->validateScheduleData($campaignData);
            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => $validation['message']
                ];
            }
            
            // Use current timestamp for datetime values
            $currentTime = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO email_campaigns (
                user_id, name, subject, email_content, from_name, from_email, 
                schedule_type, schedule_date, frequency, status, created_at, updated_at
            ) VALUES (
                :user_id, :name, :subject, :content, :sender_name, :sender_email,
                :schedule_type, :schedule_date, :frequency, :status, :created_at, :updated_at
            )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $campaignData['user_id'],
                ':name' => $campaignData['name'],
                ':subject' => $campaignData['subject'],
                ':content' => $campaignData['content'],
                ':sender_name' => $campaignData['sender_name'],
                ':sender_email' => $campaignData['sender_email'],
                ':schedule_type' => $campaignData['schedule_type'],
                ':schedule_date' => $campaignData['schedule_date'],
                ':frequency' => $campaignData['frequency'],
                ':status' => 'scheduled',
                ':created_at' => $currentTime,
                ':updated_at' => $currentTime
            ]);
            
            $campaignId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'campaign_id' => $campaignId,
                'message' => 'Scheduled campaign created successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to create scheduled campaign: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate schedule data
     */
    private function validateScheduleData($campaignData) {
        $scheduleType = $campaignData['schedule_type'] ?? 'immediate';
        $scheduleDate = $campaignData['schedule_date'] ?? null;
        $frequency = $campaignData['frequency'] ?? null;
        
        // Validate schedule type
        $validScheduleTypes = ['immediate', 'scheduled', 'recurring'];
        if (!in_array($scheduleType, $validScheduleTypes)) {
            return [
                'valid' => false,
                'message' => 'Invalid schedule type. Must be: ' . implode(', ', $validScheduleTypes)
            ];
        }
        
        // Validate schedule date for scheduled campaigns
        if ($scheduleType === 'scheduled' && empty($scheduleDate)) {
            return [
                'valid' => false,
                'message' => 'Schedule date is required for scheduled campaigns'
            ];
        }
        
        // Validate schedule date is in the future
        if ($scheduleType === 'scheduled' && !empty($scheduleDate)) {
            $scheduleDateTime = new DateTime($scheduleDate);
            $now = new DateTime();
            
            if ($scheduleDateTime <= $now) {
                return [
                    'valid' => false,
                    'message' => 'Schedule date must be in the future'
                ];
            }
        }
        
        // Validate frequency for recurring campaigns
        if ($scheduleType === 'recurring' && empty($frequency)) {
            return [
                'valid' => false,
                'message' => 'Frequency is required for recurring campaigns'
            ];
        }
        
        $validFrequencies = ['daily', 'weekly', 'monthly'];
        if ($scheduleType === 'recurring' && !in_array($frequency, $validFrequencies)) {
            return [
                'valid' => false,
                'message' => 'Invalid frequency. Must be: ' . implode(', ', $validFrequencies)
            ];
        }
        
        return ['valid' => true, 'message' => 'Valid'];
    }
    
    /**
     * Process scheduled campaigns (called by cron job)
     */
    public function processScheduledCampaigns() {
        try {
            $processed = 0;
            $sent = 0;
            $errors = [];
            
            // Get campaigns that are ready to be sent
            $sql = "SELECT * FROM email_campaigns WHERE status = 'scheduled' AND schedule_date <= " . $this->getDateTimeFunction();
            $stmt = $this->db->query($sql);
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($campaigns as $campaign) {
                try {
                    $result = $this->executeScheduledCampaign($campaign);
                    if ($result['success']) {
                        $sent += $result['sent_count'];
                    } else {
                        $errors[] = "Campaign {$campaign['id']}: " . $result['message'];
                    }
                    $processed++;
                } catch (Exception $e) {
                    $errors[] = "Campaign {$campaign['id']}: " . $e->getMessage();
                }
            }
            
            return [
                'success' => true,
                'processed' => $processed,
                'sent' => $sent,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process scheduled campaigns: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Execute a scheduled campaign
     */
    private function executeScheduledCampaign($campaign) {
        try {
            // Update campaign status to sending
            $this->updateCampaignStatus($campaign['id'], 'sending');
            
            // Get all recipients for this campaign
            $recipients = $this->getCampaignRecipients($campaign['id']);
            
            if (empty($recipients)) {
                $this->updateCampaignStatus($campaign['id'], 'completed');
                return [
                    'success' => true,
                    'sent_count' => 0,
                    'message' => 'No recipients found'
                ];
            }
            
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
                    
                    if ($emailSent) {
                        $sentCount++;
                        // Record the send
                        $this->recordEmailSend($campaign['id'], $recipient['id'], $recipient['email'], 'sent');
                    } else {
                        $this->recordEmailSend($campaign['id'], $recipient['id'], $recipient['email'], 'failed');
                        $errors[] = "Failed to send to {$recipient['email']}";
                    }
                    
                } catch (Exception $e) {
                    $this->recordEmailSend($campaign['id'], $recipient['id'], $recipient['email'], 'failed');
                    $errors[] = "Error sending to {$recipient['email']}: " . $e->getMessage();
                }
            }
            
            // Update campaign status and stats
            $this->updateCampaignStats($campaign['id'], $sentCount);
            $this->updateCampaignStatus($campaign['id'], 'completed');
            
            // Handle recurring campaigns
            if ($campaign['schedule_type'] === 'recurring') {
                $this->scheduleNextRecurrence($campaign);
            }
            
            return [
                'success' => true,
                'sent_count' => $sentCount,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            $this->updateCampaignStatus($campaign['id'], 'failed');
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get recipients for a campaign
     */
    private function getCampaignRecipients($campaignId) {
        $sql = "SELECT * FROM email_recipients WHERE campaign_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Store recipients for a scheduled campaign
     */
    public function storeScheduledRecipients($campaignId, $recipientIds) {
        try {
            // Ensure email_recipients table has campaign_id column
            $this->ensureRecipientsTable();
            
            // Clear any existing recipients for this campaign
            $sql = "DELETE FROM email_recipients WHERE campaign_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId]);
            
            // Insert new recipients
            if (!empty($recipientIds)) {
                $sql = "INSERT INTO email_recipients (campaign_id, email, name, company, status, created_at) 
                        SELECT ?, email, name, company, 'pending', " . $this->getDateTimeFunction() . "
                        FROM email_recipients 
                        WHERE id IN (" . str_repeat('?,', count($recipientIds) - 1) . "?)";
                
                $params = array_merge([$campaignId], $recipientIds);
                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Failed to store scheduled recipients: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Ensure email_recipients table has campaign_id column
     */
    private function ensureRecipientsTable() {
        try {
            // Check if campaign_id column exists
            if ($this->dbType === 'mysql') {
                $sql = "SHOW COLUMNS FROM email_recipients LIKE 'campaign_id'";
                $stmt = $this->db->query($sql);
                if ($stmt->rowCount() == 0) {
                    // Add campaign_id column
                    $sql = "ALTER TABLE email_recipients ADD COLUMN campaign_id INT NULL AFTER id";
                    $this->db->exec($sql);
                }
            }
        } catch (Exception $e) {
            error_log("Failed to ensure recipients table structure: " . $e->getMessage());
        }
    }
    
    /**
     * Send email
     */
    private function sendEmail($to, $subject, $content, $senderName, $senderEmail) {
        // Use the existing EmailService
        return $this->emailService->sendSimpleEmail($to, $subject, $content, $senderName, $senderEmail);
    }
    
    /**
     * Personalize email content
     */
    private function personalizeContent($content, $recipient) {
        $name = $recipient['name'] ?? 'there';
        $firstName = $this->getFirstName($name);
        
        $content = str_replace('{first_name}', $firstName, $content);
        $content = str_replace('{name}', $name, $content);
        $content = str_replace('{email}', $recipient['email'], $content);
        $content = str_replace('{company}', $recipient['company'] ?? '', $content);
        
        return $content;
    }
    
    /**
     * Get first name from full name
     */
    private function getFirstName($fullName) {
        $parts = explode(' ', trim($fullName));
        return $parts[0] ?? 'there';
    }
    
    /**
     * Record email send
     */
    private function recordEmailSend($campaignId, $recipientId, $recipientEmail, $status) {
        try {
            $this->ensureCampaignSendsTable();
            
            $currentTime = date('Y-m-d H:i:s');
            $sql = "INSERT INTO campaign_sends (campaign_id, recipient_id, recipient_email, status, sent_at) VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId, $recipientId, $recipientEmail, $status, $currentTime]);
        } catch (Exception $e) {
            error_log("Failed to record email send: " . $e->getMessage());
        }
    }
    
    /**
     * Update campaign stats
     */
    private function updateCampaignStats($campaignId, $sentCount) {
        try {
            $sql = "UPDATE email_campaigns SET sent_count = sent_count + ?, updated_at = " . $this->getDateTimeFunction() . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$sentCount, $campaignId]);
        } catch (Exception $e) {
            error_log("Failed to update campaign stats: " . $e->getMessage());
        }
    }
    
    /**
     * Update campaign status
     */
    private function updateCampaignStatus($campaignId, $status) {
        try {
            $sql = "UPDATE email_campaigns SET status = ?, updated_at = " . $this->getDateTimeFunction() . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$status, $campaignId]);
        } catch (Exception $e) {
            error_log("Failed to update campaign status: " . $e->getMessage());
        }
    }
    
    /**
     * Schedule next recurrence for recurring campaigns
     */
    private function scheduleNextRecurrence($campaign) {
        try {
            $frequency = $campaign['frequency'];
            $currentScheduleDate = new DateTime($campaign['schedule_date']);
            
            switch ($frequency) {
                case 'daily':
                    $nextDate = $currentScheduleDate->modify('+1 day');
                    break;
                case 'weekly':
                    $nextDate = $currentScheduleDate->modify('+1 week');
                    break;
                case 'monthly':
                    $nextDate = $currentScheduleDate->modify('+1 month');
                    break;
                default:
                    return; // No recurrence
            }
            
            $sql = "UPDATE email_campaigns SET schedule_date = ?, status = 'scheduled', updated_at = " . $this->getDateTimeFunction() . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nextDate->format('Y-m-d H:i:s'), $campaign['id']]);
            
        } catch (Exception $e) {
            error_log("Failed to schedule next recurrence: " . $e->getMessage());
        }
    }
    
    /**
     * Get scheduled campaigns
     */
    public function getScheduledCampaigns() {
        try {
            $sql = "SELECT * FROM email_campaigns WHERE status = 'scheduled' ORDER BY schedule_date ASC";
            $stmt = $this->db->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get scheduled campaigns: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get campaign statistics
     */
    public function getCampaignStats($campaignId) {
        try {
            $sql = "SELECT 
                        c.*,
                        COUNT(s.id) as total_sends,
                        SUM(CASE WHEN s.status = 'sent' THEN 1 ELSE 0 END) as successful_sends,
                        SUM(CASE WHEN s.status = 'failed' THEN 1 ELSE 0 END) as failed_sends
                    FROM email_campaigns c
                    LEFT JOIN campaign_sends s ON c.id = s.campaign_id
                    WHERE c.id = ?
                    GROUP BY c.id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Failed to get campaign stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Ensure campaigns table exists
     */
    private function ensureCampaignsTable() {
        try {
            if ($this->dbType === 'mysql') {
                $sql = "CREATE TABLE IF NOT EXISTS email_campaigns (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    subject VARCHAR(255) NOT NULL,
                    email_content TEXT NOT NULL,
                    from_name VARCHAR(100) NOT NULL,
                    from_email VARCHAR(255) NOT NULL,
                    schedule_type VARCHAR(50) DEFAULT 'immediate',
                    schedule_date DATETIME NULL,
                    frequency VARCHAR(50) NULL,
                    status VARCHAR(50) DEFAULT 'draft',
                    total_recipients INT DEFAULT 0,
                    sent_count INT DEFAULT 0,
                    opened_count INT DEFAULT 0,
                    clicked_count INT DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_status (status),
                    INDEX idx_schedule_date (schedule_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            } else {
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
            }
            
            $this->db->exec($sql);
        } catch (Exception $e) {
            error_log("Failed to create campaigns table: " . $e->getMessage());
        }
    }
    
    /**
     * Ensure campaign_sends table exists
     */
    private function ensureCampaignSendsTable() {
        try {
            if ($this->dbType === 'mysql') {
                $sql = "CREATE TABLE IF NOT EXISTS campaign_sends (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    campaign_id INT NOT NULL,
                    recipient_id INT NOT NULL,
                    recipient_email VARCHAR(255) NOT NULL,
                    status VARCHAR(50) NOT NULL,
                    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    opened_at DATETIME NULL,
                    clicked_at DATETIME NULL,
                    INDEX idx_campaign_id (campaign_id),
                    INDEX idx_recipient_id (recipient_id),
                    INDEX idx_status (status),
                    INDEX idx_sent_at (sent_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            } else {
                $sql = "CREATE TABLE IF NOT EXISTS campaign_sends (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    campaign_id INTEGER NOT NULL,
                    recipient_id INTEGER NOT NULL,
                    recipient_email TEXT NOT NULL,
                    status TEXT NOT NULL,
                    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    opened_at DATETIME,
                    clicked_at DATETIME
                )";
            }
            
            $this->db->exec($sql);
        } catch (Exception $e) {
            error_log("Failed to create campaign_sends table: " . $e->getMessage());
        }
    }
}
?> 