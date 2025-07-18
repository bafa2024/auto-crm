<?php
// debug_network_error.php - Find the exact cause of network error

echo "Debugging Network Error\n";
echo "======================\n\n";

// 1. Check if we can access the base URL
echo "1. Testing basic connectivity...\n";

$baseUrl = 'http://localhost/acrm/';
echo "Testing: $baseUrl\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response !== false && $httpCode == 200) {
    echo "✓ Base URL accessible (HTTP $httpCode)\n";
} else {
    echo "✗ Base URL failed (HTTP $httpCode): $error\n";
    echo "This might be the problem - XAMPP Apache may not be running properly\n";
}

// 2. Check specific API endpoints
echo "\n2. Testing API endpoints...\n";

$apiUrls = [
    'http://localhost/acrm/api/auth/login',
    'http://localhost/acrm/index.php/api/auth/login',
    'http://localhost/acrm/api_test.php'
];

foreach ($apiUrls as $url) {
    echo "Testing: $url\n";
    
    $postData = json_encode([
        'email' => 'admin@autocrm.com',
        'password' => 'admin123'
    ]);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($postData)
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($response !== false) {
        echo "  ✓ HTTP $httpCode - Response: " . substr($response, 0, 100) . "...\n";
        
        // Check if it's valid JSON
        $json = json_decode($response, true);
        if ($json && isset($json['success'])) {
            echo "  ✓ Valid JSON response - Success: " . ($json['success'] ? 'true' : 'false') . "\n";
            if ($json['success']) {
                echo "  🎉 THIS URL WORKS! Use this for login.\n";
            }
        }
    } else {
        echo "  ✗ Failed: $error\n";
    }
    echo "\n";
}

// 3. Check what the frontend is actually calling
echo "3. Checking frontend login form...\n";

$loginFile = 'views/auth/login.php';
if (file_exists($loginFile)) {
    $content = file_get_contents($loginFile);
    
    // Find the fetch URL
    if (preg_match('/fetch\s*\(\s*["\']([^"\']+)["\']/', $content, $matches)) {
        $frontendUrl = $matches[1];
        echo "Frontend calls: $frontendUrl\n";
        
        // Test this exact URL
        echo "Testing frontend URL...\n";
        $fullUrl = 'http://localhost' . (strpos($frontendUrl, '/') === 0 ? '' : '/acrm/') . $frontendUrl;
        echo "Full URL: $fullUrl\n";
        
        $postData = json_encode([
            'email' => 'admin@autocrm.com',
            'password' => 'admin123'
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response !== false && $httpCode == 200) {
            echo "✓ Frontend URL works!\n";
        } else {
            echo "✗ Frontend URL failed (HTTP $httpCode): $error\n";
            echo "This is likely the cause of your network error!\n";
        }
    } else {
        echo "Could not find fetch URL in login form\n";
    }
} else {
    echo "Login file not found: $loginFile\n";
}

// 4. Check XAMPP status
echo "\n4. Checking XAMPP/Apache status...\n";

$apacheRunning = false;
if (function_exists('exec')) {
    exec('tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL', $output, $return);
    if ($return === 0 && count($output) > 1) {
        echo "✓ Apache (httpd.exe) is running\n";
        $apacheRunning = true;
    } else {
        echo "✗ Apache may not be running\n";
    }
} else {
    echo "Cannot check Apache status (exec disabled)\n";
}

// 5. Check document root
echo "\n5. Checking document root...\n";
echo "Current script path: " . __FILE__ . "\n";
echo "Expected web path: http://localhost/acrm/\n";

if (strpos(__FILE__, 'xampp\\htdocs\\acrm') !== false) {
    echo "✓ File is in XAMPP htdocs\n";
} else {
    echo "⚠ File may not be in correct XAMPP location\n";
}

// 6. Create a simple test endpoint
echo "\n6. Creating simple test endpoint...\n";

$simpleTest = '<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    exit(0);
}

echo json_encode([
    "success" => true,
    "message" => "Simple test endpoint working!",
    "method" => $_SERVER["REQUEST_METHOD"],
    "timestamp" => date("Y-m-d H:i:s"),
    "url" => $_SERVER["REQUEST_URI"] ?? "unknown"
]);
?>';

file_put_contents('simple_test.php', $simpleTest);
echo "✓ Created simple_test.php\n";

// Test the simple endpoint
echo "Testing simple endpoint...\n";
$simpleUrl = 'http://localhost/acrm/simple_test.php';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $simpleUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode == 200) {
    echo "✓ Simple test works: $response\n";
} else {
    echo "✗ Simple test failed\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "SUMMARY:\n";
echo str_repeat("=", 50) . "\n";

if (!$apacheRunning) {
    echo "❌ PROBLEM: Apache is not running\n";
    echo "SOLUTION: Start XAMPP and ensure Apache is green/started\n";
} else {
    echo "✓ Apache appears to be running\n";
}

echo "\nTo fix the network error:\n";
echo "1. Ensure XAMPP Apache is running\n";
echo "2. Test: http://localhost/acrm/simple_test.php\n";
echo "3. If that works, the API should work too\n";
echo "4. Check browser console (F12) for exact error\n";
?>