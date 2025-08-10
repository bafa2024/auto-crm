<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include base path configuration
require_once 'config/base_path.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: " . base_path('login'));
    exit;
}

require_once 'config/database.php';
$database = new Database();
require_once 'services/EmailCampaignService.php';

$message = '';
$messageType = '';

// Handle campaign creation
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $campaignService = new EmailCampaignService($database);
        
        switch ($_POST['action']) {
            case 'create_campaign':
                $campaignData = [
                    'user_id' => $_SESSION['user_id'],
                    'name' => $_POST['campaign_name'],
                    'subject' => $_POST['email_subject'],
                    'content' => $_POST['email_content'],
                    'sender_name' => $_POST['sender_name'],
                    'sender_email' => $_POST['sender_email'],
                    'schedule_type' => $_POST['schedule_type'],
                    'schedule_date' => $_POST['schedule_date'] ?? null,
                    'frequency' => $_POST['frequency'] ?? null,
                    'status' => 'draft'
                ];
                
                $result = $campaignService->createCampaign($campaignData);
                if ($result['success']) {
                    $message = "Campaign created successfully! Campaign ID: " . $result['campaign_id'];
                    $messageType = 'success';
                    
                    // Handle different schedule types
                    if ($_POST['schedule_type'] === 'immediate') {
                        // If immediate, send campaign now
                        $message .= "<br><strong>Sending emails immediately...</strong>";
                        // Get all unsent recipients for this campaign
                        $unsentRecipients = $campaignService->getAllCampaignRecipients($result['campaign_id']);
                        $recipientIds = array_column($unsentRecipients, 'id');
                        if (!empty($recipientIds)) {
                            $sendResult = $campaignService->sendCampaign($result['campaign_id'], $recipientIds);
                            if ($sendResult['success']) {
                                $message .= "<br>Immediate campaign sent successfully to " . count($recipientIds) . " recipients!";
                            } else {
                                $message .= "<br>Immediate campaign sending failed: " . $sendResult['message'];
                            }
                        } else {
                            $message .= "<br>No unsent recipients found for immediate sending.";
                        }
                    } elseif ($_POST['schedule_type'] === 'send_later') {
                        // For send_later, just create the campaign without sending
                        $message .= "<br><strong>Campaign saved as draft.</strong> Use the 'Send' button when ready to send emails.";
                    } elseif ($_POST['schedule_type'] === 'scheduled') {
                        $message .= "<br>Campaign scheduled for: " . date('M d, Y g:i A', strtotime($_POST['schedule_date']));
                    }
                } else {
                    $message = 'Campaign creation failed: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
                
            case 'edit_campaign':
                $campaignId = $_POST['campaign_id'];
                $campaignData = [
                    'name' => $_POST['campaign_name'],
                    'subject' => $_POST['email_subject'],
                    'content' => $_POST['email_content'],
                    'sender_name' => $_POST['sender_name'],
                    'sender_email' => $_POST['sender_email'],
                    'schedule_type' => $_POST['schedule_type'],
                    'schedule_date' => $_POST['schedule_date'] ?? null,
                    'frequency' => $_POST['frequency'] ?? null,
                    'status' => $_POST['status'] ?? 'draft'
                ];
                
                $result = $campaignService->editCampaign($campaignId, $campaignData);
                if ($result['success']) {
                    $message = "Campaign updated successfully!";
                    $messageType = 'success';
                } else {
                    $message = 'Campaign update failed: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
                
            case 'send_campaign':
                $campaignId = $_POST['campaign_id'];
                $recipientIds = $_POST['recipient_ids'] ?? [];
                // Convert recipient IDs to integers
                $recipientIds = array_map('intval', $recipientIds);
                $batchSize = intval($_POST['batch_size'] ?? 200);
                
                // Enforce batch size limit
                if (count($recipientIds) > $batchSize) {
                    $recipientIds = array_slice($recipientIds, 0, $batchSize);
                    $message = "Note: Limited to $batchSize recipients as per batch size setting.<br>";
                } else {
                    $message = '';
                }
                
                // DEBUG: Log the number of recipient IDs received
                error_log('DEBUG: Received ' . count($recipientIds) . ' recipient_ids in POST (batch size: ' . $batchSize . ')');
                $message .= '<b>DEBUG:</b> Sending to ' . count($recipientIds) . ' recipients (batch size: ' . $batchSize . ').<br>';
                
                $result = $campaignService->sendCampaign($campaignId, $recipientIds);
                if ($result['success']) {
                    $message .= "Emails successfully sent.";
                    $messageType = 'success';
                } else {
                    $message .= 'Campaign sending failed: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
                

            case 'schedule_campaign':
                $campaignId = $_POST['campaign_id'];
                $scheduleType = $_POST['schedule_type'];
                $scheduleDate = $_POST['schedule_date'] ?? null;
                $frequency = $_POST['frequency'] ?? null;
                $emailSubject = $_POST['email_subject'];
                $emailContent = $_POST['email_content'];
                $recipientIds = $_POST['recipient_ids'] ?? [];
                // Convert recipient IDs to integers
                $recipientIds = array_map('intval', $recipientIds);
                
                // Validate schedule data
                if ($scheduleType === 'scheduled' && empty($scheduleDate)) {
                    $message = 'Schedule date is required for scheduled campaigns.';
                    $messageType = 'danger';
                    break;
                }
                
                if ($scheduleType === 'recurring' && empty($frequency)) {
                    $message = 'Frequency is required for recurring campaigns.';
                    $messageType = 'danger';
                    break;
                }
                
                // Validate recipient selection
                if (empty($recipientIds)) {
                    $message = 'Please select at least one recipient for the campaign.';
                    $messageType = 'danger';
                    break;
                }
                
                // Update campaign with schedule information
                $updateData = [
                    'name' => $_POST['campaign_name'] ?? '',
                    'schedule_type' => $scheduleType,
                    'schedule_date' => $scheduleDate,
                    'frequency' => $frequency,
                    'subject' => $emailSubject,
                    'content' => $emailContent,
                    'sender_name' => $_POST['sender_name'] ?? 'ACRM System',
                    'sender_email' => $_POST['sender_email'] ?? 'noreply@acrm.com',
                    'status' => ($scheduleType === 'immediate') ? 'draft' : 'scheduled'
                ];
                
                $result = $campaignService->editCampaign($campaignId, $updateData);
                if ($result['success']) {
                    // Store the selected recipients for the scheduled campaign
                    if ($scheduleType !== 'immediate' && !empty($recipientIds)) {
                        // Store recipient IDs for scheduled processing
                        require_once 'services/ScheduledCampaignService.php';
                        $scheduledService = new ScheduledCampaignService($database);
                        $scheduledService->storeScheduledRecipients($campaignId, $recipientIds);
                    }
                    // If immediate sending is selected, send the campaign now
                    if ($scheduleType === 'immediate') {
                        $sendResult = $campaignService->sendCampaign($campaignId, $recipientIds);
                        if ($sendResult['success']) {
                            $message = "Campaign scheduled and sent immediately to " . count($recipientIds) . " recipients!";
                        } else {
                            $message = "Campaign scheduled but sending failed: " . $sendResult['message'];
                        }
                    } else {
                        $message = "Campaign scheduled successfully!";
                        if ($scheduleType === 'scheduled') {
                            $message .= " Campaign will be sent on " . date('M d, Y g:i A', strtotime($scheduleDate)) . " to " . count($recipientIds) . " recipients.";
                        } elseif ($scheduleType === 'recurring') {
                            $message .= " Campaign will be sent " . $frequency . " starting on " . date('M d, Y g:i A', strtotime($scheduleDate)) . " to " . count($recipientIds) . " recipients.";
                        }
                    }
                    $messageType = 'success';
                } else {
                    $message = 'Failed to schedule campaign: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
            case 'delete_campaign':
                $campaignId = $_POST['campaign_id'];
                $result = $campaignService->deleteCampaign($campaignId);
                if ($result['success']) {
                    $message = 'Campaign deleted successfully!';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to delete campaign: ' . $result['message'];
                    $messageType = 'danger';
                }
                break;
        }
    }
}

// Get campaigns
$campaigns = [];
try {
    $stmt = $database->getConnection()->query("SELECT * FROM email_campaigns ORDER BY created_at DESC");
    $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}

// Get recipients for selection
$recipients = [];
try {
    $stmt = $database->getConnection()->query("SELECT id, email, name, company FROM email_recipients ORDER BY created_at DESC");
    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}

// Get email templates
$templates = [];
try {
    // Create templates table if it doesn't exist
    $dbType = $database->getDatabaseType();
    if ($dbType === 'sqlite') {
        $database->getConnection()->exec("
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
        $database->getConnection()->exec("
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
    
    // Check if templates exist, if not insert defaults
    $count = $database->getConnection()->query("SELECT COUNT(*) FROM email_templates")->fetchColumn();
    if ($count == 0) {
        // Insert default templates
        $defaultTemplates = [
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
        
        $stmt = $database->getConnection()->prepare("INSERT INTO email_templates (name, category, subject, content, variables, is_public) VALUES (?, ?, ?, ?, ?, 1)");
        foreach ($defaultTemplates as $template) {
            try {
                $stmt->execute([
                    $template['name'],
                    $template['category'],
                    $template['subject'],
                    $template['content'],
                    $template['variables']
                ]);
            } catch (Exception $e) {
                // Template may already exist
            }
        }
    }
    
    // Get all templates
    $stmt = $database->getConnection()->query("SELECT * FROM email_templates WHERE is_public = 1 OR created_by = {$_SESSION['user_id']} ORDER BY category, name");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Ignore error if table doesn't exist
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Campaigns - ACRM</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            overflow-y: auto;
            z-index: 100;
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #ffffff;
        }
        .sidebar-link {
            color: #495057;
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            margin: 4px 10px;
            transition: all 0.3s;
        }
        .sidebar-link:hover {
            background-color: #e9ecef;
            color: #212529;
        }
        .sidebar-link.active {
            background-color: #5B5FDE;
            color: white;
        }
        .sidebar-link i {
            margin-right: 10px;
            width: 20px;
        }
        .campaign-card {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .campaign-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .recipient-item {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .recipient-item:hover {
            background-color: #f8f9fa;
        }
        .recipient-item.selected {
            background-color: #e3f2fd;
            border-color: #2196f3;
        }
        .recipient-item.border-warning {
            border-color: #ffc107;
            background-color: #fff8e1;
        }
        .recipients-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .badge {
            font-size: 0.75rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'views/components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Email Campaigns</h1>
                    <p class="text-muted">Create and manage email marketing campaigns</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="scheduled_campaigns.php" class="btn btn-outline-info">
                        <i class="bi bi-calendar-event"></i> Scheduled
                    </a>
                    <button class="btn btn-outline-primary" onclick="showReports()">
                        <i class="bi bi-graph-up"></i> Reports
                    </button>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                        <i class="bi bi-plus-circle"></i> New Campaign
                    </button>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Your Campaigns</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($campaigns)): ?>
                                <?php foreach ($campaigns as $campaign): ?>
                                <div class="campaign-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($campaign['name']); ?></h6>
                                            <p class="text-muted mb-2"><?php echo htmlspecialchars($campaign['subject']); ?></p>
                                            <div class="d-flex gap-3">
                                                <span class="badge bg-<?php echo $campaign['status'] === 'active' ? 'success' : ($campaign['status'] === 'draft' ? 'warning' : 'secondary'); ?>">
                                                    <?php echo ucfirst($campaign['status']); ?>
                                                </span>
                                                <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($campaign['created_at'])); ?></small>
                                            </div>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="editCampaign(<?php echo $campaign['id']; ?>)">
                                                <i class="bi bi-pencil"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-primary" onclick="sendCampaign(<?php echo $campaign['id']; ?>)">
                                                <i class="bi bi-send"></i> Send
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteCampaign(<?php echo $campaign['id']; ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                            <a class="btn btn-sm btn-info" href="campaign_progress.php?id=<?php echo $campaign['id']; ?>">
                                                <i class="bi bi-graph-up"></i> View Progress
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-envelope display-1 text-muted"></i>
                                    <h5 class="mt-3">No campaigns yet</h5>
                                    <p class="text-muted">Create your first email campaign to get started</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                                        <i class="bi bi-plus-circle"></i> Create Campaign
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Stats</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <h4 class="text-primary"><?php echo count($campaigns); ?></h4>
                                    <small class="text-muted">Total Campaigns</small>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success"><?php echo count($recipients); ?></h4>
                                    <small class="text-muted">Total Recipients</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Campaign Modal -->
    <div class="modal fade" id="createCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createCampaignForm">
                    <input type="hidden" name="action" value="create_campaign">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> <strong>Tip:</strong> Select "Send Later" to create your campaign without sending emails immediately. You can then review and send to specific recipients when ready.
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="campaign_name" class="form-label">Campaign Name</label>
                                    <input type="text" class="form-control" id="campaign_name" name="campaign_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="schedule_type" class="form-label">Schedule Type</label>
                                    <select class="form-select" id="schedule_type" name="schedule_type" required>
                                        <option value="send_later" selected>Send Later (Manual)</option>
                                        <option value="immediate">Send Immediately</option>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="recurring">Recurring</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="scheduleOptions" style="display: none;">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="schedule_date" class="form-label">Schedule Date</label>
                                    <input type="datetime-local" class="form-control" id="schedule_date" name="schedule_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="frequency" class="form-label">Frequency</label>
                                    <select class="form-select" id="frequency" name="frequency">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sender_name" class="form-label">Sender Name</label>
                                    <input type="text" class="form-control" id="sender_name" name="sender_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sender_email" class="form-label">Sender Email</label>
                                    <input type="email" class="form-control" id="sender_email" name="sender_email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="template_id" class="form-label">Email Template (Optional)</label>
                            <select class="form-select" id="template_id" name="template_id" onchange="loadTemplate()">
                                <option value="">-- Select a template --</option>
                                <?php 
                                $currentCategory = '';
                                foreach ($templates as $template): 
                                    if ($template['category'] != $currentCategory):
                                        if ($currentCategory != ''): ?>
                                            </optgroup>
                                        <?php endif;
                                        $currentCategory = $template['category'];
                                        ?>
                                        <optgroup label="<?php echo htmlspecialchars($currentCategory); ?>">
                                    <?php endif; ?>
                                    <option value="<?php echo $template['id']; ?>" 
                                        data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                                        data-content="<?php echo htmlspecialchars($template['content']); ?>"
                                        data-variables="<?php echo htmlspecialchars($template['variables']); ?>">
                                        <?php echo htmlspecialchars($template['name']); ?>
                                    </option>
                                <?php endforeach; 
                                if ($currentCategory != ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Select a template to pre-fill subject and content</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject" required placeholder="Use {{variable_name}} for personalization">
                            <div id="subject-preview" class="mt-2 small text-muted" style="display: none;">
                                <strong>Preview:</strong> <span id="subject-preview-text"></span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="content_type" class="form-label">Content Type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="content_type" id="content_type_html" value="html" checked>
                                <label class="btn btn-outline-primary" for="content_type_html">HTML</label>
                                
                                <input type="radio" class="btn-check" name="content_type" id="content_type_text" value="text">
                                <label class="btn btn-outline-primary" for="content_type_text">Plain Text</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_content" class="form-label">Email Content</label>
                            <div id="template-variables" class="mb-2" style="display: none;">
                                <small class="text-muted">Available variables: <span id="variable-list"></span></small>
                            </div>
                            <textarea class="form-control" id="email_content" name="email_content" rows="10" required placeholder="Enter your email content here... Use {{variable_name}} for personalization"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="previewEmail()">
                                <i class="bi bi-eye"></i> Preview Email
                            </button>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Send Campaign Modal -->
    <div class="modal fade" id="sendCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Send Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="sendCampaignForm">
                    <input type="hidden" name="action" value="send_campaign">
                    <input type="hidden" name="campaign_id" id="send_campaign_id">
                    <div class="modal-body">
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> <strong>Recipient Selection:</strong><br>
                            • Select contacts from your contact list to send this campaign<br>
                            • Contacts already sent this campaign are marked as "Already Sent"<br>
                            • Failed contacts can be re-selected to retry sending<br>
                            • Use search to quickly find specific contacts
                        </div>
                        
                        <!-- Send Options -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Contact Filter</label>
                                <select class="form-select" id="contactFilter" onchange="applyContactFilter()">
                                    <option value="new_only">New contacts only (never sent this campaign)</option>
                                    <option value="include_sent">Include already sent contacts</option>
                                    <option value="failed_only">Failed contacts only (retry)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Batch Size</label>
                                <select class="form-select" name="batch_size" id="batchSize">
                                    <option value="100">100 emails</option>
                                    <option value="200" selected>200 emails</option>
                                    <option value="500">500 emails</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Select Recipients</label>
                                <small class="text-muted" id="totalRecipientsCount">0 contacts</small>
                            </div>
                            
                            <!-- Search and Select All -->
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <input type="text" class="form-control" id="recipientSearch" placeholder="Search contacts by email, name, or company...">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllFiltered()">
                                        <i class="bi bi-check-all"></i> Select Filtered
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllRecipients()">
                                        <i class="bi bi-x-circle"></i> Clear All
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Selected Count -->
                            <div class="mb-2">
                                <small class="text-primary" id="selectedCount">0 contacts selected</small>
                                <small class="text-muted ms-3" id="searchResultInfo" style="display: none;"></small>
                            </div>
                            
                            <!-- The recipients-list div is inside the form, so checkboxes will be submitted -->
                            <div class="recipients-list" id="recipientsList" style="max-height: 400px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 8px; padding: 10px;">
                                <!-- Recipients will be loaded here by JS -->
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Campaign Modal -->
    <div class="modal fade" id="editCampaignModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_campaign">
                    <input type="hidden" name="campaign_id" id="edit_campaign_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_campaign_name" class="form-label">Campaign Name</label>
                                    <input type="text" class="form-control" id="edit_campaign_name" name="campaign_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_schedule_type" class="form-label">Schedule Type</label>
                                    <select class="form-select" id="edit_schedule_type" name="schedule_type" required>
                                        <option value="send_later">Send Later (Manual)</option>
                                        <option value="immediate">Send Immediately</option>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="recurring">Recurring</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="editScheduleOptions" style="display: none;">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_schedule_date" class="form-label">Schedule Date</label>
                                    <input type="datetime-local" class="form-control" id="edit_schedule_date" name="schedule_date">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_frequency" class="form-label">Frequency</label>
                                    <select class="form-select" id="edit_frequency" name="frequency">
                                        <option value="daily">Daily</option>
                                        <option value="weekly">Weekly</option>
                                        <option value="monthly">Monthly</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sender_name" class="form-label">Sender Name</label>
                                    <input type="text" class="form-control" id="edit_sender_name" name="sender_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sender_email" class="form-label">Sender Email</label>
                                    <input type="email" class="form-control" id="edit_sender_email" name="sender_email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_template_id" class="form-label">Email Template (Optional)</label>
                            <select class="form-select" id="edit_template_id" name="template_id" onchange="loadEditTemplate()">
                                <option value="">-- Select a template --</option>
                                <?php 
                                $currentCategory = '';
                                foreach ($templates as $template): 
                                    if ($template['category'] != $currentCategory):
                                        if ($currentCategory != ''): ?>
                                            </optgroup>
                                        <?php endif;
                                        $currentCategory = $template['category'];
                                        ?>
                                        <optgroup label="<?php echo htmlspecialchars($currentCategory); ?>">
                                    <?php endif; ?>
                                    <option value="<?php echo $template['id']; ?>" 
                                        data-subject="<?php echo htmlspecialchars($template['subject']); ?>"
                                        data-content="<?php echo htmlspecialchars($template['content']); ?>"
                                        data-variables="<?php echo htmlspecialchars($template['variables']); ?>">
                                        <?php echo htmlspecialchars($template['name']); ?>
                                    </option>
                                <?php endforeach; 
                                if ($currentCategory != ''): ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <small class="form-text text-muted">Select a template to replace current content</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="edit_email_subject" name="email_subject" required placeholder="Use {{variable_name}} for personalization">
                            <div id="edit-subject-preview" class="mt-2 small text-muted" style="display: none;">
                                <strong>Preview:</strong> <span id="edit-subject-preview-text"></span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_content_type" class="form-label">Content Type</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="edit_content_type" id="edit_content_type_html" value="html" checked>
                                <label class="btn btn-outline-primary" for="edit_content_type_html">HTML</label>
                                
                                <input type="radio" class="btn-check" name="edit_content_type" id="edit_content_type_text" value="text">
                                <label class="btn btn-outline-primary" for="edit_content_type_text">Plain Text</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email_content" class="form-label">Email Content</label>
                            <div id="edit-template-variables" class="mb-2" style="display: none;">
                                <small class="text-muted">Available variables: <span id="edit-variable-list"></span></small>
                            </div>
                            <textarea class="form-control" id="edit_email_content" name="email_content" rows="10" required placeholder="Enter your email content here... Use {{variable_name}} for personalization"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="previewEditEmail()">
                                <i class="bi bi-eye"></i> Preview Email
                            </button>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Campaign Status</label>
                            <select class="form-select" id="edit_status" name="status">
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Campaign</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Batch Progress Modal -->
    <div class="modal fade" id="batchProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sending Campaign in Batches</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info mb-3">
                        <i class="bi bi-info-circle"></i> Emails are being sent in batches of 200 to ensure reliable delivery.
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Batch Progress</span>
                            <span id="batchProgressText">0 / 0 batches</span>
                        </div>
                        <div class="progress" style="height: 25px;">
                            <div id="batchProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Email Progress</span>
                            <span id="emailProgressText">0 / 0 emails</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div id="emailProgressBar" class="progress-bar bg-success" role="progressbar" style="width: 0%;">0%</div>
                        </div>
                    </div>
                    
                    <div id="batchStatus" class="text-center text-muted">
                        <i class="bi bi-clock-history"></i> Processing...
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.location.reload()">Close and Refresh</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showSection(section) {
            alert("Section: " + section + " - Coming soon!");
        }
        
        function showReports() {
            alert("Reports feature coming soon!");
        }
        
        function editCampaign(campaignId) {
            // Load campaign data via AJAX
            fetch('api/get_campaign.php?id=' + campaignId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const campaign = data.campaign;
                        
                        // Populate the edit form
                        document.getElementById('edit_campaign_id').value = campaign.id;
                        document.getElementById('edit_campaign_name').value = campaign.name;
                        document.getElementById('edit_email_subject').value = campaign.subject;
                        document.getElementById('edit_email_content').value = campaign.email_content;
                        document.getElementById('edit_sender_name').value = campaign.from_name;
                        document.getElementById('edit_sender_email').value = campaign.from_email;
                        document.getElementById('edit_schedule_type').value = campaign.schedule_type || 'immediate';
                        document.getElementById('edit_status').value = campaign.status;
                        
                        // Handle schedule date
                        if (campaign.schedule_date) {
                            // Convert to datetime-local format
                            const date = new Date(campaign.schedule_date);
                            const localDateTime = date.toISOString().slice(0, 16);
                            document.getElementById('edit_schedule_date').value = localDateTime;
                        }
                        
                        // Handle frequency
                        if (campaign.frequency) {
                            document.getElementById('edit_frequency').value = campaign.frequency;
                        }
                        
                        // Show/hide schedule options
                        const editScheduleOptions = document.getElementById('editScheduleOptions');
                        if (campaign.schedule_type === 'scheduled' || campaign.schedule_type === 'recurring') {
                            editScheduleOptions.style.display = 'block';
                        } else {
                            editScheduleOptions.style.display = 'none';
                        }
                        
                        // Show the modal
                        new bootstrap.Modal(document.getElementById('editCampaignModal')).show();
                    } else {
                        alert('Failed to load campaign data: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load campaign data. Please try again.');
                });
        }
        
        function sendCampaign(campaignId) {
            document.getElementById('send_campaign_id').value = campaignId;
            
            // First, get duplicate statistics
            fetch('api/campaign_duplicates.php?campaign_id=' + campaignId)
                .then(response => response.json())
                .then(stats => {
                    if (stats.success) {
                        const s = stats.stats;
                        const infoDiv = document.querySelector('#sendCampaignModal .alert-info');
                        infoDiv.innerHTML = `
                            <i class="bi bi-info-circle"></i> <strong>Campaign Statistics:</strong><br>
                            • Total recipients: ${s.total_recipients}<br>
                            • Unique emails: ${s.unique_emails} (${s.duplicate_count} duplicates removed)<br>
                            • Already sent: ${s.sent_emails}<br>
                            • <strong>Fresh unique emails ready to send: ${s.fresh_unique_emails}</strong><br>
                            • Each batch contains max 200 unique emails
                        `;
                    }
                });
            
            // Fetch all contacts to allow selection
            fetch('api/get_campaign.php?id=' + campaignId + '&recipients=all')
                .then(response => {
                    console.log('API Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('API Response data:', data);
                    const recipientsList = document.getElementById('recipientsList');
                    const totalRecipientsCount = document.getElementById('totalRecipientsCount');
                    recipientsList.innerHTML = '';
                    let recipients = data.recipients || [];
                    
                    // Store recipients globally for filtering
                    allRecipients = recipients;
                    currentCampaignId = campaignId;
                    
                    console.log('Recipients array:', recipients);
                    console.log('Recipients length:', recipients.length);
                    
                    // Reset filter to new_only by default
                    document.getElementById('contactFilter').value = 'new_only';
                    
                    // Apply initial filter
                    applyContactFilter();
                    
                    // Enable/disable send button based on recipients
                    if (recipients.length === 0) {
                        document.querySelector('#sendCampaignForm button[type="submit"]').disabled = true;
                    } else {
                        document.querySelector('#sendCampaignForm button[type="submit"]').disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error fetching contacts:', error);
                    const recipientsList = document.getElementById('recipientsList');
                    recipientsList.innerHTML = `
                        <div class="text-center py-4">
                            <i class="bi bi-exclamation-triangle text-warning display-4"></i>
                            <p class="mt-3 mb-0">Error loading contacts!</p>
                            <small class="text-muted">Error: ${error.message}</small>
                            <br><button class="btn btn-sm btn-outline-primary mt-2" onclick="location.reload()">Refresh Page</button>
                        </div>
                    `;
                });
            new bootstrap.Modal(document.getElementById('sendCampaignModal')).show();
        }
        


        function deleteCampaign(campaignId) {
            if (!confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) return;
            
            // Find the delete button that was clicked and show loading state
            const deleteButtons = document.querySelectorAll('.btn-danger');
            let deleteBtn = null;
            let originalText = '';
            deleteButtons.forEach(btn => {
                if (btn.getAttribute('onclick') && btn.getAttribute('onclick').includes(`deleteCampaign(${campaignId})`)) {
                    deleteBtn = btn;
                    originalText = btn.innerHTML;
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                }
            });
            
            fetch(window.location.pathname, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({ action: 'delete_campaign', campaign_id: campaignId })
            })
            .then(response => response.text())
            .then(html => {
                // Try to extract alert message from returned HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const alert = doc.querySelector('.alert');
                
                if (alert && alert.classList.contains('alert-success')) {
                    // Find and remove the campaign card with animation
                    const campaignCards = document.querySelectorAll('.campaign-card');
                    campaignCards.forEach(card => {
                        if (card.innerHTML.includes(`deleteCampaign(${campaignId})`)) {
                            card.style.transition = 'opacity 0.3s, transform 0.3s';
                            card.style.opacity = '0';
                            card.style.transform = 'scale(0.9)';
                            
                            setTimeout(() => {
                                card.remove();
                                
                                // Check if there are no more campaigns
                                const remainingCampaigns = document.querySelectorAll('.campaign-card').length;
                                if (remainingCampaigns === 0) {
                                    refreshCampaignList();
                                }
                                
                                // Update quick stats
                                const totalCampaignsElement = document.querySelector('.col-6 h4.text-primary');
                                if (totalCampaignsElement) {
                                    const currentCount = parseInt(totalCampaignsElement.textContent);
                                    totalCampaignsElement.textContent = Math.max(0, currentCount - 1);
                                }
                            }, 300);
                        }
                    });
                    
                    showMainPageAlert('Campaign deleted successfully!', 'success');
                } else {
                    // Show error message
                    const errorMsg = alert ? alert.textContent.trim() : 'Failed to delete campaign';
                    showMainPageAlert(errorMsg, 'danger');
                    
                    // Restore button if found
                    if (deleteBtn) {
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = originalText;
                    }
                }
            })
            .catch(() => {
                showMainPageAlert('Failed to delete campaign. Please try again.', 'danger');
                if (deleteBtn) {
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalText;
                }
            });
        }
        
        function toggleRecipient(recipientId) {
            const checkbox = document.getElementById('recipient_' + recipientId);
            checkbox.checked = !checkbox.checked;
            
            const item = checkbox.closest('.recipient-item');
            if (checkbox.checked) {
                item.classList.add('selected');
            } else {
                item.classList.remove('selected');
            }
            updateSelectedCount();
        }
        
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('input[name="recipient_ids[]"]:checked');
            const countElement = document.getElementById('selectedCount');
            countElement.textContent = checkboxes.length + ' contacts selected';
        }
        
        function selectAllRecipients() {
            const checkboxes = document.querySelectorAll('input[name="recipient_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
                const item = checkbox.closest('.recipient-item');
                item.classList.add('selected');
            });
            updateSelectedCount();
        }
        
        function clearAllRecipients() {
            const checkboxes = document.querySelectorAll('input[name="recipient_ids[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
                const item = checkbox.closest('.recipient-item');
                item.classList.remove('selected');
            });
            updateSelectedCount();
        }
        
        // Store all recipients data globally for filtering
        let allRecipients = [];
        let currentCampaignId = null;
        
        // Apply contact filter
        function applyContactFilter() {
            const filter = document.getElementById('contactFilter').value;
            const recipientsList = document.getElementById('recipientsList');
            
            console.log('Applying filter:', filter);
            console.log('All recipients:', allRecipients);
            console.log('Total recipients count:', allRecipients.length);
            
            let filteredRecipients = [];
            
            switch(filter) {
                case 'new_only':
                    // Include never_sent, pending, or no status (but exclude sent)
                    filteredRecipients = allRecipients.filter(r => {
                        const status = r.send_status || 'never_sent';
                        console.log(`Contact ${r.email} has status: ${status}`);
                        return status !== 'sent';
                    });
                    console.log('Filtered (new only):', filteredRecipients.length);
                    break;
                case 'include_sent':
                    filteredRecipients = allRecipients;
                    console.log('Filtered (include sent):', filteredRecipients.length);
                    break;
                case 'failed_only':
                    filteredRecipients = allRecipients.filter(r => r.send_status === 'failed');
                    console.log('Filtered (failed only):', filteredRecipients.length);
                    break;
                default:
                    filteredRecipients = allRecipients;
            }
            
            displayFilteredRecipients(filteredRecipients);
            updateFilteredCount(filteredRecipients);
        }
        
        // Display filtered recipients
        function displayFilteredRecipients(recipients) {
            const recipientsList = document.getElementById('recipientsList');
            recipientsList.innerHTML = '';
            
            if (recipients.length === 0) {
                recipientsList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-funnel text-muted display-4"></i>
                        <p class="mt-3 mb-0">No contacts match the selected filter!</p>
                        <small class="text-muted">Try changing the filter options above.</small>
                    </div>
                `;
                return;
            }
            
            const displayLimit = 200;
            const displayRecipients = recipients.slice(0, displayLimit);
            
            displayRecipients.forEach(recipient => {
                const div = document.createElement('div');
                div.className = 'recipient-item';
                if (recipient.send_status === 'failed') {
                    div.className += ' border-warning';
                } else if (recipient.send_status === 'sent') {
                    div.className += ' border-success';
                }
                div.setAttribute('onclick', `toggleRecipient(${recipient.id})`);
                div.setAttribute('data-search', (recipient.email + ' ' + (recipient.name || '') + ' ' + (recipient.company || '')).toLowerCase());
                div.setAttribute('data-status', recipient.send_status || 'new');
                
                let statusBadge = '';
                if (recipient.send_status === 'sent') {
                    statusBadge = '<span class="badge bg-success ms-2">Already Sent</span>';
                } else if (recipient.send_status === 'failed') {
                    statusBadge = '<span class="badge bg-warning ms-2">Failed</span>';
                } else {
                    statusBadge = '<span class="badge bg-info ms-2">New</span>';
                }
                
                div.innerHTML = `<div class="form-check">
                    <input class="form-check-input" type="checkbox" name="recipient_ids[]" value="${recipient.id}" id="recipient_${recipient.id}" onchange="updateSelectedCount()">
                    <label class="form-check-label" for="recipient_${recipient.id}">
                        <strong>${escapeHtml(recipient.email)}</strong>${statusBadge}
                        ${recipient.name ? `<br><small class="text-muted">${escapeHtml(recipient.name)}</small>` : ''}
                        ${recipient.company ? `<br><small class="text-muted">${escapeHtml(recipient.company)}</small>` : ''}
                        ${recipient.send_status === 'sent' && recipient.sent_at ? `<br><small class="text-success">Sent on: ${new Date(recipient.sent_at).toLocaleDateString()}</small>` : ''}
                        ${recipient.send_status === 'failed' && recipient.sent_at ? `<br><small class="text-danger">Failed on: ${new Date(recipient.sent_at).toLocaleDateString()}</small>` : ''}
                    </label>
                </div>`;
                recipientsList.appendChild(div);
            });
            
            if (recipients.length > displayLimit) {
                const info = document.createElement('div');
                info.className = 'alert alert-info mt-3';
                info.innerHTML = `<i class="bi bi-info-circle"></i> Showing first ${displayLimit} of ${recipients.length} filtered contacts. Maximum batch size: ${document.getElementById('batchSize').value} emails.`;
                recipientsList.appendChild(info);
            }
        }
        
        // Update filtered count display
        function updateFilteredCount(recipients) {
            const totalRecipientsCount = document.getElementById('totalRecipientsCount');
            const filter = document.getElementById('contactFilter').value;
            
            let newCount = recipients.filter(r => r.send_status !== 'sent').length;
            let sentCount = recipients.filter(r => r.send_status === 'sent').length;
            let failedCount = recipients.filter(r => r.send_status === 'failed').length;
            
            let countText = `<strong>${recipients.length}</strong> contacts`;
            if (filter === 'new_only') {
                countText += ` (${newCount} new)`;
            } else if (filter === 'include_sent') {
                countText += ` (${newCount} new, ${sentCount} sent)`;
            } else if (filter === 'failed_only') {
                countText += ` (${failedCount} failed)`;
            }
            
            totalRecipientsCount.innerHTML = countText;
        }
        
        // Select all filtered contacts
        function selectAllFiltered() {
            const filter = document.getElementById('contactFilter').value;
            const batchSize = parseInt(document.getElementById('batchSize').value);
            const visibleCheckboxes = document.querySelectorAll('.recipient-item:not([style*="display: none"]) input[name="recipient_ids[]"]');
            
            let count = 0;
            visibleCheckboxes.forEach(checkbox => {
                if (count < batchSize) {
                    checkbox.checked = true;
                    count++;
                } else {
                    checkbox.checked = false;
                }
            });
            
            updateSelectedCount();
            
            if (count === batchSize) {
                alert(`Selected first ${batchSize} contacts as per batch size limit.`);
            }
        }
        
        // Search functionality
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('recipientSearch');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const recipientItems = document.querySelectorAll('.recipient-item');
                    
                    recipientItems.forEach(item => {
                        const searchData = item.getAttribute('data-search');
                        if (searchData && searchData.includes(searchTerm)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
        });
        
        // Show/hide schedule options based on schedule type
        document.getElementById('schedule_type').addEventListener('change', function() {
            const scheduleOptions = document.getElementById('scheduleOptions');
            const warningDiv = document.getElementById('immediateWarning');
            
            if (this.value === 'scheduled' || this.value === 'recurring') {
                scheduleOptions.style.display = 'block';
                if (warningDiv) warningDiv.style.display = 'none';
            } else if (this.value === 'immediate') {
                scheduleOptions.style.display = 'none';
                // Show warning for immediate send
                if (!warningDiv) {
                    const warning = document.createElement('div');
                    warning.id = 'immediateWarning';
                    warning.className = 'alert alert-warning mt-3';
                    warning.innerHTML = '<i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> This will send emails to ALL recipients immediately after creation!';
                    this.parentElement.parentElement.parentElement.appendChild(warning);
                } else {
                    warningDiv.style.display = 'block';
                }
            } else {
                // For send_later option
                scheduleOptions.style.display = 'none';
                if (warningDiv) warningDiv.style.display = 'none';
            }
        });
        
        // Show/hide edit schedule options based on schedule type
        document.getElementById('edit_schedule_type').addEventListener('change', function() {
            const editScheduleOptions = document.getElementById('editScheduleOptions');
            const warningDiv = document.getElementById('editImmediateWarning');
            
            if (this.value === 'scheduled' || this.value === 'recurring') {
                editScheduleOptions.style.display = 'block';
                if (warningDiv) warningDiv.style.display = 'none';
            } else if (this.value === 'immediate') {
                editScheduleOptions.style.display = 'none';
                // Show warning for immediate send
                if (!warningDiv) {
                    const warning = document.createElement('div');
                    warning.id = 'editImmediateWarning';
                    warning.className = 'alert alert-warning mt-3';
                    warning.innerHTML = '<i class="bi bi-exclamation-triangle"></i> <strong>Warning:</strong> Changing to immediate will send emails to ALL recipients when you save!';
                    this.parentElement.parentElement.parentElement.appendChild(warning);
                } else {
                    warningDiv.style.display = 'block';
                }
            } else {
                // For send_later option
                editScheduleOptions.style.display = 'none';
                if (warningDiv) warningDiv.style.display = 'none';
            }
        });

        // AJAX for campaign creation - Optimized for speed
        const createCampaignForm = document.getElementById('createCampaignForm');
        if (createCampaignForm) {
            createCampaignForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get submit button and show loading state immediately
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
                
                const formData = new FormData(createCampaignForm);
                
                // Submit form with JSON response for faster processing
                fetch(window.location.pathname, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => {
                    // Fast check for success
                    if (response.ok) {
                        // Close modal immediately on success
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createCampaignModal'));
                        if (modal) modal.hide();
                        
                        // Show success message on main page
                        showMainPageAlert('Campaign created successfully!', 'success');
                        
                        // Refresh page to show new campaign
                        setTimeout(() => window.location.reload(), 500);
                    }
                    return response.text();
                })
                .then(html => {
                    // Handle any error messages
                    if (html.includes('alert-danger')) {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const alert = doc.querySelector('.alert-danger');
                        if (alert) {
                            const modalBody = createCampaignForm.closest('.modal-content').querySelector('.modal-body');
                            modalBody.insertAdjacentElement('afterbegin', alert);
                        }
                        // Re-enable button on error
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to create campaign. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                });
            });
        }
        
        // Function to refresh campaign list without page reload
        function refreshCampaignList() {
            fetch('api/get_campaigns.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.campaigns) {
                        const campaignContainer = document.querySelector('.col-md-8 .card-body');
                        if (!campaignContainer) return;
                        
                        if (data.campaigns.length === 0) {
                            campaignContainer.innerHTML = `
                                <div class="text-center py-4">
                                    <i class="bi bi-envelope display-1 text-muted"></i>
                                    <h5 class="mt-3">No campaigns yet</h5>
                                    <p class="text-muted">Create your first email campaign to get started</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCampaignModal">
                                        <i class="bi bi-plus-circle"></i> Create Campaign
                                    </button>
                                </div>
                            `;
                        } else {
                            let html = '';
                            data.campaigns.forEach(campaign => {
                                const statusClass = campaign.status === 'active' ? 'success' : 
                                                  (campaign.status === 'draft' ? 'warning' : 'secondary');
                                const createdDate = new Date(campaign.created_at).toLocaleDateString('en-US', 
                                    { month: 'short', day: 'numeric', year: 'numeric' });
                                
                                html += `
                                    <div class="campaign-card">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="mb-1">${escapeHtml(campaign.name)}</h6>
                                                <p class="text-muted mb-2">${escapeHtml(campaign.subject)}</p>
                                                <div class="d-flex gap-3">
                                                    <span class="badge bg-${statusClass}">
                                                        ${campaign.status.charAt(0).toUpperCase() + campaign.status.slice(1)}
                                                    </span>
                                                    <small class="text-muted">Created: ${createdDate}</small>
                                                </div>
                                            </div>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editCampaign(${campaign.id})">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <button class="btn btn-sm btn-primary" onclick="sendCampaign(${campaign.id})">
                                                    <i class="bi bi-send"></i> Send
                                                </button>
                                                <button class="btn btn-sm btn-danger" onclick="deleteCampaign(${campaign.id})">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                                <a class="btn btn-sm btn-info" href="campaign_progress.php?id=${campaign.id}">
                                                    <i class="bi bi-graph-up"></i> View Progress
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            campaignContainer.innerHTML = html;
                        }
                        
                        // Update quick stats
                        const totalCampaignsElement = document.querySelector('.col-6 h4.text-primary');
                        if (totalCampaignsElement) {
                            totalCampaignsElement.textContent = data.campaigns.length;
                        }
                    }
                })
                .catch(err => console.error('Error refreshing campaigns:', err));
        }
        
        // Helper function to escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
        }
        
        // Load template into form
        function loadTemplate() {
            const templateSelect = document.getElementById('template_id');
            const selectedOption = templateSelect.options[templateSelect.selectedIndex];
            
            if (selectedOption.value) {
                const subject = selectedOption.getAttribute('data-subject');
                const content = selectedOption.getAttribute('data-content');
                const variables = JSON.parse(selectedOption.getAttribute('data-variables') || '[]');
                
                // Set subject and content
                document.getElementById('email_subject').value = subject;
                document.getElementById('email_content').value = content;
                
                // Show available variables
                if (variables.length > 0) {
                    document.getElementById('template-variables').style.display = 'block';
                    document.getElementById('variable-list').innerHTML = variables.map(v => `<code>{{${v}}}</code>`).join(', ');
                } else {
                    document.getElementById('template-variables').style.display = 'none';
                }
                
                // Update preview
                updateEmailPreview();
            } else {
                // Clear template variables display
                document.getElementById('template-variables').style.display = 'none';
            }
        }
        
        // Preview email with merge tags replaced
        function previewEmail() {
            const subject = document.getElementById('email_subject').value;
            const content = document.getElementById('email_content').value;
            const contentType = document.querySelector('input[name="content_type"]:checked').value;
            
            // Sample data for preview
            const sampleData = {
                first_name: 'John',
                last_name: 'Doe',
                email: 'john.doe@example.com',
                company_name: 'ACRM Company',
                product_name: 'Sample Product',
                product_link: 'https://example.com/product',
                month: new Date().toLocaleString('default', { month: 'long' }),
                year: new Date().getFullYear()
            };
            
            // Replace merge tags
            let previewSubject = replaceMergeTags(subject, sampleData);
            let previewContent = replaceMergeTags(content, sampleData);
            
            // Create preview modal
            const modalHtml = `
                <div class="modal fade" id="previewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Email Preview</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <small>This is a preview with sample data. Actual emails will use recipient data.</small>
                                </div>
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Subject:</strong> ${escapeHtml(previewSubject)}
                                    </div>
                                    <div class="card-body">
                                        ${contentType === 'html' ? previewContent : '<pre>' + escapeHtml(previewContent) + '</pre>'}
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Sample merge tag values:</strong><br>
                                        ${Object.entries(sampleData).map(([key, value]) => `{{${key}}} = ${value}`).join('<br>')}
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing preview modal if any
            const existingModal = document.getElementById('previewModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            new bootstrap.Modal(document.getElementById('previewModal')).show();
        }
        
        // Replace merge tags with actual values
        function replaceMergeTags(text, data) {
            let result = text;
            for (const [key, value] of Object.entries(data)) {
                const regex = new RegExp(`{{\\s*${key}\\s*}}`, 'gi');
                result = result.replace(regex, value);
            }
            return result;
        }
        
        // Update email preview on input
        function updateEmailPreview() {
            const subject = document.getElementById('email_subject').value;
            if (subject && subject.includes('{{')) {
                document.getElementById('subject-preview').style.display = 'block';
                const sampleData = {
                    first_name: 'John',
                    company_name: 'ACRM Company',
                    month: new Date().toLocaleString('default', { month: 'long' }),
                    year: new Date().getFullYear()
                };
                document.getElementById('subject-preview-text').textContent = replaceMergeTags(subject, sampleData);
            } else {
                document.getElementById('subject-preview').style.display = 'none';
            }
        }
        
        // Add event listeners for real-time preview
        document.getElementById('email_subject').addEventListener('input', updateEmailPreview);
        
        // Toggle content editor based on type
        document.querySelectorAll('input[name="content_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const textarea = document.getElementById('email_content');
                if (this.value === 'text') {
                    // Convert HTML to plain text if switching
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = textarea.value;
                    textarea.value = tempDiv.textContent || tempDiv.innerText || '';
                }
            });
        });
        
        // Load template for edit form
        function loadEditTemplate() {
            const templateSelect = document.getElementById('edit_template_id');
            const selectedOption = templateSelect.options[templateSelect.selectedIndex];
            
            if (selectedOption.value) {
                if (confirm('This will replace your current subject and content. Continue?')) {
                    const subject = selectedOption.getAttribute('data-subject');
                    const content = selectedOption.getAttribute('data-content');
                    const variables = JSON.parse(selectedOption.getAttribute('data-variables') || '[]');
                    
                    // Set subject and content
                    document.getElementById('edit_email_subject').value = subject;
                    document.getElementById('edit_email_content').value = content;
                    
                    // Show available variables
                    if (variables.length > 0) {
                        document.getElementById('edit-template-variables').style.display = 'block';
                        document.getElementById('edit-variable-list').innerHTML = variables.map(v => `<code>{{${v}}}</code>`).join(', ');
                    } else {
                        document.getElementById('edit-template-variables').style.display = 'none';
                    }
                    
                    // Update preview
                    updateEditEmailPreview();
                } else {
                    // Reset selection
                    templateSelect.value = '';
                }
            } else {
                // Clear template variables display
                document.getElementById('edit-template-variables').style.display = 'none';
            }
        }
        
        // Preview email for edit form
        function previewEditEmail() {
            const subject = document.getElementById('edit_email_subject').value;
            const content = document.getElementById('edit_email_content').value;
            const contentType = document.querySelector('input[name="edit_content_type"]:checked').value;
            
            // Sample data for preview
            const sampleData = {
                first_name: 'John',
                last_name: 'Doe',
                email: 'john.doe@example.com',
                company_name: 'ACRM Company',
                product_name: 'Sample Product',
                product_link: 'https://example.com/product',
                month: new Date().toLocaleString('default', { month: 'long' }),
                year: new Date().getFullYear()
            };
            
            // Replace merge tags
            let previewSubject = replaceMergeTags(subject, sampleData);
            let previewContent = replaceMergeTags(content, sampleData);
            
            // Create preview modal
            const modalHtml = `
                <div class="modal fade" id="editPreviewModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Email Preview</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <small>This is a preview with sample data. Actual emails will use recipient data.</small>
                                </div>
                                <div class="card">
                                    <div class="card-header">
                                        <strong>Subject:</strong> ${escapeHtml(previewSubject)}
                                    </div>
                                    <div class="card-body">
                                        ${contentType === 'html' ? previewContent : '<pre>' + escapeHtml(previewContent) + '</pre>'}
                                    </div>
                                </div>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Sample merge tag values:</strong><br>
                                        ${Object.entries(sampleData).map(([key, value]) => `{{${key}}} = ${value}`).join('<br>')}
                                    </small>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing preview modal if any
            const existingModal = document.getElementById('editPreviewModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            new bootstrap.Modal(document.getElementById('editPreviewModal')).show();
        }
        
        // Update email preview for edit form
        function updateEditEmailPreview() {
            const subject = document.getElementById('edit_email_subject').value;
            if (subject && subject.includes('{{')) {
                document.getElementById('edit-subject-preview').style.display = 'block';
                const sampleData = {
                    first_name: 'John',
                    company_name: 'ACRM Company',
                    month: new Date().toLocaleString('default', { month: 'long' }),
                    year: new Date().getFullYear()
                };
                document.getElementById('edit-subject-preview-text').textContent = replaceMergeTags(subject, sampleData);
            } else {
                document.getElementById('edit-subject-preview').style.display = 'none';
            }
        }
        
        // Add event listener for edit subject preview
        document.getElementById('edit_email_subject').addEventListener('input', updateEditEmailPreview);
        
        // Helper function to show alerts in modal
        function showModalAlert(message, type = 'info') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const modalBody = document.querySelector('#createCampaignModal .modal-body');
            if (modalBody) {
                modalBody.insertAdjacentElement('afterbegin', alert);
            }
        }
        
        // Helper function to show alerts on main page
        function showMainPageAlert(message, type = 'info') {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show`;
            alert.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const mainContent = document.querySelector('.main-content .container-fluid');
            if (mainContent) {
                // Insert after the header
                const header = mainContent.querySelector('.d-flex.justify-content-between');
                if (header && header.nextSibling) {
                    header.parentNode.insertBefore(alert, header.nextSibling);
                } else {
                    mainContent.insertAdjacentElement('afterbegin', alert);
                }
            }
        }

        // Add JS to log the number of checked recipient_ids before form submission
        const sendCampaignForm = document.getElementById('sendCampaignForm');
        if (sendCampaignForm) {
            sendCampaignForm.addEventListener('submit', function(e) {
                const checked = document.querySelectorAll('input[name="recipient_ids[]"]:checked');
                console.log('DEBUG: Submitting with ' + checked.length + ' recipient_ids');
                
                // Show batch progress if sending to many recipients
                if (checked.length > 200) {
                    e.preventDefault();
                    sendWithBatchProgress();
                }
            });
        }
        
        // Batch progress monitoring
        let batchProgressInterval = null;
        
        function sendWithBatchProgress() {
            const form = document.getElementById('sendCampaignForm');
            const formData = new FormData(form);
            
            // Show progress modal
            const sendModal = bootstrap.Modal.getInstance(document.getElementById('sendCampaignModal'));
            sendModal.hide();
            
            const progressModal = new bootstrap.Modal(document.getElementById('batchProgressModal'));
            progressModal.show();
            
            // Submit form via AJAX
            fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                // Start monitoring progress
                const campaignId = document.getElementById('send_campaign_id').value;
                startBatchProgressMonitoring(campaignId);
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send campaign. Please try again.');
                window.location.reload();
            });
        }
        
        function startBatchProgressMonitoring(campaignId) {
            // Clear any existing interval
            if (batchProgressInterval) {
                clearInterval(batchProgressInterval);
            }
            
            // Check progress every 2 seconds
            batchProgressInterval = setInterval(() => {
                fetch(`api/batch_progress.php?campaign_id=${campaignId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.progress) {
                        updateBatchProgress(data.progress);
                        
                        // Stop monitoring if completed
                        if (data.progress.progress_percentage >= 100) {
                            clearInterval(batchProgressInterval);
                            document.getElementById('batchStatus').innerHTML = 
                                '<i class="bi bi-check-circle text-success"></i> Campaign sent successfully!';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error checking progress:', error);
                });
            }, 2000);
        }
        
        function updateBatchProgress(progress) {
            // Update batch progress
            document.getElementById('batchProgressText').textContent = 
                `${progress.completed_batches} / ${progress.total_batches} batches`;
            document.getElementById('batchProgressBar').style.width = progress.progress_percentage + '%';
            document.getElementById('batchProgressBar').textContent = Math.round(progress.progress_percentage) + '%';
            
            // Update email progress
            const emailPercentage = progress.total_recipients > 0 
                ? Math.round((progress.total_sent / progress.total_recipients) * 100) 
                : 0;
            document.getElementById('emailProgressText').textContent = 
                `${progress.total_sent} / ${progress.total_recipients} emails`;
            document.getElementById('emailProgressBar').style.width = emailPercentage + '%';
            document.getElementById('emailProgressBar').textContent = emailPercentage + '%';
            
            // Update status
            if (progress.processing_batches > 0) {
                document.getElementById('batchStatus').innerHTML = 
                    '<i class="bi bi-clock-history"></i> Processing batch...';
            }
        }

        
        // Optimized function to display schedule recipients
        window.displayScheduleRecipients = function(data) {
            const recipientsList = document.getElementById('scheduleRecipientsList');
            const totalRecipientsCount = document.getElementById('scheduleTotalRecipientsCount');
            const recipients = data.recipients || [];
            
            // Update count display
            const totalUnsent = data.total_unsent || recipients.length;
            const uniqueUnsent = data.unique_unsent_count || 0;
            totalRecipientsCount.innerHTML = `<strong>${totalUnsent}</strong> unsent recipients (<strong>${uniqueUnsent}</strong> unique emails)`;
            
            if (recipients.length === 0) {
                recipientsList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-check-circle text-success display-4"></i>
                        <p class="mt-3 mb-0">All recipients have already been sent this campaign!</p>
                        <small class="text-muted">No unsent recipients found.</small>
                    </div>
                `;
                document.querySelector('#scheduleCampaignForm button[type="submit"]').disabled = true;
                return;
            }
            
            // Build HTML in one go for better performance
            const displayLimit = 100;
            const displayRecipients = recipients.slice(0, displayLimit);
            let html = '';
            
            displayRecipients.forEach(recipient => {
                const statusBadge = recipient.send_status === 'failed' 
                    ? '<span class="badge bg-warning ms-2">Failed</span>' 
                    : '<span class="badge bg-info ms-2">Never Sent</span>';
                
                html += `<div class="schedule-recipient-item${recipient.send_status === 'failed' ? ' border-warning' : ''}" 
                    onclick="toggleScheduleRecipient(${recipient.id})" 
                    data-search="${(recipient.email + ' ' + (recipient.name || '') + ' ' + (recipient.company || '')).toLowerCase()}">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="recipient_ids[]" value="${recipient.id}" 
                            id="schedule_recipient_${recipient.id}" onchange="updateScheduleSelectedCount()">
                        <label class="form-check-label" for="schedule_recipient_${recipient.id}">
                            <strong>${escapeHtml(recipient.email)}</strong>${statusBadge}
                            ${recipient.name ? `<br><small class="text-muted">${escapeHtml(recipient.name)}</small>` : ''}
                            ${recipient.company ? `<br><small class="text-muted">${escapeHtml(recipient.company)}</small>` : ''}
                            ${recipient.send_status === 'failed' && recipient.sent_at ? `<br><small class="text-danger">Failed on: ${new Date(recipient.sent_at).toLocaleDateString()}</small>` : ''}
                        </label>
                    </div>
                </div>`;
            });
            
            if (recipients.length > displayLimit) {
                html += `<div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> Showing first ${displayLimit} of ${recipients.length} unsent recipients. 
                    Use search to find specific recipients or click "Select All" to select all ${uniqueUnsent} unique emails.
                </div>`;
            }
            
            recipientsList.innerHTML = html;
            updateScheduleSelectedCount();
        }
        
        window.loadScheduleRecipients = function(campaignId) {
            fetch('api/get_campaign.php?id=' + campaignId + '&recipients=unsent')
                .then(response => response.json())
                .then(data => displayScheduleRecipients(data))
                .catch(error => console.error('Error loading recipients:', error));
        }
        
        window.toggleScheduleRecipient = function(recipientId) {
            const checkbox = document.getElementById('schedule_recipient_' + recipientId);
            if (checkbox) {
                checkbox.checked = !checkbox.checked;
                updateScheduleSelectedCount();
            }
        }
        
        window.updateScheduleSelectedCount = function() {
            const checkboxes = document.querySelectorAll('#scheduleRecipientsList input[type="checkbox"]');
            const selectedCount = document.getElementById('scheduleSelectedCount');
            let count = 0;
            
            checkboxes.forEach(checkbox => {
                if (checkbox.checked) count++;
            });
            
            selectedCount.textContent = count + ' recipients selected';
        }
        
        window.scheduleSelectAllRecipients = function() {
            const checkboxes = document.querySelectorAll('#scheduleRecipientsList input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            window.updateScheduleSelectedCount();
        }
        
        window.scheduleClearAllRecipients = function() {
            const checkboxes = document.querySelectorAll('#scheduleRecipientsList input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            window.updateScheduleSelectedCount();
        }
        
        // Add search functionality for schedule recipients
        document.addEventListener('DOMContentLoaded', function() {
            const scheduleRecipientSearch = document.getElementById('scheduleRecipientSearch');
            if (scheduleRecipientSearch) {
                scheduleRecipientSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    const recipientItems = document.querySelectorAll('.schedule-recipient-item');
                    
                    recipientItems.forEach(item => {
                        const searchData = item.getAttribute('data-search');
                        if (searchData && searchData.includes(searchTerm)) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            }
            
            // Toggle schedule options based on schedule type
            const scheduleTypeSelect = document.getElementById('schedule_schedule_type');
            const scheduleOptions = document.getElementById('scheduleOptions');
            const scheduleDateInput = document.getElementById('schedule_date');
            const frequencySelect = document.getElementById('schedule_frequency');
            
            if (scheduleTypeSelect) {
                scheduleTypeSelect.addEventListener('change', function() {
                    if (this.value === 'immediate') {
                        scheduleOptions.style.display = 'none';
                        scheduleDateInput.removeAttribute('required');
                        frequencySelect.removeAttribute('required');
                    } else if (this.value === 'scheduled') {
                        scheduleOptions.style.display = 'block';
                        scheduleDateInput.setAttribute('required', 'required');
                        frequencySelect.removeAttribute('required');
                        frequencySelect.parentElement.style.display = 'none';
                    } else if (this.value === 'recurring') {
                        scheduleOptions.style.display = 'block';
                        scheduleDateInput.setAttribute('required', 'required');
                        frequencySelect.setAttribute('required', 'required');
                        frequencySelect.parentElement.style.display = 'block';
                    }
                });
            }
        });
    </script>

</body>
</html> 