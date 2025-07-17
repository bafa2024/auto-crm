<?php
// fix_autocrm.php - Complete fix script for AutoCRM

echo "AutoCRM Complete Fix Script\n";
echo "==========================\n\n";

// 1. Fix .htaccess
echo "1. Fixing .htaccess...\n";
$htaccessContent = 'RewriteEngine On

# Handle Authorization Header
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# Prevent direct access to sensitive files
RewriteRule ^(composer\.(json|lock)|\.env|\.git|\.gitignore|README\.md)$ - [F,L]

# Block access to sensitive directories
RewriteRule ^(config|models|controllers|services|vendor|logs|temp|backups)/.*$ - [F,L]

# Route all requests through index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]';

file_put_contents('.htaccess', $htaccessContent);
echo "✓ .htaccess fixed\n";

// 2. Fix autoload.php
echo "\n2. Fixing autoloader...\n";
$autoloadContent = '<?php
// autoload.php - Simple autoloader for the application

spl_autoload_register(function ($class) {
    // Remove namespace if present
    $class = str_replace("\\\\", "/", $class);
    $class = basename($class);
    
    // Define search paths
    $paths = [
        __DIR__ . "/controllers/" . $class . ".php",
        __DIR__ . "/models/" . $class . ".php",
        __DIR__ . "/services/" . $class . ".php",
        __DIR__ . "/router/" . $class . ".php",
    ];
    
    // Try to load from each path
    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});';

file_put_contents('autoload.php', $autoloadContent);
echo "✓ Autoloader fixed\n";

// 3. Fix index.php
echo "\n3. Fixing index.php...\n";
$indexContent = '<?php
// index.php - Main entry point

// Error reporting for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Define base path
define("BASE_PATH", __DIR__);

// Use custom autoloader
require_once __DIR__ . "/autoload.php";

// Load environment variables from .env if it exists
if (file_exists(__DIR__ . "/.env")) {
    $envFile = file_get_contents(__DIR__ . "/.env");
    $envLines = explode("\n", $envFile);
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (!empty($line) && strpos($line, "=") !== false && !str_starts_with($line, "#")) {
            list($key, $value) = explode("=", $line, 2);
            $_ENV[trim($key)] = trim($value, "\"\'");
            $_SERVER[trim($key)] = trim($value, "\"\'");
        }
    }
}

// Get request URI and method
$requestUri = $_SERVER["REQUEST_URI"] ?? "/";
$requestMethod = $_SERVER["REQUEST_METHOD"] ?? "GET";

// Remove query string
$requestUri = strtok($requestUri, "?");

// Remove base path if in subdirectory
$scriptName = $_SERVER["SCRIPT_NAME"];
$basePath = dirname($scriptName);
if ($basePath !== "/" && strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}

// Ensure URI starts with /
if ($requestUri === "" || $requestUri[0] !== "/") {
    $requestUri = "/" . $requestUri;
}

// Route static files
if (preg_match("/\.(css|js|jpg|jpeg|png|gif|ico|woff|woff2|ttf|svg)$/i", $requestUri)) {
    $file = __DIR__ . $requestUri;
    if (file_exists($file)) {
        $mime = mime_content_type($file);
        header("Content-Type: " . $mime);
        readfile($file);
        exit;
    }
}

// Initialize database connection
require_once __DIR__ . "/config/database.php";
$database = new Database();
$db = $database->getConnection();

// Simple routing
try {
    switch (true) {
        // Landing page
        case $requestUri === "/" || $requestUri === "/index.php":
            require_once __DIR__ . "/views/landing.php";
            break;
            
        // Auth pages
        case $requestUri === "/login":
            require_once __DIR__ . "/views/auth/login.php";
            break;
            
        case $requestUri === "/signup":
            require_once __DIR__ . "/views/auth/signup.php";
            break;
            
        case $requestUri === "/logout":
            session_start();
            session_destroy();
            header("Location: /");
            exit;
            
        // Dashboard
        case strpos($requestUri, "/dashboard") === 0:
            session_start();
            if (!isset($_SESSION["user_id"])) {
                header("Location: /login");
                exit;
            }
            require_once __DIR__ . "/views/dashboard/index.php";
            break;
            
        // API endpoints
        case strpos($requestUri, "/api/") === 0:
            header("Content-Type: application/json");
            header("Access-Control-Allow-Origin: *");
            header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            header("Access-Control-Allow-Headers: Content-Type, Authorization");
            
            if ($requestMethod === "OPTIONS") {
                http_response_code(200);
                exit;
            }
            
            // Parse API endpoint
            $apiPath = substr($requestUri, 4); // Remove /api
            $pathParts = explode("/", trim($apiPath, "/"));
            
            // Route to appropriate controller
            switch ($pathParts[0] ?? "") {
                case "auth":
                    require_once __DIR__ . "/controllers/AuthController.php";
                    $controller = new AuthController($db);
                    
                    switch ($pathParts[1] ?? "") {
                        case "register":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->register($request);
                            }
                            break;
                            
                        case "login":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->login($request);
                            }
                            break;
                            
                        default:
                            http_response_code(404);
                            echo json_encode(["error" => "Endpoint not found"]);
                    }
                    break;
                    
                default:
                    http_response_code(404);
                    echo json_encode(["error" => "API endpoint not found"]);
            }
            break;
            
        // 404 for everything else
        default:
            http_response_code(404);
            require_once __DIR__ . "/views/404.php";
    }
} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    http_response_code(500);
    
    if (strpos($requestUri, "/api/") === 0) {
        echo json_encode(["error" => "Internal server error"]);
    } else {
        echo "<h1>500 Internal Server Error</h1>";
        if ($_ENV["APP_DEBUG"] === "true") {
            echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        }
    }
}';

file_put_contents('index.php', $indexContent);
echo "✓ index.php fixed\n";

// 4. Fix database config
echo "\n4. Fixing database configuration...\n";
$databaseContent = '<?php
// config/database.php

class Database {
    private $conn;
    private $config;

    public function __construct() {
        // Load config from environment or defaults
        $this->config = [
            "host" => $_ENV["DB_HOST"] ?? "localhost",
            "port" => $_ENV["DB_PORT"] ?? "3306",
            "database" => $_ENV["DB_NAME"] ?? "u946493694_autocrm",
            "username" => $_ENV["DB_USER"] ?? "u946493694_autocrmu",
            "password" => $_ENV["DB_PASS"] ?? "CDExzsawq123@#$"
        ];
    }

    public function getConnection() {
        $this->conn = null;
        
        try {
            $dsn = "mysql:host=" . $this->config["host"] . 
                   ";port=" . $this->config["port"] . 
                   ";dbname=" . $this->config["database"] . 
                   ";charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->conn = new PDO($dsn, $this->config["username"], $this->config["password"], $options);
        } catch(PDOException $exception) {
            // Log error but don\'t expose to user
            error_log("Database connection failed: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }
}';

// Create config directory if it doesn't exist
if (!is_dir('config')) {
    mkdir('config', 0755, true);
}
file_put_contents('config/database.php', $databaseContent);
echo "✓ Database config fixed\n";

// 5. Fix BaseController
echo "\n5. Fixing BaseController...\n";
$baseControllerContent = '<?php
abstract class BaseController {
    protected $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    protected function sendJson($data, $statusCode = 200) {
        http_response_code($statusCode);
        header("Content-Type: application/json");
        echo json_encode($data);
        exit;
    }
    
    protected function sendError($message, $statusCode = 400, $errors = []) {
        $this->sendJson([
            "success" => false,
            "message" => $message,
            "errors" => $errors
        ], $statusCode);
    }
    
    protected function sendSuccess($data = [], $message = "Success") {
        $this->sendJson([
            "success" => true,
            "message" => $message,
            "data" => $data
        ]);
    }
    
    protected function validateRequired($data, $required) {
        $errors = [];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $errors[$field] = ucfirst($field) . " is required";
            }
        }
        return $errors;
    }
    
    protected function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([$this, "sanitizeInput"], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, "UTF-8");
    }
}';

// Create controllers directory if it doesn't exist
if (!is_dir('controllers')) {
    mkdir('controllers', 0755, true);
}
file_put_contents('controllers/BaseController.php', $baseControllerContent);
echo "✓ BaseController fixed\n";

// 6. Fix AuthController
echo "\n6. Fixing AuthController...\n";
$authControllerContent = '<?php
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
        
        // Start session
        session_start();
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user_role"] = $user["role"];
        $_SESSION["login_time"] = time();
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id()
        ], "Login successful");
    }
}';

file_put_contents('controllers/AuthController.php', $authControllerContent);
echo "✓ AuthController fixed\n";

// 7. Fix BaseModel
echo "\n7. Fixing BaseModel...\n";
$baseModelContent = '<?php
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = "id";
    protected $fillable = [];
    protected $hidden = [];
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    public function find($id) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $this->hideFields($result);
        }
        return null;
    }
    
    public function findBy($field, $value) {
        if (!$this->db) return null;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = ?");
        $stmt->execute([$value]);
        $result = $stmt->fetch();
        
        if ($result) {
            return $this->hideFields($result);
        }
        return null;
    }
    
    public function create($data) {
        if (!$this->db) return false;
        
        $data = $this->filterFillable($data);
        $data["created_at"] = date("Y-m-d H:i:s");
        $data["updated_at"] = date("Y-m-d H:i:s");
        
        $fields = array_keys($data);
        $placeholders = str_repeat("?,", count($fields) - 1) . "?";
        
        $sql = "INSERT INTO {$this->table} (" . implode(",", $fields) . ") VALUES ({$placeholders})";
        $stmt = $this->db->prepare($sql);
        
        if ($stmt->execute(array_values($data))) {
            return $this->find($this->db->lastInsertId());
        }
        return false;
    }
    
    protected function filterFillable($data) {
        if (empty($this->fillable)) {
            return $data;
        }
        
        return array_intersect_key($data, array_flip($this->fillable));
    }
    
    protected function hideFields($data) {
        if (empty($this->hidden) || !is_array($data)) {
            return $data;
        }
        
        foreach ($this->hidden as $field) {
            unset($data[$field]);
        }
        
        return $data;
    }
}';

// Create models directory if it doesn't exist
if (!is_dir('models')) {
    mkdir('models', 0755, true);
}
file_put_contents('models/BaseModel.php', $baseModelContent);
echo "✓ BaseModel fixed\n";

// 8. Fix User model
echo "\n8. Fixing User model...\n";
$userModelContent = '<?php
require_once "BaseModel.php";

class User extends BaseModel {
    protected $table = "users";
    protected $fillable = [
        "email", "password", "first_name", "last_name", "company_name", "role", "status"
    ];
    protected $hidden = ["password"];
    
    public function create($data) {
        if (isset($data["password"])) {
            $data["password"] = password_hash($data["password"], PASSWORD_DEFAULT);
        }
        return parent::create($data);
    }
    
    public function authenticate($email, $password) {
        if (!$this->db) return false;
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? AND status = \\"active\\"");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user["password"])) {
            return $this->hideFields($user);
        }
        
        return false;
    }
}';

file_put_contents('models/User.php', $userModelContent);
echo "✓ User model fixed\n";

// 9. Create other required models
echo "\n9. Creating other models...\n";

// Contact model
$contactModelContent = '<?php
require_once "BaseModel.php";

class Contact extends BaseModel {
    protected $table = "contacts";
    protected $fillable = [
        "first_name", "last_name", "email", "phone", "company", "job_title",
        "lead_source", "interest_level", "status", "notes", "assigned_agent_id", "created_by"
    ];
}';
file_put_contents('models/Contact.php', $contactModelContent);

// EmailCampaign model
$emailCampaignContent = '<?php
require_once "BaseModel.php";

class EmailCampaign extends BaseModel {
    protected $table = "email_campaigns";
    protected $fillable = [
        "name", "subject", "content", "sender_name", "sender_email", "reply_to_email",
        "campaign_type", "status", "scheduled_at", "created_by"
    ];
}';
file_put_contents('models/EmailCampaign.php', $emailCampaignContent);

// EmailTemplate model
$emailTemplateContent = '<?php
require_once "BaseModel.php";

class EmailTemplate extends BaseModel {
    protected $table = "email_templates";
    protected $fillable = [
        "name", "subject", "content", "template_type", "is_active", "created_by"
    ];
}';
file_put_contents('models/EmailTemplate.php', $emailTemplateContent);

echo "✓ All models created\n";

// 10. Create required directories
echo "\n10. Creating required directories...\n";
$directories = ['uploads', 'logs', 'temp', 'backups', 'cache', 'sessions', 'views/components', 'views/auth', 'views/dashboard', 'css', 'js'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "✓ Created $dir directory\n";
    }
}

// 11. Create or update .env file
echo "\n11. Checking .env file...\n";
if (!file_exists('.env')) {
    $envContent = '# Database Configuration
DB_HOST=localhost
DB_PORT=3306
DB_NAME=u946493694_autocrm
DB_USER=u946493694_autocrmu
DB_PASS=CDExzsawq123@#$

# Application
APP_URL=https://autocrm.regrowup.ca
APP_DEBUG=true
JWT_SECRET=your-secret-key-' . uniqid() . '

# Email (optional)
SMTP_HOST=localhost
SMTP_PORT=587
SMTP_USERNAME=
SMTP_PASSWORD=';
    
    file_put_contents('.env', $envContent);
    echo "✓ .env file created\n";
} else {
    echo "✓ .env file already exists\n";
}

// 12. Set permissions
echo "\n12. Setting permissions...\n";
$writableDirs = ['uploads', 'logs', 'temp', 'backups', 'cache', 'sessions'];
foreach ($writableDirs as $dir) {
    if (is_dir($dir)) {
        chmod($dir, 0755);
    }
}
echo "✓ Permissions set\n";

echo "\n✅ Fix completed!\n";
echo "\n🚀 Next steps:\n";
echo "1. Run: php check_env.php\n";
echo "2. Run: php setup_admin.php\n";
echo "3. Access your site at: https://autocrm.regrowup.ca\n";
echo "4. Login with: admin@autocrm.com / admin123\n";
echo "\nIf you still have issues, check the logs/ directory for errors.\n";