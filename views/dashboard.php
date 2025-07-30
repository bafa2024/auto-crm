<?php
require_once __DIR__ . '/../config/base_path.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AutoDial Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?php echo base_path('css/styles.css'); ?>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="<?php echo base_path(''); ?>">
                <i class="bi bi-telephone-fill me-2"></i>AutoDial Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo base_path('#features'); ?>">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo base_path('#pricing'); ?>">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo base_path('#contact'); ?>">Contact</a>
                    </li>
                    <?php if (isset($_SESSION["user_id"])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-2"></i><?php echo $_SESSION["user_name"] ?? "User"; ?>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo base_path('dashboard/profile'); ?>">Profile</a></li>
                                <li><a class="dropdown-item" href="<?php echo base_path('dashboard/settings'); ?>">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo base_path('logout'); ?>">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="btn btn-outline-light me-2" href="<?php echo base_path('login'); ?>">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-light" href="<?php echo base_path('signup'); ?>">Start Free Trial</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link sidebar-link active" href="<?php echo base_path('dashboard'); ?>">
                                <i class="bi bi-house-door me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="<?php echo base_path('dashboard/contacts'); ?>">
                                <i class="bi bi-people me-2"></i>Contacts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="<?php echo base_path('dashboard/campaigns'); ?>">
                                <i class="bi bi-envelope me-2"></i>Campaigns
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="<?php echo base_path('dashboard/dialer'); ?>">
                                <i class="bi bi-telephone me-2"></i>Dialer
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="<?php echo base_path('dashboard/analytics'); ?>">
                                <i class="bi bi-graph-up me-2"></i>Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link sidebar-link" href="<?php echo base_path('dashboard/settings'); ?>">
                                <i class="bi bi-gear me-2"></i>Settings
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="my-3">
                    
                    <div class="px-3">
                        <div class="d-flex align-items-center mb-2">
                            <div class="avatar me-2">
                                <i class="bi bi-person-circle"></i>
                            </div>
                            <div>
                                <small class="text-muted">Logged in as</small>
                                <div class="fw-bold"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></div>
                            </div>
                        </div>
                        <a href="<?php echo base_path('logout'); ?>" class="btn btn-sm btn-outline-danger mt-2 w-100">
                            <i class="bi bi-box-arrow-right me-2"></i>Logout
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">Share</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle">
                            <i class="bi bi-calendar me-1"></i>This week
                        </button>
                    </div>
                </div>

                <!-- Dashboard Content -->
                <div class="row">
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Total Contacts</h5>
                                <h2 class="card-text text-primary">1,234</h2>
                                <small class="text-muted">+12% from last month</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Active Campaigns</h5>
                                <h2 class="card-text text-success">8</h2>
                                <small class="text-muted">3 scheduled for today</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Conversion Rate</h5>
                                <h2 class="card-text text-info">23%</h2>
                                <small class="text-muted">+5% from last month</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Activity</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">New contact added</h6>
                                            <small class="text-muted">John Doe - john@example.com</small>
                                        </div>
                                        <small class="text-muted">2 minutes ago</small>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Campaign completed</h6>
                                            <small class="text-muted">Product Launch Campaign</small>
                                        </div>
                                        <small class="text-muted">1 hour ago</small>
                                    </div>
                                    <div class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">Email sent</h6>
                                            <small class="text-muted">Newsletter to 500 contacts</small>
                                        </div>
                                        <small class="text-muted">3 hours ago</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 