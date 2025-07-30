<?php
require_once "BaseController.php";

class AuthController extends BaseController {
    private $userModel;
    private $passwordResetModel;
    
    public function __construct($database) {
        parent::__construct($database);
        if ($database) {
            require_once __DIR__ . "/../models/User.php";
            require_once __DIR__ . "/../models/PasswordReset.php";
            $this->userModel = new User($database);
            $this->passwordResetModel = new PasswordReset($database);
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
        // Debug logging
        error_log("AuthController::login called - Method: " . $_SERVER["REQUEST_METHOD"]);
        error_log("AuthController::login - Request URI: " . ($_SERVER["REQUEST_URI"] ?? 'N/A'));
        
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
        
        // Fix for live server redirect
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        if ($host === 'acrm.regrowup.ca' || $host === 'www.acrm.regrowup.ca') {
            // Live server - use absolute URL
            $redirectUrl = $protocol . "://" . $host . "/dashboard";
        } else {
            // Local development - use base path
            $basePath = $this->getBasePath();
            $redirectUrl = $basePath . "/dashboard";
        }
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id(),
            "redirect" => $redirectUrl
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
        
        // Fix for live server redirect
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        
        if ($host === 'acrm.regrowup.ca' || $host === 'www.acrm.regrowup.ca') {
            // Live server - use absolute URL
            $redirectUrl = $protocol . "://" . $host . "/employee/email-dashboard";
        } else {
            // Local development - use base path
            $basePath = $this->getBasePath();
            $redirectUrl = $basePath . "/employee/email-dashboard";
        }
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id(),
            "redirect" => $redirectUrl
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
        
        // Store the user role before destroying session
        $wasEmployee = isset($_SESSION["user_role"]) && in_array($_SESSION["user_role"], ['agent', 'manager']);
        
        session_destroy();
        
        // If it's an API request, send JSON response
        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            $this->sendSuccess([], "Logged out successfully");
        } else {
            // For direct navigation, redirect to landing page
            header("Location: /");
            exit();
        }
    }
    
    public function employeeSendOTP($request = null) {
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
        
        if (empty($email)) {
            $this->sendError("Email is required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError("Invalid email format");
        }
        
        // Check if user exists and is an employee
        $user = $this->userModel->findBy("email", $email);
        
        if (!$user) {
            $this->sendError("Email not found", 404);
        }
        
        // Check if user is an employee (agent or manager)
        if (!in_array($user["role"], ['agent', 'manager'])) {
            $this->sendError("This login is for employees only", 403);
        }
        
        // Check if user is active
        if ($user["status"] !== "active") {
            $this->sendError("Account is inactive", 403);
        }
        
        // Generate and send OTP
        try {
            require_once __DIR__ . "/../models/OTP.php";
            require_once __DIR__ . "/../services/EmailService.php";
            
            // Check database connection
            if (!$this->db) {
                $this->sendError("Database connection error", 500);
            }
            
            $otpModel = new OTP($this->db);
            $database = new \stdClass();
            $database->getConnection = function() { return $this->db; };
            $emailService = new EmailService($database);
            
            $otp_code = $otpModel->generateOTP($email);
            
            if (!$otp_code) {
                $this->sendError("Failed to generate OTP", 500);
            }
        } catch (Exception $e) {
            error_log("OTP Generation error: " . $e->getMessage());
            $this->sendError("Failed to generate OTP: " . $e->getMessage(), 500);
        }
        
        // Send OTP email
        $emailSent = $emailService->sendOTPEmail(
            $email, 
            $otp_code, 
            $user["first_name"] . " " . $user["last_name"]
        );
        
        if (!$emailSent) {
            $this->sendError("Failed to send OTP email", 500);
        }
        
        $this->sendSuccess([
            "email" => $email,
            "message" => "OTP sent to your email"
        ], "OTP sent successfully");
    }
    
    public function employeeVerifyOTP($request = null) {
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
        $otp = $this->sanitizeInput($input["otp"] ?? "");
        
        if (empty($email) || empty($otp)) {
            $this->sendError("Email and OTP are required");
        }
        
        // Log for debugging
        error_log("OTP Verification attempt - Email: $email, OTP: $otp");
        
        // Verify OTP
        try {
            require_once __DIR__ . "/../models/OTP.php";
            $otpModel = new OTP($this->db);
            
            if (!$otpModel->verifyOTP($email, $otp)) {
                $this->sendError("Invalid or expired OTP", 401);
            }
            
            // Get user details
            $user = $this->userModel->findBy("email", $email);
            
            if (!$user || $user["status"] !== "active") {
                $this->sendError("Account not found or inactive", 404);
            }
        } catch (Exception $e) {
            error_log("OTP Verification error: " . $e->getMessage());
            $this->sendError("Verification failed: " . $e->getMessage(), 500);
        }
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["login_time"] = time();
        $_SESSION["login_method"] = "otp";
        
        $basePath = $this->getBasePath();
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id(),
            "redirect" => $basePath . "/employee/email-dashboard"
        ], "Login successful");
    }
    
    public function adminLoginAsEmployee($request = null) {
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
        
        // Check if current user is admin
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] !== "admin") {
            $this->sendError("Unauthorized. Admin access required.", 403);
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
        
        $employeeId = intval($input["employee_id"] ?? 0);
        
        if (!$employeeId) {
            $this->sendError("Employee ID is required", 400);
        }
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        // Get employee details
        $user = $this->userModel->find($employeeId);
        
        if (!$user) {
            $this->sendError("Employee not found", 404);
        }
        
        // Check if user is an employee
        if (!in_array($user["role"], ['agent', 'manager'])) {
            $this->sendError("Can only login as employees (agents or managers)", 403);
        }
        
        // Check if user is active
        if ($user["status"] !== "active") {
            $this->sendError("Employee account is inactive", 403);
        }
        
        // Clear current session and create new employee session
        session_destroy();
        session_start();
        
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_name"] = $user["first_name"] . " " . $user["last_name"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["login_time"] = time();
        $_SESSION["admin_login_as_employee"] = true; // Flag to indicate admin logged in as employee
        
        $basePath = $this->getBasePath();
        
        $this->sendSuccess([
            "user" => $this->userModel->hideFields($user),
            "session_id" => session_id(),
            "redirect" => $basePath . "/employee/email-dashboard"
        ], "Logged in as employee successfully");
    }
    
    public function employeeSendLink($request = null) {
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
        
        if (empty($email)) {
            $this->sendError("Email is required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError("Invalid email format");
        }
        
        // Check if user exists and is an employee
        $user = $this->userModel->findBy("email", $email);
        
        if (!$user) {
            $this->sendError("Email not found", 404);
        }
        
        // Check if user is an employee (agent or manager)
        if (!in_array($user["role"], ['agent', 'manager'])) {
            $this->sendError("This login is for employees only", 403);
        }
        
        // Check if user is active
        if ($user["status"] !== "active") {
            $this->sendError("Account is inactive", 403);
        }
        
        // Generate auth token
        require_once __DIR__ . "/../models/AuthToken.php";
        require_once __DIR__ . "/../services/EmailService.php";
        
        $authTokenModel = new AuthToken($this->db);
        $token = $authTokenModel->generateToken($email);
        
        if (!$token) {
            $this->sendError("Failed to generate authentication token", 500);
        }
        
        // Build login URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = $this->getBasePath();
        $loginUrl = "{$protocol}://{$host}{$basePath}/employee/auth?token={$token}";
        
        // Send email with login link
        $database_obj = new \stdClass();
        $database_obj->getConnection = function() { return $this->db; };
        $emailService = new EmailService($database_obj);
        
        $emailSent = $emailService->sendLoginLink(
            $email, 
            $loginUrl, 
            $user["first_name"] . " " . $user["last_name"]
        );
        
        if (!$emailSent) {
            $this->sendError("Failed to send login email", 500);
        }
        
        // Log the login URL in development mode
        if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
            error_log("Login URL for {$email}: {$loginUrl}");
            
            // Also write to a log file
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            $logFile = $logDir . '/login_links.log';
            $logEntry = date('Y-m-d H:i:s') . " - {$email}: {$loginUrl}\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
        
        $this->sendSuccess([
            "email" => $email,
            "message" => "Login link sent to your email"
        ], "Login link sent successfully");
    }
    
    /**
     * Request password reset
     */
    public function forgotPassword($request = null) {
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
        
        if (empty($email)) {
            $this->sendError("Email is required");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError("Invalid email format");
        }
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        // Generate password reset token
        $tokenData = $this->passwordResetModel->generateToken($email);
        
        if (!$tokenData) {
            // Don't reveal if email exists or not for security
            $this->sendSuccess([
                "message" => "If an account with that email exists, a password reset link has been sent"
            ], "Password reset email sent");
        }
        
        // Build reset URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $basePath = $this->getBasePath();
        $resetUrl = "{$protocol}://{$host}{$basePath}/reset-password?token={$tokenData['token']}";
        
        // Send email with reset link
        require_once __DIR__ . "/../services/EmailService.php";
        $database_obj = new \stdClass();
        $database_obj->getConnection = function() { return $this->db; };
        $emailService = new EmailService($database_obj);
        
        $emailSent = $emailService->sendPasswordResetEmail(
            $email, 
            $resetUrl, 
            $tokenData['expires_at']
        );
        
        // Log the reset URL in development mode
        if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
            error_log("Password reset URL for {$email}: {$resetUrl}");
            
            // Also write to a log file
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            $logFile = $logDir . '/password_reset_links.log';
            $logEntry = date('Y-m-d H:i:s') . " - {$email}: {$resetUrl}\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);
        }
        
        $this->sendSuccess([
            "message" => "If an account with that email exists, a password reset link has been sent"
        ], "Password reset email sent");
    }
    
    /**
     * Reset password with token
     */
    public function resetPassword($request = null) {
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
        
        $token = $this->sanitizeInput($input["token"] ?? "");
        $password = $input["password"] ?? "";
        $confirmPassword = $input["confirm_password"] ?? "";
        
        if (empty($token)) {
            $this->sendError("Reset token is required");
        }
        
        if (empty($password)) {
            $this->sendError("New password is required");
        }
        
        if (strlen($password) < 6) {
            $this->sendError("Password must be at least 6 characters long");
        }
        
        if ($password !== $confirmPassword) {
            $this->sendError("Passwords do not match");
        }
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        // Validate token
        $tokenData = $this->passwordResetModel->validateToken($token);
        
        if (!$tokenData) {
            $this->sendError("Invalid or expired reset token", 400);
        }
        
        // Update user password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $result = $stmt->execute([$hashedPassword, $tokenData['user_id']]);
        
        if (!$result) {
            $this->sendError("Failed to update password", 500);
        }
        
        // Mark token as used
        $this->passwordResetModel->markTokenAsUsed($token);
        
        // Clean up expired tokens
        $this->passwordResetModel->cleanupExpiredTokens();
        
        $this->sendSuccess([
            "message" => "Password has been reset successfully"
        ], "Password reset successful");
    }
    
    /**
     * Validate reset token (for frontend validation)
     */
    public function validateResetToken($request = null) {
        // Set CORS headers
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization");
        
        if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
            http_response_code(200);
            exit;
        }
        
        if ($_SERVER["REQUEST_METHOD"] !== "GET") {
            $this->sendError("Method not allowed", 405);
        }
        
        $token = $_GET["token"] ?? "";
        
        if (empty($token)) {
            $this->sendError("Reset token is required");
        }
        
        // Check if database is connected
        if (!$this->db) {
            $this->sendError("Database connection error", 500);
        }
        
        // Validate token
        $tokenData = $this->passwordResetModel->validateToken($token);
        
        if (!$tokenData) {
            $this->sendError("Invalid or expired reset token", 400);
        }
        
        $this->sendSuccess([
            "email" => $tokenData['email'],
            "name" => $tokenData['first_name'] . " " . $tokenData['last_name'],
            "expires_at" => $tokenData['expires_at']
        ], "Token is valid");
    }
}