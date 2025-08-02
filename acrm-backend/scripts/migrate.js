require('dotenv').config();
const Database = require('../config/database');
const fs = require('fs');
const path = require('path');

class DatabaseMigrator {
    constructor() {
        this.db = new Database();
    }

    async migrate() {
        try {
            console.log('🔄 Starting database migration...');
            
            const connection = await this.db.getConnection();
            const dbType = this.db.getDatabaseType();
            
            console.log(`📊 Database type: ${dbType}`);
            console.log(`🌍 Environment: ${this.db.getEnvironment()}`);

            // Create tables
            await this.createUsersTable(connection, dbType);
            await this.createContactsTable(connection, dbType);
            await this.createEmailCampaignsTable(connection, dbType);
            await this.createEmailTemplatesTable(connection, dbType);
            await this.createEmailRecipientsTable(connection, dbType);
            await this.createEmailTrackingTable(connection, dbType);
            await this.createPasswordResetTable(connection, dbType);
            await this.createOTPTable(connection, dbType);
            await this.createSmtpSettingsTable(connection, dbType);

            console.log('✅ Database migration completed successfully!');
            
            // Close connection
            await this.db.close();
            
        } catch (error) {
            console.error('❌ Migration failed:', error);
            process.exit(1);
        }
    }

    async createUsersTable(connection, dbType) {
        console.log('📝 Creating users table...');
        
        const roleType = dbType === 'sqlite' ? 'TEXT CHECK(role IN ("admin", "agent", "manager"))' : 'ENUM("admin", "agent", "manager")';
        const statusType = dbType === 'sqlite' ? 'TEXT CHECK(status IN ("active", "inactive"))' : 'ENUM("active", "inactive")';
        const updatedAt = dbType === 'sqlite' ? 'DEFAULT CURRENT_TIMESTAMP' : 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                email VARCHAR(255) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                company_name VARCHAR(255),
                phone VARCHAR(50),
                role ${roleType} DEFAULT 'admin',
                status ${statusType} DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP ${updatedAt}
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createContactsTable(connection, dbType) {
        console.log('📝 Creating contacts table...');
        
        const statusType = dbType === 'sqlite' ? 'TEXT CHECK(status IN ("new", "contacted", "qualified", "converted", "lost"))' : 'ENUM("new", "contacted", "qualified", "converted", "lost")';
        const updatedAt = dbType === 'sqlite' ? 'DEFAULT CURRENT_TIMESTAMP' : 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS contacts (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                phone VARCHAR(50),
                company VARCHAR(255),
                position VARCHAR(100),
                status ${statusType} DEFAULT 'new',
                lead_source VARCHAR(100) DEFAULT 'manual',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP ${updatedAt}
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createEmailCampaignsTable(connection, dbType) {
        console.log('📝 Creating email_campaigns table...');
        
        const statusType = dbType === 'sqlite' ? 'TEXT CHECK(status IN ("draft", "scheduled", "active", "sent", "cancelled"))' : 'ENUM("draft", "scheduled", "active", "sent", "cancelled")';
        const updatedAt = dbType === 'sqlite' ? 'DEFAULT CURRENT_TIMESTAMP' : 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS email_campaigns (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                status ${statusType} DEFAULT 'draft',
                scheduled_at TIMESTAMP NULL,
                sent_at TIMESTAMP NULL,
                total_recipients INTEGER DEFAULT 0,
                sent_count INTEGER DEFAULT 0,
                opened_count INTEGER DEFAULT 0,
                clicked_count INTEGER DEFAULT 0,
                user_id INTEGER,
                template_id INTEGER,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP ${updatedAt},
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createEmailTemplatesTable(connection, dbType) {
        console.log('📝 Creating email_templates table...');
        
        const updatedAt = dbType === 'sqlite' ? 'DEFAULT CURRENT_TIMESTAMP' : 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS email_templates (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                name VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                user_id INTEGER,
                is_default BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP ${updatedAt},
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createEmailRecipientsTable(connection, dbType) {
        console.log('📝 Creating email_recipients table...');
        
        const statusType = dbType === 'sqlite' ? 'TEXT CHECK(status IN ("pending", "sent", "failed", "bounced"))' : 'ENUM("pending", "sent", "failed", "bounced")';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS email_recipients (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                campaign_id INTEGER NOT NULL,
                contact_id INTEGER NOT NULL,
                email VARCHAR(255) NOT NULL,
                status ${statusType} DEFAULT 'pending',
                sent_at TIMESTAMP NULL,
                opened_at TIMESTAMP NULL,
                clicked_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
                FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE CASCADE
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createEmailTrackingTable(connection, dbType) {
        console.log('📝 Creating email_tracking table...');
        
        const eventType = dbType === 'sqlite' ? 'TEXT CHECK(event_type IN ("sent", "opened", "clicked", "bounced"))' : 'ENUM("sent", "opened", "clicked", "bounced")';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS email_tracking (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                recipient_id INTEGER NOT NULL,
                event_type ${eventType} NOT NULL,
                event_data JSON,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createPasswordResetTable(connection, dbType) {
        console.log('📝 Creating password_resets table...');
        
        const sql = `
            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) UNIQUE NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createOTPTable(connection, dbType) {
        console.log('📝 Creating otp table...');
        
        const sql = `
            CREATE TABLE IF NOT EXISTS otp (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                email VARCHAR(255) NOT NULL,
                otp_code VARCHAR(6) NOT NULL,
                expires_at TIMESTAMP NOT NULL,
                is_used BOOLEAN DEFAULT FALSE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async createSmtpSettingsTable(connection, dbType) {
        console.log('📝 Creating smtp_settings table...');
        
        const encryptionType = dbType === 'sqlite' ? 'TEXT CHECK(encryption IN ("tls", "ssl", "none"))' : 'ENUM("tls", "ssl", "none")';
        const updatedAt = dbType === 'sqlite' ? 'DEFAULT CURRENT_TIMESTAMP' : 'DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
        
        const sql = `
            CREATE TABLE IF NOT EXISTS smtp_settings (
                id INTEGER PRIMARY KEY ${dbType === 'sqlite' ? 'AUTOINCREMENT' : 'AUTO_INCREMENT'},
                host VARCHAR(255) NOT NULL,
                port INTEGER NOT NULL,
                username VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                encryption ${encryptionType} DEFAULT 'tls',
                from_email VARCHAR(255) NOT NULL,
                from_name VARCHAR(255),
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP ${updatedAt}
            )
        `;

        await this.executeSQL(connection, dbType, sql);
    }

    async executeSQL(connection, dbType, sql) {
        if (dbType === 'sqlite') {
            return new Promise((resolve, reject) => {
                connection.run(sql, (err) => {
                    if (err) {
                        reject(err);
                    } else {
                        resolve();
                    }
                });
            });
        } else {
            await connection.execute(sql);
        }
    }
}

// Run migration if this file is executed directly
if (require.main === module) {
    const migrator = new DatabaseMigrator();
    migrator.migrate();
}

module.exports = DatabaseMigrator; 