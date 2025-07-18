<?php
// test_api_endpoints.php - Test API endpoints after fixes

echo "Testing API Endpoints After Fixes\n";
echo "=================================\n\n";

try {
    // 1. Test login endpoint directly
    echo "1. Testing login API endpoint...\n";
    
    // Prepare test data
    $loginData = [
        'email' => 'admin@autocrm.com',
        'password' => 'admin123'
    ];
    
    // Test the endpoint by simulating the request
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    // Load and test
    require_once 'config/database.php';
    require_once 'controllers/AuthController.php';
    
    $database = new Database();
    $db = $database->getConnection();
    $authController = new AuthController($db);
    
    // Create request object
    $request = new stdClass();
    $request->body = $loginData;
    
    // Capture output
    ob_start();
    $authController->login($request);
    $output = ob_get_clean();
    
    echo "Login API Response: $output\n";
    
    $response = json_decode($output, true);
    if ($response && $response['success']) {
        echo "✓ Login API working correctly\n";
    } else {
        echo "✗ Login API failed\n";
    }
    
    // 2. Test signup endpoint
    echo "\n2. Testing signup API endpoint...\n";
    
    $signupData = [
        'first_name' => 'New',
        'last_name' => 'User',
        'email' => 'newuser' . time() . '@test.com',
        'password' => 'password123',
        'company_name' => 'Test Corp'
    ];
    
    $signupRequest = new stdClass();
    $signupRequest->body = $signupData;
    
    ob_start();
    $authController->register($signupRequest);
    $signupOutput = ob_get_clean();
    
    echo "Signup API Response: $signupOutput\n";
    
    $signupResponse = json_decode($signupOutput, true);
    if ($signupResponse && $signupResponse['success']) {
        echo "✓ Signup API working correctly\n";
    } else {
        echo "✗ Signup API failed\n";
    }
    
    // 3. Test routing path
    echo "\n3. Testing routing configuration...\n";
    
    $routingCode = file_get_contents('index.php');
    
    if (strpos($routingCode, '/api/auth/login') !== false) {
        echo "✓ Login route found in index.php\n";
    } else {
        echo "✗ Login route not found in index.php\n";
    }
    
    if (strpos($routingCode, '/api/auth/register') !== false) {
        echo "✓ Register route found in index.php\n";
    } else {
        echo "✗ Register route not found in index.php\n";
    }
    
    // 4. Check what URL the frontend is calling
    echo "\n4. Checking frontend login form...\n";
    
    $loginPageContent = file_get_contents('views/auth/login.php');
    
    // Look for the fetch URL
    if (preg_match('/fetch\s*\(\s*["\']([^"\']+)["\']/', $loginPageContent, $matches)) {
        echo "Frontend is calling: " . $matches[1] . "\n";
        
        // Check if this matches our routing
        if ($matches[1] === '/api/auth/login' || $matches[1] === 'api/auth/login') {
            echo "✓ Frontend URL matches expected route\n";
        } else {
            echo "⚠ Frontend URL might not match route: " . $matches[1] . "\n";
        }
    } else {
        echo "Could not find fetch URL in login page\n";
    }
    
    // 5. Test with curl simulation to see exact error
    echo "\n5. Testing with HTTP simulation...\n";
    
    $url = 'http://localhost/acrm/api/auth/login';
    $postData = json_encode($loginData);
    
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postData)
            ],
            'content' => $postData,
            'timeout' => 10
        ]
    ]);
    
    echo "Testing URL: $url\n";
    
    $result = @file_get_contents($url, false, $context);
    
    if ($result === false) {
        $error = error_get_last();
        echo "✗ HTTP request failed: " . ($error['message'] ?? 'Unknown error') . "\n";
        
        // Try alternative URL
        $altUrl = 'http://localhost/acrm/index.php/api/auth/login';
        echo "Trying alternative URL: $altUrl\n";
        
        $altResult = @file_get_contents($altUrl, false, $context);
        if ($altResult !== false) {
            echo "✓ Alternative URL works: $altResult\n";
        } else {
            echo "✗ Alternative URL also failed\n";
        }
    } else {
        echo "✓ HTTP request successful: $result\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n6. Debugging network issue...\n";
echo "The 'Network error' typically means:\n";
echo "1. XAMPP Apache is not running\n";
echo "2. Wrong URL path (check document root)\n";
echo "3. CORS issues (fixed)\n";
echo "4. PHP errors preventing response\n";
echo "5. Routing not working properly\n";

echo "\nNext steps to fix:\n";
echo "- Verify XAMPP Apache is running\n";
echo "- Check if http://localhost/acrm/ loads\n";
echo "- Try the login after these fixes\n";
?>