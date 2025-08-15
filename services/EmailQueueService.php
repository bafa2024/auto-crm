<?php
/**
 * Email Queue Service
 * Manages email queue for reliable delivery
 */

class EmailQueueService {
    private $db;
    private $emailService;
    
    public function __construct($db) {
        $this->db = $db;
        require_once __DIR__ . '/EmailService.php';
        
        // Create database wrapper object for EmailService
        $dbWrapper = new stdClass();
        $dbWrapper->getConnection = function() use ($db) { return $db; };
        $this->emailService = new EmailService($dbWrapper);
    }
    
    /**
     * Add email to queue
     */
    public function queueEmail($recipient, $subject, $body, $options = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (
                    recipient_email, recipient_name, sender_email, sender_name,
                    subject, body, priority, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            
            $recipientEmail = is_array($recipient) ? $recipient['email'] : $recipient;
            $recipientName = is_array($recipient) ? ($recipient['name'] ?? null) : null;
            $senderEmail = $options['from_email'] ?? 'noreply@localhost';
            $senderName = $options['from_name'] ?? 'AutoDial Pro';
            $priority = $options['priority'] ?? 5;
            
            $stmt->execute([
                $recipientEmail,
                $recipientName,
                $senderEmail,
                $senderName,
                $subject,
                $body,
                $priority
            ]);
            
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            error_log("Failed to queue email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Queue multiple emails (bulk)
     */
    public function queueBulkEmails($recipients, $subject, $body, $options = []) {
        $queuedIds = [];
        
        foreach ($recipients as $recipient) {
            $id = $this->queueEmail($recipient, $subject, $body, $options);
            if ($id) {
                $queuedIds[] = $id;
            }
        }
        
        return $queuedIds;
    }
    
    /**
     * Process email queue
     */
    public function processQueue($limit = 10) {
        $results = [
            'processed' => 0,
            'sent' => 0,
            'failed' => 0,
            'details' => []
        ];
        
        try {
            // Get pending emails from queue
            $stmt = $this->db->prepare("
                SELECT * FROM email_queue 
                WHERE status IN ('pending', 'failed') 
                AND attempts < 3
                ORDER BY priority DESC, created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($emails as $email) {
                $this->processEmailFromQueue($email, $results);
                $results['processed']++;
                
                // Small delay between emails to avoid rate limiting
                usleep(100000); // 0.1 second
            }
            
        } catch (Exception $e) {
            error_log("Queue processing error: " . $e->getMessage());
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Process single email from queue
     */
    private function processEmailFromQueue($email, &$results) {
        try {
            // Update status to sending
            $this->updateQueueStatus($email['id'], 'sending');
            
            // Try to send email
            $sendResult = $this->emailService->sendInstantEmail([
                'to' => $email['recipient_email'],
                'subject' => $email['subject'],
                'message' => $email['body'],
                'from_name' => $email['sender_name'],
                'from_email' => $email['sender_email']
            ]);
            
            if ($sendResult === true || (is_array($sendResult) && $sendResult['success'])) {
                // Success
                $this->updateQueueStatus($email['id'], 'sent', null, true);
                $this->logEmailSent($email['id'], $email['recipient_email'], $email['subject'], 'sent');
                $results['sent']++;
                $results['details'][] = "✓ Sent to: " . $email['recipient_email'];
            } else {
                // Failed
                $errorMsg = is_array($sendResult) ? ($sendResult['error'] ?? 'Unknown error') : 'Send failed';
                $this->updateQueueStatus($email['id'], 'failed', $errorMsg);
                $this->logEmailSent($email['id'], $email['recipient_email'], $email['subject'], 'failed', $errorMsg);
                $results['failed']++;
                $results['details'][] = "✗ Failed: " . $email['recipient_email'] . " - " . $errorMsg;
            }
            
        } catch (Exception $e) {
            $errorMsg = $e->getMessage();
            $this->updateQueueStatus($email['id'], 'failed', $errorMsg);
            $this->logEmailSent($email['id'], $email['recipient_email'], $email['subject'], 'failed', $errorMsg);
            $results['failed']++;
            $results['details'][] = "✗ Error: " . $email['recipient_email'] . " - " . $errorMsg;
        }
    }
    
    /**
     * Update queue item status
     */
    private function updateQueueStatus($id, $status, $errorMessage = null, $sent = false) {
        try {
            if ($sent) {
                $stmt = $this->db->prepare("
                    UPDATE email_queue 
                    SET status = ?, last_attempt = NOW(), sent_at = NOW(), 
                        attempts = attempts + 1, error_message = ?
                    WHERE id = ?
                ");
            } else {
                $stmt = $this->db->prepare("
                    UPDATE email_queue 
                    SET status = ?, last_attempt = NOW(), 
                        attempts = attempts + 1, error_message = ?
                    WHERE id = ?
                ");
            }
            
            $stmt->execute([$status, $errorMessage, $id]);
        } catch (Exception $e) {
            error_log("Failed to update queue status: " . $e->getMessage());
        }
    }
    
    /**
     * Log email sent
     */
    private function logEmailSent($queueId, $recipient, $subject, $status, $response = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_logs (queue_id, recipient_email, subject, status, sent_via, response)
                VALUES (?, ?, ?, ?, 'queue', ?)
            ");
            $stmt->execute([$queueId, $recipient, $subject, $status, $response]);
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }
    
    /**
     * Get queue statistics
     */
    public function getQueueStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    status, 
                    COUNT(*) as count
                FROM email_queue
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY status
            ");
            $stmt->execute();
            
            $stats = [
                'pending' => 0,
                'sending' => 0,
                'sent' => 0,
                'failed' => 0
            ];
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $stats[$row['status']] = $row['count'];
            }
            
            return $stats;
        } catch (Exception $e) {
            error_log("Failed to get queue stats: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Retry failed emails
     */
    public function retryFailed() {
        try {
            $stmt = $this->db->prepare("
                UPDATE email_queue 
                SET status = 'pending', attempts = 0, error_message = NULL
                WHERE status = 'failed' AND attempts < 3
            ");
            $stmt->execute();
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to retry emails: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Clear old sent emails from queue
     */
    public function clearOldSentEmails($days = 7) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM email_queue 
                WHERE status = 'sent' 
                AND sent_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            
            return $stmt->rowCount();
        } catch (Exception $e) {
            error_log("Failed to clear old emails: " . $e->getMessage());
            return 0;
        }
    }
}
?>