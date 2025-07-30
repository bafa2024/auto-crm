<?php
// Web-based script to fix password reset table
// Access this through your browser: https://yourdomain.com/fix_password_reset_web.php

echo "<h1>Fixing Password Reset Table for Live Server</h1>";
echo "<hr>";

try {
    // Force MySQL connection for live server
    $host = 'localhost';
    $port = '3306';
    $database = 'u946493694_autocrm';
    $username = 'u946493694_autocrmu';
    $password = 'CDExzsawq123@#$';
    $charset = 'utf8mb4';
    
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=$charset";
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES $charset"
    ];
    
    echo "<p><strong>Connecting to MySQL database:</strong> $database</p>";
    $db = new PDO($dsn, $username, $password, $options);
    echo "<p style='color: green;'>✓ Connected to MySQL database successfully</p>";
    
    echo "<h2>1. Creating password_reset_tokens table...</h2>";
    
    $sql = "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(255) UNIQUE NOT NULL,
            email VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $result = $db->exec($sql);
    if ($result !== false) {
        echo "<p style='color: green;'>✓ Password reset tokens table created successfully</p>";
    } else {
        echo "<p style='color: blue;'>✓ Password reset tokens table already exists</p>";
    }
    
    echo "<h2>2. Verifying table structure...</h2>";
    
    // Check if table exists
    $stmt = $db->prepare("SHOW TABLES LIKE 'password_reset_tokens'");
    $stmt->execute();
    $tableExists = $stmt->fetch();
    
    if ($tableExists) {
        echo "<p style='color: green;'>✓ password_reset_tokens table exists</p>";
        
        // Check table structure
        $stmt = $db->prepare("DESCRIBE password_reset_tokens");
        $stmt->execute();
        $columns = $stmt->fetchAll();
        
        echo "<p><strong>Table structure:</strong></p>";
        echo "<ul>";
        foreach ($columns as $column) {
            echo "<li>{$column['Field']}: {$column['Type']}</li>";
        }
        echo "</ul>";
    } else {
        echo "<p style='color: red;'>❌ password_reset_tokens table not found</p>";
    }
    
    echo "<h2>3. Testing password reset functionality...</h2>";
    
    // Test inserting a token
    $testEmail = 'test@example.com';
    $testToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Get a user ID for testing
    $stmt = $db->prepare("SELECT id FROM users LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch();
    
    if ($user) {
        $stmt = $db->prepare("
            INSERT INTO password_reset_tokens (user_id, token, email, expires_at) 
            VALUES (?, ?, ?, ?)
        ");
        $result = $stmt->execute([$user['id'], $testToken, $testEmail, $expiresAt]);
        
        if ($result) {
            echo "<p style='color: green;'>✓ Test token inserted successfully</p>";
            
            // Clean up test token
            $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
            $stmt->execute([$testEmail]);
            echo "<p style='color: green;'>✓ Test token cleaned up</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to insert test token</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠️  No users found for testing</p>";
    }
    
    echo "<h2 style='color: green;'>✅ Password reset functionality fixed successfully!</h2>";
    echo "<p>The password reset feature is now ready to use.</p>";
    echo "<p>You can test it by visiting: <a href='/forgot-password'>/forgot-password</a></p>";
    
    // Clean up - delete this file after successful execution
    echo "<hr>";
    echo "<p><strong>Security Note:</strong> Please delete this file after successful execution for security reasons.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection settings.</p>";
}
?> 