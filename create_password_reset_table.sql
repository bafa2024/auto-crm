-- Create password_reset_tokens table for AutoDial Pro CRM
-- Run this script in phpMyAdmin or your MySQL client

USE u946493694_autocrm;

-- Create password_reset_tokens table
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create indexes for better performance
CREATE INDEX idx_password_reset_token ON password_reset_tokens(token);
CREATE INDEX idx_password_reset_email ON password_reset_tokens(email);
CREATE INDEX idx_password_reset_expires ON password_reset_tokens(expires_at);

-- Verify table creation
SHOW TABLES LIKE 'password_reset_tokens';

-- Show table structure
DESCRIBE password_reset_tokens;

-- Test insert (optional - remove after testing)
-- INSERT INTO password_reset_tokens (user_id, token, email, expires_at) 
-- SELECT 1, 'test_token_123', 'admin@autocrm.com', DATE_ADD(NOW(), INTERVAL 1 HOUR)
-- WHERE EXISTS (SELECT 1 FROM users WHERE id = 1);

-- Clean up test data (uncomment if you ran the test insert)
-- DELETE FROM password_reset_tokens WHERE token = 'test_token_123'; 