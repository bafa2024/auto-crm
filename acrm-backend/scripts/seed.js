require('dotenv').config();
const Database = require('../config/database');
const User = require('../models/User');
const Contact = require('../models/Contact');
const EmailCampaign = require('../models/EmailCampaign');

class DatabaseSeeder {
    constructor() {
        this.db = new Database();
        this.userModel = new User(this.db);
        this.contactModel = new Contact(this.db);
        this.campaignModel = new EmailCampaign(this.db);
    }

    async seed() {
        try {
            console.log('🌱 Starting database seeding...');
            
            // Seed users
            await this.seedUsers();
            
            // Seed contacts
            await this.seedContacts();
            
            // Seed email campaigns
            await this.seedEmailCampaigns();
            
            console.log('✅ Database seeding completed successfully!');
            
            // Close connection
            await this.db.close();
            
        } catch (error) {
            console.error('❌ Seeding failed:', error);
            process.exit(1);
        }
    }

    async seedUsers() {
        console.log('👥 Seeding users...');
        
        const users = [
            {
                email: 'admin@acrm.com',
                password: 'admin123',
                first_name: 'Admin',
                last_name: 'User',
                company_name: 'ACRM Company',
                phone: '+1234567890',
                role: 'admin',
                status: 'active'
            },
            {
                email: 'manager@acrm.com',
                password: 'manager123',
                first_name: 'John',
                last_name: 'Manager',
                company_name: 'ACRM Company',
                phone: '+1234567891',
                role: 'manager',
                status: 'active'
            },
            {
                email: 'agent@acrm.com',
                password: 'agent123',
                first_name: 'Jane',
                last_name: 'Agent',
                company_name: 'ACRM Company',
                phone: '+1234567892',
                role: 'agent',
                status: 'active'
            }
        ];

        for (const userData of users) {
            try {
                const existingUser = await this.userModel.findByEmail(userData.email);
                if (!existingUser) {
                    await this.userModel.create(userData);
                    console.log(`✅ Created user: ${userData.email}`);
                } else {
                    console.log(`⏭️  User already exists: ${userData.email}`);
                }
            } catch (error) {
                console.error(`❌ Failed to create user ${userData.email}:`, error.message);
            }
        }
    }

    async seedContacts() {
        console.log('📇 Seeding contacts...');
        
        const contacts = [
            {
                first_name: 'John',
                last_name: 'Doe',
                email: 'john.doe@example.com',
                phone: '+1234567890',
                company: 'Tech Corp',
                position: 'CEO',
                status: 'new',
                lead_source: 'website'
            },
            {
                first_name: 'Jane',
                last_name: 'Smith',
                email: 'jane.smith@example.com',
                phone: '+1234567891',
                company: 'Marketing Inc',
                position: 'Marketing Manager',
                status: 'contacted',
                lead_source: 'referral'
            },
            {
                first_name: 'Mike',
                last_name: 'Johnson',
                email: 'mike.johnson@example.com',
                phone: '+1234567892',
                company: 'Sales Co',
                position: 'Sales Director',
                status: 'qualified',
                lead_source: 'cold_call'
            },
            {
                first_name: 'Sarah',
                last_name: 'Wilson',
                email: 'sarah.wilson@example.com',
                phone: '+1234567893',
                company: 'Startup LLC',
                position: 'Founder',
                status: 'converted',
                lead_source: 'social_media'
            },
            {
                first_name: 'David',
                last_name: 'Brown',
                email: 'david.brown@example.com',
                phone: '+1234567894',
                company: 'Consulting Group',
                position: 'Senior Consultant',
                status: 'lost',
                lead_source: 'email_campaign'
            },
            {
                first_name: 'Lisa',
                last_name: 'Davis',
                email: 'lisa.davis@example.com',
                phone: '+1234567895',
                company: 'Digital Agency',
                position: 'Creative Director',
                status: 'new',
                lead_source: 'website'
            },
            {
                first_name: 'Robert',
                last_name: 'Miller',
                email: 'robert.miller@example.com',
                phone: '+1234567896',
                company: 'Finance Corp',
                position: 'CFO',
                status: 'contacted',
                lead_source: 'referral'
            },
            {
                first_name: 'Emily',
                last_name: 'Taylor',
                email: 'emily.taylor@example.com',
                phone: '+1234567897',
                company: 'Healthcare Inc',
                position: 'Operations Manager',
                status: 'qualified',
                lead_source: 'cold_call'
            },
            {
                first_name: 'Michael',
                last_name: 'Anderson',
                email: 'michael.anderson@example.com',
                phone: '+1234567898',
                company: 'Retail Chain',
                position: 'Store Manager',
                status: 'new',
                lead_source: 'social_media'
            },
            {
                first_name: 'Amanda',
                last_name: 'Thomas',
                email: 'amanda.thomas@example.com',
                phone: '+1234567899',
                company: 'Education Center',
                position: 'Director',
                status: 'contacted',
                lead_source: 'email_campaign'
            }
        ];

        for (const contactData of contacts) {
            try {
                const existingContact = await this.contactModel.findOne({ email: contactData.email });
                if (!existingContact) {
                    await this.contactModel.create(contactData);
                    console.log(`✅ Created contact: ${contactData.email}`);
                } else {
                    console.log(`⏭️  Contact already exists: ${contactData.email}`);
                }
            } catch (error) {
                console.error(`❌ Failed to create contact ${contactData.email}:`, error.message);
            }
        }
    }

    async seedEmailCampaigns() {
        console.log('📧 Seeding email campaigns...');
        
        const campaigns = [
            {
                name: 'Welcome Campaign',
                subject: 'Welcome to ACRM!',
                content: '<h1>Welcome to ACRM</h1><p>Thank you for joining our platform. We\'re excited to help you manage your customer relationships effectively.</p>',
                status: 'sent',
                total_recipients: 5,
                sent_count: 5,
                opened_count: 3,
                clicked_count: 1,
                user_id: 1
            },
            {
                name: 'Product Launch',
                subject: 'New Features Available',
                content: '<h1>New Features Available</h1><p>We\'ve added exciting new features to help you better manage your contacts and campaigns.</p>',
                status: 'draft',
                total_recipients: 0,
                sent_count: 0,
                opened_count: 0,
                clicked_count: 0,
                user_id: 1
            },
            {
                name: 'Monthly Newsletter',
                subject: 'ACRM Monthly Newsletter',
                content: '<h1>ACRM Monthly Newsletter</h1><p>Stay updated with the latest features and tips for using ACRM effectively.</p>',
                status: 'scheduled',
                scheduled_at: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000).toISOString(), // 7 days from now
                total_recipients: 10,
                sent_count: 0,
                opened_count: 0,
                clicked_count: 0,
                user_id: 1
            }
        ];

        for (const campaignData of campaigns) {
            try {
                await this.campaignModel.create(campaignData);
                console.log(`✅ Created campaign: ${campaignData.name}`);
            } catch (error) {
                console.error(`❌ Failed to create campaign ${campaignData.name}:`, error.message);
            }
        }
    }
}

// Run seeding if this file is executed directly
if (require.main === module) {
    const seeder = new DatabaseSeeder();
    seeder.seed();
}

module.exports = DatabaseSeeder; 