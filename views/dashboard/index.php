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
                <h1 class="h3 mb-0">AutoDial Pro Dashboard</h1>
                <p class="text-muted">Intelligent auto dialing and CRM management</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="showReports()">
                    <i class="bi bi-graph-up"></i> Reports
                </button>
                <button class="btn btn-primary" onclick="startDialer()">
                    <i class="bi bi-telephone"></i> Start Dialer
                </button>
            </div>
        </div>
        
        <?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>
        <?php if (isset($_SESSION['user_id'])): ?>
            <div style="margin-bottom: 1em;"><a href="/views/dashboard/employee_management.php" class="btn btn-primary">Employee Management</a></div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stat-card card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Contacts</h6>
                                <h3 class="mb-0" id="totalContacts">0</h3>
                            </div>
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-people"></i>
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
                                <h6 class="text-muted mb-2">Calls Today</h6>
                                <h3 class="mb-0" id="callsToday">0</h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-telephone"></i>
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
                                <h6 class="text-muted mb-2">Active Campaigns</h6>
                                <h3 class="mb-0" id="activeCampaigns">0</h3>
                            </div>
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
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
                                <h6 class="text-muted mb-2">Connection Rate</h6>
                                <h3 class="mb-0" id="connectionRate">0%</h3>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity & Quick Actions -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Contact</th>
                                        <th>Action</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="activityList">
                                    <tr>
                                        <td>10:32 AM</td>
                                        <td>John Doe</td>
                                        <td>Outbound Call</td>
                                        <td><span class="badge bg-success">Connected</span></td>
                                    </tr>
                                    <tr>
                                        <td>10:28 AM</td>
                                        <td>Jane Smith</td>
                                        <td>Email Sent</td>
                                        <td><span class="badge bg-primary">Delivered</span></td>
                                    </tr>
                                    <tr>
                                        <td>10:15 AM</td>
                                        <td>Mike Johnson</td>
                                        <td>Outbound Call</td>
                                        <td><span class="badge bg-warning">Voicemail</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="startDialer()">
                                <i class="bi bi-telephone me-2"></i>Start Auto Dialer
                            </button>
                            <button class="btn btn-outline-primary" onclick="addContact()">
                                <i class="bi bi-person-plus me-2"></i>Add Contact
                            </button>
                            <button class="btn btn-outline-primary" onclick="createCampaign()">
                                <i class="bi bi-envelope-plus me-2"></i>Create Campaign
                            </button>
                            <button class="btn btn-outline-primary" onclick="importContacts()">
                                <i class="bi bi-upload me-2"></i>Import Contacts
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<script>

// Load dashboard statistics
function loadStats() {
    // This would normally fetch from API
    document.getElementById("totalContacts").textContent = "1,234";
    document.getElementById("callsToday").textContent = "156";
    document.getElementById("activeCampaigns").textContent = "8";
    document.getElementById("connectionRate").textContent = "23.5%";
}

// AutoDial Pro specific functions
function startDialer() {
    alert("Starting Auto Dialer... This feature will connect to your phone system.");
}

function addContact() {
    alert("Add Contact feature coming soon!");
}

function createCampaign() {
    alert("Create Campaign feature coming soon!");
}

function importContacts() {
    alert("Import Contacts feature coming soon!");
}

function showReports() {
    alert("Reports dashboard coming soon!");
}

// Load on page ready
document.addEventListener("DOMContentLoaded", loadStats);
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>