<?php
// Check if user is logged in and is an employee
if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
    require_once __DIR__ . "/../../config/base_path.php";
    header("Location: " . base_path('employee/login'));
    exit;
}

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
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" id="totalContacts">-</h4>
                                <p class="mb-0">My Contacts</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-people-fill fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" id="newLeads">-</h4>
                                <p class="mb-0">New Leads</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-person-plus-fill fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" id="pendingTasks">-</h4>
                                <p class="mb-0">Pending Tasks</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock-fill fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="mb-0" id="completedTasks">-</h4>
                                <p class="mb-0">Completed</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle-fill fs-1"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Contacts -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people"></i> My Recent Contacts
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="recentContactsLoading" class="text-center py-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="recentContactsContent" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Company</th>
                                            <th>Status</th>
                                            <th>Last Contact</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentContactsTable">
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div id="recentContactsEmpty" class="text-center py-4" style="display: none;">
                            <i class="bi bi-people text-muted fs-1"></i>
                            <p class="text-muted mt-2">No contacts assigned yet.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightning"></i> Quick Actions
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
                            <button class="btn btn-outline-info" onclick="viewProfile()">
                                <i class="bi bi-person"></i> My Profile
                            </button>
                            <button class="btn btn-outline-secondary" onclick="logout()">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-activity"></i> Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="recentActivityLoading" class="text-center py-2">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="recentActivityContent" style="display: none;">
                            <div id="recentActivityList">
                            </div>
                        </div>
                        <div id="recentActivityEmpty" class="text-center py-3" style="display: none;">
                            <i class="bi bi-activity text-muted"></i>
                            <p class="text-muted small mt-1">No recent activity</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                    ${contact.first_name.charAt(0).toUpperCase()}
                                </div>
                                <div>
                                    <div class="fw-medium">${contact.first_name} ${contact.last_name}</div>
                                    <small class="text-muted">${contact.email || 'No email'}</small>
                                </div>
                            </div>
                        </td>
                        <td>${contact.company || '-'}</td>
                        <td>
                            <span class="badge bg-${getStatusColor(contact.status)}">${contact.status}</span>
                        </td>
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
                    <div class="d-flex align-items-start mb-2">
                        <div class="avatar-sm bg-light rounded-circle d-flex align-items-center justify-content-center me-2">
                            <i class="bi bi-${getActivityIcon(activity.type)} text-primary"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="small fw-medium">${activity.description}</div>
                            <div class="small text-muted">${new Date(activity.created_at).toLocaleString()}</div>
                        </div>
                    </div>
                `).join('');
                
                contentEl.style.display = 'block';
            } else {
                emptyEl.style.display = 'block';
            }
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
        'lost': 'danger'
    };
    return colors[status] || 'secondary';
}

function getActivityIcon(type) {
    const icons = {
        'contact_created': 'person-plus',
        'contact_updated': 'pencil',
        'contact_contacted': 'telephone',
        'task_completed': 'check-circle'
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