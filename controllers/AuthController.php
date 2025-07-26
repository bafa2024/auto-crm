<?php
require_once "BaseController.php";

class AuthController extends BaseController {
    private $userModel;
    
    public function __construct($database) {
        parent::__construct($database);
        if ($database) {
            require_once __DIR__ . "/../models/User.php";
            $this->userModel = new User($database);
        }
    }
    
    /**
     * Auto-detect base path for live hosting compatibility
     */
    private function getBasePath() {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        
        // If the request URI contains /acrm/, we're in local development
        if (strpos($requestUri, '/acrm/') !== false || strpos($scriptName, '/acrm/') !== false) {
            return '/acrm';
        }
        
        // Otherwise, we're likely on live hosting
        return '';
    }
    
    public function login($request = null) {
        // Set CORS headers
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(200);
            exit;
        }
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        // Get input data
        if ($request && isset($request->body)) {
            $input = $request->body;
        } else {
            $input = json_decode(file_get_contents("php://input"), true);
        }
        
        if (!$input) {
            $this->sendError("Invalid JSON data", 400);
        }
        
        $email = $this->sanitizeInput($input["email"] ?? "");
        $password = $input["password"] ?? "";
        
        if (empty($email) || empty($password)) {
            $this->sendError("Email and password are required");
        }
        
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        $user = $this->userModel->authenticate($email, $password);
        
        if (!$user) {
            // Add debug info in development mode
            if (($_ENV['APP_DEBUG'] ?? 'true') === 'true') {
                // Check if user exists
                $stmt = $this->db->prepare("SELECT email, status, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existingUser = $stmt->fetch();
                
                if (!$existingUser) {
                    $this->sendError("Invalid credentials - User not found", 401);
                } elseif ($existingUser['status'] !== 'active') {
                    $this->sendError("Invalid credentials - User inactive", 401);
                } else {
                    $this->sendError("Invalid credentials - Password incorrect", 401);
                }
            } else {
                $this->sendError("Invalid credentials", 401);
            }
        }
        
        // Start session only if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["user_role"] = $user["role"] ?? "user";
        $_SESSION["login_time"] = time();
        
        $basePath = $this->getBasePath();
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id(),
            "redirect" => $basePath . "/dashboard"
        ], "Login successful");
    }
    
    public function employeeLogin($request = null) {
        // Set CORS headers
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(200);
            exit;
        }
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        // Get input data
        if ($request && isset($request->body)) {
            $input = $request->body;
        } else {
            $input = json_decode(file_get_contents("php://input"), true);
        }
        
        if (!$input) {
            $this->sendError("Invalid JSON data", 400);
        }
        
        $email = $this->sanitizeInput($input["email"] ?? "");
        $password = $input["password"] ?? "";
        
        if (empty($email) || empty($password)) {
            $this->sendError("Email and password are required");
        }
        
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        $user = $this->userModel->authenticate($email, $password);
        
        if (!$user) {
            // Add debug info in development mode
            if (($_ENV['APP_DEBUG'] ?? 'true') === 'true') {
                // Check if user exists
                $stmt = $this->db->prepare("SELECT email, status, role FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $existingUser = $stmt->fetch();
                
                if (!$existingUser) {
                    $this->sendError("Invalid credentials - User not found", 401);
                } elseif ($existingUser['status'] !== 'active') {
                    $this->sendError("Invalid credentials - User inactive", 401);
                } else {
                    $this->sendError("Invalid credentials - Password incorrect", 401);
                }
            } else {
                $this->sendError("Invalid credentials", 401);
            }
        }
        
        // Check if user is an employee (agent or manager)
        if (!in_array($user["role"], ['agent', 'manager'])) {
            $this->sendError("Access denied. This login is for employees only.", 403);
        }
        
        // Start session only if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["login_time"] = time();
        
        $basePath = $this->getBasePath();
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id(),
            "redirect" => $basePath . "/employee/dashboard"
        ], "Employee login successful");
    }
    
    public function register($request = null) {
        // Set CORS headers
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: POST, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(200);
            exit;
        }
        
        if ($_SERVER["REQUEST_METHOD"] !== "POST") {
            $this->sendError("Method not allowed", 405);
        }
        
        // Get input data
        if ($request && isset($request->body)) {
            $input = $request->body;
        } else {
            $input = json_decode(file_get_contents("php://input"), true);
        }
        
        if (!$input) {
            $this->sendError("Invalid JSON data", 400);
        }
        
        $data = $this->sanitizeInput($input);
        
        // Validate required fields
        $required = ["email", "password", "first_name", "last_name"];
        $errors = $this->validateRequired($data, $required);
        
        if (!empty($errors)) {
            $this->sendError("Validation failed", 400, $errors);
        }
        
        // Validate email format
        if (!filter_var($data["email"], FILTER_VALIDATE_EMAIL)) {
            $this->sendError("Invalid email format", 400);
        }
        
        // Validate password length
        if (strlen($data["password"]) < 6) {
            $this->sendError("Password must be at least 6 characters long", 400);
        }
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        // Check if email already exists
        if ($this->userModel->findBy("email", $data["email"])) {
            $this->sendError("Email already exists. Please use a different email or try logging in.", 409);
        }
        
        // Set default values
        $data["role"] = $data["role"] ?? "user";
        $data["status"] = "active";
        
        try {
            $user = $this->userModel->create($data);
            
            if ($user) {
                $this->sendSuccess($user, "Account created successfully");
            } else {
                $this->sendError("Failed to create account. Please try again.", 500);
            }
        } catch (Exception $e) {
            error_log("User registration error: " . $e->getMessage());
            $this->sendError("An error occurred while creating your account. Please try again.", 500);
        }
    }
    
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        session_destroy();
        
        $this->sendSuccess([], "Logged out successfully");
    }
}