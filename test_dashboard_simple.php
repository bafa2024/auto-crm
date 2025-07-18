<?php
// Simple dashboard test
echo "Testing Dashboard Components\n";
echo "============================\n\n";

// Test 1: Check if dashboard files exist
echo "1. Checking dashboard files...\n";
$files = [
    'views/dashboard/index.php',
    'views/components/header.php',
    'views/components/sidebar.php',
    'views/components/footer.php',
    'css/styles.css',
    'js/app.js'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists\n";
    } else {
        echo "✗ $file missing\n";
    }
}

// Test 2: Check if dashboard content renders without errors
echo "\n2. Testing dashboard rendering...\n";
try {
    session_start();
    $_SESSION['user_id'] = 1;
    $_SESSION['user_email'] = 'test@example.com';
    
    ob_start();
    include 'views/dashboard/index.php';
    $content = ob_get_clean();
    
    if (strpos($content, 'AutoDial Pro Dashboard') !== false) {
        echo "✓ Dashboard renders with correct title\n";
    } else {
        echo "✗ Dashboard title not found\n";
    }
    
    if (strpos($content, 'Total Contacts') !== false) {
        echo "✓ Dashboard stats section present\n";
    } else {
        echo "✗ Dashboard stats section missing\n";
    }
    
    if (strpos($content, 'Recent Activity') !== false) {
        echo "✓ Recent activity section present\n";
    } else {
        echo "✗ Recent activity section missing\n";
    }
    
    echo "✓ Dashboard renders without PHP errors\n";
    
} catch (Exception $e) {
    echo "✗ Dashboard rendering failed: " . $e->getMessage() . "\n";
}

// Test 3: Check routing
echo "\n3. Testing routing logic...\n";
if (file_exists('index.php')) {
    echo "✓ Main index.php exists\n";
    
    // Check if dashboard route exists in index.php
    $indexContent = file_get_contents('index.php');
    if (strpos($indexContent, '/dashboard') !== false) {
        echo "✓ Dashboard route found in routing\n";
    } else {
        echo "✗ Dashboard route not found in routing\n";
    }
}

echo "\nDashboard debugging completed!\n";
echo "You can access the dashboard at: http://localhost/xampp/htdocs/acrm/dashboard\n";
echo "(Make sure you're logged in first)\n";
?>