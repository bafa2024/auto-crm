<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/SmtpSetting.php';

class SettingsController extends BaseController {
    private $smtpSettingModel;
    
    public function __construct($database) {
        parent::__construct($database);
        if ($database) {
            $this->smtpSettingModel = new SmtpSetting($database);
        }
    }
    
    /**
     * Get SMTP settings
     */
    public function getSmtpSettings() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->sendError('Method not allowed', 405);
        }
        
        try {
            $settings = $this->smtpSettingModel->getAllSettings();
            
            // Don't send password in plain text
            if (isset($settings['smtp_password']) && $settings['smtp_password']) {
                $settings['smtp_password'] = '********';
                $settings['smtp_password_set'] = true;
            } else {
                $settings['smtp_password_set'] = false;
            }
            
            $this->sendSuccess($settings, 'SMTP settings retrieved');
        } catch (Exception $e) {
            $this->sendError('Failed to retrieve SMTP settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Update SMTP settings
     */
    public function updateSmtpSettings() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            $this->sendError('Invalid JSON data', 400);
        }
        
        // Validate required fields
        $requiredFields = ['smtp_host', 'smtp_port', 'smtp_from_email', 'smtp_from_name'];
        foreach ($requiredFields as $field) {
            if (empty($input[$field])) {
                $this->sendError("Field '$field' is required", 400);
            }
        }
        
        // Validate email format
        if (!filter_var($input['smtp_from_email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Invalid from email address', 400);
        }
        
        // Validate port number
        if (!is_numeric($input['smtp_port']) || $input['smtp_port'] < 1 || $input['smtp_port'] > 65535) {
            $this->sendError('Invalid port number', 400);
        }
        
        try {
            // Prepare settings to update
            $settings = [
                'smtp_host' => $input['smtp_host'],
                'smtp_port' => $input['smtp_port'],
                'smtp_username' => $input['smtp_username'] ?? '',
                'smtp_encryption' => $input['smtp_encryption'] ?? 'tls',
                'smtp_from_email' => $input['smtp_from_email'],
                'smtp_from_name' => $input['smtp_from_name'],
                'smtp_enabled' => $input['smtp_enabled'] ?? '1'
            ];
            
            // Only update password if a new one is provided
            if (!empty($input['smtp_password']) && $input['smtp_password'] !== '********') {
                $settings['smtp_password'] = $input['smtp_password'];
            }
            
            $result = $this->smtpSettingModel->updateSettings($settings);
            
            if ($result) {
                $this->sendSuccess([], 'SMTP settings updated successfully');
            } else {
                $this->sendError('Failed to update SMTP settings');
            }
        } catch (Exception $e) {
            $this->sendError('Failed to update settings: ' . $e->getMessage());
        }
    }
    
    /**
     * Test SMTP connection
     */
    public function testSmtpConnection() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        try {
            // If test data is provided, temporarily update settings
            if ($input && !empty($input['smtp_host'])) {
                $testSettings = [
                    'smtp_host' => $input['smtp_host'],
                    'smtp_port' => $input['smtp_port'],
                    'smtp_username' => $input['smtp_username'] ?? '',
                    'smtp_encryption' => $input['smtp_encryption'] ?? 'tls',
                    'smtp_password' => $input['smtp_password'] ?? ''
                ];
                
                // Temporarily update settings for testing
                foreach ($testSettings as $key => $value) {
                    $this->smtpSettingModel->updateSetting($key, $value);
                }
            }
            
            $result = $this->smtpSettingModel->testConnection();
            
            if ($result['success']) {
                $this->sendSuccess([], $result['message']);
            } else {
                $this->sendError($result['message']);
            }
        } catch (Exception $e) {
            $this->sendError('Connection test failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Send test email
     */
    public function sendTestEmail() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendError('Method not allowed', 405);
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['test_email'])) {
            $this->sendError('Test email address is required', 400);
        }
        
        if (!filter_var($input['test_email'], FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Invalid test email address', 400);
        }
        
        try {
            // Get current SMTP settings
            $settings = $this->smtpSettingModel->getAllSettings();
            
            // Create email service with dynamic settings
            require_once __DIR__ . '/../services/EmailService.php';
            $emailService = new EmailService($this->db);
            
            // Send test email
            $result = $emailService->send(
                $input['test_email'],
                'SMTP Test Email',
                "This is a test email from AutoDial Pro CRM.\n\nYour SMTP settings are configured correctly!",
                ['from_email' => $settings['smtp_from_email'], 'from_name' => $settings['smtp_from_name']]
            );
            
            if ($result === true || (is_array($result) && $result['success'])) {
                $this->sendSuccess([], 'Test email sent successfully!');
            } else {
                $error = is_array($result) && isset($result['error']) ? $result['error'] : 'Failed to send test email';
                $this->sendError($error);
            }
        } catch (Exception $e) {
            $this->sendError('Failed to send test email: ' . $e->getMessage());
        }
    }
}
?>