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
            // Apply merge tags if recipient data is provided
            if (!empty($options['merge_data'])) {
                $subject = $this->replaceMergeTags($subject, $options['merge_data']);
                $body = $this->replaceMergeTags($body, $options['merge_data']);
            }
            
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
     * Replace merge tags with actual values
     */
    private function replaceMergeTags($text, $data) {
        // Common merge tags
        $defaults = [
            'current_date' => date('F j, Y'),
            'current_year' => date('Y'),
            'current_month' => date('F'),
            'company_name' => 'ACRM'
        ];
        
        // Merge defaults with provided data
        $mergeData = array_merge($defaults, $data);
        
        // Replace all merge tags
        foreach ($mergeData as $key => $value) {
            // Handle both {{tag}} and {{ tag }} formats
            $patterns = [
                '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i',
                '/\[\[\s*' . preg_quote($key, '/') . '\s*\]\]/i'  // Alternative format
            ];
            
            foreach ($patterns as $pattern) {
                $text = preg_replace($pattern, $value ?? '', $text);
            }
        }
        
        return $text;
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
    
    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail($email, $resetUrl, $expiresAt) {
        $subject = "Password Reset Request - AutoDial Pro";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hello,</p>
                    <p>We received a request to reset your password for your AutoDial Pro account.</p>
                    
                    <p><strong>If you didn't request this password reset, please ignore this email.</strong></p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Reset Your Password</a>
                    </div>
                    
                    <div class='warning'>
                        <strong>⚠️ Security Notice:</strong>
                        <ul>
                            <li>This link will expire on: " . date('F j, Y \a\t g:i A', strtotime($expiresAt)) . "</li>
                            <li>If you don't reset your password within this time, you'll need to request a new reset link</li>
                            <li>This link can only be used once</li>
                        </ul>
                    </div>
                    
                    <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f1f1f1; padding: 10px; border-radius: 3px;'>{$resetUrl}</p>
                    
                    <p>Best regards,<br>The AutoDial Pro Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($email, $subject, $body, [
            'from_email' => $this->config['smtp']['from']['address'] ?? 'noreply@autodialpro.com',
            'from_name' => $this->config['smtp']['from']['name'] ?? 'AutoDial Pro'
        ]);
    }
    
    /**
     * Send login link email (for employee login)
     */
    public function sendLoginLink($email, $loginUrl, $userName) {
        $subject = "Login Link - AutoDial Pro";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Login Link</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔑 Login Link</h1>
                </div>
                <div class='content'>
                    <p>Hello {$userName},</p>
                    <p>You requested a login link for your AutoDial Pro employee account.</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$loginUrl}' class='button'>Login to AutoDial Pro</a>
                    </div>
                    
                    <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f1f1f1; padding: 10px; border-radius: 3px;'>{$loginUrl}</p>
                    
                    <p><strong>Note:</strong> This link is valid for a limited time and can only be used once.</p>
                    
                    <p>Best regards,<br>The AutoDial Pro Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->send($email, $subject, $body, [
            'from_email' => $this->config['smtp']['from']['address'] ?? 'noreply@autodialpro.com',
            'from_name' => $this->config['smtp']['from']['name'] ?? 'AutoDial Pro'
        ]);
    }
}