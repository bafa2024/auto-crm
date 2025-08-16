<?php
// Try to ensure Composer autoload is loaded so PHPMailer is available
if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $config;
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        $this->config = include __DIR__ . '/../config/email.php';
        
        // Load SMTP settings from database if available
        $this->loadSmtpSettingsFromDatabase();
        
        // Check if PHPMailer is available, if not use built-in mail()
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $this->config['driver'] = 'mail';
        }
    }
    
    /**
     * Load SMTP settings from database
     */
    private function loadSmtpSettingsFromDatabase() {
        try {
            // Get database connection
            $conn = null;
            if (is_object($this->db) && method_exists($this->db, 'getConnection')) {
                $conn = $this->db->getConnection();
            } elseif ($this->db instanceof PDO) {
                $conn = $this->db;
            }
            
            if (!$conn) {
                return;
            }
            
            // Check if smtp_settings table exists
            $stmt = $conn->prepare("SHOW TABLES LIKE 'smtp_settings'");
            $stmt->execute();
            if (!$stmt->fetch()) {
                return;
            }
            
            // Load settings from database
            $stmt = $conn->prepare("SELECT setting_key, setting_value FROM smtp_settings");
            $stmt->execute();
            
            $dbSettings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $dbSettings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Update config with database settings if SMTP is enabled
            if (!empty($dbSettings['smtp_enabled']) && $dbSettings['smtp_enabled'] == '1') {
                $this->config['driver'] = 'smtp';
                $this->config['smtp']['host'] = $dbSettings['smtp_host'] ?? $this->config['smtp']['host'];
                $this->config['smtp']['port'] = $dbSettings['smtp_port'] ?? $this->config['smtp']['port'];
                $this->config['smtp']['username'] = $dbSettings['smtp_username'] ?? $this->config['smtp']['username'];
                $this->config['smtp']['password'] = $dbSettings['smtp_password'] ?? $this->config['smtp']['password'];
                $this->config['smtp']['encryption'] = $dbSettings['smtp_encryption'] ?? $this->config['smtp']['encryption'];
                $this->config['smtp']['from']['address'] = $dbSettings['smtp_from_email'] ?? $this->config['smtp']['from']['address'];
                $this->config['smtp']['from']['name'] = $dbSettings['smtp_from_name'] ?? $this->config['smtp']['from']['name'];
                
                // Also update main from config for fallback
                $this->config['from']['address'] = $dbSettings['smtp_from_email'] ?? $this->config['smtp']['from']['address'];
                $this->config['from']['name'] = $dbSettings['smtp_from_name'] ?? $this->config['smtp']['from']['name'];
            }
        } catch (Exception $e) {
            // Silently fail and use config file settings
            error_log("Failed to load SMTP settings from database: " . $e->getMessage());
        }
    }
    
    /**
     * Send a single email using mail() function
     */
    public function send($to, $subject, $body, $options = []) {
        try {
            // Apply merge tags if recipient data is provided
            if (!empty($options['merge_data'])) {
                $subject = $this->replaceMergeTags($subject, $options['merge_data']);
                $body = $this->replaceMergeTags($body, $options['merge_data']);
            }
            
            // Check if test mode is enabled (disabled by default for production)
            if ($this->config['test_mode'] === true) {
                return $this->sendViaTestMode($to, $subject, $body, $options);
            }
            
            // Always use PHP mail() function
            return $this->sendViaMail($to, $subject, $body, $options);
            
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
     * Send email via test mode (simulates sending)
     */
    private function sendViaTestMode($to, $subject, $body, $options = []) {
        $to_email = is_array($to) ? $to['email'] : $to;
        $from_email = $options['from_email'] ?? $this->config['smtp']['from']['address'];
        $from_name = $options['from_name'] ?? $this->config['smtp']['from']['name'];
        
        // Log the email details
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to' => $to_email,
            'from' => "$from_name <$from_email>",
            'subject' => $subject,
            'body_length' => strlen($body),
            'options' => $options
        ];
        
        // Create logs directory if it doesn't exist
        $logDir = dirname($this->config['test_mode_log_path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        // Write to log file
        $logLine = date('Y-m-d H:i:s') . " - TO: $to_email - SUBJECT: $subject - FROM: $from_name <$from_email> - CAMPAIGN SEND\n";
        file_put_contents($this->config['test_mode_log_path'], $logLine, FILE_APPEND | LOCK_EX);
        
        // Log to error log as well
        error_log("TEST MODE CAMPAIGN: Email would be sent to: $to_email with subject: $subject");
        
        // Simulate successful sending
        return ['success' => true, 'test_mode' => true];
    }
    
    /**
     * Send email via PHP mail() function
     */
    private function sendViaMail($to, $subject, $body, $options = []) {
        // Determine the best from address based on environment
        $defaultFromAddress = 'noreply@localhost';
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
            $domain = str_replace('www.', '', $_SERVER['HTTP_HOST']);
            $defaultFromAddress = 'noreply@' . $domain;
        }
        
        $from_email = $options['from_email'] ?? $this->config['from']['address'] ?? $defaultFromAddress;
        $from_name = $options['from_name'] ?? $this->config['from']['name'] ?? 'AutoDial Pro';
        
        // Build headers
        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-type: text/plain; charset=UTF-8'; // Use plain text for better deliverability
        $headers[] = 'From: ' . $from_email; // Simple from header
        $headers[] = 'Reply-To: ' . $from_email;
        $headers[] = 'X-Mailer: PHP/' . phpversion();
        $headers[] = 'Return-Path: ' . $from_email;
        
        if (!empty($options['reply_to'])) {
            $headers[] = "Reply-To: " . $options['reply_to'];
        }
        
        if (!empty($options['unsubscribe_url'])) {
            $headers[] = "List-Unsubscribe: <" . $options['unsubscribe_url'] . ">";
        }
        
        // Convert body to plain text
        $plainBody = strip_tags($body);
        
        $to_email = is_array($to) ? $to['email'] : $to;
        
        // Additional parameters for mail() function (Linux/Unix only)
        $additionalParams = '';
        if (PHP_OS_FAMILY !== 'Windows') {
            $additionalParams = '-f' . $from_email;
        }
        
        // Log the email attempt
        error_log("Attempting to send email via mail() to: $to_email, subject: $subject");
        
        // Send the email
        $result = @mail($to_email, $subject, $plainBody, implode("\r\n", $headers), $additionalParams);
        
        if ($result) {
            error_log("Email sent successfully via mail() to: $to_email");
            return ['success' => true];
        } else {
            $lastError = error_get_last();
            $errorMsg = $lastError ? $lastError['message'] : 'Unknown error';
            error_log("Failed to send email via mail() to: $to_email - Error: " . $errorMsg);
            return ['success' => false, 'error' => 'Failed to send email: ' . $errorMsg];
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
    
    /**
     * Send employee password reset email
     */
    public function sendEmployeePasswordResetEmail($email, $resetUrl, $expiresAt, $userName) {
        $subject = "Employee Password Reset - AutoDial Pro";
        
        $body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Employee Password Reset</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #6c757d; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                .button { display: inline-block; background: #6c757d; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Employee Password Reset</h1>
                </div>
                <div class='content'>
                    <p>Hello {$userName},</p>
                    <p>You requested a password reset for your AutoDial Pro employee account.</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Reset Your Password</a>
                    </div>
                    
                    <p>If the button above doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; background: #f1f1f1; padding: 10px; border-radius: 3px;'>{$resetUrl}</p>
                    
                    <div class='warning'>
                        <strong>⚠️ Important:</strong>
                        <ul>
                            <li>This link will expire on: {$expiresAt}</li>
                            <li>This link can only be used once</li>
                            <li>If you didn't request this reset, please ignore this email</li>
                        </ul>
                    </div>
                    
                    <p>For security reasons, this link will expire in 1 hour.</p>
                    
                    <p>Best regards,<br>The AutoDial Pro Team</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact your system administrator.</p>
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
     * Send instant email using mail() function
     */
    public function sendInstantEmail($data) {
        try {
            $to = $data['to'];
            $subject = $data['subject'];
            $message = $data['message'];
            $cc = $data['cc'] ?? [];
            $bcc = $data['bcc'] ?? [];
            $senderName = $data['from_name'] ?? $data['sender_name'] ?? 'AutoDial Pro';
            $senderEmail = $data['from_email'] ?? $data['sender_email'] ?? null;
            
            // Always use mail() function for instant emails
            return $this->sendViaMailInstant($to, $subject, $message, $cc, $bcc, $senderName, $senderEmail);
            
        } catch (Exception $e) {
            error_log("Instant email send error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Sanitize HTML content for emails - allows basic formatting tags
     */
    private function sanitizeEmailContent($content) {
        // Allow basic HTML formatting tags
        $allowedTags = '<b><strong><i><em><u><br><p><div><span><h1><h2><h3><h4><h5><h6><ul><ol><li><a>';
        
        // Strip unwanted tags but keep allowed ones
        $sanitized = strip_tags($content, $allowedTags);
        
        // Convert line breaks to <br> tags for plain text portions
        $sanitized = nl2br($sanitized);
        
        return $sanitized;
    }

    /**
     * Send via PHPMailer for instant emails
     */
    private function sendViaPHPMailer($to, $subject, $message, $cc = [], $bcc = [], $senderName = '', $senderEmail = null) {
        try {
            $mail = new PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->config['smtp']['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->SMTPSecure = $this->config['smtp']['encryption'];
            $mail->Port = $this->config['smtp']['port'];
            
            // Recipients
            // Use custom sender email if provided, otherwise use config
            $mail->setFrom(
                $senderEmail ?: $this->config['smtp']['from']['address'],
                $senderName ?: $this->config['smtp']['from']['name']
            );
            $mail->addAddress($to);
            
            // Add CC recipients
            foreach ($cc as $ccEmail) {
                $mail->addCC($ccEmail);
            }
            
            // Add BCC recipients
            foreach ($bcc as $bccEmail) {
                $mail->addBCC($bccEmail);
            }
            
            // Content - Simple, plain text style
            $mail->isHTML(false); // Send as plain text
            $mail->Subject = $subject;
            
            // Simple, handwritten-style message without templates
            $simpleMessage = $message; // Use the message as-is, without any formatting
            
            error_log("DEBUG PHPMailer - Simple message length: " . strlen($simpleMessage));
            
            $mail->Body = $simpleMessage;
            
            $mail->send();
            
            // Log the instant email
            $this->logInstantEmail($to, $subject, $senderName, $this->config['smtp']['from']['address']);
            
            return true;
            
        } catch (Exception $e) {
            error_log("PHPMailer instant email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send via built-in mail() function for instant emails
     */
    private function sendViaMailInstant($to, $subject, $message, $cc = [], $bcc = [], $senderName = '', $senderEmail = null) {
        try {
            // Determine the best from address based on environment
            $defaultFromAddress = 'noreply@localhost';
            if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' && $_SERVER['HTTP_HOST'] !== '127.0.0.1') {
                // On production, use the domain
                $domain = str_replace('www.', '', $_SERVER['HTTP_HOST']);
                $defaultFromAddress = 'noreply@' . $domain;
            }
            
            // Use custom sender email if provided, otherwise use config
            $fromAddress = $senderEmail ?: ($this->config['from']['address'] ?? $defaultFromAddress);
            $fromName = $senderName ?: ($this->config['from']['name'] ?? 'AutoDial Pro');
            
            // Build headers array
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-type: text/plain; charset=UTF-8'; // Use plain text for better deliverability
            $headers[] = 'From: ' . $fromAddress; // Simple from header
            $headers[] = 'Reply-To: ' . $fromAddress;
            $headers[] = 'X-Mailer: PHP/' . phpversion();
            $headers[] = 'Return-Path: ' . $fromAddress;
            
            if (!empty($cc)) {
                $headers[] = 'Cc: ' . implode(', ', $cc);
            }
            
            if (!empty($bcc)) {
                $headers[] = 'Bcc: ' . implode(', ', $bcc);
            }
            
            // Keep message as plain text for mail() function
            $plainMessage = strip_tags($message);
            
            // Additional parameters for mail() function (Linux/Unix only)
            $additionalParams = '';
            if (PHP_OS_FAMILY !== 'Windows') {
                $additionalParams = '-f' . $fromAddress;
            }
            
            error_log("Sending email via mail() to: " . $to);
            error_log("From: " . $fromAddress);
            error_log("Subject: " . $subject);
            
            // Send email with mail() function
            $result = @mail($to, $subject, $plainMessage, implode("\r\n", $headers), $additionalParams);
            
            if ($result) {
                error_log("SUCCESS: Email sent to " . $to);
                // Log the instant email
                $this->logInstantEmail($to, $subject, $senderName, $fromAddress);
            } else {
                $lastError = error_get_last();
                error_log("FAILED: Could not send email to " . $to);
                if ($lastError) {
                    error_log("Error: " . $lastError['message']);
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Mail() instant email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Format email content for better display
     */
    private function formatEmailContent($content) {
        // Debug logging
        error_log("DEBUG formatEmailContent - Input content: " . var_export($content, true));
        error_log("DEBUG formatEmailContent - Line breaks in input: " . substr_count($content, "\n"));
        
        // Check if content is already HTML
        if (strip_tags($content) != $content) {
            // Content already contains HTML tags, just ensure proper structure
            error_log("DEBUG formatEmailContent - Content has HTML tags, wrapping only");
            return $this->wrapInHtmlTemplate($content);
        }
        
        // For plain text content, preserve line breaks while ensuring security
        // First escape the content for security
        $formatted = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        error_log("DEBUG formatEmailContent - After htmlspecialchars: " . var_export($formatted, true));
        
        // Then convert line breaks to HTML breaks
        $formatted = nl2br($formatted, false);  // false = use <br> instead of <br />
        error_log("DEBUG formatEmailContent - After nl2br: " . var_export($formatted, true));
        
        // Convert URLs to clickable links (after escaping, so we need to handle escaped URLs)
        $formatted = $this->convertUrlsToLinks($formatted);
        
        // Wrap in HTML template for better presentation
        $finalResult = $this->wrapInHtmlTemplate($formatted);
        error_log("DEBUG formatEmailContent - Final result length: " . strlen($finalResult));
        
        return $finalResult;
    }
    
    /**
     * Convert URLs in text to clickable links
     */
    private function convertUrlsToLinks($text) {
        // Pattern to match URLs (including escaped ones)
        $pattern = '/(https?:\/\/[^\s<>"]+)/i';
        return preg_replace($pattern, '<a href="$1" target="_blank" style="color: #007bff; text-decoration: none;">$1</a>', $text);
    }
    
    /**
     * Wrap content in HTML template for better presentation
     */
    private function wrapInHtmlTemplate($content) {
        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
        }
        .email-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px 0;
        }
        .email-content {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 20px;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .email-footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-content">
            ' . $content . '
        </div>
        <div class="email-footer">
            <p>This email was sent via AutoDial Pro CRM</p>
        </div>
    </div>
</body>
</html>';
    }

    private function logInstantEmail($to, $subject, $from_name, $from_email) {
        try {
            // First, check if recipient exists in contacts table
            $stmt = $this->db->prepare("SELECT id FROM contacts WHERE email = ?");
            $stmt->execute([$to]);
            $recipient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $recipientId = null;
            if ($recipient) {
                $recipientId = $recipient['id'];
            } else {
                // Create a temporary contact for this email
                $stmt = $this->db->prepare("
                    INSERT INTO contacts (
                        first_name, last_name, email, phone, company, status, created_at, updated_at
                    ) VALUES (
                        'Instant', 'Email', ?, 'N/A', 'Instant Email Recipient', 'new', NOW(), NOW()
                    )
                ");
                $stmt->execute([$to]);
                $recipientId = $this->db->lastInsertId();
            }
            
            // Create a temporary campaign for instant emails
            $stmt = $this->db->prepare("
                INSERT INTO email_campaigns (
                    name, subject, content, status, created_at, updated_at
                ) VALUES (
                    'Instant Email', ?, ?, 'sent', NOW(), NOW()
                )
            ");
            
            $stmt->execute([$subject, 'Instant email sent to ' . $to]);
            $campaignId = $this->db->lastInsertId();
            
            // Insert into campaign_sends with the correct columns
            $stmt = $this->db->prepare("
                INSERT INTO campaign_sends (
                    campaign_id, recipient_id, recipient_email, status, sent_at
                ) VALUES (
                    ?, ?, ?, 'sent', NOW()
                )
            ");
            
            $stmt->execute([$campaignId, $recipientId, $to]);
            
        } catch (Exception $e) {
            error_log("Failed to log instant email: " . $e->getMessage());
        }
    }
}