<?php
/**
 * Simple Campaign Scheduler Service
 * A clean, PHP-focused approach to campaign scheduling
 */
class SimpleCampaignScheduler {
    private $db;
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
        $this->db = $database->getConnection();
        $this->initializeTables();
    }
    
    /**
     * Initialize required tables for scheduling
     */
    private function initializeTables() {
        try {
            // Create scheduled_campaigns table if it doesn't exist
            $sql = "CREATE TABLE IF NOT EXISTS scheduled_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id INT NOT NULL,
                schedule_type ENUM('immediate', 'scheduled', 'recurring') DEFAULT 'immediate',
                schedule_date DATETIME NULL,
                frequency ENUM('once', 'daily', 'weekly', 'monthly') DEFAULT 'once',
                recipient_ids TEXT,
                status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_sent_at DATETIME NULL,
                next_send_at DATETIME NULL,
                sent_count INT DEFAULT 0,
                failed_count INT DEFAULT 0,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE
            )";
            
            $this->db->exec($sql);
            
            // Create schedule_log table for tracking
            $logSql = "CREATE TABLE IF NOT EXISTS schedule_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                scheduled_campaign_id INT NOT NULL,
                action ENUM('created', 'sent', 'failed', 'cancelled') NOT NULL,
                message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (scheduled_campaign_id) REFERENCES scheduled_campaigns(id) ON DELETE CASCADE
            )";
            
            $this->db->exec($logSql);
            
        } catch (Exception $e) {
            error_log("Failed to initialize scheduler tables: " . $e->getMessage());
        }
    }
    
    /**
     * Schedule a campaign for sending
     */
    public function scheduleCampaign($campaignId, $scheduleData) {
        try {
            // Validate campaign exists
            $campaignStmt = $this->db->prepare("SELECT id, name, status FROM email_campaigns WHERE id = ?");
            $campaignStmt->execute([$campaignId]);
            $campaign = $campaignStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campaign not found'];
            }
            
            // Prepare schedule data
            $scheduleType = $scheduleData['schedule_type'] ?? 'immediate';
            $scheduleDate = null;
            $nextSendAt = null;
            $frequency = $scheduleData['frequency'] ?? 'once';
            
            // Set schedule date and next send time
            if ($scheduleType === 'scheduled' || $scheduleType === 'recurring') {
                $scheduleDate = $scheduleData['schedule_date'] ?? null;
                if (!$scheduleDate) {
                    return ['success' => false, 'message' => 'Schedule date is required'];
                }
                
                // Log timezone information for debugging
                $clientTimezone = $scheduleData['client_timezone'] ?? 'unknown';
                $clientOffset = $scheduleData['client_offset'] ?? 'unknown';
                error_log("Schedule request - Client timezone: $clientTimezone, Client offset: $clientOffset, Provided date: $scheduleDate");
                
                // Validate and parse the date properly with timezone handling
                $currentTimestamp = time();
                $buffer = 60; // 1 minute buffer for processing time
                
                // Try to parse the date using multiple formats and handle timezone issues
                $parsedDate = null;
                $scheduleTimestamp = false;
                
                // First try datetime-local format (Y-m-d\TH:i) - assume it's in user's local timezone
                $parsedDate = DateTime::createFromFormat('Y-m-d\TH:i', $scheduleDate);
                if (!$parsedDate) {
                    // Try standard datetime format (Y-m-d H:i:s)
                    $parsedDate = DateTime::createFromFormat('Y-m-d H:i:s', $scheduleDate);
                }
                if (!$parsedDate) {
                    // Try with DateTime constructor (handles various formats)
                    try {
                        $parsedDate = new DateTime($scheduleDate);
                    } catch (Exception $e) {
                        return ['success' => false, 'message' => 'Invalid date format. Please use YYYY-MM-DDTHH:MM or YYYY-MM-DD HH:MM:SS format. Received: ' . $scheduleDate];
                    }
                }
                
                // Get timestamp from parsed date
                if ($parsedDate) {
                    $scheduleTimestamp = $parsedDate->getTimestamp();
                    
                    // Check if the date seems to be in the past due to timezone issues
                    $timeDiff = $scheduleTimestamp - $currentTimestamp;
                    
                    // If the difference is between -12 hours and +12 hours, it might be a timezone issue
                    if ($timeDiff < -43200 && $timeDiff > -43200) { // -12 to +12 hours
                        // Try interpreting the date as UTC and convert to server timezone
                        $utcDate = DateTime::createFromFormat('Y-m-d\TH:i', $scheduleDate, new DateTimeZone('UTC'));
                        if (!$utcDate) {
                            $utcDate = DateTime::createFromFormat('Y-m-d H:i:s', $scheduleDate, new DateTimeZone('UTC'));
                        }
                        
                        if ($utcDate) {
                            // Convert to server timezone
                            $utcDate->setTimezone(new DateTimeZone(date_default_timezone_get()));
                            $altTimestamp = $utcDate->getTimestamp();
                            
                            // If this makes the date a reasonable future time, use it
                            if ($altTimestamp > ($currentTimestamp + $buffer) && $altTimestamp < ($currentTimestamp + 86400 * 365)) {
                                $scheduleTimestamp = $altTimestamp;
                                $parsedDate = $utcDate;
                            }
                        }
                    }
                }
                
                // Final validation - check if date is in the future
                if ($scheduleTimestamp <= ($currentTimestamp + $buffer)) {
                    $currentDateTime = date('Y-m-d H:i:s', $currentTimestamp);
                    $providedDateTime = date('Y-m-d H:i:s', $scheduleTimestamp);
                    $diffSeconds = $scheduleTimestamp - $currentTimestamp;
                    $diffHours = round($diffSeconds / 3600, 1);
                    
                    // Provide helpful error message based on the time difference
                    if ($diffSeconds < -3600) {
                        // Large negative difference - likely timezone confusion
                        $clientTz = $scheduleData['client_timezone'] ?? 'unknown';
                        return ['success' => false, 'message' => "The selected date appears to be in the past. Server time (Europe/Berlin): $currentDateTime, Selected time when converted: $providedDateTime (difference: {$diffHours} hours). Your timezone: $clientTz. Please select a date and time that will be in the future in your local timezone."];
                    } else {
                        // Small difference - just need to select a bit further in the future
                        return ['success' => false, 'message' => "Schedule date must be at least 1 minute in the future. Current server time: $currentDateTime, Provided: $providedDateTime (difference: {$diffSeconds} seconds, minimum required: {$buffer} seconds). Please select a time at least 2-3 minutes from now."];
                    }
                }
                
                $nextSendAt = $scheduleDate;
            } else {
                // Immediate sending - set to now
                $nextSendAt = date('Y-m-d H:i:s');
            }
            
            // Convert recipient IDs to JSON
            $recipientIds = isset($scheduleData['recipient_ids']) ? 
                json_encode($scheduleData['recipient_ids']) : '[]';
            
            // Insert scheduled campaign
            $insertSql = "INSERT INTO scheduled_campaigns 
                (campaign_id, schedule_type, schedule_date, frequency, recipient_ids, next_send_at, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            
            $insertStmt = $this->db->prepare($insertSql);
            $insertStmt->execute([
                $campaignId,
                $scheduleType,
                $scheduleDate,
                $frequency,
                $recipientIds,
                $nextSendAt
            ]);
            
            $scheduledId = $this->db->lastInsertId();
            
            // Log the scheduling
            $this->logScheduleAction($scheduledId, 'created', "Campaign scheduled for: " . ($scheduleDate ?? 'immediate'));
            
            // If immediate, process it right away
            if ($scheduleType === 'immediate') {
                $result = $this->processPendingCampaign($scheduledId);
                if ($result['success']) {
                    return [
                        'success' => true, 
                        'message' => 'Campaign sent immediately',
                        'scheduled_id' => $scheduledId,
                        'sent_count' => $result['sent_count'] ?? 0
                    ];
                } else {
                    return [
                        'success' => false, 
                        'message' => 'Failed to send campaign: ' . $result['message'],
                        'scheduled_id' => $scheduledId
                    ];
                }
            }
            
            return [
                'success' => true, 
                'message' => 'Campaign scheduled successfully',
                'scheduled_id' => $scheduledId,
                'next_send_at' => $nextSendAt
            ];
            
        } catch (Exception $e) {
            error_log("Schedule campaign error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Process a pending scheduled campaign
     */
    public function processPendingCampaign($scheduledId) {
        try {
            // Get scheduled campaign details
            $stmt = $this->db->prepare("
                SELECT sc.*, ec.name, ec.subject, ec.email_content, ec.from_name, ec.from_email
                FROM scheduled_campaigns sc
                JOIN email_campaigns ec ON sc.campaign_id = ec.id
                WHERE sc.id = ? AND sc.status = 'pending'
            ");
            $stmt->execute([$scheduledId]);
            $scheduled = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$scheduled) {
                return ['success' => false, 'message' => 'Scheduled campaign not found or already processed'];
            }
            
            // Update status to running
            $this->updateScheduledStatus($scheduledId, 'running');
            
            // Get recipient IDs
            $recipientIds = json_decode($scheduled['recipient_ids'], true) ?? [];
            
            if (empty($recipientIds)) {
                $this->updateScheduledStatus($scheduledId, 'failed');
                return ['success' => false, 'message' => 'No recipients selected'];
            }
            
            // Get recipient details
            $placeholders = str_repeat('?,', count($recipientIds) - 1) . '?';
            $recipientStmt = $this->db->prepare("
                SELECT id, email, name, company 
                FROM email_recipients 
                WHERE id IN ($placeholders)
            ");
            $recipientStmt->execute($recipientIds);
            $recipients = $recipientStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($recipients)) {
                $this->updateScheduledStatus($scheduledId, 'failed');
                return ['success' => false, 'message' => 'No valid recipients found'];
            }
            
            // Send emails
            $sentCount = 0;
            $failedCount = 0;
            
            foreach ($recipients as $recipient) {
                $emailResult = $this->sendSingleEmail($scheduled, $recipient);
                if ($emailResult['success']) {
                    $sentCount++;
                    // Record successful send
                    $this->recordCampaignSend($scheduled['campaign_id'], $recipient['id'], 'sent');
                } else {
                    $failedCount++;
                    // Record failed send
                    $this->recordCampaignSend($scheduled['campaign_id'], $recipient['id'], 'failed');
                }
            }
            
            // Update scheduled campaign with results
            $updateSql = "UPDATE scheduled_campaigns 
                SET sent_count = sent_count + ?, 
                    failed_count = failed_count + ?,
                    last_sent_at = NOW(),
                    status = ?,
                    next_send_at = ?
                WHERE id = ?";
            
            $newStatus = ($sentCount > 0) ? 'completed' : 'failed';
            $nextSendAt = $this->calculateNextSendTime($scheduled);
            
            // If recurring and successful, set next send time and status to pending
            if ($scheduled['schedule_type'] === 'recurring' && $sentCount > 0 && $nextSendAt) {
                $newStatus = 'pending';
            }
            
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                $sentCount, 
                $failedCount, 
                $newStatus, 
                $nextSendAt, 
                $scheduledId
            ]);
            
            // Log the result
            $message = "Sent: $sentCount, Failed: $failedCount";
            $this->logScheduleAction($scheduledId, 'sent', $message);
            
            return [
                'success' => true,
                'message' => $message,
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'next_send_at' => $nextSendAt
            ];
            
        } catch (Exception $e) {
            error_log("Process campaign error: " . $e->getMessage());
            $this->updateScheduledStatus($scheduledId, 'failed');
            return ['success' => false, 'message' => 'Processing error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Send a single email
     */
    private function sendSingleEmail($campaign, $recipient) {
        try {
            // Replace merge tags in subject and content
            $subject = $this->replaceMergeTags($campaign['subject'], $recipient);
            $content = $this->replaceMergeTags($campaign['email_content'], $recipient);
            
            // Check if we're in local development environment
            if (strpos($_SERVER['HTTP_HOST'] ?? 'localhost', 'localhost') !== false) {
                // Local development - log emails instead of sending
                $logFile = 'logs/email_log.txt';
                $logDir = dirname($logFile);
                if (!is_dir($logDir)) {
                    mkdir($logDir, 0755, true);
                }
                
                $logEntry = "=== Email Log Entry ===\n";
                $logEntry .= "Date: " . date('Y-m-d H:i:s') . "\n";
                $logEntry .= "To: " . $recipient['email'] . "\n";
                $logEntry .= "From: " . $campaign['from_name'] . " <" . $campaign['from_email'] . ">\n";
                $logEntry .= "Subject: " . $subject . "\n";
                $logEntry .= "Content: " . substr(strip_tags($content), 0, 200) . "...\n";
                $logEntry .= "========================\n\n";
                
                file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
                
                return ['success' => true, 'message' => 'Email logged for local development'];
            } else {
                // Production environment - attempt to send real email
                $headers = [
                    'MIME-Version: 1.0',
                    'Content-type: text/html; charset=UTF-8',
                    'From: ' . $campaign['from_name'] . ' <' . $campaign['from_email'] . '>',
                    'Reply-To: ' . $campaign['from_email'],
                    'X-Mailer: ACRM Simple Scheduler'
                ];
                
                // Send email using PHP mail() function
                $result = mail(
                    $recipient['email'],
                    $subject,
                    $content,
                    implode("\r\n", $headers)
                );
                
                if ($result) {
                    return ['success' => true, 'message' => 'Email sent successfully'];
                } else {
                    return ['success' => false, 'message' => 'Failed to send email'];
                }
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Email error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Replace merge tags in text
     */
    private function replaceMergeTags($text, $recipient) {
        $tags = [
            '{{name}}' => $recipient['name'] ?? '',
            '{{email}}' => $recipient['email'] ?? '',
            '{{company}}' => $recipient['company'] ?? '',
            '{{first_name}}' => explode(' ', $recipient['name'] ?? '')[0] ?? '',
            '{{last_name}}' => trim(str_replace(explode(' ', $recipient['name'] ?? '')[0] ?? '', '', $recipient['name'] ?? '')),
            '{{company_name}}' => $recipient['company'] ?? ''
        ];
        
        return str_ireplace(array_keys($tags), array_values($tags), $text);
    }
    
    /**
     * Calculate next send time for recurring campaigns
     */
    private function calculateNextSendTime($scheduled) {
        if ($scheduled['schedule_type'] !== 'recurring') {
            return null;
        }
        
        $currentTime = strtotime($scheduled['schedule_date']);
        
        switch ($scheduled['frequency']) {
            case 'daily':
                return date('Y-m-d H:i:s', strtotime('+1 day', $currentTime));
            case 'weekly':
                return date('Y-m-d H:i:s', strtotime('+1 week', $currentTime));
            case 'monthly':
                return date('Y-m-d H:i:s', strtotime('+1 month', $currentTime));
            default:
                return null;
        }
    }
    
    /**
     * Record a campaign send
     */
    private function recordCampaignSend($campaignId, $recipientId, $status) {
        try {
            $sql = "INSERT INTO campaign_sends (campaign_id, recipient_id, status, sent_at) 
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE status = ?, sent_at = NOW()";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$campaignId, $recipientId, $status, $status]);
        } catch (Exception $e) {
            error_log("Failed to record campaign send: " . $e->getMessage());
        }
    }
    
    /**
     * Update scheduled campaign status
     */
    private function updateScheduledStatus($scheduledId, $status) {
        $stmt = $this->db->prepare("UPDATE scheduled_campaigns SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$status, $scheduledId]);
    }
    
    /**
     * Log schedule action
     */
    private function logScheduleAction($scheduledId, $action, $message = '') {
        try {
            $stmt = $this->db->prepare("INSERT INTO schedule_log (scheduled_campaign_id, action, message) VALUES (?, ?, ?)");
            $stmt->execute([$scheduledId, $action, $message]);
        } catch (Exception $e) {
            error_log("Failed to log schedule action: " . $e->getMessage());
        }
    }
    
    /**
     * Process all pending scheduled campaigns
     */
    public function processPendingCampaigns() {
        try {
            $stmt = $this->db->prepare("
                SELECT id FROM scheduled_campaigns 
                WHERE status = 'pending' 
                AND next_send_at <= NOW()
                ORDER BY next_send_at ASC
            ");
            $stmt->execute();
            $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            foreach ($pending as $campaign) {
                $results[] = $this->processPendingCampaign($campaign['id']);
            }
            
            return [
                'success' => true,
                'processed' => count($results),
                'results' => $results
            ];
            
        } catch (Exception $e) {
            error_log("Process pending campaigns error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get scheduled campaigns list
     */
    public function getScheduledCampaigns($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT sc.*, ec.name as campaign_name, ec.subject
                FROM scheduled_campaigns sc
                JOIN email_campaigns ec ON sc.campaign_id = ec.id
                ORDER BY sc.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get scheduled campaigns error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Cancel a scheduled campaign
     */
    public function cancelScheduledCampaign($scheduledId) {
        try {
            $stmt = $this->db->prepare("UPDATE scheduled_campaigns SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
            $result = $stmt->execute([$scheduledId]);
            
            if ($stmt->rowCount() > 0) {
                $this->logScheduleAction($scheduledId, 'cancelled', 'Campaign cancelled by user');
                return ['success' => true, 'message' => 'Campaign cancelled successfully'];
            } else {
                return ['success' => false, 'message' => 'Campaign not found or cannot be cancelled'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error cancelling campaign: ' . $e->getMessage()];
        }
    }
}
?>
