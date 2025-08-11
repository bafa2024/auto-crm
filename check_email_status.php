<?php
require_once 'config/database.php';
require_once 'services/EmailService.php';

$db = (new Database())->getConnection();
$emailService = new EmailService($db);
$config = include 'config/email.php';

echo "Email Configuration Status:\n";
echo "==========================\n";
echo "Test Mode: " . ($config['test_mode'] ? 'ENABLED (emails will be logged, not sent)' : 'DISABLED (emails will be sent)') . "\n";
echo "Driver: " . $config['driver'] . "\n";
echo "SMTP Host: " . $config['smtp']['host'] . "\n";
echo "SMTP Username: " . (!empty($config['smtp']['username']) ? '***configured***' : 'NOT SET') . "\n";

// Check SMTP settings from database
$smtpSettings = [];
try {
    $stmt = $db->prepare("SELECT setting_key, setting_value FROM smtp_settings");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $smtpSettings[$row['setting_key']] = $row['setting_value'];
    }
    
    echo "\nDatabase SMTP Settings:\n";
    echo "SMTP Enabled: " . ($smtpSettings['smtp_enabled'] ?? '0') . "\n";
    echo "SMTP Host (DB): " . ($smtpSettings['smtp_host'] ?? 'not set') . "\n";
    echo "SMTP Username (DB): " . (!empty($smtpSettings['smtp_username']) ? '***configured***' : 'NOT SET') . "\n";
} catch (Exception $e) {
    echo "\nCould not read SMTP settings from database.\n";
}

echo "\nRECOMMENDATION:\n";
if ($config['test_mode']) {
    echo "Test mode is ENABLED. Emails are being logged instead of sent.\n";
    echo "This has been fixed in config/email.php - test mode is now disabled.\n";
} else {
    echo "Test mode is DISABLED. Emails should be sent properly now.\n";
    if (empty($smtpSettings['smtp_username']) || $smtpSettings['smtp_enabled'] != '1') {
        echo "However, SMTP is not properly configured.\n";
        echo "Please configure SMTP at: http://localhost/acrm/smtp_config.php\n";
    }
}
?>