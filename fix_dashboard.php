<?php
// fix_dashboard.php - Fix dashboard and all view files

echo "Fixing Dashboard and Views\n";
echo "=========================\n\n";

// 1. Create views/components directory
echo "1. Creating components directory...\n";
if (!is_dir('views/components')) {
    mkdir('views/components', 0755, true);
}
echo "✓ Components directory ready\n";

// 2. Create header component
echo "\n2. Creating header component...\n";
$headerContent = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoDial Pro - Enterprise Auto Dialer Solution</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>';

file_put_contents('views/components/header.php', $headerContent);
echo "✓ Header component created\n";

// 3. Create navigation component
echo "\n3. Creating navigation component...\n";
$navContent = '<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="/">
            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="/#features">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/#pricing">Pricing</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/#contact">Contact</a>
                </li>
            </ul>
            <div class="ms-3">
                <?php if (isset($_SESSION["user_id"])): ?>
                    <a class="btn btn-outline-primary me-2" href="/dashboard">Dashboard</a>
                    <a class="btn btn-primary" href="/logout">Logout</a>
                <?php else: ?>
                    <a class="btn btn-outline-primary me-2" href="/login">Login</a>
                    <a class="btn btn-primary" href="/signup">Start Free Trial</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>';

file_put_contents('views/components/navigation.php', $navContent);
echo "✓ Navigation component created\n";

// 4. Create sidebar component
echo "\n4. Creating sidebar component...\n";
$sidebarContent = '<div class="sidebar bg-white shadow" id="sidebar">
    <div class="p-4">
        <h5 class="mb-4">
            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
        </h5>
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a class="nav-link sidebar-link active" href="/dashboard">
                    <i class="bi bi-speedometer2 me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link sidebar-link" href="/dashboard/contacts">
                    <i class="bi bi-people me-2"></i> Contacts
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link sidebar-link" href="/dashboard/campaigns">
                    <i class="bi bi-envelope me-2"></i> Campaigns
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link sidebar-link" href="/dashboard/dialer">
                    <i class="bi bi-telephone me-2"></i> Auto Dialer
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link sidebar-link" href="/dashboard/analytics">
                    <i class="bi bi-graph-up me-2"></i> Analytics
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link sidebar-link" href="/dashboard/settings">
                    <i class="bi bi-gear me-2"></i> Settings
                </a>
            </li>
        </ul>
        <hr>
        <div class="mt-4">
            <p class="mb-2 text-muted small">Logged in as:</p>
            <p class="mb-0 fw-bold"><?php echo $_SESSION["user_email"] ?? "User"; ?></p>
            <a href="/logout" class="btn btn-sm btn-outline-danger mt-2 w-100">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
</div>';

file_put_contents('views/components/sidebar.php', $sidebarContent);
echo "✓ Sidebar component created\n";

// 5. Create dashboard overview component
echo "\n5. Creating dashboard overview component...\n";
$dashboardOverviewContent = '<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Total Contacts</h6>
                        <h3 class="mb-0">1,234</h3>
                    </div>
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Calls Today</h6>
                        <h3 class="mb-0">156</h3>
                    </div>
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-telephone"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Active Campaigns</h6>
                        <h3 class="mb-0">8</h3>
                    </div>
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-envelope"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-4">
        <div class="card stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Conversion Rate</h6>
                        <h3 class="mb-0">23.5%</h3>
                    </div>
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-graph-up"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Contact</th>
                                <th>Action</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>10:32 AM</td>
                                <td>John Doe</td>
                                <td>Outbound Call</td>
                                <td><span class="badge bg-success">Connected</span></td>
                            </tr>
                            <tr>
                                <td>10:28 AM</td>
                                <td>Jane Smith</td>
                                <td>Email Sent</td>
                                <td><span class="badge bg-primary">Delivered</span></td>
                            </tr>
                            <tr>
                                <td>10:15 AM</td>
                                <td>Mike Johnson</td>
                                <td>Outbound Call</td>
                                <td><span class="badge bg-warning">Voicemail</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary">
                        <i class="bi bi-telephone me-2"></i>Start Auto Dialer
                    </button>
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-person-plus me-2"></i>Add Contact
                    </button>
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-envelope-plus me-2"></i>Create Campaign
                    </button>
                    <button class="btn btn-outline-primary">
                        <i class="bi bi-upload me-2"></i>Import Contacts
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>';

file_put_contents('views/components/dashboard-overview.php', $dashboardOverviewContent);
echo "✓ Dashboard overview component created\n";

// 6. Create footer component
echo "\n6. Creating footer component...\n";
$footerContent = '<!-- Footer -->
<footer class="bg-dark text-white py-4 mt-5">
    <div class="container text-center">
        <p class="mb-0">&copy; 2024 AutoDial Pro. All rights reserved.</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="/js/app.js"></script>
</body>
</html>';

file_put_contents('views/components/footer.php', $footerContent);
echo "✓ Footer component created\n";

// 7. Fix dashboard index.php
echo "\n7. Fixing dashboard index.php...\n";
$dashboardIndexContent = '<?php
// Ensure user is logged in
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: /login");
    exit;
}
?>
<?php include __DIR__ . "/../components/header.php"; ?>

<style>
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: 250px;
    overflow-y: auto;
    z-index: 100;
}
.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: 100vh;
    background-color: #f8f9fa;
}
.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.sidebar-link {
    color: #333;
    text-decoration: none;
    padding: 10px 15px;
    display: block;
    border-radius: 8px;
    transition: all 0.3s;
}
.sidebar-link:hover {
    background-color: #f8f9fa;
    color: #333;
}
.sidebar-link.active {
    background-color: #e3f2fd;
    color: #1976d2;
}
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .main-content {
        margin-left: 0;
    }
}
</style>

<?php include __DIR__ . "/../components/sidebar.php"; ?>

<div class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">Dashboard</h1>
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-calendar3"></i> Today
                </button>
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-download"></i> Export
                </button>
            </div>
        </div>
        
        <?php include __DIR__ . "/../components/dashboard-overview.php"; ?>
    </div>
</div>

<?php include __DIR__ . "/../components/footer.php"; ?>';

file_put_contents('views/dashboard/index.php', $dashboardIndexContent);
echo "✓ Dashboard index fixed\n";

// 8. Fix landing page
echo "\n8. Fixing landing page...\n";
$landingContent = '<?php session_start(); ?>
<?php include __DIR__ . "/components/header.php"; ?>
<?php include __DIR__ . "/components/navigation.php"; ?>

<!-- Hero Section -->
<section class="hero-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">Supercharge Your Sales with Intelligent Auto Dialing</h1>
                <p class="lead mb-4">Increase your team\'s productivity by 300% with our AI-powered auto dialer. Connect with more prospects, close more deals, and grow your business faster.</p>
                <div class="d-flex gap-3">
                    <a class="btn btn-light btn-lg" href="/login">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </a>
                    <a class="btn btn-warning btn-lg" href="/signup">
                        <i class="bi bi-rocket-takeoff me-2"></i>Start Free Trial
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="https://via.placeholder.com/600x400/ffffff/5B5FDE?text=AutoDial+Pro" class="img-fluid rounded shadow" alt="AutoDial Pro">
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section id="features" class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Powerful Features for Modern Sales Teams</h2>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-robot display-4 text-primary"></i>
                    </div>
                    <h4>AI-Powered Detection</h4>
                    <p class="text-muted">Advanced answering machine detection with 98% accuracy using machine learning algorithms.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-lightning-charge display-4 text-primary"></i>
                    </div>
                    <h4>Multiple Dialing Modes</h4>
                    <p class="text-muted">Predictive, Progressive, Preview, and Power dialing modes to match your campaign needs.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 text-center p-4">
                    <div class="mb-3">
                        <i class="bi bi-diagram-3 display-4 text-primary"></i>
                    </div>
                    <h4>CRM Integration</h4>
                    <p class="text-muted">Seamless integration with Salesforce, HubSpot, Pipedrive, and 50+ other CRM platforms.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . "/components/footer.php"; ?>';

file_put_contents('views/landing.php', $landingContent);
echo "✓ Landing page fixed\n";

// 9. Fix login page
echo "\n9. Fixing login page...\n";
$loginContent = '<?php include __DIR__ . "/../components/header.php"; ?>
<?php include __DIR__ . "/../components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
                        </h3>
                        <h4 class="mt-3">Welcome Back</h4>
                        <p class="text-muted">Sign in to your account</p>
                    </div>
                    
                    <div id="loginMessages"></div>
                    
                    <form id="loginForm">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label" for="remember">
                                    Remember me
                                </label>
                            </div>
                            <a href="#">Forgot password?</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="loginBtn">
                            <span class="btn-text">Sign In</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Signing In...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Don\'t have an account? <a href="/signup">Sign up</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const loginForm = document.getElementById("loginForm");
    const loginBtn = document.getElementById("loginBtn");
    const btnText = loginBtn.querySelector(".btn-text");
    const btnLoading = loginBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("loginMessages");
    
    loginForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        loginBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(loginForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch("/api/auth/login", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        Login successful! Redirecting...
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = "/dashboard";
                }, 1000);
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Invalid email or password"}
                    </div>
                `;
            }
        } catch (error) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            loginBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>';

file_put_contents('views/auth/login.php', $loginContent);
echo "✓ Login page fixed\n";

// 10. Fix signup page
echo "\n10. Fixing signup page...\n";
$signupContent = '<?php include __DIR__ . "/../components/header.php"; ?>
<?php include __DIR__ . "/../components/navigation.php"; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">
                            <i class="bi bi-telephone-fill text-primary"></i> AutoDial Pro
                        </h3>
                        <h4 class="mt-3">Create Your Account</h4>
                        <p class="text-muted">Start your 14-day free trial</p>
                    </div>
                    
                    <div id="signupMessages"></div>
                    
                    <form id="signupForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Work Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="8">
                            <small class="text-muted">Must be at least 8 characters</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 mb-3" id="signupBtn">
                            <span class="btn-text">Create Account</span>
                            <span class="btn-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2"></span>
                                Creating Account...
                            </span>
                        </button>
                        <div class="text-center">
                            <p class="mb-0">Already have an account? <a href="/login">Sign in</a></p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const signupForm = document.getElementById("signupForm");
    const signupBtn = document.getElementById("signupBtn");
    const btnText = signupBtn.querySelector(".btn-text");
    const btnLoading = signupBtn.querySelector(".btn-loading");
    const messagesContainer = document.getElementById("signupMessages");
    
    signupForm.addEventListener("submit", async function(e) {
        e.preventDefault();
        
        btnText.classList.add("d-none");
        btnLoading.classList.remove("d-none");
        signupBtn.disabled = true;
        
        messagesContainer.innerHTML = "";
        
        const formData = new FormData(signupForm);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch("/api/auth/register", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                messagesContainer.innerHTML = `
                    <div class="alert alert-success">
                        Account created successfully! Redirecting to login...
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = "/login";
                }, 2000);
            } else {
                messagesContainer.innerHTML = `
                    <div class="alert alert-danger">
                        ${result.message || "Failed to create account"}
                    </div>
                `;
            }
        } catch (error) {
            messagesContainer.innerHTML = `
                <div class="alert alert-danger">
                    Network error. Please try again.
                </div>
            `;
        } finally {
            btnText.classList.remove("d-none");
            btnLoading.classList.add("d-none");
            signupBtn.disabled = false;
        }
    });
});
</script>

<?php include __DIR__ . "/../components/footer.php"; ?>';

file_put_contents('views/auth/signup.php', $signupContent);
echo "✓ Signup page fixed\n";

// 11. Create 404 page
echo "\n11. Creating 404 page...\n";
$notFoundContent = '<?php include __DIR__ . "/components/header.php"; ?>
<?php include __DIR__ . "/components/navigation.php"; ?>

<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="display-1 text-primary">404</h1>
            <h2 class="mb-4">Page Not Found</h2>
            <p class="text-muted mb-4">The page you are looking for doesn\'t exist or has been moved.</p>
            <a href="/" class="btn btn-primary">Go to Homepage</a>
        </div>
    </div>
</div>

<?php include __DIR__ . "/components/footer.php"; ?>';

file_put_contents('views/404.php', $notFoundContent);
echo "✓ 404 page created\n";

// 12. Create basic CSS file
echo "\n12. Creating basic CSS file...\n";
$cssContent = '/* Basic styles for AutoDial Pro */
:root {
    --primary-color: #5B5FDE;
    --secondary-color: #6C63FF;
    --success-color: #10B981;
    --danger-color: #EF4444;
    --warning-color: #F59E0B;
    --info-color: #3B82F6;
}

body {
    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card {
    border: none;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.sidebar {
    border-right: 1px solid #e5e7eb;
}

.btn-primary {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.btn-primary:hover {
    background-color: var(--secondary-color);
    border-color: var(--secondary-color);
}

.text-primary {
    color: var(--primary-color) !important;
}

.bg-primary {
    background-color: var(--primary-color) !important;
}';

if (!is_dir('css')) {
    mkdir('css', 0755, true);
}
file_put_contents('css/styles.css', $cssContent);
echo "✓ CSS file created\n";

// 13. Create basic JS file
echo "\n13. Creating basic JS file...\n";
$jsContent = '// Basic JavaScript for AutoDial Pro
document.addEventListener("DOMContentLoaded", function() {
    console.log("AutoDial Pro loaded");
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll(\'[data-bs-toggle="tooltip"]\'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});';

if (!is_dir('js')) {
    mkdir('js', 0755, true);
}
file_put_contents('js/app.js', $jsContent);
echo "✓ JS file created\n";

echo "\n✅ Dashboard and views fixed!\n";
echo "\nYour dashboard should now work properly.\n";
echo "Access it at: https://autocrm.regrowup.ca/dashboard\n";
echo "(You need to login first with admin@autocrm.com / admin123)\n";