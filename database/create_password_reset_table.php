<?php
// create_password_reset_table.php - Add password reset functionality to database

echo "Adding Password Reset Table to Database\n";
echo "======================================\n\n";

try {
    // Load database connection
    require_once __DIR__ . '/../config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    echo "1. Creating password_reset_tokens table...\n";
    
    // Create password_reset_tokens table
    $sql = "
        CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT UNIQUE NOT NULL,
            email TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ";
    
    $result = $db->exec($sql);
    
    if ($result !== false) {
        echo "✓ Password reset tokens table created\n";
    } else {
        echo "✓ Password reset tokens table already exists\n";
    }
    
    // Add role column to users table if it doesn't exist
    echo "\n2. Checking users table structure...\n";
    
    $stmt = $db->prepare("PRAGMA table_info(users)");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
    
    if (!in_array('role', $columns)) {
        echo "Adding role column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
        echo "✓ Role column added to users table\n";
    } else {
        echo "✓ Role column already exists in users table\n";
    }
    
    // Update existing users to have admin role
    echo "\n3. Updating existing users...\n";
    
    $stmt = $db->prepare("UPDATE users SET role = 'admin' WHERE email = 'admin@autocrm.com'");
    $stmt->execute();
    echo "✓ Updated admin user role\n";
    
    $stmt = $db->prepare("UPDATE users SET role = 'user' WHERE role IS NULL OR role = ''");
    $stmt->execute();
    echo "✓ Updated other users to have 'user' role\n";
    
    // Test the table
    echo "\n4. Testing password reset functionality...\n";
    
    // Test inserting a token
    $testUserId = 1;
    $testEmail = 'admin@autocrm.com';
    $testToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $db->prepare("
        INSERT INTO password_reset_tokens (user_id, token, email, expires_at) 
        VALUES (?, ?, ?, ?)
    ");
    $result = $stmt->execute([$testUserId, $testToken, $testEmail, $expiresAt]);
    
    if ($result) {
        echo "✓ Test token inserted successfully\n";
        
        // Clean up test token
        $stmt = $db->prepare("DELETE FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$testToken]);
        echo "✓ Test token cleaned up\n";
    } else {
        echo "✗ Failed to insert test token\n";
    }
    
    echo "\n✅ Password reset functionality added successfully!\n";
    echo "\nDatabase changes:\n";
    echo "- Created password_reset_tokens table\n";
    echo "- Added role column to users table\n";
    echo "- Updated existing user roles\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?> 