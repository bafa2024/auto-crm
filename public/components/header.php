<?php
// Include version manager
require_once __DIR__ . '/../../version.php';
require_once __DIR__ . '/../../config/base_path.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDial Pro - Enterprise Auto Dialer Solution</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_path('public/css/styles.css'); ?>">
    <style>
        .version-badge {
            font-size: 0.75rem;
            margin-left: 15px;
        }
        .version-badge .badge {
            font-size: 0.7rem;
            margin-right: 5px;
        }
        .version-badge small {
            font-size: 0.65rem;
            color: rgba(0, 0, 0, 0.6) !important;
        }
        .navbar-brand {
            margin-right: 0;
        }
        @media (max-width: 768px) {
            .version-badge small {
                display: none !important;
            }
            .version-badge {
                margin-left: 10px;
            }
        }
        @media (max-width: 576px) {
            .version-badge .badge {
                font-size: 0.6rem;
            }
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg landing-nav">
    <div class="container">
        <div class="d-flex align-items-center">
            <a class="navbar-brand fw-bold" href="<?php echo base_path('landing'); ?>">
                <i class="bi bi-telephone-fill text-primary"></i>
            </a>
            
            <!-- Version Badge -->
            <?php echo VersionManager::getVersionBadge(); ?>
        </div>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo base_path('landing#features'); ?>">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo base_path('landing#pricing'); ?>">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo base_path('landing#testimonials'); ?>">Testimonials</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo base_path('landing#contact'); ?>">Contact</a>
                </li>
            </ul>
            <div class="ms-3">
                <a class="btn btn-outline-primary me-2" href="<?php echo base_path('login'); ?>">Login</a>
                <a class="btn btn-primary" href="<?php echo base_path('signup'); ?>">Start Free Trial</a>
            </div>
        </div>
    </div>
</nav>
<!-- End Navigation --> 