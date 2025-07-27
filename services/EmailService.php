<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $config;
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = include __DIR__ . '/../config/email.php';
        
        // Check if PHPMailer is available, if not use built-in mail()
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->config['driver'] = 'mail';
        }
    }
    
    /**
     * Send a single email
     */
    public function send($to, $subject, $body, $options = []) {
        try {
            if ($this->config['driver'] === 'smtp' && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendViaSMTP($to, $subject, $body, $options);
            } else {
                return $this->sendViaMail($to, $subject, $body, $options);
            }
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Send email via PHPMailer SMTP
     */
    private function sendViaSMTP($to, $subject, $body, $options = []) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];
            
            // Recipients
            $mail->setFrom(
                $options['from_email'] ?? $this->config['smtp']['from']['address'],
                $options['from_name'] ?? $this->config['smtp']['from']['name']
            );
            
            if (is_array($to)) {
                $mail->addAddress($to['email'], $to['name'] ?? '');
            } else {
                $mail->addAddress($to);
            }
            
            if (!empty($options['reply_to'])) {
                $mail->addReplyTo($options['reply_to']);
            }
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            
            // Add tracking pixel if enabled
            if ($this->config['track_opens'] && !empty($options['tracking_id'])) {
                $body = $this->addTrackingPixel($body, $options['tracking_id']);
            }
            
            // Replace click links with tracking links
            if ($this->config['track_clicks'] && !empty($options['tracking_id'])) {
                $body = $this->replaceLinksWithTracking($body, $options['tracking_id']);
            }
            
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            // Add unsubscribe header
            if (!empty($options['unsubscribe_url'])) {
                $mail->addCustomHeader('List-Unsubscribe', '<' . $options['unsubscribe_url'] . '>');
            }
            
            $mail->send();
            return ['success' => true];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $mail->ErrorInfo];
        }
    }
    
    /**
     * Send email via PHP mail() function
     */
    private function sendViaMail($to, $subject, $body, $options = []) {
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/html; charset=UTF-8';
        
        $from_email = $options['from_email'] ?? $this->config['smtp']['from']['address'];
        $from_name = $options['from_name'] ?? $this->config['smtp']['from']['name'];
        $headers[] = "From: $from_name <$from_email>";
        
        if (!empty($options['reply_to'])) {
            $headers[] = "Reply-To: " . $options['reply_to'];
        }
        
        if (!empty($options['unsubscribe_url'])) {
            $headers[] = "List-Unsubscribe: <" . $options['unsubscribe_url'] . ">";
        }
        
        // Add tracking if enabled
        if ($this->config['track_opens'] && !empty($options['tracking_id'])) {
            $body = $this->addTrackingPixel($body, $options['tracking_id']);
        }
        
        if ($this->config['track_clicks'] && !empty($options['tracking_id'])) {
            $body = $this->replaceLinksWithTracking($body, $options['tracking_id']);
        }
        
        $to_email = is_array($to) ? $to['email'] : $to;
        
        if (mail($to_email, $subject, $body, implode("\r\n", $headers))) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Failed to send email'];
        }
    }
    
    /**
     * Add tracking pixel to email body
     */
    private function addTrackingPixel($body, $trackingId) {
        $pixelUrl = $this->config['tracking_domain'] . "/api/email/track/open/" . $trackingId;
        $pixel = '<img src="' . $pixelUrl . '" width="1" height="1" style="display:none;" />';
        
        // Add before closing body tag
        if (stripos($body, '</body>') !== false) {
            $body = str_ireplace('</body>', $pixel . '</body>', $body);
        } else {
            $body .= $pixel;
        }
        
        return $body;
    }
    
    /**
     * Replace links with tracking links
     */
    private function replaceLinksWithTracking($body, $trackingId) {
        $pattern = '/<a\s+(?:[^>]*?\s+)?href=(["\'])(.*?)\1/i';
        
        return preg_replace_callback($pattern, function($matches) use ($trackingId) {
            $url = $matches[2];
            
            // Skip if already a tracking link or mailto/tel
            if (strpos($url, '/api/email/track/click/') !== false || 
                strpos($url, 'mailto:') === 0 || 
                strpos($url, 'tel:') === 0) {
                return $matches[0];
            }
            
            $trackUrl = $this->config['tracking_domain'] . "/api/email/track/click/" . $trackingId . "?url=" . urlencode($url);
            return str_replace($matches[2], $trackUrl, $matches[0]);
        }, $body);
    }
    
    /**
     * Process campaign and send emails
     */
    public function processCampaign($campaignId) {
        // Get campaign details
        $stmt = $this->db->prepare("
            SELECT * FROM email_campaigns WHERE id = ? AND status = 'active'
        ");
        $stmt->execute([$campaignId]);
        $campaign = $stmt->fetch();
        
        if (!$campaign) {
            return ['success' => false, 'error' => 'Campaign not found or not active'];
        }
        
        // Update campaign status to sending
        $this->db->prepare("UPDATE email_campaigns SET status = 'sending' WHERE id = ?")
                 ->execute([$campaignId]);
        
        // Get pending recipients
        $stmt = $this->db->prepare("
            SELECT er.*, c.first_name, c.last_name, c.email as contact_email, c.company, c.phone
            FROM email_recipients er
            LEFT JOIN contacts c ON er.contact_id = c.id
            WHERE er.campaign_id = ? AND er.status = 'pending'
            LIMIT ?
        ");
        
        $totalSent = 0;
        $totalFailed = 0;
        
        do {
            $stmt->execute([$campaignId, $this->config['batch_size']]);
            $recipients = $stmt->fetchAll();
            
            if (empty($recipients)) {
                break;
            }
            
            foreach ($recipients as $recipient) {
                // Prepare email content with variable replacement
                $emailContent = $this->replaceVariables($campaign['content'], $recipient);
                $subject = $this->replaceVariables($campaign['subject'], $recipient);
                
                // Prepare recipient info
                $to = [
                    'email' => $recipient['email'] ?? $recipient['contact_email'],
                    'name' => trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''))
                ];
                
                // Generate unsubscribe URL
                $unsubscribeToken = $this->generateUnsubscribeToken($recipient['email'], $campaignId);
                $unsubscribeUrl = $this->config['tracking_domain'] . "/unsubscribe/" . $unsubscribeToken;
                
                // Send email
                $result = $this->send($to, $subject, $emailContent, [
                    'from_email' => $campaign['sender_email'] ?? $campaign['from_email'],
                    'from_name' => $campaign['sender_name'] ?? $campaign['from_name'],
                    'reply_to' => $campaign['reply_to_email'] ?? null,
                    'tracking_id' => $recipient['tracking_id'],
                    'unsubscribe_url' => $unsubscribeUrl
                ]);
                
                // Update recipient status
                if ($result['success']) {
                    $this->db->prepare("
                        UPDATE email_recipients 
                        SET status = 'sent', sent_at = NOW() 
                        WHERE id = ?
                    ")->execute([$recipient['id']]);
                    $totalSent++;
                } else {
                    $this->db->prepare("
                        UPDATE email_recipients 
                        SET status = 'failed', error_message = ? 
                        WHERE id = ?
                    ")->execute([$result['error'] ?? 'Unknown error', $recipient['id']]);
                    $totalFailed++;
                }
                
                // Delay between emails
                if ($this->config['delay_between_emails'] > 0) {
                    sleep($this->config['delay_between_emails']);
                }
            }
            
            // Delay between batches
            if (count($recipients) == $this->config['batch_size'] && $this->config['delay_between_batches'] > 0) {
                sleep($this->config['delay_between_batches']);
            }
            
        } while (!empty($recipients));
        
        // Update campaign statistics
        $this->updateCampaignStats($campaignId);
        
        // Update campaign status
        $status = ($totalFailed == 0 && $totalSent > 0) ? 'completed' : 'completed_with_errors';
        $this->db->prepare("UPDATE email_campaigns SET status = ? WHERE id = ?")
                 ->execute([$status, $campaignId]);
        
        return [
            'success' => true,
            'sent' => $totalSent,
            'failed' => $totalFailed
        ];
    }
    
    /**
     * Replace variables in content
     */
    private function replaceVariables($content, $recipient) {
        $variables = [
            '{{first_name}}' => $recipient['first_name'] ?? '',
            '{{last_name}}' => $recipient['last_name'] ?? '',
            '{{email}}' => $recipient['email'] ?? $recipient['contact_email'] ?? '',
            '{{company}}' => $recipient['company'] ?? '',
            '{{phone}}' => $recipient['phone'] ?? '',
            '{{full_name}}' => trim(($recipient['first_name'] ?? '') . ' ' . ($recipient['last_name'] ?? ''))
        ];
        
        return str_replace(array_keys($variables), array_values($variables), $content);
    }
    
    /**
     * Generate unsubscribe token
     */
    private function generateUnsubscribeToken($email, $campaignId) {
        return base64_encode($email . '|' . $campaignId . '|' . time());
    }
    
    /**
     * Update campaign statistics
     */
    private function updateCampaignStats($campaignId) {
        $stmt = $this->db->prepare("
            UPDATE email_campaigns 
            SET 
                sent_count = (SELECT COUNT(*) FROM email_recipients WHERE campaign_id = ? AND status = 'sent'),
                total_recipients = (SELECT COUNT(*) FROM email_recipients WHERE campaign_id = ?)
            WHERE id = ?
        ");
        $stmt->execute([$campaignId, $campaignId, $campaignId]);
    }
}