<?php
// Prevent session already started error
require_once __DIR__ . '/../../config/base_path.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: " . base_path('login'));
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
.quick-action-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    transition: all 0.3s;
}
.quick-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}
.campaign-status {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
}
.campaign-status.draft { background: #e9ecef; color: #6c757d; }
.campaign-status.scheduled { background: #fff3cd; color: #856404; }
.campaign-status.sending { background: #d1ecf1; color: #0c5460; }
.campaign-status.completed { background: #d4edda; color: #155724; }
.campaign-status.paused { background: #f8d7da; color: #721c24; }
.progress-ring {
    width: 60px;
    height: 60px;
    position: relative;
}
.progress-ring svg {
    width: 100%;
    height: 100%;
    transform: rotate(-90deg);
}
.progress-ring circle {
    fill: none;
    stroke-width: 4;
}
.progress-ring .bg {
    stroke: #e9ecef;
}
.progress-ring .progress {
    stroke: #5B5FDE;
    stroke-linecap: round;
    transition: stroke-dasharray 0.3s ease;
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
                <a href="<?php echo base_path('campaigns/create'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>New Campaign
                </a>
                <a href="<?php echo base_path('contacts/import'); ?>" class="btn btn-outline-primary">
                    <i class="bi bi-upload me-2"></i>Import Contacts
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-6 mb-3">
                <div class="card quick-action-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-envelope-plus" style="font-size: 2rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Create Email Campaign</h5>
                                <p class="mb-0 opacity-75">Design and schedule your next email campaign</p>
                            </div>
                        </div>
                        <a href="<?php echo base_path('campaigns/create'); ?>" class="btn btn-light btn-sm mt-3">
                            Get Started
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card quick-action-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="me-3">
                                <i class="bi bi-people-fill" style="font-size: 2rem;"></i>
                            </div>
                            <div>
                                <h5 class="mb-1">Import Contacts</h5>
                                <p class="mb-0 opacity-75">Upload your contact list via CSV or Excel</p>
                            </div>
                        </div>
                        <a href="<?php echo base_path('contacts/import'); ?>" class="btn btn-light btn-sm mt-3">
                            Import Now
                        </a>
                    </div>
                </div>
            </div>
        </div>

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
                                <h6 class="text-muted mb-2">Emails Sent</h6>
                                <h3 class="mb-0" id="emailsSent">0</h3>
                            </div>
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-envelope-check"></i>
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
                                <h6 class="text-muted mb-2">Open Rate</h6>
                                <h3 class="mb-0" id="openRate">0%</h3>
                            </div>
                            <div class="stat-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Performance Overview -->
        <div class="row mb-4">
            <div class="col-md-8 mb-3">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Email Campaign Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 text-center mb-3">
                                <div class="progress-ring">
                                    <svg>
                                        <circle class="bg" cx="30" cy="30" r="26"></circle>
                                        <circle class="progress" cx="30" cy="30" r="26" id="deliveryRate"></circle>
                                    </svg>
                                </div>
                                <h6 class="mt-2 mb-1">Delivery Rate</h6>
                                <small class="text-muted" id="deliveryRateText">0%</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="progress-ring">
                                    <svg>
                                        <circle class="bg" cx="30" cy="30" r="26"></circle>
                                        <circle class="progress" cx="30" cy="30" r="26" id="openRateRing"></circle>
                                    </svg>
                                </div>
                                <h6 class="mt-2 mb-1">Open Rate</h6>
                                <small class="text-muted" id="openRateText">0%</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="progress-ring">
                                    <svg>
                                        <circle class="bg" cx="30" cy="30" r="26"></circle>
                                        <circle class="progress" cx="30" cy="30" r="26" id="clickRate"></circle>
                                    </svg>
                                </div>
                                <h6 class="mt-2 mb-1">Click Rate</h6>
                                <small class="text-muted" id="clickRateText">0%</small>
                            </div>
                            <div class="col-md-3 text-center mb-3">
                                <div class="progress-ring">
                                    <svg>
                                        <circle class="bg" cx="30" cy="30" r="26"></circle>
                                        <circle class="progress" cx="30" cy="30" r="26" id="bounceRate"></circle>
                                    </svg>
                                </div>
                                <h6 class="mt-2 mb-1">Bounce Rate</h6>
                                <small class="text-muted" id="bounceRateText">0%</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Scheduled Campaigns</h5>
                    </div>
                    <div class="card-body">
                        <div id="scheduledCampaigns">
                            <div class="text-center text-muted py-3">
                                <i class="bi bi-clock" style="font-size: 2rem;"></i>
                                <p class="mt-2">No scheduled campaigns</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Campaigns -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Recent Email Campaigns</h5>
                        <a href="<?php echo base_path('campaigns'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Campaign Name</th>
                                        <th>Status</th>
                                        <th>Sent</th>
                                        <th>Opened</th>
                                        <th>Clicked</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recentCampaigns">
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">
                                            <i class="bi bi-envelope" style="font-size: 2rem;"></i>
                                            <p class="mt-2">No campaigns found</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
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
                                        <td colspan="4" class="text-center text-muted py-4">
                                            <i class="bi bi-activity" style="font-size: 2rem;"></i>
                                            <p class="mt-2">No recent activity</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load dashboard statistics
async function loadStats() {
    try {
        const response = await fetch('/api/dashboard_stats.php');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById("totalContacts").textContent = data.stats.totalContacts.toLocaleString();
            document.getElementById("emailsSent").textContent = data.stats.emailsSent.toLocaleString();
            document.getElementById("activeCampaigns").textContent = data.stats.activeCampaigns.toLocaleString();
            document.getElementById("openRate").textContent = data.stats.openRate;
            
            // Update progress rings
            updateProgressRing('deliveryRate', data.stats.deliveryRate);
            updateProgressRing('openRateRing', data.stats.openRate);
            updateProgressRing('clickRate', data.stats.clickRate);
            updateProgressRing('bounceRate', data.stats.bounceRate);
            
            // Update text
            document.getElementById("deliveryRateText").textContent = data.stats.deliveryRate;
            document.getElementById("openRateText").textContent = data.stats.openRate;
            document.getElementById("clickRateText").textContent = data.stats.clickRate;
            document.getElementById("bounceRateText").textContent = data.stats.bounceRate;
        } else {
            console.error('Failed to load stats:', data.message);
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

// Update progress ring
function updateProgressRing(elementId, percentage) {
    const circle = document.getElementById(elementId);
    const radius = 26;
    const circumference = 2 * Math.PI * radius;
    const percentageValue = parseFloat(percentage) || 0;
    const offset = circumference - (percentageValue / 100) * circumference;
    
    circle.style.strokeDasharray = circumference;
    circle.style.strokeDashoffset = offset;
}

// Load scheduled campaigns
async function loadScheduledCampaigns() {
    try {
        const response = await fetch('/api/get_campaigns.php?status=scheduled');
        const data = await response.json();
        
        const container = document.getElementById('scheduledCampaigns');
        if (data.success && data.campaigns.length > 0) {
            container.innerHTML = data.campaigns.map(campaign => `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="mb-1">${campaign.name}</h6>
                        <small class="text-muted">${campaign.scheduled_at}</small>
                    </div>
                    <span class="campaign-status scheduled">Scheduled</span>
                </div>
            `).join('');
        } else {
            container.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="bi bi-clock" style="font-size: 2rem;"></i>
                    <p class="mt-2">No scheduled campaigns</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error loading scheduled campaigns:', error);
    }
}

// Load recent campaigns
async function loadRecentCampaigns() {
    try {
        const response = await fetch('/api/get_campaigns.php?limit=5');
        const data = await response.json();
        
        const container = document.getElementById('recentCampaigns');
        if (data.success && data.campaigns.length > 0) {
            container.innerHTML = data.campaigns.map(campaign => `
                <tr>
                    <td>${campaign.name}</td>
                    <td><span class="campaign-status ${campaign.status}">${campaign.status}</span></td>
                    <td>${campaign.sent_count || 0}</td>
                    <td>${campaign.opened_count || 0}</td>
                    <td>${campaign.clicked_count || 0}</td>
                    <td>${new Date(campaign.created_at).toLocaleDateString()}</td>
                    <td>
                        <a href="/campaigns/view/${campaign.id}" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
            `).join('');
        } else {
            container.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="bi bi-envelope" style="font-size: 2rem;"></i>
                        <p class="mt-2">No campaigns found</p>
                    </td>
                </tr>
            `;
        }
    } catch (error) {
        console.error('Error loading recent campaigns:', error);
    }
}

// Load on page ready
document.addEventListener("DOMContentLoaded", function() {
    loadStats();
    loadScheduledCampaigns();
    loadRecentCampaigns();
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>