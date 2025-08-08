<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

// Load user permissions for the dashboard
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../models/EmployeePermission.php";

$database = new Database();
$db = $database->getConnection();
$permissionModel = new EmployeePermission($db);
$permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);

include __DIR__ . "/../components/header.php";
include __DIR__ . "/../components/employee-sidebar.php";
?>

<div class="main-content">
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">Employee Dashboard</h1>
                <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                    <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-sm-inline">Refresh</span>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card h-100 bg-primary text-white shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1" id="totalContacts">-</h4>
                                <p class="mb-0 small">My Contacts</p>
                            </div>
                            <div>
                                <i class="bi bi-people-fill fs-2 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 bg-success text-white shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1" id="newLeads">-</h4>
                                <p class="mb-0 small">New Leads</p>
                            </div>
                            <div>
                                <i class="bi bi-person-plus-fill fs-2 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 bg-warning text-white shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1" id="pendingTasks">-</h4>
                                <p class="mb-0 small">Pending Tasks</p>
                            </div>
                            <div>
                                <i class="bi bi-clock-fill fs-2 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100 bg-info text-white shadow-sm">
                    <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1" id="completedTasks">-</h4>
                                <p class="mb-0 small">Completed</p>
                            </div>
                            <div>
                                <i class="bi bi-check-circle-fill fs-2 opacity-75"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Row -->
        <div class="row g-3">
            <!-- Recent Contacts -->
            <div class="col-12 col-lg-8">
                <div class="card h-100 shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people text-primary"></i> My Recent Contacts
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="recentContactsLoading" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2 mb-0">Loading contacts...</p>
                        </div>
                        <div id="recentContactsContent" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="border-0">Name</th>
                                            <th class="border-0 d-none d-md-table-cell">Company</th>
                                            <th class="border-0">Status</th>
                                            <th class="border-0 d-none d-sm-table-cell">Last Contact</th>
                                            <th class="border-0">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentContactsTable">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div id="recentContactsEmpty" class="text-center py-5" style="display: none;">
                            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3">No contacts assigned yet</h6>
                            <p class="text-muted small">Start by adding some contacts to see them here.</p>
                            <button class="btn btn-sm btn-primary" onclick="addNewContact()">
                                <i class="bi bi-person-plus"></i> Add First Contact
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar with Quick Actions and Activity -->
            <div class="col-12 col-lg-4">
                <!-- Quick Actions -->
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightning text-warning"></i> Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="viewAllContacts()">
                                <i class="bi bi-people"></i> View All Contacts
                            </button>
                            <button class="btn btn-outline-success" onclick="addNewContact()">
                                <i class="bi bi-person-plus"></i> Add New Contact
                            </button>
                            <?php if (hasPermission($permissions ?? [], 'can_send_instant_emails')): ?>
                            <button class="btn btn-outline-warning" onclick="openInstantEmail()">
                                <i class="bi bi-send"></i> Send Instant Email
                            </button>
                            <?php endif; ?>
                            <?php if (hasPermission($permissions ?? [], 'can_create_campaigns')): ?>
                            <button class="btn btn-outline-info" onclick="createCampaign()">
                                <i class="bi bi-plus-circle"></i> Create Campaign
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-outline-secondary" onclick="viewProfile()">
                                <i class="bi bi-person"></i> My Profile
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity text-success"></i> Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="recentActivityLoading" class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Loading activity...</p>
                        </div>
                        <div id="recentActivityContent" style="display: none;">
                            <div id="recentActivityList" class="activity-list">
                            </div>
                        </div>
                        <div id="recentActivityEmpty" class="text-center py-4" style="display: none;">
                            <i class="bi bi-activity text-muted" style="font-size: 2rem;"></i>
                            <h6 class="text-muted mt-2">No recent activity</h6>
                            <p class="text-muted small mb-0">Your recent actions will appear here.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Styles -->
<style>
.main-content {
    margin-left: 260px;
    padding: 20px;
    min-height: 100vh;
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 15px;
    }
}

.card {
    border: none;
    border-radius: 12px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1) !important;
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    border-radius: 12px 12px 0 0 !important;
    padding: 1rem 1.25rem;
}

.card-body {
    padding: 1.25rem;
}

.btn {
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.btn:hover {
    transform: translateY(-1px);
}

.table th {
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.75rem;
}

.table td {
    padding: 0.875rem 0.75rem;
    vertical-align: middle;
}

.badge {
    font-weight: 500;
    border-radius: 6px;
}

.activity-list .activity-item {
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.activity-list .activity-item:last-child {
    border-bottom: none;
}

.activity-item .activity-icon {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
}

.shadow-sm {
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.08) !important;
}

/* Responsive improvements */
@media (max-width: 576px) {
    .card-body {
        padding: 1rem;
    }
    
    .btn {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
    }
    
    .h3 {
        font-size: 1.5rem;
    }
}

/* Stats cards hover effects */
.bg-primary:hover { background-color: #0056b3 !important; }
.bg-success:hover { background-color: #157347 !important; }
.bg-warning:hover { background-color: #e0a800 !important; }
.bg-info:hover { background-color: #0dcaf0 !important; }

/* Loading states */
.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Table responsive improvements */
.table-responsive {
    border-radius: 8px;
}

.table thead th {
    background-color: var(--bs-light);
}
</style>

<script>
// Auto-detect base path for live hosting compatibility
const basePath = window.location.pathname.includes('/acrm/') ? '/acrm' : '';

// Load dashboard data on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
});

async function loadDashboardData() {
    await Promise.all([
        loadStats(),
        loadRecentContacts(),
        loadRecentActivity()
    ]);
}

async function loadStats() {
    try {
        const response = await fetch(basePath + '/api/employee/stats');
        const data = await response.json();
        
        if (response.ok && data.success) {
            document.getElementById('totalContacts').textContent = data.stats.totalContacts || 0;
            document.getElementById('newLeads').textContent = data.stats.newLeads || 0;
            document.getElementById('pendingTasks').textContent = data.stats.pendingTasks || 0;
            document.getElementById('completedTasks').textContent = data.stats.completedTasks || 0;
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadRecentContacts() {
    const loadingEl = document.getElementById('recentContactsLoading');
    const contentEl = document.getElementById('recentContactsContent');
    const emptyEl = document.getElementById('recentContactsEmpty');
    const tableEl = document.getElementById('recentContactsTable');

    try {
        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';
        emptyEl.style.display = 'none';

        const response = await fetch(basePath + '/api/employee/recent-contacts');
        const data = await response.json();

        if (response.ok && data.success) {
            if (data.contacts && data.contacts.length > 0) {
                tableEl.innerHTML = data.contacts.map(contact => `
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.875rem;">
                                    ${(contact.first_name || contact.name || 'U').charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div class="fw-medium">${contact.first_name || contact.name || 'Unknown'} ${contact.last_name || ''}</div>
                                    <small class="text-muted d-block">${contact.email || 'No email'}</small>
                                </div>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">${contact.company || '-'}</td>
                        <td>
                            <span class="badge bg-${getStatusColor(contact.status)} text-white">${contact.status || 'new'}</span>
                        </td>
                        <td class="d-none d-sm-table-cell">
                            <small class="text-muted">${contact.last_contact ? new Date(contact.last_contact).toLocaleDateString() : 'Never'}</small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-sm" onclick="viewContact(${contact.id})" title="View Contact">
                                    <i class="bi bi-eye"></i>
                                </button>
                                <button class="btn btn-outline-success btn-sm" onclick="contactPerson(${contact.id})" title="Contact">
                                    <i class="bi bi-telephone"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
                
                contentEl.style.display = 'block';
            } else {
                emptyEl.style.display = 'block';
            }
        } else {
            emptyEl.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading recent contacts:', error);
        emptyEl.style.display = 'block';
    } finally {
        loadingEl.style.display = 'none';
    }
}
                        <td>${contact.last_contacted ? new Date(contact.last_contacted).toLocaleDateString() : 'Never'}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="viewContact(${contact.id})">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                `).join('');
                
                contentEl.style.display = 'block';
            } else {
                emptyEl.style.display = 'block';
            }
        }
    } catch (error) {
        console.error('Error loading recent contacts:', error);
        emptyEl.style.display = 'block';
    } finally {
        loadingEl.style.display = 'none';
    }
}

async function loadRecentActivity() {
    const loadingEl = document.getElementById('recentActivityLoading');
    const contentEl = document.getElementById('recentActivityContent');
    const emptyEl = document.getElementById('recentActivityEmpty');
    const listEl = document.getElementById('recentActivityList');

    try {
        loadingEl.style.display = 'block';
        contentEl.style.display = 'none';
        emptyEl.style.display = 'none';

        const response = await fetch(basePath + '/api/employee/recent-activity');
        const data = await response.json();

        if (response.ok && data.success) {
            if (data.activities && data.activities.length > 0) {
                listEl.innerHTML = data.activities.map(activity => `
                    <div class="activity-item d-flex align-items-start">
                        <div class="activity-icon bg-${getActivityColor(activity.type)} text-white me-3">
                            <i class="bi bi-${getActivityIcon(activity.type)}"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-medium small">${activity.description}</div>
                            <div class="text-muted" style="font-size: 0.8rem;">${formatTimeAgo(activity.created_at)}</div>
                        </div>
                    </div>
                `).join('');
                
                contentEl.style.display = 'block';
            } else {
                emptyEl.style.display = 'block';
            }
        } else {
            emptyEl.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading recent activity:', error);
        emptyEl.style.display = 'block';
    } finally {
        loadingEl.style.display = 'none';
    }
}

function getStatusColor(status) {
    const colors = {
        'new': 'primary',
        'contacted': 'warning',
        'qualified': 'info',
        'converted': 'success',
        'lost': 'danger',
        'active': 'success',
        'inactive': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getActivityIcon(type) {
    const icons = {
        'contact_created': 'person-plus',
        'contact_updated': 'pencil',
        'contact_contacted': 'telephone',
        'email_sent': 'envelope',
        'campaign_created': 'plus-circle',
        'task_completed': 'check-circle',
        'note_added': 'chat-left-text'
    };
    return icons[type] || 'activity';
}

function getActivityColor(type) {
    const colors = {
        'contact_created': 'success',
        'contact_updated': 'warning', 
        'contact_contacted': 'primary',
        'email_sent': 'info',
        'campaign_created': 'purple',
        'task_completed': 'success',
        'note_added': 'secondary'
    };
    return colors[type] || 'primary';
}

function formatTimeAgo(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + ' minutes ago';
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + ' hours ago';
    if (diffInSeconds < 604800) return Math.floor(diffInSeconds / 86400) + ' days ago';
    
    return date.toLocaleDateString();
}

function contactPerson(id) {
    // Open contact modal or navigate to contact details
    window.location.href = basePath + '/employee/contacts/' + id;
}
    };
    return colors[status] || 'secondary';
}

function getActivityIcon(type) {
    const icons = {
        'contact_created': 'person-plus',
        'contact_updated': 'pencil',
        'contact_contacted': 'telephone',
        'email_sent': 'envelope',
        'campaign_created': 'plus-circle',
        'task_completed': 'check-circle',
        'note_added': 'chat-left-text'
    };
    return icons[type] || 'activity';
}

function refreshDashboard() {
    loadDashboardData();
}

function viewAllContacts() {
    window.location.href = basePath + '/employee/contacts';
}

function addNewContact() {
    window.location.href = basePath + '/employee/contacts/add';
}

function openInstantEmail() {
    window.location.href = basePath + '/employee/instant-email';
}

function createCampaign() {
    window.location.href = basePath + '/employee/campaigns/create';
}

function viewContact(id) {
    window.location.href = basePath + '/employee/contacts/' + id;
}

function viewProfile() {
    window.location.href = basePath + '/employee/profile';
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = basePath + '/employee/logout';
    }
}
</script>

<?php include __DIR__ . "/../components/footer.php"; ?> 