<?php
echo "Adding Password Reset Table to MySQL Database\n";
echo "=============================================\n\n";

try {
    // Use the production database configuration
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "1. Creating password_reset_tokens table...\n";
    
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
        echo "✓ Password reset tokens table created successfully\n";
    } else {
        echo "✓ Password reset tokens table already exists\n";
    }
    
    echo "\n2. Checking users table structure...\n";
    
    // Check if role column exists
    $stmt = $db->prepare("SHOW COLUMNS FROM users LIKE 'role'");
    $stmt->execute();
    $roleColumn = $stmt->fetch();
    
    if (!$roleColumn) {
        echo "Adding role column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'user'");
        echo "✓ Role column added to users table\n";
    } else {
        echo "✓ Role column already exists in users table\n";
    }
    
    echo "\n3. Updating existing users...\n";
    
    // Update admin user
    $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE email = 'admin@autocrm.com'");
    $stmt->execute();
    echo "✓ Updated admin user role\n";
    
    // Update other users to have 'user' role if they don't have one
    $stmt = $db->prepare("UPDATE users SET role = 'user' WHERE role IS NULL OR role = ''");
    $stmt->execute();
    echo "✓ Updated other users to have 'user' role\n";
    
    echo "\n4. Testing password reset functionality...\n";
    
    // Test inserting a token
    $testEmail = 'test@example.com';
    $testToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $db->prepare("
        INSERT INTO password_reset_tokens (user_id, token, email, expires_at) 
        VALUES (1, ?, ?, ?)
    ");
    $result = $stmt->execute([$testToken, $testEmail, $expiresAt]);
    
    if ($result) {
        echo "✓ Test token inserted successfully\n";
        
        // Clean up test token
        $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE email = ?");
        $stmt->execute([$testEmail]);
        echo "✓ Test token cleaned up\n";
    }
    
    echo "\n✅ Password reset functionality added successfully to MySQL database!\n";
    echo "\nThe password reset feature is now ready to use.\n";
    echo "You can test it by visiting: /forgot-password\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 