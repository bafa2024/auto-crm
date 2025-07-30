<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Database connection failed\n");
    }
    
    // Create smtp_settings table
    $sql = "CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $db->exec($sql);
    echo "✓ SMTP settings table created successfully\n";
    
    // Insert default settings if not exists
    $defaultSettings = [
        'smtp_host' => 'smtp.gmail.com',
        'smtp_port' => '587',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'noreply@example.com',
        'smtp_from_name' => 'AutoDial Pro',
        'smtp_enabled' => '0'
    ];
    
    $stmt = $db->prepare("INSERT INTO smtp_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_key = setting_key");
    
    foreach ($defaultSettings as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    
    echo "✓ Default SMTP settings inserted\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>