<?php
require_once __DIR__ . '/../../config/base_path.php';
?>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?php echo base_path(''); ?>">
            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo base_path('#features'); ?>">Features</a>
                </li>
            </ul>
            <div class="ms-3">
                <?php if (isset($_SESSION["user_id"])): ?>
                    <a class="btn btn-outline-primary me-2" href="<?php echo base_path('dashboard'); ?>">Dashboard</a>
                    <a class="btn btn-primary" href="<?php echo base_path('logout'); ?>">Logout</a>
                <?php else: ?>
                    <a class="btn btn-outline-primary me-2" href="<?php echo base_path('login'); ?>">Admin Login</a>
                    <a class="btn btn-outline-secondary" href="<?php echo base_path('employee/login'); ?>">Employee Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>