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

// Helper function to check permissions
if (!function_exists('hasPermission')) {
    function hasPermission($permissions, $permission) {
        return isset($permissions[$permission]) && $permissions[$permission];
    }
}

include __DIR__ . "/../components/header.php";
?>

<!-- Employee Sidebar (Admin Style) -->
<style>
/* Modern Sidebar Styling */
.modern-sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 260px;
    height: 100vh;
    background: #1e293b;
    z-index: 1040;
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

/* Custom Scrollbar */
.modern-sidebar::-webkit-scrollbar {
    width: 6px;
}

.modern-sidebar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.modern-sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
}

.modern-sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* Logo Section */
.sidebar-logo {
    padding: 24px 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.sidebar-logo h5 {
    color: #fff;
    font-size: 1.25rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.sidebar-logo .logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}

/* Navigation */
.sidebar-nav {
    padding: 0 12px;
    padding-bottom: 140px;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding-right: 8px;
    height: calc(100vh - 100px);
}

.nav-section {
    margin-bottom: 32px;
}

.nav-section-title {
    color: #94a3b8;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
    padding: 0 8px;
}

.nav-item {
    margin-bottom: 4px;
}

.sidebar-link {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    color: #cbd5e1;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    font-size: 0.9rem;
    font-weight: 500;
    position: relative;
}

.sidebar-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    transform: translateX(4px);
}

.sidebar-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.sidebar-link i {
    margin-right: 12px;
    font-size: 1.1rem;
    width: 20px;
    text-align: center;
}

/* User Profile at Bottom */
.sidebar-user {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(0, 0, 0, 0.2);
}

.user-info {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 12px;
    color: #fff;
    font-size: 1.1rem;
}

.user-details h6 {
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
}

.user-details p {
    color: #94a3b8;
    font-size: 0.75rem;
    margin: 0;
}

.logout-btn {
    width: 100%;
    padding: 8px 16px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #fca5a5;
    border-radius: 6px;
    font-size: 0.85rem;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fff;
}

/* Main Content */
.main-content {
    margin-left: 260px;
    padding: 30px;
    min-height: 100vh;
    background-color: #f8fafc;
}

/* Stats Cards */
.stat-card {
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    transition: all 0.3s ease;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.stat-card .card-body {
    padding: 24px;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    margin-bottom: 16px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 8px;
}

.stat-label {
    color: #64748b;
    font-size: 0.9rem;
    font-weight: 500;
}

.stat-change {
    font-size: 0.85rem;
    margin-top: 8px;
}

/* Activity Cards */
.activity-card {
    border: none;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    margin-bottom: 20px;
}

/* Quick Actions */
.quick-action-btn {
    padding: 12px 20px;
    border-radius: 8px;
    border: none;
    font-weight: 500;
    transition: all 0.2s ease;
    margin-bottom: 12px;
    width: 100%;
}

.quick-action-btn:hover {
    transform: translateY(-1px);
}

/* Instant Email Section */
.dropdown-menu {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.dropdown-item {
    padding: 8px 12px;
    border-bottom: 1px solid #f1f5f9;
}

.dropdown-item:hover {
    background-color: #f8fafc;
}

.dropdown-item:last-child {
    border-bottom: none;
}

#quickEmailForm .form-control {
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
}

#quickEmailForm .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

#quickEmailMessage {
    resize: vertical;
    min-height: 100px;
}

.template-btn {
    font-size: 0.75rem;
    padding: 4px 8px;
    border-radius: 6px;
}

/* Floating Action Button for Instant Email */
.floating-email-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    color: white;
    font-size: 1.5rem;
    z-index: 1000;
    transition: all 0.3s ease;
    cursor: pointer;
}

.floating-email-btn:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(16, 185, 129, 0.6);
    color: white;
}

.floating-email-btn:active {
    transform: scale(0.95);
}

/* Instant Email Highlight */
.instant-email-highlight {
    animation: pulse-green 2s infinite;
}

@keyframes pulse-green {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

/* Enhanced Quick Email Card */
.quick-email-card {
    background: linear-gradient(135deg, #ecfdf5 0%, #f0fdf4 100%);
    border: 2px solid #10b981;
    border-radius: 12px;
    position: relative;
    overflow: hidden;
}

.quick-email-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #10b981, #059669, #10b981);
    animation: shimmer 2s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%);
    }
    100% {
        transform: translateX(100%);
    }
}

@media (max-width: 768px) {
    .modern-sidebar {
        transform: translateX(-100%);
        width: 280px;
    }
    
    .main-content {
        margin-left: 0;
        padding: 20px 15px;
    }
    
    .sidebar-toggle {
        display: block;
    }
    
    #quickEmailForm .col-md-4,
    #quickEmailForm .col-md-8 {
        flex: 0 0 100%;
        max-width: 100%;
    }
    
    .floating-email-btn {
        width: 50px;
        height: 50px;
        bottom: 20px;
        right: 20px;
        font-size: 1.2rem;
    }
}
</style>

<!-- Sidebar -->
<div class="modern-sidebar">
    <!-- Logo Section -->
    <div class="sidebar-logo">
        <h5>
            <div class="logo-icon">
                <i class="bi bi-telephone-fill"></i>
            </div>
            AutoDial Pro
        </h5>
    </div>

    <!-- Navigation -->
    <div class="sidebar-nav">
        <!-- Dashboard Section -->
        <div class="nav-section">
            <div class="nav-section-title">Dashboard</div>
            <div class="nav-item">
                <a class="sidebar-link active" href="<?php echo base_path('employee/admin-dashboard'); ?>">
                    <i class="bi bi-house-door"></i>
                    <span>Dashboard</span>
                </a>
            </div>
        </div>

        <!-- Quick Actions -->
        <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
        <div class="nav-section">
            <div class="nav-section-title">Quick Actions</div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/instant-email'); ?>">
                    <i class="bi bi-send-fill"></i>
                    <span>Send Instant Email</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="sidebar-link" href="/bulk_email.php">
                    <i class="bi bi-envelope-at-fill"></i>
                    <span>Send Bulk Email</span>
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contact Management -->
        <div class="nav-section">
            <div class="nav-section-title">Contact Management</div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/contacts'); ?>">
                    <i class="bi bi-people"></i>
                    <span>All Contacts</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/contacts/add'); ?>">
                    <i class="bi bi-person-plus"></i>
                    <span>Add Contact</span>
                </a>
            </div>
        </div>

        <!-- Email & Campaigns -->
        <div class="nav-section">
            <div class="nav-section-title">Email & Campaigns</div>
            <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/instant-email'); ?>">
                    <i class="bi bi-send"></i>
                    <span>Instant Email</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="sidebar-link" href="/bulk_email.php">
                    <i class="bi bi-envelope-at"></i>
                    <span>Bulk Email</span>
                </a>
            </div>
            <?php endif; ?>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/campaigns'); ?>">
                    <i class="bi bi-envelope"></i>
                    <span>Email Campaigns</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/campaigns/create'); ?>">
                    <i class="bi bi-plus-circle"></i>
                    <span>Create Campaign</span>
                </a>
            </div>
        </div>

        <!-- Analytics & Reports -->
        <div class="nav-section">
            <div class="nav-section-title">Analytics</div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/analytics'); ?>">
                    <i class="bi bi-graph-up"></i>
                    <span>Analytics</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/reports'); ?>">
                    <i class="bi bi-file-text"></i>
                    <span>Reports</span>
                </a>
            </div>
        </div>

        <!-- Settings -->
        <div class="nav-section">
            <div class="nav-section-title">Settings</div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/profile'); ?>">
                    <i class="bi bi-person"></i>
                    <span>Profile</span>
                </a>
            </div>
            <div class="nav-item">
                <a class="sidebar-link" href="<?php echo base_path('employee/settings'); ?>">
                    <i class="bi bi-gear"></i>
                    <span>Settings</span>
                </a>
            </div>
        </div>
    </div>

    <!-- User Profile -->
    <div class="sidebar-user">
        <div class="user-info">
            <div class="user-avatar">
                <i class="bi bi-person"></i>
            </div>
            <div class="user-details">
                <h6><?php echo htmlspecialchars($_SESSION["user_name"] ?? "Employee"); ?></h6>
                <p><?php echo htmlspecialchars($_SESSION["user_role"] ?? "agent"); ?></p>
            </div>
        </div>
        <button class="logout-btn" onclick="logout()">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </button>
    </div>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Employee Dashboard</h1>
            <p class="text-muted">Welcome back, <?php echo htmlspecialchars($_SESSION["user_name"]); ?>!</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
            <button class="btn btn-success" onclick="sendInstantEmail()">
                <i class="bi bi-send-fill"></i> <span class="d-none d-sm-inline">Send Email</span>
            </button>
            <?php endif; ?>
            <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                <i class="bi bi-arrow-clockwise"></i> <span class="d-none d-sm-inline">Refresh</span>
            </button>
            <button class="btn btn-primary" onclick="quickActions()">
                <i class="bi bi-plus-circle"></i> <span class="d-none d-sm-inline">Quick Actions</span>
            </button>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-primary text-white mx-auto">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-value text-primary" id="totalContacts">-</div>
                    <div class="stat-label">Total Contacts</div>
                    <div class="stat-change text-success">
                        <i class="bi bi-arrow-up"></i> +12% this month
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-success text-white mx-auto">
                        <i class="bi bi-envelope-check"></i>
                    </div>
                    <div class="stat-value text-success" id="emailsSent">-</div>
                    <div class="stat-label">Emails Sent</div>
                    <div class="stat-change text-success">
                        <i class="bi bi-arrow-up"></i> +8% this week
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-info text-white mx-auto">
                        <i class="bi bi-activity"></i>
                    </div>
                    <div class="stat-value text-info" id="campaignsActive">-</div>
                    <div class="stat-label">Active Campaigns</div>
                    <div class="stat-change text-muted">
                        <i class="bi bi-dash"></i> No change
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card h-100">
                <div class="card-body text-center">
                    <div class="stat-icon bg-warning text-white mx-auto">
                        <i class="bi bi-percent"></i>
                    </div>
                    <div class="stat-value text-warning" id="conversionRate">-</div>
                    <div class="stat-label">Conversion Rate</div>
                    <div class="stat-change text-success">
                        <i class="bi bi-arrow-up"></i> +5% this month
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Instant Email Stats & Quick Access -->
    <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.8rem;">
                                        <i class="bi bi-send-fill"></i>
                                    </div>
                                </div>
                                <div>
                                    <h4 class="mb-1">⚡ Instant Email Ready</h4>
                                    <p class="mb-0 opacity-90">Send emails quickly without leaving this dashboard. Your email productivity tool.</p>
                                    <small class="opacity-75">
                                        <span id="todayEmailCount">0</span> emails sent today • 
                                        <span id="weekEmailCount">0</span> this week
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <button class="btn btn-light btn-lg" onclick="scrollToQuickEmail()">
                                <i class="bi bi-send-fill me-2"></i>Send Email Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Instant Email Section -->
    <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card quick-email-card activity-card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-send-fill"></i> ⚡ Instant Email</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-success">Quick Send</span>
                        <a href="<?php echo base_path('employee/instant-email'); ?>" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-up-right"></i> Full View
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form id="quickEmailForm" class="row g-3">
                        <div class="col-md-4">
                            <label for="quickEmailTo" class="form-label">To</label>
                            <input type="email" class="form-control" id="quickEmailTo" placeholder="recipient@example.com" required>
                            <div id="contactSuggestions" class="dropdown-menu w-100" style="display: none;"></div>
                        </div>
                        <div class="col-md-8">
                            <label for="quickEmailSubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="quickEmailSubject" placeholder="Email subject" required>
                        </div>
                        <div class="col-12">
                            <label for="quickEmailMessage" class="form-label">Message</label>
                            <textarea class="form-control" id="quickEmailMessage" rows="4" placeholder="Type your message here..." required></textarea>
                        </div>
                        <div class="col-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="saveAsTemplate">
                                    <label class="form-check-label" for="saveAsTemplate">
                                        Save as template
                                    </label>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary me-2" onclick="clearQuickEmail()">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </button>
                                    <button type="submit" class="btn btn-success" id="quickEmailSendBtn">
                                        <i class="bi bi-send"></i> Send Email
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Email Templates Quick Access -->
                    <div class="mt-3 pt-3 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <small class="text-muted fw-semibold">Quick Templates</small>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadEmailTemplates()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                        <div id="emailTemplatesQuick" class="d-flex gap-2 flex-wrap">
                            <!-- Templates will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bulk Email Section -->
    <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
    <div class="row g-4 mb-4">
        <div class="col-12">
            <div class="card quick-email-card activity-card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-envelope-at-fill"></i> 📧 Bulk Email</h5>
                    <div class="d-flex gap-2">
                        <span class="badge bg-light text-primary">Mass Send</span>
                        <a href="/bulk_email.php" class="btn btn-sm btn-light">
                            <i class="bi bi-arrow-up-right"></i> Full View
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">📋 Bulk Email Features</h6>
                                    <p class="text-muted mb-0">Send emails to multiple recipients efficiently</p>
                                </div>
                                <div class="text-end">
                                    <a href="/bulk_email.php" class="btn btn-primary">
                                        <i class="bi bi-envelope-at me-2"></i>Start Bulk Email
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="bi bi-people-fill text-primary fs-4"></i>
                                        <div class="mt-1">
                                            <small class="text-muted">Multi-Select</small><br>
                                            <strong class="text-primary">Recipients</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="bi bi-speedometer2 text-success fs-4"></i>
                                        <div class="mt-1">
                                            <small class="text-muted">Fast</small><br>
                                            <strong class="text-success">Batch Send</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="bi bi-bar-chart-fill text-info fs-4"></i>
                                        <div class="mt-1">
                                            <small class="text-muted">Progress</small><br>
                                            <strong class="text-info">Tracking</strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="text-center p-2 bg-light rounded">
                                        <i class="bi bi-template text-warning fs-4"></i>
                                        <div class="mt-1">
                                            <small class="text-muted">Email</small><br>
                                            <strong class="text-warning">Templates</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Dashboard Content Row -->
    <div class="row g-4">
        <!-- Quick Actions -->
        <div class="col-lg-4">
            <div class="card activity-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                </div>
                <div class="card-body">
                    <button class="quick-action-btn btn btn-outline-primary" onclick="addContact()">
                        <i class="bi bi-person-plus me-2"></i>Add New Contact
                    </button>
                    <?php if (hasPermission($permissions, 'send_instant_emails')): ?>
                    <button class="quick-action-btn btn btn-outline-success" onclick="sendInstantEmail()">
                        <i class="bi bi-send me-2"></i>Send Instant Email
                    </button>
                    <button class="quick-action-btn btn btn-outline-success" onclick="sendBulkEmail()">
                        <i class="bi bi-envelope-at me-2"></i>Send Bulk Email
                    </button>
                    <?php endif; ?>
                    <button class="quick-action-btn btn btn-outline-info" onclick="createCampaign()">
                        <i class="bi bi-envelope-plus me-2"></i>Create Campaign
                    </button>
                    <button class="quick-action-btn btn btn-outline-warning" onclick="viewAnalytics()">
                        <i class="bi bi-graph-up me-2"></i>View Analytics
                    </button>
                </div>
            </div>
        </div>

        <!-- Recent Contacts -->
        <div class="col-lg-8">
            <div class="card activity-card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people"></i> Recent Contacts</h5>
                    <a href="<?php echo base_path('employee/contacts'); ?>" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div id="recentContactsLoader" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2">Loading recent contacts...</span>
                    </div>
                    <div id="recentContactsList" style="display: none;">
                        <!-- Contacts will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Row -->
    <div class="row g-4 mt-2">
        <div class="col-12">
            <div class="card activity-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div id="recentActivityLoader" class="text-center py-3">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <span class="ms-2">Loading recent activity...</span>
                    </div>
                    <div id="recentActivityList" style="display: none;">
                        <!-- Activity will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Overview -->
    <div class="row g-4 mt-2">
        <div class="col-lg-6">
            <div class="card activity-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Email Performance</h5>
                </div>
                <div class="card-body">
                    <canvas id="emailChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card activity-card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Contact Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="contactChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Helper function for permission checking
function hasPermission(permissions, permission) {
    return permissions && permissions[permission];
}

// Base path helper
const basePath = '<?php echo rtrim(base_path(), '/'); ?>';

// Dashboard Stats Loading
async function loadDashboardStats() {
    try {
        const response = await fetch(basePath + '/api/employee/stats');
        if (response.ok) {
            const data = await response.json();
            
            document.getElementById('totalContacts').textContent = data.total_contacts || '0';
            document.getElementById('emailsSent').textContent = data.emails_sent || '0';
            document.getElementById('campaignsActive').textContent = data.active_campaigns || '0';
            document.getElementById('conversionRate').textContent = (data.conversion_rate || '0') + '%';
            
            // Update instant email stats if elements exist
            const todayCount = document.getElementById('todayEmailCount');
            const weekCount = document.getElementById('weekEmailCount');
            if (todayCount) todayCount.textContent = data.emails_today || '0';
            if (weekCount) weekCount.textContent = data.emails_week || '0';
        }
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

// Recent Contacts Loading
async function loadRecentContacts() {
    try {
        const response = await fetch(basePath + '/api/employee/recent-contacts');
        if (response.ok) {
            const data = await response.json();
            
            const loader = document.getElementById('recentContactsLoader');
            const list = document.getElementById('recentContactsList');
            
            if (data.contacts && data.contacts.length > 0) {
                let html = '<div class="table-responsive"><table class="table table-hover"><tbody>';
                data.contacts.forEach(contact => {
                    html += `
                        <tr style="cursor: pointer;" onclick="viewContact(${contact.id})">
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-sm me-3">
                                        <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <span class="text-white fw-bold">${contact.name.charAt(0).toUpperCase()}</span>
                                        </div>
                                    </div>
                                    <div>
                                        <h6 class="mb-0">${contact.name}</h6>
                                        <small class="text-muted">${contact.email}</small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small class="text-muted">${contact.company || 'No Company'}</small>
                            </td>
                            <td>
                                <small class="text-muted">${contact.created_at || 'Unknown'}</small>
                            </td>
                        </tr>
                    `;
                });
                html += '</tbody></table></div>';
                list.innerHTML = html;
            } else {
                list.innerHTML = '<p class="text-center text-muted">No recent contacts found.</p>';
            }
            
            loader.style.display = 'none';
            list.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading recent contacts:', error);
        document.getElementById('recentContactsLoader').style.display = 'none';
        document.getElementById('recentContactsList').innerHTML = '<p class="text-center text-danger">Error loading contacts.</p>';
        document.getElementById('recentContactsList').style.display = 'block';
    }
}

// Recent Activity Loading
async function loadRecentActivity() {
    try {
        const response = await fetch(basePath + '/api/employee/recent-activity');
        if (response.ok) {
            const data = await response.json();
            
            const loader = document.getElementById('recentActivityLoader');
            const list = document.getElementById('recentActivityList');
            
            if (data.activities && data.activities.length > 0) {
                let html = '<div class="list-group list-group-flush">';
                data.activities.forEach(activity => {
                    const icon = getActivityIcon(activity.type);
                    const color = getActivityColor(activity.type);
                    html += `
                        <div class="list-group-item border-0 py-3">
                            <div class="d-flex align-items-center">
                                <div class="me-3">
                                    <div class="bg-${color} rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                        <i class="bi bi-${icon} text-white"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">${activity.title}</h6>
                                    <p class="mb-0 text-muted small">${activity.description}</p>
                                </div>
                                <div>
                                    <small class="text-muted">${activity.time}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                list.innerHTML = html;
            } else {
                list.innerHTML = '<p class="text-center text-muted">No recent activity found.</p>';
            }
            
            loader.style.display = 'none';
            list.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading recent activity:', error);
        document.getElementById('recentActivityLoader').style.display = 'none';
        document.getElementById('recentActivityList').innerHTML = '<p class="text-center text-danger">Error loading activity.</p>';
        document.getElementById('recentActivityList').style.display = 'block';
    }
}

// Helper functions for activity display
function getActivityIcon(type) {
    const icons = {
        'contact_added': 'person-plus',
        'email_sent': 'envelope-check',
        'campaign_created': 'envelope-plus',
        'login': 'box-arrow-in-right',
        'default': 'clock'
    };
    return icons[type] || icons.default;
}

function getActivityColor(type) {
    const colors = {
        'contact_added': 'success',
        'email_sent': 'primary',
        'campaign_created': 'info',
        'login': 'secondary',
        'default': 'muted'
    };
    return colors[type] || colors.default;
}

// Quick Action Functions
function addContact() {
    window.location.href = basePath + '/employee/contacts/add';
}

function sendInstantEmail() {
    window.location.href = basePath + '/employee/instant-email';
}

function sendBulkEmail() {
    window.location.href = '/bulk_email.php';
}

function createCampaign() {
    window.location.href = basePath + '/employee/campaigns/create';
}

function viewAnalytics() {
    window.location.href = basePath + '/employee/analytics';
}

function viewContact(id) {
    window.location.href = basePath + '/employee/contacts/' + id;
}

function refreshDashboard() {
    location.reload();
}

function quickActions() {
    // Show quick actions modal or dropdown
    window.location.href = basePath + '/employee/contacts/add';
}

// Instant Email Functions
function clearQuickEmail() {
    document.getElementById('quickEmailTo').value = '';
    document.getElementById('quickEmailSubject').value = '';
    document.getElementById('quickEmailMessage').value = '';
    document.getElementById('saveAsTemplate').checked = false;
}

async function loadEmailTemplates() {
    try {
        const response = await fetch(basePath + '/api/instant-email/templates');
        if (response.ok) {
            const data = await response.json();
            const container = document.getElementById('emailTemplatesQuick');
            
            if (data.templates && data.templates.length > 0) {
                let html = '';
                // Show first 3 templates only
                data.templates.slice(0, 3).forEach(template => {
                    html += `
                        <button type="button" class="btn btn-sm btn-outline-info" 
                                onclick="useTemplate('${template.subject}', '${template.body.replace(/'/g, "\\'")}')">
                            <i class="bi bi-file-text"></i> ${template.name || template.subject}
                        </button>
                    `;
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<small class="text-muted">No templates found</small>';
            }
        }
    } catch (error) {
        console.error('Error loading templates:', error);
    }
}

function useTemplate(subject, body) {
    document.getElementById('quickEmailSubject').value = subject;
    document.getElementById('quickEmailMessage').value = body;
}

// Contact suggestions for email
let contactSuggestionsTimeout;
document.addEventListener('DOMContentLoaded', function() {
    const emailToInput = document.getElementById('quickEmailTo');
    const suggestionsDiv = document.getElementById('contactSuggestions');
    
    if (emailToInput) {
        emailToInput.addEventListener('input', function() {
            clearTimeout(contactSuggestionsTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                suggestionsDiv.style.display = 'none';
                return;
            }
            
            contactSuggestionsTimeout = setTimeout(async () => {
                try {
                    const response = await fetch(basePath + '/api/instant-email/contacts?q=' + encodeURIComponent(query));
                    if (response.ok) {
                        const data = await response.json();
                        
                        if (data.contacts && data.contacts.length > 0) {
                            let html = '';
                            data.contacts.forEach(contact => {
                                html += `
                                    <div class="dropdown-item" style="cursor: pointer;" 
                                         onclick="selectContact('${contact.email}', '${contact.name}')">
                                        <div class="d-flex align-items-center">
                                            <div class="me-2">
                                                <i class="bi bi-person-circle"></i>
                                            </div>
                                            <div>
                                                <div class="fw-semibold">${contact.name}</div>
                                                <small class="text-muted">${contact.email}</small>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            suggestionsDiv.innerHTML = html;
                            suggestionsDiv.style.display = 'block';
                        } else {
                            suggestionsDiv.style.display = 'none';
                        }
                    }
                } catch (error) {
                    console.error('Error fetching contacts:', error);
                }
            }, 300);
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!emailToInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
                suggestionsDiv.style.display = 'none';
            }
        });
    }
});

function selectContact(email, name) {
    document.getElementById('quickEmailTo').value = email;
    document.getElementById('contactSuggestions').style.display = 'none';
}

// Quick Email Form Handler
document.addEventListener('DOMContentLoaded', function() {
    const quickEmailForm = document.getElementById('quickEmailForm');
    if (quickEmailForm) {
        quickEmailForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const sendBtn = document.getElementById('quickEmailSendBtn');
            const originalText = sendBtn.innerHTML;
            
            // Show loading state
            sendBtn.disabled = true;
            sendBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
            
            try {
                const formData = new FormData();
                formData.append('to', document.getElementById('quickEmailTo').value);
                formData.append('subject', document.getElementById('quickEmailSubject').value);
                formData.append('message', document.getElementById('quickEmailMessage').value);
                formData.append('save_as_template', document.getElementById('saveAsTemplate').checked ? '1' : '0');
                
                const response = await fetch(basePath + '/api/instant-email/send', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (response.ok && result.success) {
                    // Success feedback
                    sendBtn.innerHTML = '<i class="bi bi-check-circle"></i> Sent!';
                    sendBtn.className = 'btn btn-success';
                    
                    // Clear form
                    setTimeout(() => {
                        clearQuickEmail();
                        sendBtn.disabled = false;
                        sendBtn.innerHTML = originalText;
                        sendBtn.className = 'btn btn-success';
                        
                        // Refresh dashboard stats
                        loadDashboardStats();
                        loadRecentActivity();
                    }, 2000);
                    
                    // Show success message
                    showNotification('Email sent successfully!', 'success');
                } else {
                    throw new Error(result.message || 'Failed to send email');
                }
            } catch (error) {
                console.error('Error sending email:', error);
                sendBtn.disabled = false;
                sendBtn.innerHTML = originalText;
                showNotification('Failed to send email: ' + error.message, 'error');
            }
        });
    }
});

// Notification function
function showNotification(message, type = 'info') {
    // Create notification element
    const notification = document.createElement('div');
    notification.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = basePath + '/employee/logout';
    }
}

// Initialize Dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardStats();
    loadRecentContacts();
    loadRecentActivity();
    loadEmailTemplates(); // Load email templates for quick access
});

// Auto-refresh every 5 minutes
setInterval(function() {
    loadDashboardStats();
    loadRecentContacts();
    loadRecentActivity();
}, 300000);
</script>

<!-- Floating Action Button for Instant Email -->
<?php if (hasPermission($permissions, 'send_instant_emails')): ?>
<button class="floating-email-btn instant-email-highlight" onclick="scrollToQuickEmail()" title="Quick Email">
    <i class="bi bi-send-fill"></i>
</button>
<?php endif; ?>

<script>
// Scroll to quick email section
function scrollToQuickEmail() {
    const quickEmailSection = document.querySelector('.quick-email-card');
    if (quickEmailSection) {
        quickEmailSection.scrollIntoView({ 
            behavior: 'smooth', 
            block: 'center' 
        });
        
        // Focus on the email input
        setTimeout(() => {
            const emailInput = document.getElementById('quickEmailTo');
            if (emailInput) {
                emailInput.focus();
            }
        }, 500);
    }
}

// Add floating button hide/show on scroll
let lastScrollTop = 0;
window.addEventListener('scroll', function() {
    const floatingBtn = document.querySelector('.floating-email-btn');
    if (floatingBtn) {
        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        
        if (scrollTop > lastScrollTop && scrollTop > 200) {
            // Scrolling down
            floatingBtn.style.transform = 'translateY(100px)';
        } else {
            // Scrolling up
            floatingBtn.style.transform = 'translateY(0)';
        }
        lastScrollTop = scrollTop;
    }
});
</script>

<!-- Footer -->
<?php include __DIR__ . "/../components/footer.php"; ?>
