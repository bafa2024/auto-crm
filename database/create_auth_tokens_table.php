<?php
// Migration script to create auth_tokens table

require_once __DIR__ . "/../config/database.php";

$database = new Database();
$db = $database->getConnection();

echo "=== Creating Auth Tokens Table ===\n\n";
echo "Database Type: " . $database->getDatabaseType() . "\n";
echo "Environment: " . $database->getEnvironment() . "\n\n";

try {
    if ($database->getDatabaseType() === 'sqlite') {
        // SQLite version
        $sql = "CREATE TABLE IF NOT EXISTS auth_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            token TEXT NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            is_used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($sql);
        
        // Create indexes
        $db->exec("CREATE INDEX IF NOT EXISTS idx_auth_token ON auth_tokens(token)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_auth_email ON auth_tokens(email)");
        
    } else {
        // MySQL version
        $sql = "CREATE TABLE IF NOT EXISTS auth_tokens (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            is_used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
    }
    
    echo "✅ Auth tokens table created successfully!\n";
    
    // Verify table creation
    if ($database->getDatabaseType() === 'sqlite') {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='auth_tokens'");
    } else {
        $stmt = $db->query("SHOW TABLES LIKE 'auth_tokens'");
    }
    
    if ($stmt->fetch()) {
        echo "✅ Table 'auth_tokens' verified!\n";
    } else {
        echo "❌ Table 'auth_tokens' not found!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating auth tokens table: " . $e->getMessage() . "\n";
}

echo "\n⚠️  This script can be run multiple times safely (CREATE IF NOT EXISTS)\n";