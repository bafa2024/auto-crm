<?php
// Prevent session already started error
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include version manager
require_once __DIR__ . '/../../version.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDial Pro - Email Campaign Platform</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/sidebar-fix.css">
    <link rel="stylesheet" href="/css/sidebar-layout-fix.css">
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
            color: rgba(255, 255, 255, 0.8) !important;
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
        @media (max-width: 768px) {
            .navbar {
                margin-left: 0 !important;
            }
        }
        .deployment-link {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .deployment-link:hover {
            color: rgba(255, 255, 255, 0.9);
        }
    </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary" style="margin-left: 250px;">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <a class="navbar-brand fw-bold" href="/dashboard">
                <i class="bi bi-telephone-fill"></i>
            </a>
            
            <!-- Version Badge -->
            <?php 
            // Don't show version badge on landing page
            $currentUri = $_SERVER["REQUEST_URI"] ?? "/";
            if ($currentUri !== "/" && $currentUri !== "/index.php") {
                echo VersionManager::getVersionBadge(); 
                // Add deployment info link for admins
                if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
                    echo '<a href="/deployment-info.php" class="deployment-link" title="View deployment details"><i class="bi bi-info-circle"></i></a>';
                }
            }
            ?>
        </div>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> Account
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="/profile">
                            <i class="bi bi-person"></i> Profile
                        </a></li>
                        <li><a class="dropdown-item" href="/settings">
                            <i class="bi bi-gear"></i> Settings
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/logout">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- End Navigation -->