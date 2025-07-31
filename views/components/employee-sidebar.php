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
function hasPermission($permissions, $permission) {
    return isset($permissions[$permission]) && $permissions[$permission];
}

// Get current page for active state
$currentPage = $_SERVER['REQUEST_URI'];
$currentPage = str_replace('/acrm', '', $currentPage); // Remove base path if present
?>

<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
    <div class="position-sticky pt-3">
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-circle fa-3x"></i>
            <h6 class="mt-2"><?php echo htmlspecialchars($_SESSION["user_name"]); ?></h6>
            <small><?php echo ucfirst($_SESSION["user_role"]); ?></small>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($currentPage, '/employee/email-dashboard') !== false ? 'active' : ''; ?>" 
                   href="<?php echo base_path('employee/email-dashboard'); ?>">
                    <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                </a>
            </li>
            
            <?php if (hasPermission($permissions, 'can_create_campaigns') || hasPermission($permissions, 'can_view_all_campaigns')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($currentPage, '/employee/campaigns') !== false && strpos($currentPage, '/employee/campaigns/create') === false ? 'active' : ''; ?>" 
                   href="<?php echo base_path('employee/campaigns'); ?>">
                    <i class="fas fa-envelope me-2"></i> My Campaigns
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($permissions, 'can_create_campaigns')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($currentPage, '/employee/campaigns/create') !== false ? 'active' : ''; ?>" 
                   href="<?php echo base_path('employee/campaigns/create'); ?>">
                    <i class="fas fa-plus-circle me-2"></i> Create Campaign
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($currentPage, '/employee/contacts') !== false ? 'active' : ''; ?>" 
                   href="<?php echo base_path('employee/contacts'); ?>">
                    <i class="fas fa-address-book me-2"></i> Contacts
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($currentPage, '/employee/profile') !== false ? 'active' : ''; ?>" 
                   href="<?php echo base_path('employee/profile'); ?>">
                    <i class="fas fa-user me-2"></i> Profile
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link text-danger" href="<?php echo base_path('employee/logout'); ?>">
                    <i class="fas fa-sign-out-alt me-2"></i> Logout
                </a>
            </li>
        </ul>
        
        <!-- Permission Status -->
        <div class="mt-4 p-3 bg-dark rounded">
            <h6 class="text-white-50 mb-2">Your Permissions</h6>
            <div class="small text-white-50">
                <?php if (hasPermission($permissions, 'can_create_campaigns')): ?>
                    <div><i class="fas fa-check text-success"></i> Create Campaigns</div>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_send_campaigns')): ?>
                    <div><i class="fas fa-check text-success"></i> Send Campaigns</div>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_edit_campaigns')): ?>
                    <div><i class="fas fa-check text-success"></i> Edit Campaigns</div>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_delete_campaigns')): ?>
                    <div><i class="fas fa-check text-success"></i> Delete Campaigns</div>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_upload_contacts')): ?>
                    <div><i class="fas fa-check text-success"></i> Manage Contacts</div>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_export_contacts')): ?>
                    <div><i class="fas fa-check text-success"></i> Export Contacts</div>
                <?php endif; ?>
                <?php if (hasPermission($permissions, 'can_view_all_campaigns')): ?>
                    <div><i class="fas fa-check text-success"></i> View All Campaigns</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav> 