<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../config/base_path.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";
require_once __DIR__ . "/../../models/Contact.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";
require_once __DIR__ . "/../../models/EmailTemplate.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: " . base_path('employee/login'));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user permissions
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

// Helper function to check permissions
if (!function_exists('hasPermission')) {
    function hasPermission($permissions, $permission) {
        return isset($permissions[$permission]) && $permissions[$permission];
    }
}

// Check if user has permission to edit campaigns
if (!hasPermission($permissions, 'can_edit_campaigns')) {
    $_SESSION['error'] = "You don't have permission to edit campaigns.";
    header("Location: " . base_path('employee/campaigns'));
    exit();
}

// Get campaign ID
$campaignId = $_GET['id'] ?? 0;
if (!$campaignId) {
    header("Location: " . base_path('employee/campaigns'));
    exit();
}

// Get campaign details
$campaignModel = new EmailCampaign($db);
$campaign = $campaignModel->findById($campaignId);

// Check if campaign exists and belongs to this user
if (!$campaign || $campaign['created_by'] != $_SESSION["user_id"]) {
    $_SESSION['error'] = "Campaign not found or you don't have permission to edit it.";
    header("Location: " . base_path('employee/campaigns'));
    exit();
}

// Check if campaign can be edited (only draft campaigns can be edited)
if ($campaign['status'] !== 'draft') {
    $_SESSION['error'] = "Only draft campaigns can be edited.";
    header("Location: " . base_path('employee/campaigns/view/' . $campaignId));
    exit();
}

// Get total contacts (only if user has permission to view contacts)
$totalContacts = 0;
if (hasPermission($permissions, 'can_upload_contacts')) {
    $stmt = $db->query("SELECT COUNT(*) as total FROM contacts");
    $totalContacts = $stmt->fetch()['total'];
}

// Get contact groups/tags for targeting (only if user has permission to view contacts)
$tags = [];
if (hasPermission($permissions, 'can_upload_contacts')) {
    // Check if tags column exists
    try {
        $stmt = $db->query("SHOW COLUMNS FROM contacts LIKE 'tags'");
        $tagsColumnExists = $stmt->rowCount() > 0;
    } catch (Exception $e) {
        // For SQLite or if SHOW COLUMNS doesn't work
        try {
            $stmt = $db->query("PRAGMA table_info(contacts)");
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);
            $tagsColumnExists = in_array('tags', $columns);
        } catch (Exception $e2) {
            $tagsColumnExists = false;
        }
    }
    
    if ($tagsColumnExists) {
        $stmt = $db->query("SELECT DISTINCT tags FROM contacts WHERE tags IS NOT NULL AND tags != ''");
        while ($row = $stmt->fetch()) {
            $contactTags = explode(',', $row['tags']);
            foreach ($contactTags as $tag) {
                $tag = trim($tag);
                if ($tag && !in_array($tag, $tags)) {
                    $tags[] = $tag;
                }
            }
        }
        sort($tags);
    }
}

// Get email templates
$templateModel = new EmailTemplate($db);
$templates = $templateModel->getAvailableTemplates($_SESSION["user_id"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Campaign - Email Campaign Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
        }
        .sidebar .nav-link {
            color: #fff;
            padding: 1rem;
        }
        .sidebar .nav-link:hover {
            background-color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #007bff;
        }
        .form-section {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .preview-section {
            border: 2px dashed #dee2e6;
            padding: 2rem;
            border-radius: 0.5rem;
            background-color: #fff;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include __DIR__ . "/../components/employee-sidebar.php"; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4" style="margin-left: 260px;">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit Campaign</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="<?php echo base_path('employee/campaigns'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Campaigns
                        </a>
                    </div>
                </div>

                <form id="campaignForm" method="POST">
                    <input type="hidden" name="campaign_id" value="<?php echo $campaignId; ?>">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Campaign Details -->
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-info-circle me-2"></i>Campaign Details
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="campaignName" class="form-label">Campaign Name *</label>
                                        <input type="text" class="form-control" id="campaignName" name="name" 
                                               value="<?php echo htmlspecialchars($campaign['name']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="campaignSubject" class="form-label">Email Subject *</label>
                                        <input type="text" class="form-control" id="campaignSubject" name="subject" 
                                               value="<?php echo htmlspecialchars($campaign['subject']); ?>" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="campaignDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="campaignDescription" name="description" rows="3"><?php echo htmlspecialchars($campaign['description'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <!-- Recipients -->
                            <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-users me-2"></i>Recipients
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="recipientType" class="form-label">Recipient Type</label>
                                        <select class="form-select" id="recipientType" name="recipient_type" required>
                                            <option value="">Select Recipient Type</option>
                                            <option value="all" <?php echo ($campaign['recipient_type'] ?? '') === 'all' ? 'selected' : ''; ?>>All Contacts</option>
                                            <?php if (!empty($tags)): ?>
                                            <option value="tags" <?php echo ($campaign['recipient_type'] ?? '') === 'tags' ? 'selected' : ''; ?>>By Tags</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <?php if (!empty($tags)): ?>
                                    <div class="col-md-6 mb-3">
                                        <label for="tags" class="form-label">Tags (Optional)</label>
                                        <select class="form-select" id="tags" name="tags[]" multiple>
                                            <?php foreach ($tags as $tag): ?>
                                            <option value="<?php echo htmlspecialchars($tag); ?>" 
                                                <?php echo in_array($tag, explode(',', $campaign['target_tags'] ?? '')) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tag); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="text-muted">Hold Ctrl/Cmd to select multiple tags</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label for="customRecipients" class="form-label">Custom Recipients (One email per line)</label>
                                    <textarea class="form-control" id="customRecipients" name="custom_recipients" rows="5" 
                                              placeholder="email1@example.com&#10;email2@example.com&#10;email3@example.com"><?php echo htmlspecialchars($campaign['custom_recipients'] ?? ''); ?></textarea>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-users me-2"></i>Recipients
                                </h5>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    You don't have permission to manage contacts. The current recipient settings will be preserved.
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Email Content -->
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-envelope me-2"></i>Email Content
                                </h5>
                                <div class="mb-3">
                                    <label for="emailTemplate" class="form-label">Email Template</label>
                                    <select class="form-select" id="emailTemplate" name="template_id">
                                        <option value="">Select a template</option>
                                        <?php foreach ($templates as $template): ?>
                                            <option value="<?php echo $template['id']; ?>" 
                                                    <?php echo $campaign['template_id'] == $template['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($template['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="emailContent" class="form-label">Email Content *</label>
                                    <textarea class="form-control" id="emailContent" name="content" rows="10" required><?php echo htmlspecialchars($campaign['content']); ?></textarea>
                                </div>
                            </div>

                            <!-- Scheduling -->
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-clock me-2"></i>Scheduling
                                </h5>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sendType" class="form-label">Send Type</label>
                                        <select class="form-select" id="sendType" name="send_type">
                                            <option value="immediate" <?php echo ($campaign['send_type'] ?? 'immediate') === 'immediate' ? 'selected' : ''; ?>>Send Immediately</option>
                                            <option value="scheduled" <?php echo ($campaign['send_type'] ?? '') === 'scheduled' ? 'selected' : ''; ?>>Schedule for Later</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="scheduledDate" class="form-label">Scheduled Date & Time</label>
                                        <input type="datetime-local" class="form-control" id="scheduledDate" name="scheduled_at" 
                                               value="<?php echo $campaign['scheduled_at'] ? date('Y-m-d\TH:i', strtotime($campaign['scheduled_at'])) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Preview -->
                            <div class="preview-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-eye me-2"></i>Preview
                                </h5>
                                <div id="emailPreview">
                                    <div class="border-bottom pb-2 mb-3">
                                        <strong>Subject:</strong> <?php echo htmlspecialchars($campaign['subject']); ?>
                                    </div>
                                    <div style="max-height: 300px; overflow-y: auto;">
                                        <?php echo $campaign['content']; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Campaign Stats -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title">Campaign Information</h6>
                                    <div class="small">
                                        <div class="mb-2">
                                            <strong>Status:</strong> 
                                            <span class="badge bg-<?php echo $campaign['status'] === 'draft' ? 'secondary' : 'success'; ?>">
                                                <?php echo ucfirst($campaign['status']); ?>
                                            </span>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Created:</strong> <?php echo date('M j, Y', strtotime($campaign['created_at'])); ?>
                                        </div>
                                        <div class="mb-2">
                                            <strong>Last Updated:</strong> <?php echo date('M j, Y', strtotime($campaign['updated_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <button type="button" class="btn btn-secondary" onclick="saveDraft()">
                                    <i class="fas fa-save me-2"></i>Save as Draft
                                </button>
                                <div>
                                    <button type="button" class="btn btn-info me-2" onclick="previewCampaign()">
                                        <i class="fas fa-eye me-2"></i>Preview
                                    </button>
                                    <?php if (hasPermission($permissions, 'can_send_campaigns')): ?>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-paper-plane me-2"></i>Update & Send Campaign
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-secondary" disabled>
                                        <i class="fas fa-lock me-2"></i>No Send Permission
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-detect base path for live hosting compatibility
        const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
        
        // Form handling
        document.getElementById('campaignForm').addEventListener('submit', function(e) {
            e.preventDefault();
            updateCampaign();
        });
        
        // Template selection
        document.getElementById('emailTemplate').addEventListener('change', function() {
            const templateId = this.value;
            if (templateId) {
                loadTemplate(templateId);
            }
        });
        
        // Recipient type change
        document.getElementById('recipientType').addEventListener('change', function() {
            updateRecipientType();
        });
        
        // Send type change
        document.getElementById('sendType').addEventListener('change', function() {
            const scheduledDateField = document.getElementById('scheduledDate');
            if (this.value === 'scheduled') {
                scheduledDateField.style.display = 'block';
                scheduledDateField.required = true;
            } else {
                scheduledDateField.style.display = 'none';
                scheduledDateField.required = false;
            }
        });
        
        function loadTemplate(templateId) {
            fetch(`${basePath}/api/templates/${templateId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('campaignSubject').value = data.template.subject;
                        document.getElementById('emailContent').value = data.template.content;
                        updatePreview();
                    }
                })
                .catch(error => {
                    console.error('Error loading template:', error);
                });
        }
        
        function updateRecipientType() {
            const recipientType = document.getElementById('recipientType').value;
            const tagsField = document.getElementById('tags')?.parentElement?.parentElement;
            
            if (recipientType === 'tags' && tagsField) {
                tagsField.style.display = 'block';
            } else if (tagsField) {
                tagsField.style.display = 'none';
            }
        }
        
        function updatePreview() {
            const subject = document.getElementById('campaignSubject').value;
            const content = document.getElementById('emailContent').value;
            
            const preview = document.getElementById('emailPreview');
            preview.innerHTML = `
                <div class="border-bottom pb-2 mb-3">
                    <strong>Subject:</strong> ${subject || 'No subject'}
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    ${content || '<em>No content</em>'}
                </div>
            `;
        }
        
        function previewCampaign() {
            updatePreview();
        }
        
        function saveDraft() {
            const formData = new FormData(document.getElementById('campaignForm'));
            formData.append('status', 'draft');
            
            fetch(`${basePath}/api/campaigns/${<?php echo $campaignId; ?>}`, {
                method: 'PUT',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Campaign updated successfully!');
                    window.location.href = `${basePath}/employee/campaigns`;
                } else {
                    alert(data.message || 'Failed to update campaign');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update campaign');
            });
        }
        
        function updateCampaign() {
            if (!confirm('Are you sure you want to update this campaign?')) {
                return;
            }
            
            const formData = new FormData(document.getElementById('campaignForm'));
            formData.append('status', 'active');
            
            fetch(`${basePath}/api/campaigns/${<?php echo $campaignId; ?>}`, {
                method: 'PUT',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Campaign updated and sent successfully!');
                    window.location.href = `${basePath}/employee/campaigns`;
                } else {
                    alert(data.message || 'Failed to update campaign');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to update campaign');
            });
        }
        
        // Initialize
        updateRecipientType();
        updatePreview();
    </script>
</body>
</html> 