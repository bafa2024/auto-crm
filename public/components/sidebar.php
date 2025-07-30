<?php
require_once __DIR__ . '/../../config/base_path.php';
?>
<nav class="sidebar">
    <div class="sidebar-header">
        <h5 class="mb-0">
            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
        </h5>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false && strpos($_SERVER['REQUEST_URI'], 'profile') === false && strpos($_SERVER['REQUEST_URI'], 'settings') === false) ? 'active' : ''; ?>" href="<?php echo base_path('dashboard'); ?>">
                <i class="bi bi-house-door me-2"></i>Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'contacts.php') !== false) ? 'active' : ''; ?>" href="<?php echo base_path('contacts.php'); ?>">
                <i class="bi bi-people me-2"></i>Contacts
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'instant_email.php') !== false) ? 'active' : ''; ?>" href="<?php echo base_path('instant_email.php'); ?>">
                <i class="bi bi-envelope me-2"></i>Email Campaigns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'campaigns.php') !== false) ? 'active' : ''; ?>" href="<?php echo base_path('campaigns.php'); ?>">
                <i class="bi bi-telephone me-2"></i>Dialer Campaigns
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], 'employee_management.php') !== false) ? 'active' : ''; ?>" href="<?php echo base_path('dashboard/employee_management.php'); ?>">
                <i class="bi bi-person-badge me-2"></i>Employee Management
            </a>
        </li>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="d-flex align-items-center mb-2">
                <div class="avatar me-2">
                    <i class="bi bi-person-circle"></i>
                </div>
                <div>
                    <small class="text-muted">Logged in as</small>
                    <div class="fw-bold"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></div>
                </div>
            </div>
        </div>
        <a href="<?php echo base_path('logout'); ?>" class="btn btn-sm btn-outline-danger mt-2 w-100">
            <i class="bi bi-box-arrow-right me-2"></i>Logout
        </a>
    </div>
</nav> 