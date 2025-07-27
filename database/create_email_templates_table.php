<?php
// Migration to create email templates table

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
    
    if ($driver === 'sqlite') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_templates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(100),
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                thumbnail TEXT,
                variables TEXT DEFAULT '[]',
                created_by INTEGER,
                is_public INTEGER DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
            )
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS email_templates (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255) NOT NULL,
                category VARCHAR(100),
                subject VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                thumbnail TEXT,
                variables JSON,
                created_by INT,
                is_public BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_category (category),
                INDEX idx_public (is_public)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    echo "Email templates table created successfully\n";
    
    // Insert default templates
    $templates = [
        [
            'name' => 'Welcome Email',
            'category' => 'Welcome',
            'subject' => 'Welcome to {{company_name}}!',
            'content' => '<h2>Welcome {{first_name}}!</h2>
<p>We\'re thrilled to have you join us at {{company_name}}.</p>
<p>Your journey with us begins now, and we\'re here to support you every step of the way.</p>
<p>If you have any questions, feel free to reach out to our support team.</p>
<p>Best regards,<br>The {{company_name}} Team</p>',
            'variables' => json_encode(['first_name', 'company_name'])
        ],
        [
            'name' => 'Newsletter Template',
            'category' => 'Newsletter',
            'subject' => '{{company_name}} Newsletter - {{month}} {{year}}',
            'content' => '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
    <h1 style="color: #333;">{{company_name}} Newsletter</h1>
    <h2 style="color: #666;">{{month}} {{year}} Edition</h2>
    
    <p>Dear {{first_name}},</p>
    
    <h3>In This Issue:</h3>
    <ul>
        <li>Latest Updates</li>
        <li>Featured Products</li>
        <li>Customer Success Stories</li>
        <li>Upcoming Events</li>
    </ul>
    
    <p>Thank you for being a valued member of our community!</p>
    
    <p>Best regards,<br>The {{company_name}} Team</p>
</div>',
            'variables' => json_encode(['first_name', 'company_name', 'month', 'year'])
        ],
        [
            'name' => 'Product Announcement',
            'category' => 'Announcement',
            'subject' => 'Introducing Our Latest Product: {{product_name}}',
            'content' => '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">
    <h1 style="color: #333;">Exciting News, {{first_name}}!</h1>
    
    <p>We\'re thrilled to announce the launch of <strong>{{product_name}}</strong>.</p>
    
    <h3>Key Features:</h3>
    <ul>
        <li>Feature 1</li>
        <li>Feature 2</li>
        <li>Feature 3</li>
    </ul>
    
    <p><a href="{{product_link}}" style="display: inline-block; padding: 12px 24px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px;">Learn More</a></p>
    
    <p>Questions? Reply to this email and we\'ll be happy to help!</p>
    
    <p>Best regards,<br>The {{company_name}} Team</p>
</div>',
            'variables' => json_encode(['first_name', 'product_name', 'product_link', 'company_name'])
        ]
    ];
    
    $stmt = $db->prepare("INSERT INTO email_templates (name, category, subject, content, variables, is_public) VALUES (?, ?, ?, ?, ?, 1)");
    
    foreach ($templates as $template) {
        try {
            $stmt->execute([
                $template['name'],
                $template['category'],
                $template['subject'],
                $template['content'],
                $template['variables']
            ]);
            echo "Inserted template: {$template['name']}\n";
        } catch (Exception $e) {
            echo "Template may already exist: {$template['name']}\n";
        }
    }
    
    echo "\nMigration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}