<?php
/**
 * XAMPP Mail Configuration Helper
 * This script helps configure XAMPP to send emails
 */

echo "XAMPP Mail Configuration Helper\n";
echo "==============================\n\n";

// Check current PHP mail configuration
echo "Current PHP Mail Configuration:\n";
echo "- SMTP Server: " . ini_get('SMTP') . "\n";
echo "- SMTP Port: " . ini_get('smtp_port') . "\n";
echo "- Sendmail From: " . ini_get('sendmail_from') . "\n";
echo "- Sendmail Path: " . ini_get('sendmail_path') . "\n\n";

// Test mail() function
echo "Testing PHP mail() function...\n";
$test_email = "test@example.com";
$test_subject = "Test Email from XAMPP";
$test_message = "This is a test email to verify mail() function is working.";
$test_headers = "From: noreply@localhost\r\nContent-Type: text/plain; charset=UTF-8";

$result = @mail($test_email, $test_subject, $test_message, $test_headers);
echo "Mail function result: " . ($result ? "SUCCESS (mail() returned true)" : "FAILED (mail() returned false)") . "\n\n";

echo "IMPORTANT NOTES:\n";
echo "================\n";
echo "1. XAMPP on Windows requires additional configuration to send emails.\n";
echo "2. You have several options:\n\n";

echo "Option 1: Use SMTP directly (RECOMMENDED)\n";
echo "   - Configure SMTP settings at: http://localhost/acrm/smtp_config.php\n";
echo "   - This uses PHPMailer to send emails via SMTP\n\n";

echo "Option 2: Configure XAMPP's fake sendmail\n";
echo "   - Edit C:\\xampp\\sendmail\\sendmail.ini\n";
echo "   - Configure it with your SMTP server details\n";
echo "   - Example for Gmail:\n";
echo "     smtp_server=smtp.gmail.com\n";
echo "     smtp_port=587\n";
echo "     auth_username=your-email@gmail.com\n";
echo "     auth_password=your-app-password\n\n";

echo "Option 3: Use a local mail server\n";
echo "   - Install a mail server like hMailServer or MailEnable\n";
echo "   - Configure it to relay emails\n\n";

echo "QUICK FIX:\n";
echo "===========\n";
echo "Since instant emails are working, the issue is likely just the test mode.\n";
echo "I've already disabled test mode in config/email.php.\n";
echo "Now emails should be sent using the same method as instant emails.\n\n";

echo "To verify:\n";
echo "1. Go to http://localhost/acrm/test_email_fix.php\n";
echo "2. Send a test email\n";
echo "3. Check if 'Test Mode' shows as 'Disabled'\n";
?>