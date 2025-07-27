<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmailCampaign.php";
require_once __DIR__ . "/../../models/Contact.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    header("Location: /employee/login");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Check if user has permission to create campaigns
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

if (!$permissions['can_create_campaigns']) {
    $_SESSION['error'] = "You don't have permission to create campaigns.";
    header("Location: /employee/campaigns");
    exit();
}

// Get total contacts
$stmt = $db->query("SELECT COUNT(*) as total FROM contacts");
$totalContacts = $stmt->fetch()['total'];

// Get contact groups/tags for targeting
$stmt = $db->query("SELECT DISTINCT tags FROM contacts WHERE tags IS NOT NULL AND tags != ''");
$tags = [];
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
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center text-white mb-4">
                        <i class="fas fa-user-circle fa-3x"></i>
                        <h6 class="mt-2"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></h6>
                        <small><?php echo ucfirst($_SESSION["user_role"]); ?></small>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/email-dashboard">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/campaigns">
                                <i class="fas fa-envelope me-2"></i> My Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/employee/campaigns/create">
                                <i class="fas fa-plus-circle me-2"></i> Create Campaign
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/contacts">
                                <i class="fas fa-address-book me-2"></i> Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/employee/profile">
                                <i class="fas fa-user me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="/employee/logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New Campaign</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="/employee/campaigns" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Campaigns
                        </a>
                    </div>
                </div>

                <form id="campaignForm" method="POST">
                    <div class="row">
                        <div class="col-lg-8">
                            <!-- Basic Information -->
                            <div class="form-section">
                                <h5 class="mb-3">Campaign Details</h5>
                                <div class="mb-3">
                                    <label for="name" class="form-label">Campaign Name *</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           placeholder="e.g., Holiday Sale Campaign">
                                </div>
                                <div class="mb-3">
                                    <label for="subject" class="form-label">Email Subject *</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required 
                                           placeholder="e.g., Special Holiday Offer Just for You!">
                                </div>
                                <div class="mb-3">
                                    <label for="from_name" class="form-label">From Name *</label>
                                    <input type="text" class="form-control" id="from_name" name="from_name" required 
                                           value="<?php echo htmlspecialchars($_SESSION["user_name"]); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="from_email" class="form-label">From Email *</label>
                                    <input type="email" class="form-control" id="from_email" name="from_email" required 
                                           placeholder="noreply@company.com">
                                </div>
                            </div>

                            <!-- Email Content -->
                            <div class="form-section">
                                <h5 class="mb-3">Email Content</h5>
                                <div class="mb-3">
                                    <label for="content" class="form-label">Email Body *</label>
                                    <div id="editor-toolbar">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('bold')">
                                            <i class="fas fa-bold"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('italic')">
                                            <i class="fas fa-italic"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('underline')">
                                            <i class="fas fa-underline"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertLink()">
                                            <i class="fas fa-link"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertUnorderedList')">
                                            <i class="fas fa-list-ul"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="formatText('insertOrderedList')">
                                            <i class="fas fa-list-ol"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="insertVariable()">
                                            <i class="fas fa-code"></i> Variable
                                        </button>
                                    </div>
                                    <div contenteditable="true" id="content-editor" class="form-control" 
                                         style="min-height: 300px; max-height: 500px; overflow-y: auto;"
                                         placeholder="Write your email content here..."></div>
                                    <textarea class="form-control d-none" id="content" name="content" required></textarea>
                                    <div class="form-text">
                                        You can use these variables: {{first_name}}, {{last_name}}, {{email}}, {{company}}, {{phone}}
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduling -->
                            <div class="form-section">
                                <h5 class="mb-3">Scheduling</h5>
                                <div class="mb-3">
                                    <label class="form-label">Send Type *</label>
                                    <div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="send_type" 
                                                   id="send_immediate" value="immediate" checked>
                                            <label class="form-check-label" for="send_immediate">
                                                Send Immediately
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="send_type" 
                                                   id="send_scheduled" value="scheduled">
                                            <label class="form-check-label" for="send_scheduled">
                                                Schedule for Later
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3" id="scheduleSection" style="display: none;">
                                    <label for="scheduled_at" class="form-label">Schedule Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="scheduled_at" name="scheduled_at">
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-4">
                            <!-- Recipients -->
                            <div class="form-section">
                                <h5 class="mb-3">Recipients</h5>
                                <div class="mb-3">
                                    <label class="form-label">Target Audience *</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="target_type" 
                                               id="target_all" value="all" checked>
                                        <label class="form-check-label" for="target_all">
                                            All Contacts (<?php echo $totalContacts; ?> contacts)
                                        </label>
                                    </div>
                                    <?php if (!empty($tags)): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="target_type" 
                                                   id="target_tags" value="tags">
                                            <label class="form-check-label" for="target_tags">
                                                Contacts with specific tags
                                            </label>
                                        </div>
                                        <div class="mt-2" id="tagsSection" style="display: none;">
                                            <select class="form-select" name="target_tags[]" multiple size="5">
                                                <?php foreach ($tags as $tag): ?>
                                                    <option value="<?php echo htmlspecialchars($tag); ?>">
                                                        <?php echo htmlspecialchars($tag); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Hold Ctrl/Cmd to select multiple tags</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    <span id="recipientCount"><?php echo $totalContacts; ?></span> contacts will receive this campaign
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="form-section">
                                <h5 class="mb-3">Actions</h5>
                                <button type="submit" name="action" value="draft" class="btn btn-secondary w-100 mb-2">
                                    <i class="fas fa-save me-2"></i>Save as Draft
                                </button>
                                <button type="submit" name="action" value="send" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Create & Send Campaign
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Rich text editor functions
        function formatText(command) {
            document.execCommand(command, false, null);
            document.getElementById('content-editor').focus();
        }
        
        function insertLink() {
            const url = prompt('Enter URL:');
            if (url) {
                document.execCommand('createLink', false, url);
                document.getElementById('content-editor').focus();
            }
        }
        
        function insertVariable() {
            const variables = ['{{first_name}}', '{{last_name}}', '{{email}}', '{{company}}', '{{phone}}'];
            const variable = prompt('Choose variable:\n' + variables.join('\n'));
            if (variable && variables.includes(variable)) {
                document.execCommand('insertText', false, variable);
                document.getElementById('content-editor').focus();
            }
        }
        
        // Sync content editor with textarea
        const contentEditor = document.getElementById('content-editor');
        const contentTextarea = document.getElementById('content');
        
        contentEditor.addEventListener('input', function() {
            contentTextarea.value = contentEditor.innerHTML;
        });
        
        // Toggle schedule section
        document.querySelectorAll('input[name="send_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const scheduleSection = document.getElementById('scheduleSection');
                const scheduledInput = document.getElementById('scheduled_at');
                
                if (this.value === 'scheduled') {
                    scheduleSection.style.display = 'block';
                    scheduledInput.required = true;
                    // Set minimum date to now
                    const now = new Date();
                    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                    scheduledInput.min = now.toISOString().slice(0, 16);
                } else {
                    scheduleSection.style.display = 'none';
                    scheduledInput.required = false;
                }
            });
        });

        // Toggle tags section
        document.querySelectorAll('input[name="target_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const tagsSection = document.getElementById('tagsSection');
                const tagsSelect = document.querySelector('select[name="target_tags[]"]');
                
                if (this.value === 'tags') {
                    tagsSection.style.display = 'block';
                    tagsSelect.required = true;
                } else {
                    tagsSection.style.display = 'none';
                    tagsSelect.required = false;
                }
                
                // Update recipient count (in real app, would make API call)
                updateRecipientCount();
            });
        });

        function updateRecipientCount() {
            // This would normally make an API call to get actual count
            const targetType = document.querySelector('input[name="target_type"]:checked').value;
            const count = document.getElementById('recipientCount');
            
            if (targetType === 'all') {
                count.textContent = '<?php echo $totalContacts; ?>';
            } else {
                // Would calculate based on selected tags
                count.textContent = 'Calculating...';
            }
        }

        // Handle form submission
        document.getElementById('campaignForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = e.submitter.value;
            formData.append('status', action === 'send' ? 'active' : 'draft');
            
            // Convert to JSON
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key.endsWith('[]')) {
                    const realKey = key.slice(0, -2);
                    if (!data[realKey]) data[realKey] = [];
                    data[realKey].push(value);
                } else {
                    data[key] = value;
                }
            }
            
            try {
                const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';
                const response = await fetch(`${basePath}/api/campaigns`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    // Redirect to campaigns list
                    window.location.href = '/employee/campaigns';
                } else {
                    alert(result.message || 'Failed to create campaign');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Network error. Please try again.');
            }
        });
    </script>
</body>
</html>