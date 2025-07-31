<?php
// Direct endpoint for sending employee login links
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/models/User.php";
require_once __DIR__ . "/models/AuthToken.php";
require_once __DIR__ . "/services/EmailService.php";

// Set headers
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    // Get input
    $input = json_decode(file_get_contents("php://input"), true);
    $email = filter_var($input['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Valid email address is required");
    }
    
    // Create database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Check if user exists and is an employee
    $userModel = new User($db);
    $user = $userModel->findBy("email", $email);
    
    if (!$user) {
        throw new Exception("Email not found in our system");
    }
    
    // Check if user is an employee (agent or manager)
    if (!in_array($user["role"], ['agent', 'manager'])) {
        throw new Exception("This login is for employees only");
    }
    
    // Check if user is active
    if ($user["status"] !== "active") {
        throw new Exception("Account is inactive. Please contact admin.");
    }
    
    // Generate auth token
    $authTokenModel = new AuthToken($db);
    $token = $authTokenModel->generateToken($email);
    
    if (!$token) {
        throw new Exception("Failed to generate authentication token");
    }
    
    // Build login URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    // Get base path
    require_once __DIR__ . "/config/base_path.php";
    $basePath = BasePath::getBasePath();
    
    $loginUrl = "{$protocol}://{$host}{$basePath}/employee/auth?token={$token}";
    
    // Send email with login link
    $database_obj = new \stdClass();
    $database_obj->getConnection = function() use ($db) { return $db; };
    $emailService = new EmailService($database_obj);
    
    $emailSent = $emailService->sendLoginLink(
        $email, 
        $loginUrl, 
        $user["first_name"] . " " . $user["last_name"]
    );
    
    if (!$emailSent) {
        throw new Exception("Failed to send login email");
    }
    
    // Log the login URL in development mode
    if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
        error_log("Login URL for {$email}: {$loginUrl}");
        
        // Also write to a log file
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logFile = $logDir . '/login_links.log';
        $logEntry = date('Y-m-d H:i:s') . " - {$email}: {$loginUrl}\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Login link sent to your email',
        'debug' => ($_ENV['APP_ENV'] ?? 'development') === 'development' ? [
            'login_url' => $loginUrl,
            'token' => $token
        ] : null
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}