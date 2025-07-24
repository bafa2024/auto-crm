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
            session_destroy();
            header("Location: /");
            exit;
            
        // Dashboard
        case strpos($requestUri, "/dashboard") === 0:
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
                    
                case "campaigns":
                    require_once __DIR__ . "/controllers/EmailCampaignController.php";
                    $controller = new EmailCampaignController($db);
                    
                    switch ($pathParts[1] ?? "") {
                        case "create":
                            if ($requestMethod === "POST") {
                                $controller->createCampaign();
                            }
                            break;
                            
                        default:
                            http_response_code(404);
                            echo json_encode(["error" => "Campaign endpoint not found"]);
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