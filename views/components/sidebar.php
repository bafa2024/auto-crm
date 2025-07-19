<?php
// Include base path configuration
require_once __DIR__ . '/../../config/base_path.php';
?>
<div class="sidebar" id="sidebar">
    <div class="p-4">
        <h5 class="mb-4">
            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
        </h5>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard' || basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>" href="<?php echo base_path('dashboard'); ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'contacts.php' ? 'active' : ''; ?>" href="<?php echo base_path('contacts.php'); ?>">
                    <i class="bi bi-people"></i> Contacts
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link <?php echo basename($_SERVER['PHP_SELF']) === 'campaigns.php' ? 'active' : ''; ?>" href="<?php echo base_path('campaigns.php'); ?>">
                    <i class="bi bi-envelope"></i> Campaigns
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link" href="#" onclick="showSection('dialer')">
                    <i class="bi bi-telephone"></i> Auto Dialer
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link" href="#" onclick="showSection('analytics')">
                    <i class="bi bi-graph-up"></i> Analytics
                </a>
            </li>
            <li class="nav-item">
                <a class="sidebar-link" href="#" onclick="showSection('settings')">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </li>
        </ul>
        <hr>
        <div class="mt-4">
            <p class="mb-2 text-muted small">Logged in as:</p>
            <p class="mb-0 fw-bold"><?php echo $_SESSION["user_email"] ?? "User"; ?></p>
            <a href="<?php echo base_path('logout'); ?>" class="btn btn-sm btn-outline-danger mt-2 w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</div>

<script>
function showSection(section) {
    alert("Section: " + section + " - Coming soon!");
}
</script>