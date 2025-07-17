<?php
// Prevent session already started error
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: /login");
    exit;
}

// Get user info from session
$userName = $_SESSION["user_name"] ?? "User";
$userEmail = $_SESSION["user_email"] ?? "user@example.com";
?>
<?php include __DIR__ . "/../components/header.php"; ?>

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
.stat-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-radius: 12px;
    transition: transform 0.2s;
}
.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
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
.upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.3s;
}
.upload-area:hover {
    border-color: #5B5FDE;
    background: #f0f2ff;
}
.upload-area.dragover {
    border-color: #5B5FDE;
    background: #e8ebff;
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
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .main-content {
        margin-left: 0;
    }
}
</style>

<?php include __DIR__ . "/../components/sidebar.php"; ?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Email Campaign Dashboard</h1>
                <p class="text-muted">Send bulk email campaigns to your contacts</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="showUploadHistory()">
                    <i class="bi bi-clock-history"></i> Upload History
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                    <i class="bi bi-plus-lg"></i> New Campaign
                </button>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Emails</h6>
                                <h3 class="mb-0" id="totalEmails">0</h3>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-envelope"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Emails Sent</h6>
                                <h3 class="mb-0" id="emailsSent">0</h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-send-check"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Open Rate</h6>
                                <h3 class="mb-0" id="openRate">0%</h3>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-envelope-open"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stat-card card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Click Rate</h6>
                                <h3 class="mb-0" id="clickRate">0%</h3>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-mouse"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Campaigns -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Active Campaigns</h5>
                    </div>
                    <div class="card-body" id="campaignsList">
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox display-1"></i>
                            <p class="mt-3">No active campaigns yet. Create your first campaign to get started!</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                                <i class="bi bi-plus-lg"></i> Create Campaign
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- New Campaign Modal -->
<div class="modal fade" id="newCampaignModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Email Campaign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCampaignForm">
                    <div class="mb-4">
                        <label class="form-label">Campaign Name</label>
                        <input type="text" class="form-control" name="campaign_name" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Subject Line</label>
                        <input type="text" class="form-control" name="subject" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">From Name</label>
                        <input type="text" class="form-control" name="from_name" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">From Email</label>
                        <input type="email" class="form-control" name="from_email" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Email Template</label>
                        <textarea class="form-control" name="email_content" rows="10" required placeholder="Dear {{name}},

Your email content here...

You can use variables like:
{{name}} - Recipient name
{{email}} - Recipient email
{{company}} - Company name"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Upload Email List (Excel/CSV)</label>
                        <div class="upload-area" id="emailListUpload" onclick="document.getElementById('emailFile').click()">
                            <i class="bi bi-cloud-upload display-4 text-muted"></i>
                            <p class="mt-3 mb-0">Click to upload or drag and drop</p>
                            <small class="text-muted">Supported formats: .xlsx, .xls, .csv</small>
                        </div>
                        <input type="file" id="emailFile" name="email_file" accept=".xlsx,.xls,.csv" style="display:none" onchange="handleFileSelect(this)">
                        <div id="fileInfo" class="mt-2"></div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6>Excel Format Requirements:</h6>
                        <ul class="mb-0">
                            <li>Column A: Email Address (required)</li>
                            <li>Column B: Full Name (optional)</li>
                            <li>Column C: Company (optional)</li>
                            <li>Column D: Any additional data (optional)</li>
                        </ul>
                        <a href="#" onclick="downloadTemplate()">Download sample template</a>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createCampaign()">Create Campaign</button>
            </div>
        </div>
    </div>
</div>

<script>
// Fix for drag and drop
const uploadArea = document.getElementById("emailListUpload");
if (uploadArea) {
    uploadArea.addEventListener("dragover", (e) => {
        e.preventDefault();
        uploadArea.classList.add("dragover");
    });
    
    uploadArea.addEventListener("dragleave", () => {
        uploadArea.classList.remove("dragover");
    });
    
    uploadArea.addEventListener("drop", (e) => {
        e.preventDefault();
        uploadArea.classList.remove("dragover");
        
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById("emailFile").files = files;
            handleFileSelect(document.getElementById("emailFile"));
        }
    });
}

function handleFileSelect(input) {
    const file = input.files[0];
    if (file) {
        const fileInfo = document.getElementById("fileInfo");
        fileInfo.innerHTML = `
            <div class="alert alert-success">
                <i class="bi bi-file-earmark-check"></i> 
                ${file.name} (${(file.size / 1024).toFixed(2)} KB)
            </div>
        `;
    }
}

function createCampaign() {
    const form = document.getElementById("newCampaignForm");
    const formData = new FormData(form);
    
    // Add file
    const fileInput = document.getElementById("emailFile");
    if (fileInput.files[0]) {
        formData.append("email_file", fileInput.files[0]);
    }
    
    // Show loading
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    
    // Simulate campaign creation
    setTimeout(() => {
        alert("Campaign created successfully!");
        location.reload();
    }, 2000);
}

function downloadTemplate() {
    const csvContent = "Email,Name,Company,Custom Field\nuser@example.com,John Doe,ABC Company,Additional Info\nexample@email.com,Jane Smith,XYZ Corp,More Data";
    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "email_campaign_template.csv";
    a.click();
    window.URL.revokeObjectURL(url);
}

function showUploadHistory() {
    alert("Upload history feature coming soon!");
}

// Load campaign statistics
function loadStats() {
    // This would normally fetch from API
    document.getElementById("totalEmails").textContent = "1,234";
    document.getElementById("emailsSent").textContent = "987";
    document.getElementById("openRate").textContent = "24.5%";
    document.getElementById("clickRate").textContent = "3.2%";
}

// Load on page ready
document.addEventListener("DOMContentLoaded", loadStats);
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>