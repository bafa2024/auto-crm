<?php
/**
 * Create Missing Database Tables Script
 * Run this to create all missing tables found by QC dashboard
 */

require_once 'config/database.php';

echo "<h2>Creating Missing Database Tables</h2>";

try {
    $db = new Database();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
        exit;
    }
    
    echo "<p style='color: green;'>✅ Database connected successfully</p>";
    
    // 1. Create password_resets table
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_token (token),
        INDEX idx_expires (expires_at)
    )";
    
    if ($pdo->exec($sql) !== false) {
        echo "<p style='color: green;'>✅ Created password_resets table</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create password_resets table</p>";
    }
    
    // 2. Create otp_codes table
    $sql = "CREATE TABLE IF NOT EXISTS otp_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL,
        otp_code VARCHAR(10) NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_otp (otp_code),
        INDEX idx_expires (expires_at)
    )";
    
    if ($pdo->exec($sql) !== false) {
        echo "<p style='color: green;'>✅ Created otp_codes table</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create otp_codes table</p>";
    }
    
    // 3. Create smtp_settings table
    $sql = "CREATE TABLE IF NOT EXISTS smtp_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        host VARCHAR(255) NOT NULL,
        port INT NOT NULL DEFAULT 587,
        username VARCHAR(255) NOT NULL,
        password VARCHAR(255) NOT NULL,
        encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
        from_email VARCHAR(255) NOT NULL,
        from_name VARCHAR(255) NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($pdo->exec($sql) !== false) {
        echo "<p style='color: green;'>✅ Created smtp_settings table</p>";
        
        // Insert default SMTP settings
        $insertSql = "INSERT INTO smtp_settings (host, port, username, password, encryption, from_email, from_name) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($insertSql);
        
        $defaultSettings = [
            'smtp.gmail.com', 587, 'noreply@acrm.regrowup.ca', 'your_password_here', 'tls', 
            'noreply@acrm.regrowup.ca', 'AutoDial Pro'
        ];
        
        if ($stmt->execute($defaultSettings)) {
            echo "<p style='color: green;'>✅ Inserted default SMTP settings</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not insert default SMTP settings</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Failed to create smtp_settings table</p>";
    }
    
    // 4. Create email_templates table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS email_templates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        subject VARCHAR(255) NOT NULL,
        content TEXT NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($pdo->exec($sql) !== false) {
        echo "<p style='color: green;'>✅ Created email_templates table</p>";
        
        // Insert default email templates
        $templates = [
            [
                'name' => 'Welcome Email',
                'subject' => 'Welcome to AutoDial Pro',
                'content' => '<h2>Welcome to AutoDial Pro!</h2><p>Thank you for joining our platform.</p>'
            ],
            [
                'name' => 'Password Reset',
                'subject' => 'Password Reset Request',
                'content' => '<h2>Password Reset</h2><p>Click the link below to reset your password: {reset_link}</p>'
            ],
            [
                'name' => 'OTP Email',
                'subject' => 'Your OTP Code',
                'content' => '<h2>Your OTP Code</h2><p>Your OTP code is: {otp_code}</p>'
            ]
        ];
        
        $insertSql = "INSERT INTO email_templates (name, subject, content) VALUES (?, ?, ?)";
        $stmt = $pdo->prepare($insertSql);
        
        foreach ($templates as $template) {
            if ($stmt->execute([$template['name'], $template['subject'], $template['content']])) {
                echo "<p style='color: green;'>✅ Inserted template: {$template['name']}</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Failed to create email_templates table</p>";
    }
    
    echo "<h3>Database Tables Creation Complete!</h3>";
    echo "<p>All required tables have been created with proper structure.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?> 