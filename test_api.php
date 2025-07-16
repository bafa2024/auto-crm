<?php
// test_api.php - Test API endpoints

echo "AutoDial Pro CRM - API Test\n";
echo "===========================\n\n";

// Test basic connectivity
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
if (substr($baseUrl, -1) === '/') {
    $baseUrl = substr($baseUrl, 0, -1);
}

echo "Base URL: $baseUrl\n\n";

// Test 1: Check if index.php is accessible
echo "Test 1: Checking if index.php is accessible...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($httpCode == 200) {
    echo "✓ Main page accessible (HTTP $httpCode)\n";
    if ($finalUrl !== $baseUrl . '/') {
        echo "  Redirected to: $finalUrl\n";
    }
} else {
    echo "❌ Main page returned HTTP $httpCode\n";
    echo "  Final URL: $finalUrl\n";
}

// Test 2: Check login page
echo "\nTest 2: Checking login page...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($httpCode == 200) {
    echo "✓ Login page accessible (HTTP $httpCode)\n";
    if ($finalUrl !== $baseUrl . '/login') {
        echo "  Redirected to: $finalUrl\n";
    }
} else {
    echo "❌ Login page returned HTTP $httpCode\n";
    echo "  Final URL: $finalUrl\n";
}

// Test 3: Check signup page
echo "\nTest 3: Checking signup page...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/signup');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($httpCode == 200) {
    echo "✓ Signup page accessible (HTTP $httpCode)\n";
    if ($finalUrl !== $baseUrl . '/signup') {
        echo "  Redirected to: $finalUrl\n";
    }
} else {
    echo "❌ Signup page returned HTTP $httpCode\n";
    echo "  Final URL: $finalUrl\n";
}

// Test 4: Test API endpoint with OPTIONS (CORS preflight)
echo "\nTest 4: Testing API CORS preflight...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/auth/register');
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
curl_close($ch);

if ($httpCode == 200) {
    echo "✓ API CORS preflight successful (HTTP $httpCode)\n";
} else {
    echo "❌ API CORS preflight returned HTTP $httpCode\n";
    echo "  Final URL: $finalUrl\n";
}

// Test 5: Test API endpoint with POST
echo "\nTest 5: Testing API endpoint...\n";
$testData = [
    'first_name' => 'Test',
    'last_name' => 'User',
    'company_name' => 'Test Company',
    'email' => 'test@example.com',
    'password' => 'testpassword123'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/auth/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: $error\n";
} elseif ($httpCode == 200) {
    echo "✓ API endpoint working (HTTP $httpCode)\n";
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['success'])) {
        echo "  Response: " . ($responseData['success'] ? 'Success' : 'Error') . "\n";
        if (isset($responseData['message'])) {
            echo "  Message: " . $responseData['message'] . "\n";
        }
    }
} elseif ($httpCode == 409) {
    echo "✓ API endpoint working - Email already exists (HTTP $httpCode)\n";
} else {
    echo "❌ API endpoint returned HTTP $httpCode\n";
    echo "  Final URL: $finalUrl\n";
    echo "  Response: $response\n";
}

// Test 6: Test fallback API endpoint
echo "\nTest 6: Testing fallback API endpoint...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api.php/api/auth/register');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ Fallback API cURL Error: $error\n";
} elseif ($httpCode == 200) {
    echo "✓ Fallback API endpoint working (HTTP $httpCode)\n";
    $responseData = json_decode($response, true);
    if ($responseData && isset($responseData['success'])) {
        echo "  Response: " . ($responseData['success'] ? 'Success' : 'Error') . "\n";
        if (isset($responseData['message'])) {
            echo "  Message: " . $responseData['message'] . "\n";
        }
    }
} elseif ($httpCode == 409) {
    echo "✓ Fallback API endpoint working - Email already exists (HTTP $httpCode)\n";
} else {
    echo "❌ Fallback API endpoint returned HTTP $httpCode\n";
    echo "  Final URL: $finalUrl\n";
    echo "  Response: $response\n";
}

echo "\n✅ API testing completed!\n";
echo "\nIf you see any ❌ errors above, the issue is likely:\n";
echo "1. Database connection problems (check .env file)\n";
echo "2. .htaccess not enabled on your server\n";
echo "3. mod_rewrite not enabled\n";
echo "4. File permissions issues\n";
echo "5. PHP configuration problems\n"; 