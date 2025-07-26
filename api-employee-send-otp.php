<?php
// Direct OTP sending endpoint - bypasses routing for testing
require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/controllers/AuthController.php";

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
    // Create database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Database connection failed");
    }
    
    // Create controller and handle request
    $controller = new AuthController($db);
    
    // Get input
    $input = json_decode(file_get_contents("php://input"), true);
    
    // Create request object
    $request = new stdClass();
    $request->body = $input;
    
    // Call the controller method
    $controller->employeeSendOTP($request);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}