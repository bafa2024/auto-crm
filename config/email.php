<?php
// Email configuration

return [
    // Default mail driver (smtp, mail, sendmail)
    'driver' => $_ENV['MAIL_DRIVER'] ?? 'smtp',
    
    // SMTP Configuration
    'smtp' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
        'port' => $_ENV['MAIL_PORT'] ?? 587,
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from' => [
            'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@autocrm.com',
            'name' => $_ENV['MAIL_FROM_NAME'] ?? 'AutoCRM'
        ]
    ],
    
    // Email sending settings
    'batch_size' => 50, // Number of emails to send per batch
    'delay_between_emails' => 1, // Seconds to wait between each email
    'delay_between_batches' => 5, // Seconds to wait between batches
    'max_retries' => 3, // Maximum retry attempts for failed emails
    
    // Tracking settings
    'track_opens' => false,
    'track_clicks' => false,
    'tracking_domain' => $_ENV['APP_URL'] ?? 'http://localhost/acrm',
    
    // Test mode - simulates email sending without actual SMTP
    'test_mode' => $_ENV['MAIL_TEST_MODE'] ?? true,
    'test_mode_log_path' => __DIR__ . '/../logs/test_emails.log'
];