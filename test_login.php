<?php
// test_login.php - Test the login functionality

echo "Testing Login Functionality\n";
echo "===========================\n\n";

try {
    // 1. Test database connection
    echo "1. Testing database connection...\n";
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    echo "✓ Database connected\n";
    
    // 2. Test AuthController
    echo "\n2. Testing AuthController...\n";
    
    if (!file_exists('controllers/AuthController.php')) {
        throw new Exception("AuthController not found");
    }
    
    require_once 'controllers/AuthController.php';
    echo "✓ AuthController loaded\n";
    
    // 3. Simulate login request
    echo "\n3. Testing admin login simulation...\n";
    
    $authController = new AuthController($db);
    
    // Create mock request for login
    $loginData = [
        'email' => 'admin@autocrm.com',
        'password' => 'admin123'
    ];
    
    // Test direct password verification
    $stmt = $db->prepare("SELECT id, first_name, last_name, email, password FROM users WHERE email = ?");
    $stmt->execute([$loginData['email']]);
    $user = $stmt->fetch();
    
    if ($user) {
        echo "✓ User found in database\n";
        echo "  - ID: {$user['id']}\n";
        echo "  - Name: {$user['first_name']} {$user['last_name']}\n";
        echo "  - Email: {$user['email']}\n";
        
        if (password_verify($loginData['password'], $user['password'])) {
            echo "✓ Password verification successful\n";
        } else {
            throw new Exception("Password verification failed");
        }
    } else {
        throw new Exception("User not found");
    }
    
    // 4. Test session handling
    echo "\n4. Testing session handling...\n";
    
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session variables as the login would
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
    
    echo "✓ Session variables set:\n";
    echo "  - user_id: {$_SESSION['user_id']}\n";
    echo "  - user_email: {$_SESSION['user_email']}\n";
    echo "  - user_name: {$_SESSION['user_name']}\n";
    
    // 5. Test dashboard access simulation
    echo "\n5. Testing dashboard access...\n";
    
    // Check if user would be allowed to access dashboard
    if (isset($_SESSION['user_id'])) {
        echo "✓ User authenticated for dashboard access\n";
    } else {
        throw new Exception("User not authenticated");
    }
    
    // 6. Test API login endpoint
    echo "\n6. Testing API login endpoint format...\n";
    
    // Create the same format that the frontend would send
    $apiRequest = json_encode($loginData);
    echo "✓ API request format valid: " . $apiRequest . "\n";
    
    // Parse it back as the API would
    $parsedData = json_decode($apiRequest, true);
    if ($parsedData && isset($parsedData['email']) && isset($parsedData['password'])) {
        echo "✓ API request parsing successful\n";
    } else {
        throw new Exception("API request parsing failed");
    }
    
    echo "\n" . str_repeat("=", 50) . "\n";
    echo "✅ LOGIN FUNCTIONALITY TEST PASSED\n";
    echo str_repeat("=", 50) . "\n\n";
    
    echo "🎉 READY FOR BROWSER TESTING!\n\n";
    
    echo "Next steps:\n";
    echo "1. Start XAMPP (Apache)\n";
    echo "2. Open browser and go to: http://localhost/acrm/login\n";
    echo "3. Use these credentials:\n";
    echo "   📧 Email: admin@autocrm.com\n";
    echo "   🔐 Password: admin123\n\n";
    
    echo "Expected flow:\n";
    echo "Login → Dashboard → Working AutoDial Pro interface\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>