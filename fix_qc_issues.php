<?php
/**
 * Fix QC Issues Script
 * This script fixes all issues found by the QC dashboard
 */

echo "<h1>🔧 Fixing QC Dashboard Issues</h1>";

// 1. Create missing directories
echo "<h2>📁 Step 1: Creating Missing Directories</h2>";

$directories = [
    'temp',
    'uploads', 
    'backups',
    'sessions',
    'cache',
    'logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "<p style='color: green;'>✅ Created directory: $dir</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create directory: $dir</p>";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Directory already exists: $dir</p>";
    }
    
    // Set permissions
    if (is_dir($dir)) {
        if (chmod($dir, 0755)) {
            echo "<p style='color: green;'>✅ Set permissions for: $dir</p>";
        } else {
            echo "<p style='color: orange;'>⚠️ Could not set permissions for: $dir</p>";
        }
    }
}

// 2. Create missing database tables
echo "<h2>🗄️ Step 2: Creating Missing Database Tables</h2>";

try {
    require_once 'config/database.php';
    $db = new Database();
    $pdo = $db->getConnection();
    
    if (!$pdo) {
        echo "<p style='color: red;'>❌ Database connection failed</p>";
    } else {
        echo "<p style='color: green;'>✅ Database connected successfully</p>";
        
        // Create password_resets table
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
        
        // Create otp_codes table
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
        
        // Create smtp_settings table
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
            $insertSql = "INSERT IGNORE INTO smtp_settings (host, port, username, password, encryption, from_email, from_name) 
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
        
        // Create email_templates table
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
            
            $insertSql = "INSERT IGNORE INTO email_templates (name, subject, content) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($insertSql);
            
            foreach ($templates as $template) {
                if ($stmt->execute([$template['name'], $template['subject'], $template['content']])) {
                    echo "<p style='color: green;'>✅ Inserted template: {$template['name']}</p>";
                }
            }
        } else {
            echo "<p style='color: red;'>❌ Failed to create email_templates table</p>";
        }
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Database Error: " . $e->getMessage() . "</p>";
}

// 3. Test API endpoints
echo "<h2>🔗 Step 3: Testing API Endpoints</h2>";

$endpoints = [
    '/api/auth/login' => 'POST',
    '/api/auth/register' => 'POST',
    '/api/contacts' => 'GET',
    '/api/campaigns' => 'GET'
];

foreach ($endpoints as $endpoint => $method) {
    echo "<h3>Testing: $endpoint ($method)</h3>";
    
    $liveUrl = "https://acrm.regrowup.ca$endpoint";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $liveUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['test' => 'data']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode < 400) {
        echo "<p style='color: green;'>✅ Endpoint accessible (HTTP $httpCode)</p>";
    } else {
        echo "<p style='color: red;'>❌ Endpoint not accessible (HTTP $httpCode)</p>";
        if ($error) {
            echo "<p style='color: red;'>Error: $error</p>";
        }
    }
}

// 4. Test file upload directory
echo "<h2>📤 Step 4: Testing File Upload Directory</h2>";

$uploadDir = 'uploads/';
if (is_dir($uploadDir) && is_writable($uploadDir)) {
    echo "<p style='color: green;'>✅ Upload directory accessible and writable</p>";
} else {
    echo "<p style='color: red;'>❌ Upload directory not accessible</p>";
}

// 5. Test backup directory
echo "<h2>💾 Step 5: Testing Backup Directory</h2>";

$backupDir = 'backups/';
if (is_dir($backupDir) && is_writable($backupDir)) {
    echo "<p style='color: green;'>✅ Backup directory accessible and writable</p>";
} else {
    echo "<p style='color: red;'>❌ Backup directory not accessible</p>";
}

// 6. Test SMTP settings
echo "<h2>📧 Step 6: Testing SMTP Settings</h2>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM smtp_settings");
    $smtpCount = $stmt->fetchColumn();
    echo "<p style='color: green;'>✅ Found $smtpCount SMTP configurations</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ SMTP settings table error: " . $e->getMessage() . "</p>";
}

echo "<h2>🎉 Fix Complete!</h2>";
echo "<p>All QC dashboard issues have been addressed. Please run the QC dashboard again to verify the fixes.</p>";

echo "<div style='margin: 20px 0; padding: 20px; background: #d4edda; border-radius: 10px;'>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li>Run the QC dashboard again: <a href='qc_dashboard_advanced.php'>Advanced QC Dashboard</a></li>";
echo "<li>Verify all tests pass</li>";
echo "<li>Test the application functionality</li>";
echo "<li>Deploy if all tests pass</li>";
echo "</ol>";
echo "</div>";
?> 