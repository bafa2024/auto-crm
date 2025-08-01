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
            
        // Employee Analytics
        case $requestUri === "/employee/analytics":
            if (!isset($_SESSION["user_id"]) || !in_array($_SESSION["user_role"], ['agent', 'manager'])) {
                header("Location: " . base_path('employee/login'));
                exit;
            }
            require_once __DIR__ . "/views/employee/analytics.php";
            break;
            
        // Unsubscribe
        case strpos($requestUri, "/unsubscribe/") === 0:
            require_once __DIR__ . "/controllers/EmailTrackingController.php";
            $controller = new EmailTrackingController($db);
            $token = substr($requestUri, 13); // Remove "/unsubscribe/"
            $controller->unsubscribe($token);
            break;
            
        // API endpoints - Route to dedicated API handler
        case strpos($requestUri, "/api/") === 0:
            // Debug logging for API requests
            error_log("API Request Debug - URI: " . $requestUri . ", Method: " . $requestMethod);
            
            // Route to the dedicated API handler
            require_once __DIR__ . "/api/index.php";
            exit;
            
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
?> 