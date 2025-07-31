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
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get user permissions
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

// Helper function to check permissions
function hasPermission($permissions, $permission) {
    return isset($permissions[$permission]) && $permissions[$permission];
}

// Check if user has permission to create campaigns
if (!hasPermission($permissions, 'can_create_campaigns')) {
    $_SESSION['error'] = "You don't have permission to create campaigns.";
    header("Location: " . base_path('employee/campaigns'));
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

// Get email templates
$templateModel = new EmailTemplate($db);
$templates = $templateModel->getAvailableTemplates($_SESSION["user_id"]);
$templateCategories = $templateModel->getCategories();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Campaign - Email Campaign Management</title>
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
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New Campaign</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="<?php echo base_path('employee/campaigns'); ?>" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Campaigns
                        </a>
                    </div>
                </div>

                <form id="campaignForm" method="POST">
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
                                        <input type="text" class="form-control" id="campaignName" name="name" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="campaignSubject" class="form-label">Email Subject *</label>
                                        <input type="text" class="form-control" id="campaignSubject" name="subject" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="campaignDescription" class="form-label">Description</label>
                                    <textarea class="form-control" id="campaignDescription" name="description" rows="3"></textarea>
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
                                        <select class="form-select" id="recipientType" name="recipient_type">
                                            <option value="all">All Contacts (<?php echo number_format($totalContacts); ?>)</option>
                                            <option value="tags">By Tags</option>
                                            <option value="custom">Custom List</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="tags" class="form-label">Tags (Optional)</label>
                                        <select class="form-select" id="tags" name="tags[]" multiple>
                                            <?php foreach ($tags as $tag): ?>
                                                <option value="<?php echo htmlspecialchars($tag); ?>"><?php echo htmlspecialchars($tag); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="customRecipients" class="form-label">Custom Recipients (One email per line)</label>
                                    <textarea class="form-control" id="customRecipients" name="custom_recipients" rows="5" placeholder="email1@example.com&#10;email2@example.com&#10;email3@example.com"></textarea>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="form-section">
                                <h5 class="mb-3">
                                    <i class="fas fa-users me-2"></i>Recipients
                                </h5>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    You don't have permission to manage contacts. Please contact your administrator to get access to contact management features.
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
                                            <option value="<?php echo $template['id']; ?>"><?php echo htmlspecialchars($template['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="emailContent" class="form-label">Email Content *</label>
                                    <textarea class="form-control" id="emailContent" name="content" rows="10" required></textarea>
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
                                            <option value="immediate">Send Immediately</option>
                                            <option value="scheduled">Schedule for Later</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="scheduledDate" class="form-label">Scheduled Date & Time</label>
                                        <input type="datetime-local" class="form-control" id="scheduledDate" name="scheduled_at">
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
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-envelope fa-3x mb-3"></i>
                                        <p>Email preview will appear here</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Campaign Stats -->
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h6 class="card-title">Campaign Statistics</h6>
                                    <div class="row text-center">
                                        <div class="col-6">
                                            <div class="h4 text-primary" id="recipientCount">0</div>
                                            <small class="text-muted">Recipients</small>
                                        </div>
                                        <div class="col-6">
                                            <div class="h4 text-success" id="estimatedCost">$0.00</div>
                                            <small class="text-muted">Estimated Cost</small>
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
                                        <i class="fas fa-paper-plane me-2"></i>Send Campaign
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
            sendCampaign();
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
            updateRecipientFields();
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
        
        function updateRecipientFields() {
            const recipientType = document.getElementById('recipientType').value;
            const tagsField = document.getElementById('tags').parentElement.parentElement;
            const customField = document.getElementById('customRecipients').parentElement;
            
            if (recipientType === 'tags') {
                tagsField.style.display = 'block';
                customField.style.display = 'none';
            } else if (recipientType === 'custom') {
                tagsField.style.display = 'none';
                customField.style.display = 'block';
            } else {
                tagsField.style.display = 'none';
                customField.style.display = 'none';
            }
            
            updateRecipientCount();
        }
        
        function updateRecipientCount() {
            const recipientType = document.getElementById('recipientType').value;
            let count = 0;
            
            if (recipientType === 'all') {
                count = <?php echo $totalContacts; ?>;
            } else if (recipientType === 'tags') {
                const selectedTags = Array.from(document.getElementById('tags').selectedOptions).map(option => option.value);
                // This would need to be calculated via AJAX
                count = selectedTags.length * 10; // Placeholder
            } else if (recipientType === 'custom') {
                const emails = document.getElementById('customRecipients').value.split('\n').filter(email => email.trim());
                count = emails.length;
            }
            
            document.getElementById('recipientCount').textContent = count;
            document.getElementById('estimatedCost').textContent = `$${(count * 0.001).toFixed(2)}`;
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
            
            fetch(`${basePath}/api/campaigns`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Campaign saved as draft successfully!');
                    window.location.href = `${basePath}/employee/campaigns`;
                } else {
                    alert(data.message || 'Failed to save campaign');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to save campaign');
            });
        }
        
        function sendCampaign() {
            if (!confirm('Are you sure you want to send this campaign?')) {
                return;
            }
            
            const formData = new FormData(document.getElementById('campaignForm'));
            formData.append('status', 'active');
            
            fetch(`${basePath}/api/campaigns`, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Campaign sent successfully!');
                    window.location.href = `${basePath}/employee/campaigns`;
                } else {
                    alert(data.message || 'Failed to send campaign');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to send campaign');
            });
        }
        
        // Initialize
        updateRecipientFields();
        updatePreview();
    </script>
</body>
</html>