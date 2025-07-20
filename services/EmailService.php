<?php
// For production, you would use PHPMailer
// composer require phpmailer/phpmailer

class EmailService {
    private $db;
    private $config;
    
    public function __construct($database) {
        $this->db = $database->getConnection();
        $this->loadConfig();
    }
    
    private function loadConfig() {
        // Load email configuration from database settings
        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
            $stmt->execute();
            $settings = $stmt->fetchAll();
            
            $this->config = [];
            foreach ($settings as $setting) {
                $this->config[$setting['setting_key']] = $setting['setting_value'];
            }
        } catch (Exception $e) {
            // If system_settings table doesn't exist, use default config
            $this->config = [
                'smtp_host' => 'localhost',
                'smtp_port' => '587',
                'smtp_username' => 'noreply@regrowup.ca',
                'smtp_password' => '',
                'smtp_encryption' => 'tls'
            ];
        }
    }
    
    public function queueCampaignEmails($campaignId) {
        try {
            // Get campaign details
            $campaignModel = new EmailCampaign($this->db);
            $campaign = $campaignModel->find($campaignId);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campaign not found'];
            }
            
            // Get recipients
            $recipients = $campaignModel->getRecipients($campaignId, 'pending');
            
            if (empty($recipients)) {
                return ['success' => false, 'message' => 'No recipients found'];
            }
            
            // Queue emails
            $queuedCount = 0;
            $stmt = $this->db->prepare("
                INSERT INTO email_queue 
                (campaign_id, contact_id, recipient_email, subject, content, sender_name, sender_email, reply_to_email, tracking_id, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            foreach ($recipients as $recipient) {
                $content = $this->parseTemplate($campaign['content'], [
                    'first_name' => $recipient['first_name'],
                    'last_name' => $recipient['last_name'],
                    'email' => $recipient['email'],
                    'company' => $recipient['company'],
                    'sender_name' => $campaign['sender_name']
                ]);
                
                $trackingId = $recipient['tracking_id'] ?? uniqid('track_');
                
                $stmt->execute([
                    $campaignId,
                    $recipient['contact_id'],
                    $recipient['email'],
                    $campaign['subject'],
                    $content,
                    $campaign['sender_name'],
                    $campaign['sender_email'],
                    $campaign['reply_to_email'],
                    $trackingId
                ]);
                
                $queuedCount++;
            }
            
            return [
                'success' => true,
                'queued' => $queuedCount,
                'message' => "{$queuedCount} emails queued for sending"
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to queue emails: ' . $e->getMessage()
            ];
        }
    }
    
    public function processEmailQueue($batchSize = 50) {
        try {
            // Get pending emails from queue
            $stmt = $this->db->prepare("
                SELECT * FROM email_queue 
                WHERE status = 'pending' 
                AND scheduled_at <= NOW() 
                AND attempts < max_attempts
                ORDER BY priority DESC, created_at ASC
                LIMIT ?
            ");
            $stmt->execute([$batchSize]);
            $emails = $stmt->fetchAll();
            
            $sent = 0;
            $failed = 0;
            
            foreach ($emails as $email) {
                if ($this->sendEmail($email)) {
                    $this->updateEmailStatus($email['id'], 'sent');
                    $this->updateRecipientStatus($email['campaign_id'], $email['contact_id'], 'sent');
                    $sent++;
                } else {
                    $this->updateEmailStatus($email['id'], 'failed', 'Failed to send email');
                    $failed++;
                }
            }
            
            return [
                'success' => true,
                'processed' => count($emails),
                'sent' => $sent,
                'failed' => $failed
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to process email queue: ' . $e->getMessage()
            ];
        }
    }
    
    private function sendEmail($emailData) {
        try {
            // In production, you would use PHPMailer or similar
            // For now, we'll simulate sending
            
            // Simulate sending delay
            usleep(100000); // 0.1 second delay
            
            // Add tracking pixel to content
            $trackingPixel = "<img src='" . APP_URL . "/api/track/open/{$emailData['tracking_id']}' width='1' height='1' style='display:none;' />";
            $content = $emailData['content'] . $trackingPixel;
            
            // In a real implementation:
            /*
            use PHPMailer\PHPMailer\PHPMailer;
            use PHPMailer\PHPMailer\SMTP;
            
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->config['smtp_host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp_username'];
            $mail->Password = $this->config['smtp_password'];
            $mail->SMTPSecure = $this->config['smtp_encryption'];
            $mail->Port = $this->config['smtp_port'];
            
            $mail->setFrom($emailData['sender_email'], $emailData['sender_name']);
            $mail->addAddress($emailData['recipient_email']);
            $mail->addReplyTo($emailData['reply_to_email']);
            
            $mail->isHTML(true);
            $mail->Subject = $emailData['subject'];
            $mail->Body = $content;
            
            return $mail->send();
            */
            
            // For demo purposes, simulate success rate
            return (rand(1, 100) <= 95); // 95% success rate
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function updateEmailStatus($emailId, $status, $errorMessage = null) {
        $sql = "UPDATE email_queue SET status = ?, updated_at = NOW()";
        $params = [$status];
        
        if ($status === 'sent') {
            $sql .= ", sent_at = NOW()";
        } elseif ($status === 'failed') {
            $sql .= ", attempts = attempts + 1";
            if ($errorMessage) {
                $sql .= ", error_message = ?";
                $params[] = $errorMessage;
            }
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $emailId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
    
    private function updateRecipientStatus($campaignId, $contactId, $status) {
        $sql = "UPDATE campaign_recipients SET status = ?, updated_at = NOW()";
        $params = [$status];
        
        if ($status === 'sent') {
            $sql .= ", sent_at = NOW()";
        } elseif ($status === 'opened') {
            $sql .= ", opened_at = NOW()";
        } elseif ($status === 'clicked') {
            $sql .= ", clicked_at = NOW()";
        }
        
        $sql .= " WHERE campaign_id = ? AND contact_id = ?";
        $params[] = $campaignId;
        $params[] = $contactId;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // Update campaign stats
        $this->updateCampaignStats($campaignId);
    }
    
    private function updateCampaignStats($campaignId) {
        $stmt = $this->db->prepare("
            UPDATE email_campaigns SET
                sent_count = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status IN ('sent', 'opened', 'clicked')),
                opened_count = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status IN ('opened', 'clicked')),
                clicked_count = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'clicked'),
                replied_count = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'replied'),
                bounced_count = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'bounced'),
                unsubscribed_count = (SELECT COUNT(*) FROM campaign_recipients WHERE campaign_id = ? AND status = 'unsubscribed'),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$campaignId, $campaignId, $campaignId, $campaignId, $campaignId, $campaignId, $campaignId]);
    }
    
    private function parseTemplate($content, $variables) {
        foreach ($variables as $key => $value) {
            $content = str_replace("{{" . $key . "}}", $value, $content);
        }
        return $content;
    }
    
    public function trackEmailOpen($trackingId) {
        $stmt = $this->db->prepare("
            SELECT cr.campaign_id, cr.contact_id 
            FROM campaign_recipients cr 
            WHERE cr.tracking_id = ? AND cr.status = 'sent'
        ");
        $stmt->execute([$trackingId]);
        $recipient = $stmt->fetch();
        
        if ($recipient) {
            $this->updateRecipientStatus($recipient['campaign_id'], $recipient['contact_id'], 'opened');
            return true;
        }
        
        return false;
    }
    
    public function trackEmailClick($trackingId, $url) {
        $stmt = $this->db->prepare("
            SELECT cr.campaign_id, cr.contact_id 
            FROM campaign_recipients cr 
            WHERE cr.tracking_id = ?
        ");
        $stmt->execute([$trackingId]);
        $recipient = $stmt->fetch();
        
        if ($recipient) {
            $this->updateRecipientStatus($recipient['campaign_id'], $recipient['contact_id'], 'clicked');
            
            // Log click
            $stmt = $this->db->prepare("
                INSERT INTO email_clicks (campaign_id, contact_id, tracking_id, url, clicked_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$recipient['campaign_id'], $recipient['contact_id'], $trackingId, $url]);
            
            return true;
        }
        
        return false;
    }
    
    public function unsubscribe($trackingId) {
        $stmt = $this->db->prepare("
            SELECT cr.campaign_id, cr.contact_id 
            FROM campaign_recipients cr 
            WHERE cr.tracking_id = ?
        ");
        $stmt->execute([$trackingId]);
        $recipient = $stmt->fetch();
        
        if ($recipient) {
            $this->updateRecipientStatus($recipient['campaign_id'], $recipient['contact_id'], 'unsubscribed');
            
            // Add to DNC list
            $stmt = $this->db->prepare("
                UPDATE contacts SET dnc_status = 1 WHERE id = ?
            ");
            $stmt->execute([$recipient['contact_id']]);
            
            return true;
        }
        
        return false;
    }
    
    public function sendTestEmail($campaignId, $testEmail) {
        try {
            $campaignModel = new EmailCampaign($this->db);
            $campaign = $campaignModel->find($campaignId);
            
            if (!$campaign) {
                return ['success' => false, 'message' => 'Campaign not found'];
            }
            
            $content = $this->parseTemplate($campaign['content'], [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => $testEmail,
                'company' => 'Test Company',
                'sender_name' => $campaign['sender_name']
            ]);
            
            $emailData = [
                'id' => 0,
                'campaign_id' => $campaignId,
                'contact_id' => 0,
                'recipient_email' => $testEmail,
                'subject' => '[TEST] ' . $campaign['subject'],
                'content' => $content,
                'sender_name' => $campaign['sender_name'],
                'sender_email' => $campaign['sender_email'],
                'reply_to_email' => $campaign['reply_to_email'],
                'tracking_id' => 'test_' . uniqid()
            ];
            
            if ($this->sendEmail($emailData)) {
                return ['success' => true, 'message' => 'Test email sent successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to send test email'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    public function getEmailStats($campaignId = null) {
        $whereClause = $campaignId ? "WHERE campaign_id = ?" : "";
        $params = $campaignId ? [$campaignId] : [];
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_emails,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_emails,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_emails,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_emails
            FROM email_queue
            {$whereClause}
        ");
        $stmt->execute($params);
        
        return $stmt->fetch();
    }
    
    /**
     * Simple email sending method for ScheduledCampaignService
     */
    public function sendSimpleEmail($to, $subject, $content, $senderName, $senderEmail) {
        try {
            // In production, you would use PHPMailer or similar
            // For now, we'll simulate sending
            
            // Simulate sending delay
            usleep(100000); // 0.1 second delay
            
            // Log the email send attempt
            error_log("Email sent to: $to, Subject: $subject, From: $senderName <$senderEmail>");
            
            // For demo purposes, simulate success rate
            $success = (rand(1, 100) <= 95); // 95% success rate
            
            if ($success) {
                error_log("Email sent successfully to: $to");
            } else {
                error_log("Email failed to send to: $to");
            }
            
            return $success;
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
} 