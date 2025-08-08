<?php
// Get user permissions if not already loaded
if (!isset($permissions)) {
    require_once __DIR__ . "/../../config/database.php";
    require_once __DIR__ . "/../../models/EmployeePermission.php";
    
    $database = new Database();
    $db = $database->getConnection();
    
    $permissionModel = new EmployeePermission($db);
    $permissions = $permissionModel->getUserPermissions($_SESSION["user_id"]);
}

// Helper function to check permissions
if (!function_exists('hasPermission')) {
    function hasPermission($permissions, $permission) {
        return isset($permissions[$permission]) && $permissions[$permission];
    }
}

// Get current page for active state
$currentPage = $_SERVER['REQUEST_URI'];
$currentPage = str_replace('/acrm', '', $currentPage); // Remove base path if present

// Helper function to check if current page matches pattern
if (!function_exists('isCurrentPage')) {
    function isCurrentPage($pattern) {
        global $currentPage;
        return strpos($currentPage, $pattern) !== false;
    }
}

// Helper function to check if current page is exactly
if (!function_exists('isExactPage')) {
    function isExactPage($pattern) {
        global $currentPage;
        return $currentPage === $pattern;
    }
}
?>

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
    padding-bottom: 20px;
    flex: 1;
    overflow-y: scroll;
    overflow-x: hidden;
    padding-right: 8px;
}

/* Custom scrollbar for navigation */
.sidebar-nav::-webkit-scrollbar {
    width: 8px;
}

.sidebar-nav::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 4px;
    margin: 10px 0;
}

.sidebar-nav::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 4px;
    transition: background 0.2s ease;
}

.sidebar-nav::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
}

/* For Firefox */
.sidebar-nav {
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) rgba(255, 255, 255, 0.1);
}

.nav-section {
    margin-bottom: 25px;
}

.nav-section:last-child {
    margin-bottom: 30px;
}

.nav-section-title {
    color: rgba(255, 255, 255, 0.5);
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 0 12px;
    margin-bottom: 8px;
}

.sidebar-nav .nav-item {
    margin-bottom: 4px;
}

.sidebar-nav .sidebar-link {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.2s ease;
    position: relative;
    font-size: 0.95rem;
    font-weight: 500;
}

.sidebar-nav .sidebar-link:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
    transform: translateX(4px);
}

.sidebar-nav .sidebar-link.active {
    color: #fff;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.sidebar-nav .sidebar-link i {
    font-size: 1.1rem;
    width: 20px;
    margin-right: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

/* Badge for notifications */
.nav-badge {
    margin-left: auto;
    background: #ef4444;
    color: #fff;
    font-size: 0.75rem;
    padding: 2px 8px;
    border-radius: 12px;
    font-weight: 600;
}

/* User Profile Section */
.sidebar-profile {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 260px;
    padding: 20px;
    background: #0f172a;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 1041;
}

.profile-info {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}

.profile-avatar {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 600;
    margin-right: 12px;
}

.profile-details {
    flex: 1;
    overflow: hidden;
}

.profile-name {
    color: #fff;
    font-size: 0.9rem;
    font-weight: 600;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.profile-email {
    color: rgba(255, 255, 255, 0.6);
    font-size: 0.75rem;
    margin: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.logout-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    padding: 8px;
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 8px;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.2);
    color: #fff;
    border-color: #ef4444;
}


/* Responsive */
@media (max-width: 768px) {
    .modern-sidebar {
        transform: translateX(-100%);
    }
    
    .modern-sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-profile {
        width: 260px;
    }
}

/* Toggle Button for Mobile */
.sidebar-toggle {
    display: none;
    position: fixed;
    top: 20px;
    left: 20px;
    z-index: 1050;
    width: 40px;
    height: 40px;
    background: #1e293b;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 1.2rem;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

@media (max-width: 768px) {
    .sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
    }
}
</style>

<div class="modern-sidebar" id="sidebar">
    <!-- Logo Section -->
    <div class="sidebar-logo">
        <h5>
            <span class="logo-icon">
                <i class="bi bi-telephone-fill"></i>
            </span>
            AutoDial Pro
        </h5>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- Main Menu -->
        <div class="nav-section">
            <div class="nav-section-title">Main Menu</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="sidebar-link <?php echo isCurrentPage('/employee/email-dashboard') ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/email-dashboard'); ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (hasPermission($permissions, 'can_create_campaigns') || hasPermission($permissions, 'can_view_all_campaigns')): ?>
                <li class="nav-item">
                    <a class="sidebar-link <?php echo (isCurrentPage('/employee/campaigns') && !isCurrentPage('/employee/campaigns/create') && !isCurrentPage('/employee/campaigns/edit')) ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/campaigns'); ?>">
                        <i class="bi bi-envelope"></i>
                        <span>My Campaigns</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission($permissions, 'can_create_campaigns')): ?>
                <li class="nav-item">
                    <a class="sidebar-link <?php echo isCurrentPage('/employee/campaigns/create') ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/campaigns/create'); ?>">
                        <i class="bi bi-plus-circle"></i>
                        <span>Create Campaign</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission($permissions, 'can_send_instant_emails')): ?>
                <li class="nav-item">
                    <a class="sidebar-link <?php echo isCurrentPage('/employee/instant-email') ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/instant-email'); ?>">
                        <i class="bi bi-send"></i>
                        <span>Instant Email</span>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
        
        <!-- Management -->
        <div class="nav-section">
            <div class="nav-section-title">Management</div>
            <ul class="nav flex-column">
                <?php if (hasPermission($permissions, 'can_edit_campaigns')): ?>
                <li class="nav-item">
                    <a class="sidebar-link <?php echo isCurrentPage('/employee/campaigns/edit') ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/campaigns'); ?>">
                        <i class="bi bi-pencil-square"></i>
                        <span>Edit Campaigns</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                <li class="nav-item">
                    <a class="sidebar-link <?php echo isCurrentPage('/employee/contacts') ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/contacts'); ?>">
                        <i class="bi bi-people"></i>
                        <span>Contacts</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="sidebar-link <?php echo isCurrentPage('/employee/profile') ? 'active' : ''; ?>" 
                       href="<?php echo base_path('employee/profile'); ?>">
                        <i class="bi bi-person"></i>
                        <span>Profile</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Analytics -->
        <div class="nav-section">
            <div class="nav-section-title">Analytics</div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="sidebar-link" href="#" onclick="showComingSoon('Campaign Analytics')">
                        <i class="bi bi-graph-up-arrow"></i>
                        <span>Campaign Analytics</span>
                        <span class="nav-badge">Soon</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="sidebar-link" href="#" onclick="showComingSoon('Performance Reports')">
                        <i class="bi bi-bar-chart"></i>
                        <span>Performance Reports</span>
                        <span class="nav-badge">Soon</span>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- User Profile Section -->
    <div class="sidebar-profile">
        <div class="profile-info">
            <div class="profile-avatar">
                <?php 
                $userName = $_SESSION["user_name"] ?? "User";
                $initials = implode('', array_map(function($part) {
                    return strtoupper(substr($part, 0, 1));
                }, array_slice(explode(' ', $userName), 0, 2)));
                echo $initials ?: 'U';
                ?>
            </div>
            <div class="profile-details">
                <p class="profile-name"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></p>
                <p class="profile-email"><?php echo htmlspecialchars($_SESSION["user_email"] ?? "employee@example.com"); ?></p>
            </div>
        </div>
        

        
        <a href="<?php echo base_path('employee/logout'); ?>" class="logout-btn" onclick="return confirm('Are you sure you want to logout?')">
            <i class="bi bi-box-arrow-right me-2"></i>
            Logout
        </a>
    </div>
</div>

<!-- Mobile Toggle Button -->
<button class="sidebar-toggle" onclick="toggleSidebar()">
    <i class="bi bi-list"></i>
</button>

<script>
// Toggle sidebar on mobile
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    sidebar.classList.toggle('show');
}

// Close sidebar when clicking outside on mobile
document.addEventListener('click', function(event) {
    const sidebar = document.getElementById('sidebar');
    const toggle = document.querySelector('.sidebar-toggle');
    
    if (window.innerWidth <= 768) {
        if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
            sidebar.classList.remove('show');
        }
    }
});

// Show coming soon toast
function showComingSoon(feature) {
    // Create toast element
    const toastHtml = `
        <div class="position-fixed top-0 end-0 p-3" style="z-index: 2000">
            <div class="toast show" role="alert">
                <div class="toast-header">
                    <i class="bi bi-info-circle text-primary me-2"></i>
                    <strong class="me-auto">Coming Soon</strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${feature} feature will be available soon!
                </div>
            </div>
        </div>
    `;
    
    // Add toast to body
    const toastContainer = document.createElement('div');
    toastContainer.innerHTML = toastHtml;
    document.body.appendChild(toastContainer);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toastContainer.remove();
    }, 3000);
}
</script> 