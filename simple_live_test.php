<?php
// simple_live_test.php - Quick live hosting diagnostic
header('Content-Type: text/plain');

echo "🔍 AutoDial Pro Live Hosting Quick Test\n";
echo "=====================================\n\n";

// 1. Basic environment
echo "1. Environment:\n";
echo "   Domain: " . $_SERVER['HTTP_HOST'] . "\n";
echo "   Path: " . $_SERVER['REQUEST_URI'] . "\n";
echo "   Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "   Current Dir: " . __DIR__ . "\n";

// 2. Calculate correct API path
$currentScript = $_SERVER['SCRIPT_NAME'];
$basePath = dirname($currentScript);
if ($basePath === '/') $basePath = '';

echo "\n2. Path Analysis:\n";
echo "   Script: $currentScript\n";
echo "   Base Path: $basePath\n";
echo "   Expected API URL: $basePath/api/auth/login\n";

// 3. Test API file exists
$apiFile = __DIR__ . '/api.php';
echo "\n3. API File Check:\n";
if (file_exists($apiFile)) {
    echo "   ✓ api.php exists\n";
} else {
    echo "   ✗ api.php missing\n";
}

// 4. Test AuthController
$authFile = __DIR__ . '/controllers/AuthController.php';
echo "\n4. Controller Check:\n";
if (file_exists($authFile)) {
    echo "   ✓ AuthController.php exists\n";
} else {
    echo "   ✗ AuthController.php missing\n";
}

// 5. Test database config
echo "\n5. Database Config:\n";
if (file_exists(__DIR__ . '/config/database.php')) {
    echo "   ✓ database.php exists\n";
    try {
        require_once __DIR__ . '/config/database.php';
        $db = new Database();
        $conn = $db->getConnection();
        if ($conn) {
            echo "   ✓ Database connection works\n";
        } else {
            echo "   ✗ Database connection failed\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Database error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ✗ database.php missing\n";
}

// 6. Suggest correct frontend URL
echo "\n6. Fix for Login Form:\n";
echo "   Change this line in views/auth/login.php (line 75):\n";
echo "   FROM: const response = await fetch(\"/acrm/api/auth/login\", {\n";
echo "   TO:   const response = await fetch(\"$basePath/api/auth/login\", {\n";

echo "\n7. Alternative (if above doesn't work):\n";
echo "   Change to relative URL:\n";
echo "   FROM: const response = await fetch(\"/acrm/api/auth/login\", {\n";
echo "   TO:   const response = await fetch(\"api/auth/login\", {\n";

echo "\n8. Test API Endpoint:\n";
echo "   Try visiting: https://" . $_SERVER['HTTP_HOST'] . "$basePath/api/auth/login\n";
echo "   Should show: Method not allowed or similar error (not 404)\n";

echo "\n✅ Upload this file to your live hosting and run it to see the exact paths.\n";
?>