<?php
// setup_email_tables.php - Create email campaign database tables

echo "Setting up Email Campaign Database Tables\n";
echo "========================================\n\n";

// Load environment variables
$env = [];
if (file_exists(__DIR__ . '/.env')) {
    $envFile = file_get_contents(__DIR__ . '/.env');
    $envLines = explode("\n", $envFile);
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, '=') !== false && !str_starts_with($line, '#')) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value, '"\'');
        }
    }
    echo "✓ Environment file loaded\n";
} else {
    die("❌ .env file not found\n");
}

// Database connection
try {
    $dsn = "mysql:host=" . $env['DB_HOST'] . 
           ";port=" . ($env['DB_PORT'] ?? '3306') . 
           ";dbname=" . $env['DB_NAME'] . 
           ";charset=utf8mb4";
    
    $pdo = new PDO($dsn, $env['DB_USER'], $env['DB_PASS'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✓ Database connection successful\n\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage() . "\n");
}

// Create email campaigns table
echo "Creating email_campaigns table...\n";
$createCampaignsTable = "
CREATE TABLE IF NOT EXISTS email_campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    sender_name VARCHAR(100),
    sender_email VARCHAR(255),
    reply_to_email VARCHAR(255),
    campaign_type VARCHAR(50) DEFAULT 'bulk',
    status ENUM('draft', 'scheduled', 'sending', 'completed', 'paused', 'failed') DEFAULT 'draft',
    scheduled_at DATETIME,
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    opened_count INT DEFAULT 0,
    clicked_count INT DEFAULT 0,
    bounced_count INT DEFAULT 0,
    unsubscribed_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createCampaignsTable);
    echo "✓ email_campaigns table created\n";
} catch (Exception $e) {
    echo "⚠️  email_campaigns table error: " . $e->getMessage() . "\n";
}

// Create email recipients table
echo "\nCreating email_recipients table...\n";
$createRecipientsTable = "
CREATE TABLE IF NOT EXISTS email_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    company VARCHAR(255),
    custom_data JSON,
    status ENUM('pending', 'sent', 'opened', 'clicked', 'bounced', 'unsubscribed', 'failed') DEFAULT 'pending',
    sent_at DATETIME,
    opened_at DATETIME,
    clicked_at DATETIME,
    bounced_at DATETIME,
    unsubscribed_at DATETIME,
    tracking_id VARCHAR(64) UNIQUE,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE CASCADE,
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_tracking_id (tracking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createRecipientsTable);
    echo "✓ email_recipients table created\n";
} catch (Exception $e) {
    echo "⚠️  email_recipients table error: " . $e->getMessage() . "\n";
}

// Create email templates table
echo "\nCreating email_templates table...\n";
$createTemplatesTable = "
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    template_type VARCHAR(50) DEFAULT 'custom',
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_active (is_active),
    INDEX idx_type (template_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createTemplatesTable);
    echo "✓ email_templates table created\n";
} catch (Exception $e) {
    echo "⚠️  email_templates table error: " . $e->getMessage() . "\n";
}

// Create email uploads table
echo "\nCreating email_uploads table...\n";
$createUploadsTable = "
CREATE TABLE IF NOT EXISTS email_uploads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT,
    total_emails INT DEFAULT 0,
    valid_emails INT DEFAULT 0,
    invalid_emails INT DEFAULT 0,
    duplicate_emails INT DEFAULT 0,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_log TEXT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES email_campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_campaign (campaign_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createUploadsTable);
    echo "✓ email_uploads table created\n";
} catch (Exception $e) {
    echo "⚠️  email_uploads table error: " . $e->getMessage() . "\n";
}

// Create email tracking table
echo "\nCreating email_tracking table...\n";
$createTrackingTable = "
CREATE TABLE IF NOT EXISTS email_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    event_type ENUM('open', 'click', 'bounce', 'unsubscribe') NOT NULL,
    event_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE,
    INDEX idx_recipient (recipient_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createTrackingTable);
    echo "✓ email_tracking table created\n";
} catch (Exception $e) {
    echo "⚠️  email_tracking table error: " . $e->getMessage() . "\n";
}

// Create email queue table
echo "\nCreating email_queue table...\n";
$createQueueTable = "
CREATE TABLE IF NOT EXISTS email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_id INT NOT NULL,
    priority INT DEFAULT 5,
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    scheduled_for DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME,
    error_message TEXT,
    status ENUM('pending', 'processing', 'sent', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (recipient_id) REFERENCES email_recipients(id) ON DELETE CASCADE,
    INDEX idx_status_scheduled (status, scheduled_for),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    $pdo->exec($createQueueTable);
    echo "✓ email_queue table created\n";
} catch (Exception $e) {
    echo "⚠️  email_queue table error: " . $e->getMessage() . "\n";
}

// Create sample templates
echo "\n=== Creating Sample Email Templates ===\n";
$sampleTemplates = [
    [
        'Welcome Email',
        'Welcome to {{company_name}}!',
        '<h2>Hello {{name}},</h2>
<p>Welcome to our email list! We\'re excited to have you on board.</p>
<p>Here\'s what you can expect from us:</p>
<ul>
<li>Weekly newsletters with industry insights</li>
<li>Exclusive offers and promotions</li>
<li>Early access to new features</li>
</ul>
<p>Best regards,<br>{{sender_name}}</p>',
        'welcome',
        json_encode(['name', 'company_name', 'sender_name'])
    ],
    [
        'Newsletter Template',
        '{{company_name}} Newsletter - {{month}} {{year}}',
        '<h2>{{company_name}} Newsletter</h2>
<p>Dear {{name}},</p>
<p>Here are this month\'s highlights:</p>
{{content}}
<p>Thank you for being a valued subscriber!</p>
<p>Best regards,<br>{{sender_name}}</p>
<hr>
<p><small><a href="{{unsubscribe_link}}">Unsubscribe</a> | <a href="{{preferences_link}}">Update Preferences</a></small></p>',
        'newsletter',
        json_encode(['name', 'company_name', 'sender_name', 'month', 'year', 'content', 'unsubscribe_link', 'preferences_link'])
    ],
    [
        'Promotional Email',
        'Special Offer Just for You, {{name}}!',
        '<h2>Exclusive Offer for {{name}}</h2>
<p>We have a special offer just for you!</p>
<div style="background: #f0f0f0; padding: 20px; margin: 20px 0; text-align: center;">
<h3>{{offer_title}}</h3>
<p style="font-size: 24px; color: #e74c3c;">{{discount}}% OFF</p>
<p>Valid until: {{expiry_date}}</p>
<a href="{{cta_link}}" style="background: #3498db; color: white; padding: 10px 30px; text-decoration: none; border-radius: 5px;">{{cta_text}}</a>
</div>
<p>Don\'t miss out on this limited-time offer!</p>
<p>Best regards,<br>{{sender_name}}</p>',
        'promotional',
        json_encode(['name', 'offer_title', 'discount', 'expiry_date', 'cta_link', 'cta_text', 'sender_name'])
    ]
];

$stmt = $pdo->prepare("
    INSERT INTO email_templates (name, subject, content, template_type, variables, created_by) 
    VALUES (?, ?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
");

foreach ($sampleTemplates as $template) {
    try {
        $stmt->execute($template);
        echo "✓ Created template: " . $template[0] . "\n";
    } catch (Exception $e) {
        echo "⚠️  Template error: " . $e->getMessage() . "\n";
    }
}

// Create EmailCampaign model with proper methods
echo "\n=== Updating EmailCampaign Model ===\n";
$emailCampaignModel = '<?php
require_once "BaseModel.php";

class EmailCampaign extends BaseModel {
    protected $table = "email_campaigns";
    protected $fillable = [
        "name", "subject", "content", "sender_name", "sender_email", "reply_to_email",
        "campaign_type", "status", "scheduled_at", "created_by"
    ];
    
    public function addRecipients($campaignId, $recipients) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("
            INSERT INTO email_recipients (campaign_id, email, name, company, custom_data, tracking_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $added = 0;
        foreach ($recipients as $recipient) {
            $trackingId = uniqid("track_", true);
            $customData = isset($recipient["custom_data"]) ? json_encode($recipient["custom_data"]) : null;
            
            try {
                $stmt->execute([
                    $campaignId,
                    $recipient["email"],
                    $recipient["name"] ?? null,
                    $recipient["company"] ?? null,
                    $customData,
                    $trackingId
                ]);
                $added++;
            } catch (Exception $e) {
                // Skip duplicate emails
                continue;
            }
        }
        
        // Update total recipients count
        $this->updateRecipientCount($campaignId);
        
        return $added;
    }
    
    public function updateRecipientCount($campaignId) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("
            UPDATE email_campaigns 
            SET total_recipients = (
                SELECT COUNT(*) FROM email_recipients WHERE campaign_id = ?
            )
            WHERE id = ?
        ");
        
        return $stmt->execute([$campaignId, $campaignId]);
    }
    
    public function getWithStats($id) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("
            SELECT 
                c.*,
                COUNT(DISTINCT r.id) as total_recipients,
                SUM(CASE WHEN r.status = \'sent\' THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN r.status = \'opened\' THEN 1 ELSE 0 END) as opened_count,
                SUM(CASE WHEN r.status = \'clicked\' THEN 1 ELSE 0 END) as clicked_count,
                SUM(CASE WHEN r.status = \'bounced\' THEN 1 ELSE 0 END) as bounced_count,
                SUM(CASE WHEN r.status = \'unsubscribed\' THEN 1 ELSE 0 END) as unsubscribed_count
            FROM email_campaigns c
            LEFT JOIN email_recipients r ON c.id = r.campaign_id
            WHERE c.id = ?
            GROUP BY c.id
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getRecipients($campaignId, $status = null) {
        if (!$this->db) return [];
        
        $sql = "SELECT * FROM email_recipients WHERE campaign_id = ?";
        $params = [$campaignId];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}';

file_put_contents('models/EmailCampaign.php', $emailCampaignModel);
echo "✓ EmailCampaign model updated\n";

echo "\n✅ Email Campaign Database Setup Complete!\n";
echo "\nDatabase tables created:\n";
echo "- email_campaigns: Store campaign information\n";
echo "- email_recipients: Store recipient list for each campaign\n";
echo "- email_templates: Pre-made email templates\n";
echo "- email_uploads: Track uploaded Excel/CSV files\n";
echo "- email_tracking: Track opens, clicks, bounces\n";
echo "- email_queue: Email sending queue\n";
echo "\nSample templates created:\n";
echo "- Welcome Email\n";
echo "- Newsletter Template\n";
echo "- Promotional Email\n";
echo "\nExcel Upload Format:\n";
echo "Column A: Email Address (required)\n";
echo "Column B: Full Name (optional)\n";
echo "Column C: Company (optional)\n";
echo "Column D+: Custom fields (optional)\n";