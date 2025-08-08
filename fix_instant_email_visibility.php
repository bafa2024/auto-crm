<?php
/**
 * Fix Instant Email Visibility and Layout Issues
 * This script fixes layout and visibility issues for the instant email feature
 * Run this after deployment to ensure proper display on Hostinger Cloud
 */

// Include base path configuration
require_once __DIR__ . '/config/base_path.php';

echo "Instant Email Visibility Fix Script\n";
echo "==================================\n\n";

// Check current environment
echo "Environment: " . BasePath::getEnvironment() . "\n";
echo "Base Path: " . BasePath::getBasePath() . "\n";
echo "Base URL: " . BasePath::getBaseUrl() . "\n\n";

// Check if instant_email.php exists
$instantEmailPath = __DIR__ . '/instant_email.php';
if (file_exists($instantEmailPath)) {
    echo "✓ instant_email.php found\n";
} else {
    echo "✗ instant_email.php NOT found - this is the issue!\n";
    exit(1);
}

// Check sidebar components
$sidebarPath = __DIR__ . '/views/components/sidebar.php';
if (file_exists($sidebarPath)) {
    echo "✓ sidebar.php found\n";
    
    // Check if instant email link exists in sidebar
    $sidebarContent = file_get_contents($sidebarPath);
    if (strpos($sidebarContent, 'instant_email.php') !== false) {
        echo "✓ Instant Email link found in sidebar\n";
    } else {
        echo "✗ Instant Email link NOT found in sidebar\n";
    }
} else {
    echo "✗ sidebar.php NOT found\n";
}

// Check CSS files
$cssFiles = [
    'css/styles.css',
    'css/sidebar-fix.css'
];

foreach ($cssFiles as $cssFile) {
    $cssPath = __DIR__ . '/' . $cssFile;
    if (file_exists($cssPath)) {
        echo "✓ $cssFile found\n";
    } else {
        echo "✗ $cssFile NOT found\n";
    }
}

// Create a debug CSS file to ensure proper display
$debugCss = '/* Debug CSS for Instant Email Visibility */

/* Ensure sidebar is visible and properly sized */
.modern-sidebar {
    width: 260px !important;
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Ensure all navigation items are visible */
.sidebar-nav .nav-item {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
}

/* Ensure instant email link is visible */
.sidebar-nav .nav-item a[href*="instant_email"] {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    background-color: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
}

/* Fix main content area margin */
.main-content {
    margin-left: 260px !important;
}

/* Responsive fixes */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0 !important;
    }
    
    .modern-sidebar {
        transform: translateX(-100%);
    }
    
    .modern-sidebar.show {
        transform: translateX(0);
    }
}

/* Force visibility of instant email menu item */
.sidebar-link[href*="instant_email"] {
    display: flex !important;
    min-height: 44px !important;
}

/* Debug border to make instant email visible */
.sidebar-link[href*="instant_email"]:after {
    content: " (DEBUG: Should be visible)";
    font-size: 10px;
    color: #fbbf24;
    margin-left: 5px;
}
';

$debugCssPath = __DIR__ . '/css/instant-email-debug.css';
if (file_put_contents($debugCssPath, $debugCss)) {
    echo "\n✓ Created debug CSS file: css/instant-email-debug.css\n";
    echo "  Add this to your header: <link rel=\"stylesheet\" href=\"css/instant-email-debug.css\">\n";
} else {
    echo "\n✗ Failed to create debug CSS file\n";
}

// Create a test page to verify instant email functionality
$testPage = '<?php
session_start();
require_once __DIR__ . "/config/base_path.php";

// Simple auth check
if (!isset($_SESSION["user_id"])) {
    die("Not logged in. Please login first.");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Instant Email Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/instant-email-debug.css">
</head>
<body>
    <div class="container mt-5">
        <h1>Instant Email Feature Test</h1>
        <div class="alert alert-info">
            <h5>Debug Information:</h5>
            <ul>
                <li>Base Path: <?php echo BasePath::getBasePath(); ?></li>
                <li>Base URL: <?php echo BasePath::getBaseUrl(); ?></li>
                <li>Environment: <?php echo BasePath::getEnvironment(); ?></li>
                <li>Instant Email URL: <?php echo base_path("instant_email.php"); ?></li>
            </ul>
        </div>
        
        <div class="mt-4">
            <h5>Test Links:</h5>
            <a href="<?php echo base_path("instant_email.php"); ?>" class="btn btn-primary">
                Go to Instant Email
            </a>
            <a href="<?php echo base_path("dashboard"); ?>" class="btn btn-secondary">
                Go to Dashboard
            </a>
        </div>
        
        <div class="mt-4">
            <h5>Sidebar HTML Test:</h5>
            <div style="background: #1e293b; padding: 20px; border-radius: 8px;">
                <a class="sidebar-link" href="<?php echo base_path("instant_email.php"); ?>" style="display: flex; align-items: center; padding: 10px 16px; color: rgba(255, 255, 255, 0.8); text-decoration: none; border-radius: 8px; background: rgba(255, 255, 255, 0.1);">
                    <i class="bi bi-envelope-plus" style="margin-right: 12px;"></i>
                    <span>Instant Email (Test)</span>
                </a>
            </div>
        </div>
    </div>
</body>
</html>
';

$testPagePath = __DIR__ . '/test_instant_email_visibility.php';
if (file_put_contents($testPagePath, $testPage)) {
    echo "\n✓ Created test page: test_instant_email_visibility.php\n";
    echo "  Access it at: " . BasePath::absoluteUrl('test_instant_email_visibility.php') . "\n";
} else {
    echo "\n✗ Failed to create test page\n";
}

echo "\n\nRecommendations:\n";
echo "================\n";
echo "1. Make sure instant_email.php is included in your deployment\n";
echo "2. Check if the file permissions are correct (should be readable)\n";
echo "3. Add the debug CSS to your pages temporarily to diagnose issues\n";
echo "4. Check the browser console for any JavaScript errors\n";
echo "5. Verify that the base path detection is working correctly on Hostinger\n";

echo "\n\nDone!\n";
?>