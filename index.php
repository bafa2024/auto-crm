<?php
// index.php - Main entry point

// Error reporting for debugging
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
            $_ENV[trim($key)] = trim($value, "\"'");
            $_SERVER[trim($key)] = trim($value, "\"'");
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

// Load base path configuration
require_once __DIR__ . "/config/base_path.php";

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
            
        case $requestUri === "/forgot-password":
            require_once __DIR__ . "/views/auth/forgot-password.php";
            break;
            
        case strpos($requestUri, "/reset-password") === 0:
            require_once __DIR__ . "/views/auth/reset-password.php";
            break;
            
        case $requestUri === "/logout":
            session_destroy();
            header("Location: /");
            exit;
            
        // Employee auth pages
        case $requestUri === "/employee/login":
            require_once __DIR__ . "/views/auth/employee_login.php";
            break;
            
        case $requestUri === "/employee/forgot-password":
            require_once __DIR__ . "/views/auth/employee_forgot_password.php";
            break;
            
        case strpos($requestUri, "/employee/reset-password") === 0:
            require_once __DIR__ . "/views/auth/employee_reset_password.php";
            break;
            
        case $requestUri === "/employee/logout":
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_destroy();
            header("Location: " . base_path('employee/login'));
            exit;
            
        // Contacts page
        case $requestUri === "/contacts.php":
            if (!isset($_SESSION["user_id"])) {
                header("Location: /login");
                exit;
            }
            require_once __DIR__ . "/contacts.php";
            break;
            
        // Campaigns page
        case $requestUri === "/campaigns.php":
            if (!isset($_SESSION["user_id"])) {
                header("Location: /login");
                exit;
            }
            require_once __DIR__ . "/campaigns.php";
            break;
            
        // Dashboard
        case strpos($requestUri, "/dashboard") === 0:
            if (!isset($_SESSION["user_id"])) {
                header("Location: /login");
                exit;
            }
            
            // Handle dashboard sub-pages
            $dashboardPath = substr($requestUri, 10); // Remove "/dashboard"
            $dashboardPath = ltrim($dashboardPath, '/');
            
            if (empty($dashboardPath)) {
                // Main dashboard
                require_once __DIR__ . "/views/dashboard/index.php";
            } else {
                // Dashboard sub-pages
                $subPagePath = __DIR__ . "/views/dashboard/" . $dashboardPath;
                
                // Check if the path already has .php extension
                if (!preg_match('/\.php$/', $subPagePath)) {
                    // Try with .php extension first
                    if (file_exists($subPagePath . '.php')) {
                        $subPagePath .= '.php';
                    }
                }
                
                if (file_exists($subPagePath)) {
                    require_once $subPagePath;
                } else {
                    http_response_code(404);
                    require_once __DIR__ . "/views/404.php";
                }
            }
            break;
            
        // Employee Auth (Magic Link)
        case strpos($requestUri, "/employee/auth") === 0:
            require_once __DIR__ . "/views/employee/auth.php";
            break;
            
        // Employee Dashboard - Redirect to email dashboard
        case $requestUri === "/employee/dashboard":
            if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
                header("Location: " . base_path('employee/login'));
                exit;
            }
            // Redirect to email dashboard for employees
            header("Location: " . base_path('employee/email-dashboard'));
            exit;
            break;
            
        // Employee Email Dashboard
        case $requestUri === "/employee/email-dashboard":
            if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
                header("Location: " . base_path('employee/login'));
                exit;
            }
            require_once __DIR__ . "/views/employee/email-dashboard.php";
            break;
            
        // Employee Campaigns
        case strpos($requestUri, "/employee/campaigns") === 0:
            if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
                header("Location: " . base_path('employee/login'));
                exit;
            }
            
            $campaignPath = substr($requestUri, 19); // Remove "/employee/campaigns"
            $campaignPath = ltrim($campaignPath, '/');
            
            if (empty($campaignPath)) {
                require_once __DIR__ . "/views/employee/campaigns.php";
            } elseif ($campaignPath === 'create') {
                require_once __DIR__ . "/views/employee/campaign-create.php";
            } elseif (preg_match('/^view\/(\d+)$/', $campaignPath, $matches)) {
                $_GET['id'] = $matches[1];
                require_once __DIR__ . "/views/employee/campaign-view.php";
            } elseif (preg_match('/^edit\/(\d+)$/', $campaignPath, $matches)) {
                $_GET['id'] = $matches[1];
                require_once __DIR__ . "/views/employee/campaign-edit.php";
            } elseif ($campaignPath === 'analytics') {
                require_once __DIR__ . "/views/employee/campaign-analytics.php";
            } else {
                http_response_code(404);
                require_once __DIR__ . "/views/404.php";
            }
            break;
            
        // Employee Contacts
        case $requestUri === "/employee/contacts":
            if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
                header("Location: " . base_path('employee/login'));
                exit;
            }
            require_once __DIR__ . "/views/employee/contacts.php";
            break;
            
        // Employee Profile
        case $requestUri === "/employee/profile":
            if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
                header("Location: " . base_path('employee/login'));
                exit;
            }
            require_once __DIR__ . "/views/employee/profile.php";
            break;
            
        // Unsubscribe page
        case strpos($requestUri, "/unsubscribe/") === 0:
            require_once __DIR__ . "/controllers/EmailTrackingController.php";
            $controller = new EmailTrackingController($db);
            $token = substr($requestUri, 13); // Remove "/unsubscribe/"
            $controller->unsubscribe($token);
            break;
            
        // API endpoints
        case strpos($requestUri, "/api/") === 0:
            // Debug logging for API requests
            error_log("API Request Debug - URI: " . $requestUri . ", Method: " . $requestMethod);
            
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
            
            // Debug logging for employee routes
            if (strpos($apiPath, "/employees") !== false) {
                error_log("API Debug - Original URI: " . $requestUri);
                error_log("API Debug - API Path: " . $apiPath);
                error_log("API Debug - Path Parts: " . json_encode($pathParts));
                error_log("API Debug - Method: " . $requestMethod);
            }
            
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
                            
                        case "employee-login":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->employeeLogin($request);
                            }
                            break;
                            
                        case "employee-send-otp":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->employeeSendOTP($request);
                            }
                            break;
                            
                        case "employee-verify-otp":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->employeeVerifyOTP($request);
                            }
                            break;
                            
                        case "admin-login-as-employee":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->adminLoginAsEmployee($request);
                            }
                            break;
                            
                        case "employee-send-link":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->employeeSendLink($request);
                            }
                            break;
                            
                        case "employee-forgot-password":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->employeeForgotPassword($request);
                            }
                            break;
                            
                        case "employee-reset-password":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->employeeResetPassword($request);
                            }
                            break;
                            
                        case "employee-validate-reset-token":
                            if ($requestMethod === "GET") {
                                $controller->employeeValidateResetToken();
                            }
                            break;
                            
                        default:
                            http_response_code(404);
                            echo json_encode(["error" => "Endpoint not found"]);
                    }
                    break;
                    
                case "campaigns":
                    require_once __DIR__ . "/controllers/EmailCampaignController.php";
                    $controller = new EmailCampaignController($db);
                    
                    // Handle /api/campaigns (POST) - create campaign
                    if (!isset($pathParts[1]) && $requestMethod === "POST") {
                        $controller->createCampaign();
                    }
                    // Handle /api/campaigns/{id}/status (PUT)
                    elseif (isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "status" && $requestMethod === "PUT") {
                        $controller->updateCampaignStatus((int)$pathParts[1]);
                    }
                    // Handle /api/campaigns/{id}/duplicate (POST)
                    elseif (isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "duplicate" && $requestMethod === "POST") {
                        $controller->duplicateCampaign((int)$pathParts[1]);
                    }
                    // Handle /api/campaigns/{id} (DELETE)
                    elseif (isset($pathParts[1]) && is_numeric($pathParts[1]) && !isset($pathParts[2]) && $requestMethod === "DELETE") {
                        $controller->deleteCampaign((int)$pathParts[1]);
                    }
                    // Handle /api/campaigns/{id}/send (POST) - manually send campaign
                    elseif (isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "send" && $requestMethod === "POST") {
                        $controller->sendCampaign((int)$pathParts[1]);
                    }
                    // Handle /api/campaigns/{id}/stats (GET) - get campaign statistics
                    elseif (isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "stats" && $requestMethod === "GET") {
                        $controller->getCampaignStats((int)$pathParts[1]);
                    }
                    // Handle /api/campaigns/send-test (POST) - send test email
                    elseif (isset($pathParts[1]) && $pathParts[1] === "send-test" && $requestMethod === "POST") {
                        $controller->sendTestEmail();
                    }
                    else {
                        http_response_code(404);
                        echo json_encode(["error" => "Campaign endpoint not found"]);
                    }
                    break;
                    
                case "email":
                    // Email tracking endpoints
                    if (isset($pathParts[1]) && $pathParts[1] === "track") {
                        require_once __DIR__ . "/controllers/EmailTrackingController.php";
                        $controller = new EmailTrackingController($db);
                        
                        if (isset($pathParts[2]) && $pathParts[2] === "open" && isset($pathParts[3])) {
                            // /api/email/track/open/{trackingId}
                            $controller->trackOpen($pathParts[3]);
                        } elseif (isset($pathParts[2]) && $pathParts[2] === "click" && isset($pathParts[3])) {
                            // /api/email/track/click/{trackingId}
                            $controller->trackClick($pathParts[3]);
                        } else {
                            http_response_code(404);
                        }
                    } 
                    // Email webhook endpoints
                    elseif (isset($pathParts[1]) && $pathParts[1] === "webhook") {
                        require_once __DIR__ . "/controllers/EmailWebhookController.php";
                        $controller = new EmailWebhookController($db);
                        
                        if (isset($pathParts[2]) && $pathParts[2] === "bounce" && $requestMethod === "POST") {
                            // /api/email/webhook/bounce
                            $controller->handleBounce();
                        } elseif (isset($pathParts[2]) && $pathParts[2] === "complaint" && $requestMethod === "POST") {
                            // /api/email/webhook/complaint
                            $controller->handleComplaint();
                        } else {
                            http_response_code(404);
                        }
                    } else {
                        http_response_code(404);
                    }
                    break;
                    
                case "teams":
                    require_once __DIR__ . "/controllers/TeamController.php";
                    $controller = new TeamController($db);
                    switch ($pathParts[1] ?? "") {
                        case "create":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->createTeam($request);
                            }
                            break;
                        case "add-member":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->addMember($request);
                            }
                            break;
                        case "remove-member":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->removeMember($request);
                            }
                            break;
                        case "set-privilege":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->setPrivilege($request);
                            }
                            break;
                        case "edit":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->editTeam($request);
                            }
                            break;
                        case "delete":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->deleteTeam($request);
                            }
                            break;
                        case "update-member-role":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->updateMemberRole($request);
                            }
                            break;
                        case "list":
                            if ($requestMethod === "GET") {
                                $controller->listTeams();
                            }
                            break;
                        case "search-users":
                            if ($requestMethod === "GET") {
                                $controller->searchUsers();
                            }
                            break;
                        default:
                            // GET /api/teams/{id}, /api/teams/{id}/members, /api/teams/{id}/privileges/{user_id}
                            if ($requestMethod === "GET" && isset($pathParts[1]) && is_numeric($pathParts[1])) {
                                $teamId = (int)$pathParts[1];
                                if (!isset($pathParts[2])) {
                                    $controller->getTeam($teamId);
                                } elseif ($pathParts[2] === "members") {
                                    $controller->getMembers($teamId);
                                } elseif ($pathParts[2] === "privileges" && isset($pathParts[3]) && is_numeric($pathParts[3])) {
                                    $userId = (int)$pathParts[3];
                                    $controller->getPrivileges($teamId, $userId);
                                } else {
                                    http_response_code(404);
                                    echo json_encode(["error" => "Teams endpoint not found"]);
                                }
                            } else {
                                http_response_code(404);
                                echo json_encode(["error" => "Teams endpoint not found"]);
                            }
                    }
                    break;
                    
                case "employee":
                    require_once __DIR__ . "/controllers/EmployeeController.php";
                    $controller = new EmployeeController($db);
                    switch ($pathParts[1] ?? "") {
                        case "stats":
                            if ($requestMethod === "GET") {
                                $controller->getStats();
                            }
                            break;
                        case "recent-contacts":
                            if ($requestMethod === "GET") {
                                $controller->getRecentContacts();
                            }
                            break;
                        case "recent-activity":
                            if ($requestMethod === "GET") {
                                $controller->getRecentActivity();
                            }
                            break;
                        case "contacts":
                            if ($requestMethod === "GET") {
                                if (isset($pathParts[2]) && is_numeric($pathParts[2])) {
                                    $contactId = (int)$pathParts[2];
                                    $controller->getContact($contactId);
                                } else {
                                    $controller->getContacts();
                                }
                            }
                            break;
                        case "profile":
                            if ($requestMethod === "GET") {
                                $controller->getProfile();
                            } elseif ($requestMethod === "POST") {
                                $controller->updateProfile();
                            }
                            break;
                        case "change-password":
                            if ($requestMethod === "POST") {
                                $controller->changePassword();
                            }
                            break;
                        default:
                            http_response_code(404);
                            echo json_encode(["error" => "Employee endpoint not found"]);
                    }
                    break;
                    
                case "employees":
                    require_once __DIR__ . "/controllers/UserController.php";
                    $controller = new UserController($db);
                    switch ($pathParts[1] ?? "") {
                        case "list":
                            if ($requestMethod === "GET") {
                                $controller->listEmployees();
                            }
                            break;
                        case "create":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->createEmployee($request);
                            }
                            break;
                        case "edit":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->editEmployee($request);
                            }
                            break;
                        case "delete":
                            if ($requestMethod === "POST") {
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->deleteEmployee($request);
                            }
                            break;
                        default:
                            // /api/employees/{id}/teams, add-to-team, remove-from-team
                            if ($requestMethod === "GET" && isset($pathParts[1]) && is_numeric($pathParts[1]) && !isset($pathParts[2])) {
                                // GET /api/employees/{id} - Get individual employee details
                                $userId = (int)$pathParts[1];
                                $controller->getEmployee($userId);
                            } elseif ($requestMethod === "GET" && isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "teams") {
                                $userId = (int)$pathParts[1];
                                $controller->getEmployeeTeams($userId);
                            } elseif ($requestMethod === "POST" && isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "add-to-team") {
                                $userId = (int)$pathParts[1];
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->addToTeam($userId, $request);
                            } elseif ($requestMethod === "POST" && isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "remove-from-team") {
                                $userId = (int)$pathParts[1];
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->removeFromTeam($userId, $request);
                            } elseif ($requestMethod === "GET" && isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "permissions") {
                                $userId = (int)$pathParts[1];
                                $controller->getEmployeePermissions($userId);
                            } elseif ($requestMethod === "PUT" && isset($pathParts[1]) && is_numeric($pathParts[1]) && isset($pathParts[2]) && $pathParts[2] === "permissions") {
                                $userId = (int)$pathParts[1];
                                $input = json_decode(file_get_contents("php://input"), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->updateEmployeePermissions($userId, $request);
                            } else {
                                http_response_code(404);
                                echo json_encode(["error" => "Employees endpoint not found"]);
                            }
                    }
                    break;
                    
                case "dashboard_stats":
                    if ($requestMethod === "GET") {
                        require_once __DIR__ . "/api/dashboard_stats.php";
                        exit;
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
}