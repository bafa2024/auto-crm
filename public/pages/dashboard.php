<?php
include __DIR__ . '/../components/sidebar.php';
?>
<div class="main-content">
    <!-- Top Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm">
        <button class="btn btn-light d-md-none mobile-toggle" onclick="toggleSidebar()">
            <i class="bi bi-list"></i>
        </button>
        <div>
            <h5 class="mb-0">Welcome back, Sarah</h5>
            <small class="text-secondary">Thursday, November 14, 2024</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-light position-relative">
                <i class="bi bi-bell"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                    3
                </span>
            </button>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-2"></i>Sarah Johnson
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/dashboard/profile">Profile</a></li>
                    <li><a class="dropdown-item" href="/dashboard/settings">Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/landing">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
    <?php include __DIR__ . '/../components/dashboard-overview.php'; ?>
</div> 