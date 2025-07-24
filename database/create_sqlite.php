<?php
// create_sqlite.php - Create SQLite database for local testing

echo "Creating SQLite Database for AutoDial Pro\n";
echo "========================================\n\n";

$dbPath = __DIR__ . '/autocrm_local.db';

// Remove existing database if it exists
if (file_exists($dbPath)) {
    unlink($dbPath);
    echo "Removed existing database\n";
}

try {
    // Create SQLite database
    $pdo = new PDO("sqlite:$dbPath");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "1. Creating database structure...\n";
    
    // Create users table
    $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            company_name TEXT,
            phone TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            status TEXT DEFAULT 'active'
        )
    ");
    echo "✓ Users table created\n";
    
    // Create contacts table
    $pdo->exec("
        CREATE TABLE contacts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            first_name TEXT NOT NULL,
            last_name TEXT NOT NULL,
            email TEXT,
            phone TEXT,
            company TEXT,
            title TEXT,
            address TEXT,
            city TEXT,
            state TEXT,
            zip TEXT,
            country TEXT DEFAULT 'US',
            notes TEXT,
            tags TEXT,
            status TEXT DEFAULT 'active',
            last_contacted DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Contacts table created\n";
    
    // Create email_campaigns table
    $pdo->exec("
        CREATE TABLE email_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            from_name TEXT NOT NULL,
            from_email TEXT NOT NULL,
            email_content TEXT NOT NULL,
            status TEXT DEFAULT 'draft',
            scheduled_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sent_count INTEGER DEFAULT 0,
            opened_count INTEGER DEFAULT 0,
            clicked_count INTEGER DEFAULT 0,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Email campaigns table created\n";
    
    // Create email_templates table
    $pdo->exec("
        CREATE TABLE email_templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            subject TEXT NOT NULL,
            content TEXT NOT NULL,
            category TEXT DEFAULT 'general',
            is_active INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Email templates table created\n";
    
    // Create call_logs table for dialer functionality
    $pdo->exec("
        CREATE TABLE call_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            contact_id INTEGER NOT NULL,
            phone_number TEXT NOT NULL,
            call_type TEXT DEFAULT 'outbound',
            status TEXT NOT NULL,
            duration INTEGER DEFAULT 0,
            notes TEXT,
            call_date DATETIME DEFAULT CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (contact_id) REFERENCES contacts(id)
        )
    ");
    echo "✓ Call logs table created\n";
    
    // Create dialer_campaigns table
    $pdo->exec("
        CREATE TABLE dialer_campaigns (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            description TEXT,
            dialer_mode TEXT DEFAULT 'progressive',
            status TEXT DEFAULT 'active',
            max_lines INTEGER DEFAULT 1,
            dial_ratio REAL DEFAULT 1.0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Dialer campaigns table created\n";
    
    // Create bulk_uploads table
    $pdo->exec("
        CREATE TABLE bulk_uploads (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            filename TEXT NOT NULL,
            file_type TEXT NOT NULL,
            total_records INTEGER DEFAULT 0,
            processed_records INTEGER DEFAULT 0,
            successful_records INTEGER DEFAULT 0,
            failed_records INTEGER DEFAULT 0,
            status TEXT DEFAULT 'pending',
            error_log TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )
    ");
    echo "✓ Bulk uploads table created\n";

    // Create teams table
    $pdo->exec("
        CREATE TABLE teams (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            description TEXT,
            created_by INTEGER,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Teams table created\n";

    // Create team_members table
    $pdo->exec("
        CREATE TABLE team_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            role TEXT DEFAULT 'worker',
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Team members table created\n";

    // Create worker_privileges table
    $pdo->exec("
        CREATE TABLE worker_privileges (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            privilege TEXT NOT NULL,
            allowed INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    echo "✓ Worker privileges table created\n";
    
    echo "\n2. Adding sample data...\n";
    
    // Add admin user
    $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (first_name, last_name, email, password, company_name, phone) 
        VALUES ('Admin', 'User', 'admin@autocrm.com', '$hashedPassword', 'AutoDial Pro', '+1-555-0100')
    ");
    echo "✓ Admin user created (admin@autocrm.com / admin123)\n";
    
    // Add test user
    $testPassword = password_hash('test123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (first_name, last_name, email, password, company_name, phone) 
        VALUES ('John', 'Doe', 'john@example.com', '$testPassword', 'Test Company', '+1-555-0101')
    ");
    echo "✓ Test user created (john@example.com / test123)\n";
    
    // Add sample contacts
    $contacts = [
        ['Sarah', 'Johnson', 'sarah.johnson@acme.com', '+1-555-0201', 'Acme Corp', 'Sales Manager'],
        ['Mike', 'Davis', 'mike.davis@techstart.com', '+1-555-0202', 'TechStart Inc', 'CTO'],
        ['Emily', 'Brown', 'emily.brown@services.com', '+1-555-0203', 'Pro Services LLC', 'Director'],
        ['David', 'Wilson', 'david.wilson@consulting.com', '+1-555-0204', 'Wilson Consulting', 'Owner'],
        ['Lisa', 'Anderson', 'lisa.anderson@solutions.com', '+1-555-0205', 'Smart Solutions', 'VP Sales'],
        ['Mark', 'Taylor', 'mark.taylor@innovations.com', '+1-555-0206', 'Innovation Labs', 'Product Manager'],
        ['Jennifer', 'Garcia', 'jennifer.garcia@global.com', '+1-555-0207', 'Global Enterprises', 'Account Executive'],
        ['Robert', 'Martinez', 'robert.martinez@dynamics.com', '+1-555-0208', 'Business Dynamics', 'CEO'],
        ['Amanda', 'Thompson', 'amanda.thompson@ventures.com', '+1-555-0209', 'Tech Ventures', 'Founder'],
        ['Chris', 'Lee', 'chris.lee@systems.com', '+1-555-0210', 'Advanced Systems', 'Lead Developer']
    ];
    
    foreach ($contacts as $contact) {
        $pdo->exec("
            INSERT INTO contacts (user_id, first_name, last_name, email, phone, company, title, status) 
            VALUES (1, '$contact[0]', '$contact[1]', '$contact[2]', '$contact[3]', '$contact[4]', '$contact[5]', 'active')
        ");
    }
    echo "✓ Added 10 sample contacts\n";
    
    // Add sample call logs
    $callStatuses = ['connected', 'no-answer', 'busy', 'voicemail', 'failed'];
    for ($i = 1; $i <= 20; $i++) {
        $contactId = rand(1, 10);
        $status = $callStatuses[array_rand($callStatuses)];
        $duration = $status === 'connected' ? rand(30, 300) : 0;
        $callDate = date('Y-m-d H:i:s', strtotime("-$i hours"));
        
        $pdo->exec("
            INSERT INTO call_logs (user_id, contact_id, phone_number, call_type, status, duration, call_date) 
            VALUES (1, $contactId, '+1-555-0" . str_pad($contactId + 200, 3, '0', STR_PAD_LEFT) . "', 'outbound', '$status', $duration, '$callDate')
        ");
    }
    echo "✓ Added 20 sample call logs\n";
    
    // Add sample email campaign
    $pdo->exec("
        INSERT INTO email_campaigns (user_id, name, subject, from_name, from_email, email_content, status, sent_count, opened_count, clicked_count) 
        VALUES (1, 'Welcome Campaign', 'Welcome to AutoDial Pro!', 'AutoDial Pro Team', 'hello@autodialpro.com', 
                'Dear {{name}},\n\nWelcome to AutoDial Pro! We''re excited to help you boost your sales productivity.\n\nBest regards,\nThe AutoDial Pro Team', 
                'active', 150, 45, 12)
    ");
    echo "✓ Added sample email campaign\n";
    
    // Add sample email templates
    $templates = [
        ['Cold Outreach', 'Quick Introduction', 'Hi {{name}}, I wanted to reach out regarding {{company}}...'],
        ['Follow Up', 'Following up on our conversation', 'Hi {{name}}, Thanks for taking the time to speak with me yesterday...'],
        ['Demo Request', 'AutoDial Pro Demo Request', 'Hi {{name}}, I would love to show you how AutoDial Pro can help {{company}}...']
    ];
    
    foreach ($templates as $template) {
        $stmt = $pdo->prepare("
            INSERT INTO email_templates (user_id, name, subject, content, category) 
            VALUES (1, ?, ?, ?, 'sales')
        ");
        $stmt->execute([$template[0], $template[1], $template[2]]);
    }
    echo "✓ Added 3 sample email templates\n";
    
    // Add sample dialer campaign
    $pdo->exec("
        INSERT INTO dialer_campaigns (user_id, name, description, dialer_mode, status, max_lines, dial_ratio) 
        VALUES (1, 'Sales Outreach Q4', 'Q4 sales outreach campaign for new prospects', 'progressive', 'active', 2, 1.2)
    ");
    echo "✓ Added sample dialer campaign\n";
    
    echo "\n3. Database creation completed successfully!\n";
    echo "Database file: " . $dbPath . "\n";
    echo "File size: " . number_format(filesize($dbPath)) . " bytes\n";
    
    // Test connection
    $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $contactCount = $pdo->query("SELECT COUNT(*) FROM contacts")->fetchColumn();
    $callCount = $pdo->query("SELECT COUNT(*) FROM call_logs")->fetchColumn();
    
    echo "\nDatabase contents:\n";
    echo "- Users: $userCount\n";
    echo "- Contacts: $contactCount\n";
    echo "- Call logs: $callCount\n";
    
    echo "\nLogin credentials:\n";
    echo "- Admin: admin@autocrm.com / admin123\n";
    echo "- Test User: john@example.com / test123\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ SQLite database ready for local testing!\n";
?>