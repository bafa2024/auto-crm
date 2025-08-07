-- =======================================================
-- ACRM Campaign Scheduling System - Database Migration
-- Production Deployment Script v1.0
-- =======================================================

-- This script adds the new scheduling tables to existing ACRM installations
-- Run this after your existing database is set up

USE autocrm;

-- =======================================================
-- 1. ADD SCHEDULED CAMPAIGNS TABLE
-- =======================================================

CREATE TABLE IF NOT EXISTS scheduled_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    schedule_type ENUM('immediate', 'scheduled', 'recurring') DEFAULT 'immediate',
    schedule_date DATETIME NULL,
    frequency ENUM('once', 'daily', 'weekly', 'monthly') DEFAULT 'once',
    recipient_ids TEXT COMMENT 'JSON array of recipient IDs to send to',
    status ENUM('pending', 'running', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_sent_at DATETIME NULL,
    next_send_at DATETIME NULL COMMENT 'When this campaign should be sent next',
    sent_count INT DEFAULT 0 COMMENT 'Number of emails successfully sent',
    failed_count INT DEFAULT 0 COMMENT 'Number of emails that failed to send',
    
    -- Indexes for performance
    INDEX idx_status (status),
    INDEX idx_next_send (next_send_at),
    INDEX idx_schedule_type (schedule_type),
    INDEX idx_campaign_status (campaign_id, status),
    
    -- Foreign key constraint (will fail silently if email_campaigns doesn't exist)
    CONSTRAINT fk_scheduled_campaign_id 
        FOREIGN KEY (campaign_id) 
        REFERENCES email_campaigns(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Stores scheduled campaign information for the new scheduling system';

-- =======================================================
-- 2. ADD SCHEDULE LOG TABLE
-- =======================================================

CREATE TABLE IF NOT EXISTS schedule_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scheduled_campaign_id INT NOT NULL,
    action ENUM('created', 'sent', 'failed', 'cancelled', 'processed') NOT NULL,
    message TEXT COMMENT 'Detailed log message or error description',
    recipient_count INT DEFAULT 0 COMMENT 'Number of recipients processed in this action',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_scheduled_campaign (scheduled_campaign_id),
    INDEX idx_action_date (action, created_at),
    INDEX idx_created_at (created_at),
    
    -- Foreign key constraint
    CONSTRAINT fk_schedule_log_campaign_id 
        FOREIGN KEY (scheduled_campaign_id) 
        REFERENCES scheduled_campaigns(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
  COMMENT='Logs all scheduling actions and processing results';

-- =======================================================
-- 3. CREATE EMAIL_RECIPIENTS TABLE IF NOT EXISTS
-- =======================================================
-- This table might already exist in legacy installations

CREATE TABLE IF NOT EXISTS email_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NULL COMMENT 'Link to email_campaigns table',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    company VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    status ENUM('pending', 'sent', 'failed', 'bounced', 'unsubscribed') DEFAULT 'pending',
    sent_at DATETIME NULL,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_campaign_status (campaign_id, status),
    INDEX idx_status (status),
    INDEX idx_sent_at (sent_at),
    
    -- Foreign key constraint (optional, might fail in legacy setups)
    CONSTRAINT fk_recipient_campaign_id 
        FOREIGN KEY (campaign_id) 
        REFERENCES email_campaigns(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE
        
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Email recipients for campaigns - legacy table with enhancements';

-- =======================================================
-- 4. UPDATE EMAIL_CAMPAIGNS TABLE (ADD MISSING COLUMNS)
-- =======================================================

-- Add columns that might be missing in older installations
ALTER TABLE email_campaigns 
ADD COLUMN IF NOT EXISTS sender_name VARCHAR(100) NULL AFTER content,
ADD COLUMN IF NOT EXISTS sender_email VARCHAR(255) NULL AFTER sender_name,
ADD COLUMN IF NOT EXISTS reply_to_email VARCHAR(255) NULL AFTER sender_email;

-- Add indexes if they don't exist
ALTER TABLE email_campaigns 
ADD INDEX IF NOT EXISTS idx_status (status),
ADD INDEX IF NOT EXISTS idx_created_at (created_at);

-- =======================================================
-- 5. CREATE LOGS DIRECTORY STRUCTURE
-- =======================================================

-- Note: This needs to be done at the file system level
-- CREATE DIRECTORY: /logs/ in your project root
-- SET PERMISSIONS: 755 for the logs directory

-- =======================================================
-- 6. VERIFICATION QUERIES
-- =======================================================

-- Run these queries to verify the migration was successful:

-- Check if tables were created
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    TABLE_COMMENT 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'autocrm' 
    AND TABLE_NAME IN ('scheduled_campaigns', 'schedule_log', 'email_recipients', 'email_campaigns')
ORDER BY TABLE_NAME;

-- Check foreign key constraints
SELECT 
    CONSTRAINT_NAME,
    TABLE_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE 
WHERE TABLE_SCHEMA = 'autocrm' 
    AND REFERENCED_TABLE_NAME IS NOT NULL
    AND TABLE_NAME IN ('scheduled_campaigns', 'schedule_log')
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Check indexes
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    INDEX_TYPE
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = 'autocrm' 
    AND TABLE_NAME IN ('scheduled_campaigns', 'schedule_log', 'email_recipients')
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- =======================================================
-- MIGRATION COMPLETE
-- =======================================================

-- Summary of changes:
-- ✅ Added scheduled_campaigns table for campaign scheduling
-- ✅ Added schedule_log table for action logging  
-- ✅ Enhanced email_recipients table if it didn't exist
-- ✅ Added missing columns to email_campaigns table
-- ✅ Added performance indexes
-- ✅ Added foreign key constraints for data integrity

-- Next steps after running this migration:
-- 1. Create logs/ directory with write permissions
-- 2. Update your cron jobs to run process_scheduled_campaigns.php
-- 3. Test the scheduling system with a sample campaign
-- 4. Monitor the schedule_log table for any issues
