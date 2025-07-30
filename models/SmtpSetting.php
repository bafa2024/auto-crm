<?php
require_once __DIR__ . '/BaseModel.php';

class SmtpSetting extends BaseModel {
    protected $table = 'smtp_settings';
    
    /**
     * Get all SMTP settings as key-value pairs
     */
    public function getAllSettings() {
        try {
            $stmt = $this->db->prepare("SELECT setting_key, setting_value FROM {$this->table}");
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
            
            return $settings;
        } catch (Exception $e) {
            error_log("Error fetching SMTP settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get a specific setting value
     */
    public function getSetting($key) {
        try {
            $stmt = $this->db->prepare("SELECT setting_value FROM {$this->table} WHERE setting_key = ?");
            $stmt->execute([$key]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['setting_value'] : null;
        } catch (Exception $e) {
            error_log("Error fetching SMTP setting: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update a specific setting
     */
    public function updateSetting($key, $value) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (setting_key, setting_value) 
                 VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            return $stmt->execute([$key, $value]);
        } catch (Exception $e) {
            error_log("Error updating SMTP setting: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update multiple settings at once
     */
    public function updateSettings($settings) {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                $this->updateSetting($key, $value);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error updating SMTP settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Test SMTP connection with current settings
     */
    public function testConnection() {
        $settings = $this->getAllSettings();
        
        // Check if PHPMailer is available
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Try to include PHPMailer
            $vendorPath = __DIR__ . '/../vendor/autoload.php';
            if (file_exists($vendorPath)) {
                require_once $vendorPath;
            } else {
                return [
                    'success' => false,
                    'message' => 'PHPMailer is not installed. Please run: composer require phpmailer/phpmailer'
                ];
            }
        }
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $settings['smtp_host'] ?? '';
            $mail->SMTPAuth = true;
            $mail->Username = $settings['smtp_username'] ?? '';
            $mail->Password = $settings['smtp_password'] ?? '';
            $mail->SMTPSecure = $settings['smtp_encryption'] ?? 'tls';
            $mail->Port = intval($settings['smtp_port'] ?? 587);
            
            // Test connection
            $mail->SMTPDebug = 0;
            $mail->Timeout = 10;
            
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return [
                    'success' => true,
                    'message' => 'SMTP connection successful!'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to connect to SMTP server'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
}
?>