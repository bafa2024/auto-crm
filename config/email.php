<?php
// Email configuration

return [
    // Force use of mail() function only
    'driver' => 'mail',
    
    // SMTP Configuration (disabled - using mail() function only)
    'smtp' => [
        'enabled' => false,
        'host' => '',
        'port' => '',
        'username' => '',
        'password' => '',
        'encryption' => '',
        'from' => [
            'address' => '',
            'name' => ''
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
    
    // Test mode - disabled for production (only enable for local testing)
    'test_mode' => false,
    
    // Default from address for mail() function
    'from' => [
        'address' => isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== 'localhost' 
            ? 'noreply@' . str_replace('www.', '', $_SERVER['HTTP_HOST'])
            : 'noreply@localhost',
        'name' => 'AutoDial Pro'
    ],
    'test_mode_log_path' => __DIR__ . '/../logs/test_emails.log'
];