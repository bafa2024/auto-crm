<?php
require_once __DIR__ . '/../../config/base_path.php';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="d-flex align-items-center">
            <div class="sidebar-logo">
                <i class="bi bi-person-badge text-primary"></i>
            </div>
            <div class="sidebar-title">
                <h6 class="mb-0">Employee Portal</h6>
                <small class="text-muted"><?php echo htmlspecialchars($_SESSION["user_name"] ?? "Employee"); ?></small>
            </div>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/employee/dashboard') !== false ? 'active' : ''; ?>" href="<?php echo base_path('employee/dashboard'); ?>">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/employee/contacts') !== false ? 'active' : ''; ?>" href="<?php echo base_path('employee/contacts'); ?>">
                    <i class="bi bi-people"></i>
                    <span>My Contacts</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], '/employee/profile') !== false ? 'active' : ''; ?>" href="<?php echo base_path('employee/profile'); ?>">
                    <i class="bi bi-person"></i>
                    <span>My Profile</span>
                </a>
            </li>
        </ul>
        
        <hr class="sidebar-divider">
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link text-muted" href="<?php echo base_path('employee/logout'); ?>" onclick="return confirm('Are you sure you want to logout?')">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.sidebar {
    width: 250px;
    height: 100vh;
    background: #f8f9fa;
    border-right: 1px solid #dee2e6;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    overflow-y: auto;
}

.sidebar-header {
    padding: 1rem;
    border-bottom: 1px solid #dee2e6;
    background: #fff;
}

.sidebar-logo {
    width: 40px;
    height: 40px;
    background: #e9ecef;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 0.75rem;
    font-size: 1.25rem;
}

.sidebar-title h6 {
    font-weight: 600;
    color: #212529;
}

.sidebar-menu {
    padding: 1rem 0;
}

.sidebar-menu .nav-link {
    color: #6c757d;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.2s ease;
}

.sidebar-menu .nav-link:hover {
    color: #495057;
    background: #e9ecef;
}

.sidebar-menu .nav-link.active {
    color: #0d6efd;
    background: #e7f1ff;
    border-right: 3px solid #0d6efd;
}

.sidebar-menu .nav-link i {
    margin-right: 0.75rem;
    width: 16px;
    text-align: center;
}

.sidebar-divider {
    margin: 1rem;
    border-color: #dee2e6;
}

.main-content {
    margin-left: 250px;
    padding: 2rem;
    min-height: 100vh;
    background: #fff;
}

@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
        padding: 1rem;
    }
}
</style> 