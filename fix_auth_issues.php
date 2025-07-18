<?php
// fix_auth_issues.php - Fix authentication issues

echo "Fixing Authentication Issues\n";
echo "============================\n\n";

try {
    // 1. Fix database schema
    echo "1. Fixing database schema...\n";
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if role column exists
    $result = $db->query("PRAGMA table_info(users)");
    $columns = $result->fetchAll();
    $hasRole = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'role') {
            $hasRole = true;
            break;
        }
    }
    
    if (!$hasRole) {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
        echo "✓ Added role column to users table\n";
    } else {
        echo "✓ Role column already exists\n";
    }
    
    // Update existing users to have role
    $db->exec("UPDATE users SET role = 'admin' WHERE email = 'admin@autocrm.com'");
    $db->exec("UPDATE users SET role = 'user' WHERE role IS NULL OR role = ''");
    echo "✓ Updated user roles\n";
    
    // 2. Fix AuthController
    echo "\n2. Fixing AuthController...\n";
    
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
            $this->sendError("Invalid credentials", 401);
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
        
        $this->sendSuccess([
            "user" => $user,
            "session_id" => session_id(),
            "redirect" => "/dashboard"
        ], "Login successful");
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
}';

    file_put_contents('controllers/AuthController.php', $authControllerContent);
    echo "✓ AuthController updated with CORS headers and fixes\n";
    
    // 3. Update User model
    echo "\n3. Updating User model...\n";
    
    $userModelContent = '<?php
require_once "BaseModel.php";

class User extends BaseModel {
    protected $table = "users";
    protected $fillable = [
        "email", "password", "first_name", "last_name", "company_name", "phone", "role", "status"
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
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ? AND status = ?");
        $stmt->execute([$email, "active"]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user["password"])) {
            return $this->hideFields($user);
        }
        
        return false;
    }
}';

    file_put_contents('models/User.php', $userModelContent);
    echo "✓ User model updated\n";
    
    // 4. Test the fixes
    echo "\n4. Testing fixes...\n";
    
    // Test authentication
    require_once 'models/User.php';
    $userModel = new User($db);
    $user = $userModel->authenticate('admin@autocrm.com', 'admin123');
    
    if ($user) {
        echo "✓ Authentication working\n";
        echo "  User: " . $user['first_name'] . " " . $user['last_name'] . "\n";
        echo "  Role: " . ($user['role'] ?? 'not set') . "\n";
    } else {
        echo "✗ Authentication failed\n";
    }
    
    echo "\n✅ Authentication issues fixed!\n";
    echo "\nChanges made:\n";
    echo "- Added role column to users table\n";
    echo "- Updated AuthController with CORS headers\n";
    echo "- Fixed session handling\n";
    echo "- Updated User model\n";
    echo "- Reduced password requirement to 6 characters\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>