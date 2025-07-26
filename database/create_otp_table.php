<?php
// Migration script to create OTP table

require_once __DIR__ . "/../config/database.php";

$database = new Database();
$db = $database->getConnection();

echo "=== Creating OTP Table ===\n\n";
echo "Database Type: " . $database->getDatabaseType() . "\n";
echo "Environment: " . $database->getEnvironment() . "\n\n";

try {
    if ($database->getDatabaseType() === 'sqlite') {
        // SQLite version
        $sql = "CREATE TABLE IF NOT EXISTS otps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL,
            otp_code TEXT NOT NULL,
            expires_at DATETIME NOT NULL,
            is_used INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )";
        
        $db->exec($sql);
        
        // Create index for faster lookups
        $db->exec("CREATE INDEX IF NOT EXISTS idx_otp_email ON otps(email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_otp_code ON otps(otp_code)");
        
    } else {
        // MySQL version
        $sql = "CREATE TABLE IF NOT EXISTS otps (
            id INT PRIMARY KEY AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            otp_code VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            is_used BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_otp_code (otp_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
    }
    
    echo "✅ OTP table created successfully!\n";
    
    // Check if table was created
    if ($database->getDatabaseType() === 'sqlite') {
        $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='otps'");
    } else {
        $stmt = $db->query("SHOW TABLES LIKE 'otps'");
    }
    
    if ($stmt->fetch()) {
        echo "✅ Table 'otps' verified!\n";
    } else {
        echo "❌ Table 'otps' not found!\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error creating OTP table: " . $e->getMessage() . "\n";
}

echo "\n⚠️  This script can be run multiple times safely (CREATE IF NOT EXISTS)\n";