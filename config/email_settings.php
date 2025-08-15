<?php
/**
 * Email Settings Helper
 * This file provides email configuration with multiple fallback options
 */

// Check if SMTP credentials are available in environment
$smtp_configured = !empty($_ENV['SMTP_USERNAME']) || !empty($_ENV['MAIL_USERNAME']);

// Determine the best email driver to use
function getEmailDriver() {
    // If SMTP credentials are configured, use SMTP
    if (!empty($_ENV['SMTP_USERNAME']) || !empty($_ENV['MAIL_USERNAME'])) {
        return 'smtp';
    }
    
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return 'smtp';
    }
    
    // Fallback to PHP mail() function
    return 'mail';
}

// Get SMTP configuration with fallbacks
function getSmtpConfig() {
    // Priority 1: Environment variables
    if (!empty($_ENV['SMTP_USERNAME']) || !empty($_ENV['MAIL_USERNAME'])) {
        return [
            'host' => $_ENV['SMTP_HOST'] ?? $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
            'port' => $_ENV['SMTP_PORT'] ?? $_ENV['MAIL_PORT'] ?? 587,
            'username' => $_ENV['SMTP_USERNAME'] ?? $_ENV['MAIL_USERNAME'] ?? '',
            'password' => $_ENV['SMTP_PASSWORD'] ?? $_ENV['MAIL_PASSWORD'] ?? '',
            'encryption' => $_ENV['SMTP_ENCRYPTION'] ?? $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
            'from' => [
                'address' => $_ENV['SMTP_FROM_EMAIL'] ?? $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@localhost',
                'name' => $_ENV['SMTP_FROM_NAME'] ?? $_ENV['MAIL_FROM_NAME'] ?? 'AutoDial Pro'
            ]
        ];
    }
    
    // Priority 2: Check database for SMTP settings
    try {
        if (file_exists(__DIR__ . '/database.php')) {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                $stmt = $db->prepare("SELECT setting_key, setting_value FROM smtp_settings WHERE setting_key IN ('smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from_email', 'smtp_from_name')");
                $stmt->execute();
                
                $settings = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
                
                if (!empty($settings['smtp_enabled']) && $settings['smtp_enabled'] == '1' && !empty($settings['smtp_username'])) {
                    return [
                        'host' => $settings['smtp_host'] ?? 'smtp.gmail.com',
                        'port' => $settings['smtp_port'] ?? 587,
                        'username' => $settings['smtp_username'] ?? '',
                        'password' => $settings['smtp_password'] ?? '',
                        'encryption' => $settings['smtp_encryption'] ?? 'tls',
                        'from' => [
                            'address' => $settings['smtp_from_email'] ?? 'noreply@localhost',
                            'name' => $settings['smtp_from_name'] ?? 'AutoDial Pro'
                        ]
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
    
    // Priority 3: Default configuration (no SMTP, will use mail())
    return [
        'host' => 'localhost',
        'port' => 25,
        'username' => '',
        'password' => '',
        'encryption' => '',
        'from' => [
            'address' => 'noreply@localhost',
            'name' => 'AutoDial Pro'
        ]
    ];
}

// Check if system can send emails
function canSendEmails() {
    // Check if mail() function is available
    if (function_exists('mail')) {
        // Try to configure PHP's mail settings for Windows
        if (PHP_OS_FAMILY === 'Windows') {
            // Use localhost SMTP if available
            ini_set('SMTP', 'localhost');
            ini_set('smtp_port', '25');
            ini_set('sendmail_from', 'noreply@localhost');
        }
        return true;
    }
    
    // Check if PHPMailer is available
    if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
        return true;
    }
    
    return false;
}

// Export configuration
return [
    'driver' => getEmailDriver(),
    'smtp' => getSmtpConfig(),
    'can_send' => canSendEmails(),
    'test_mode' => false // Disable test mode to actually try sending
];
?>