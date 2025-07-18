<?php
// api_test.php - Direct API test endpoint

// Set headers first
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle OPTIONS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Test database connection
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception('Database connection failed');
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Test login
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            throw new Exception('Email and password required');
        }
        
        // Test authentication
        require_once 'models/User.php';
        $userModel = new User($db);
        $user = $userModel->authenticate($email, $password);
        
        if ($user) {
            // Start session
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'] ?? 'user';
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful via direct API test',
                'data' => [
                    'user' => $user,
                    'session_id' => session_id(),
                    'redirect' => '/acrm/dashboard'
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid credentials'
            ]);
        }
        
    } else {
        // GET request - show status
        echo json_encode([
            'success' => true,
            'message' => 'API test endpoint working',
            'method' => $_SERVER['REQUEST_METHOD'],
            'database' => 'Connected',
            'time' => date('Y-m-d H:i:s')
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage(),
        'file' => __FILE__
    ]);
}
?>