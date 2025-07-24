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
                    // If immediate, send campaign now
                    if ($_POST['schedule_type'] === 'immediate') {
                        // Get all recipient IDs
                        $recipientStmt = $database->getConnection()->query("SELECT id FROM email_recipients");
                        $recipientIds = $recipientStmt->fetchAll(PDO::FETCH_COLUMN);
                        if (!empty($recipientIds)) {
                            $sendResult = $campaignService->sendCampaign($result['campaign_id'], $recipientIds);
                            if ($sendResult['success']) {
                                $message .= "<br>Immediate campaign sent to " . $sendResult['sent_count'] . " recipients.";
                            } else {
                                $message .= "<br>Immediate campaign sending failed: " . $sendResult['message'];
                            }
                        } else {
                            $message .= "<br>No recipients found for immediate sending.";
                        }
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
                
                $result = $campaignService->sendCampaign($campaignId, $recipientIds);
                if ($result['success']) {
                    $message = "Campaign sent successfully! Sent to " . $result['sent_count'] . " recipients";
                    $messageType = 'success';
                } else {
                    $message = 'Campaign sending failed: ' . $result['message'];
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
if (isset($_GET['send_campaign_id'])) {
    // If sending, filter recipients for that campaign
    require_once 'services/EmailCampaignService.php';
    $campaignService = new EmailCampaignService($database);
    $allRecipients = $campaignService->getCampaignRecipientsWithStatus($_GET['send_campaign_id']);
    // Only show recipients who have not been sent (status is null or not 'sent')
    $recipients = array_filter($allRecipients, function($r) {
        return empty($r['status']) || $r['status'] !== 'sent';
    });
} else {
    try {
        $stmt = $database->getConnection()->query("SELECT id, email, name, company FROM email_recipients ORDER BY created_at DESC");
        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Ignore error if table doesn't exist
    }
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
                <form id="createCampaignForm" method="POST">
                    <input type="hidden" name="action" value="create_campaign">
                    <div class="modal-body">
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
                            <label for="email_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="email_subject" name="email_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_content" class="form-label">Email Content</label>
                            <textarea class="form-control" id="email_content" name="email_content" rows="10" required placeholder="Enter your email content here..."></textarea>
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
                <form method="POST">
                    <input type="hidden" name="action" value="send_campaign">
                    <input type="hidden" name="campaign_id" id="send_campaign_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label mb-0">Select Recipients</label>
                                <small class="text-muted" id="totalRecipientsCount">0 total recipients</small>
                            </div>
                            
                            <!-- Search and Select All -->
                            <div class="row mb-3">
                                <div class="col-md-8">
                                    <input type="text" class="form-control" id="recipientSearch" placeholder="Search recipients by email, name, or company...">
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="selectAllRecipients()">
                                        <i class="bi bi-check-all"></i> Select All
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearAllRecipients()">
                                        <i class="bi bi-x-circle"></i> Clear All
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Selected Count -->
                            <div class="mb-2">
                                <small class="text-primary" id="selectedCount">0 recipients selected</small>
                            </div>
                            
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
                            <label for="edit_email_subject" class="form-label">Email Subject</label>
                            <input type="text" class="form-control" id="edit_email_subject" name="email_subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_email_content" class="form-label">Email Content</label>
                            <textarea class="form-control" id="edit_email_content" name="email_content" rows="10" required placeholder="Enter your email content here..."></textarea>
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
            // Fetch unsent recipients via AJAX
            fetch('api/get_campaign.php?id=' + campaignId + '&recipients=unsent')
                .then(response => response.json())
                .then(data => {
                    const recipientsList = document.getElementById('recipientsList');
                    const totalRecipientsCount = document.getElementById('totalRecipientsCount');
                    recipientsList.innerHTML = '';
                    let recipients = data.recipients || [];
                    totalRecipientsCount.textContent = recipients.length + ' total recipients';
                    // Show only first 50
                    recipients = recipients.slice(0, 50);
                    recipients.forEach(recipient => {
                        const div = document.createElement('div');
                        div.className = 'recipient-item';
                        div.setAttribute('onclick', `toggleRecipient(${recipient.id})`);
                        div.setAttribute('data-search', (recipient.email + ' ' + (recipient.name || '') + ' ' + (recipient.company || '')).toLowerCase());
                        div.innerHTML = `<div class=\"form-check\">
                            <input class=\"form-check-input\" type=\"checkbox\" name=\"recipient_ids[]\" value=\"${recipient.id}\" id=\"recipient_${recipient.id}\" onchange=\"updateSelectedCount()\">
                            <label class=\"form-check-label\" for=\"recipient_${recipient.id}\">
                                <strong>${recipient.email}</strong>
                                ${recipient.name ? `<br><small class=\"text-muted\">${recipient.name}</small>` : ''}
                                ${recipient.company ? `<br><small class=\"text-muted\">${recipient.company}</small>` : ''}
                            </label>
                        </div>`;
                        recipientsList.appendChild(div);
                    });
                    if (data.recipients && data.recipients.length > 50) {
                        const info = document.createElement('div');
                        info.className = 'text-center mt-3';
                        info.innerHTML = '<small class="text-muted">Showing first 50 recipients. Use search to find specific recipients.</small>';
                        recipientsList.appendChild(info);
                    }
                    updateSelectedCount();
                });
            new bootstrap.Modal(document.getElementById('sendCampaignModal')).show();
        }

        function deleteCampaign(campaignId) {
            if (!confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) return;
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
                if (alert) {
                    document.body.insertAdjacentElement('afterbegin', alert);
                }
                // Reload if success
                if (alert && alert.classList.contains('alert-success')) {
                    setTimeout(() => window.location.reload(), 1200);
                }
            })
            .catch(() => alert('Failed to delete campaign. Please try again.'));
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
            countElement.textContent = checkboxes.length + ' recipients selected';
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
            if (this.value === 'scheduled' || this.value === 'recurring') {
                scheduleOptions.style.display = 'block';
            } else {
                scheduleOptions.style.display = 'none';
            }
        });
        
        // Show/hide edit schedule options based on schedule type
        document.getElementById('edit_schedule_type').addEventListener('change', function() {
            const editScheduleOptions = document.getElementById('editScheduleOptions');
            if (this.value === 'scheduled' || this.value === 'recurring') {
                editScheduleOptions.style.display = 'block';
            } else {
                editScheduleOptions.style.display = 'none';
            }
        });

        // AJAX for campaign creation
        const createCampaignForm = document.getElementById('createCampaignForm');
        if (createCampaignForm) {
            createCampaignForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(createCampaignForm);
                fetch(window.location.pathname, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(html => {
                    // Try to extract alert message from returned HTML
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const alert = doc.querySelector('.alert');
                    if (alert) {
                        // Show alert in modal or above form
                        const modalBody = createCampaignForm.closest('.modal-content').querySelector('.modal-body');
                        let existingAlert = modalBody.querySelector('.alert');
                        if (existingAlert) existingAlert.remove();
                        modalBody.insertAdjacentElement('afterbegin', alert);
                    }
                    // Optionally, close modal and refresh campaign list if success
                    if (alert && alert.classList.contains('alert-success')) {
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('createCampaignModal'));
                            if (modal) modal.hide();
                            window.location.reload(); // Or use AJAX to refresh campaign list only
                        }, 1500);
                    }
                })
                .catch(err => {
                    alert('Failed to create campaign. Please try again.');
                });
            });
        }
    </script>
</body>
</html> 