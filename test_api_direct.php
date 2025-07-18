<?php
// test_api_direct.php - Test API endpoints directly

echo "Testing API Endpoints Directly\n";
echo "==============================\n\n";

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    echo "1. Testing database connection...\n";
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    echo "✓ Database connected\n";
    
    echo "\n2. Testing AuthController instantiation...\n";
    require_once 'controllers/AuthController.php';
    
    $authController = new AuthController($db);
    echo "✓ AuthController created\n";
    
    echo "\n3. Testing login API simulation...\n";
    
    // Simulate POST request data
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';
    
    // Create mock request object
    $mockRequest = new stdClass();
    $mockRequest->body = [
        'email' => 'admin@autocrm.com',
        'password' => 'admin123'
    ];
    
    echo "Request data: " . json_encode($mockRequest->body) . "\n";
    
    // Capture output
    ob_start();
    try {
        $authController->login($mockRequest);
    } catch (Exception $e) {
        $output = ob_get_clean();
        echo "Exception caught: " . $e->getMessage() . "\n";
        echo "Output: " . $output . "\n";
    }
    $output = ob_get_clean();
    
    echo "API Response: " . $output . "\n";
    
    // Parse response
    $response = json_decode($output, true);
    if ($response) {
        echo "✓ Valid JSON response\n";
        echo "Success: " . ($response['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . ($response['message'] ?? 'No message') . "\n";
    } else {
        echo "✗ Invalid JSON response\n";
    }
    
    echo "\n4. Testing direct database authentication...\n";
    
    // Test User model authentication directly
    require_once 'models/User.php';
    $userModel = new User($db);
    
    $user = $userModel->authenticate('admin@autocrm.com', 'admin123');
    if ($user) {
        echo "✓ Direct authentication successful\n";
        echo "User ID: " . $user['id'] . "\n";
        echo "User Email: " . $user['email'] . "\n";
    } else {
        echo "✗ Direct authentication failed\n";
    }
    
    echo "\n5. Testing signup API simulation...\n";
    
    $signupRequest = new stdClass();
    $signupRequest->body = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'testuser' . time() . '@example.com',
        'password' => 'testpass123',
        'company_name' => 'Test Company'
    ];
    
    echo "Signup data: " . json_encode($signupRequest->body) . "\n";
    
    ob_start();
    try {
        $authController->register($signupRequest);
    } catch (Exception $e) {
        $output = ob_get_clean();
        echo "Exception caught: " . $e->getMessage() . "\n";
        echo "Output: " . $output . "\n";
    }
    $output = ob_get_clean();
    
    echo "Signup API Response: " . $output . "\n";
    
    $signupResponse = json_decode($output, true);
    if ($signupResponse) {
        echo "✓ Valid signup JSON response\n";
        echo "Success: " . ($signupResponse['success'] ? 'true' : 'false') . "\n";
        echo "Message: " . ($signupResponse['message'] ?? 'No message') . "\n";
    } else {
        echo "✗ Invalid signup JSON response\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n6. Testing actual HTTP request simulation...\n";

// Test with actual HTTP simulation
$testUrl = 'http://localhost/acrm/api/auth/login';
$postData = json_encode([
    'email' => 'admin@autocrm.com',
    'password' => 'admin123'
]);

$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($postData)
        ],
        'content' => $postData
    ]
]);

echo "Testing URL: $testUrl\n";
echo "POST Data: $postData\n";

$result = @file_get_contents($testUrl, false, $context);
if ($result === false) {
    echo "✗ HTTP request failed - this explains the network error!\n";
    echo "Error: " . error_get_last()['message'] . "\n";
} else {
    echo "✓ HTTP request successful\n";
    echo "Response: $result\n";
}
?>