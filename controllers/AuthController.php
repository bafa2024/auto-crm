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
    
    public function login($request = null) {
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
            $this->sendError("Invalid credentials", 401);
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
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id()
        ], "Login successful");
    }
    
    public function register($request = null) {
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
        if (strlen($data["password"]) < 8) {
            $this->sendError("Password must be at least 8 characters long", 400);
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
        $data["role"] = $data["role"] ?? "agent";
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
}