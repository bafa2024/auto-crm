<?php
require_once '../config/config.php';
require_once '../config/database.php';

// Set API mode
define('API_MODE', true);

// CORS headers
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
    if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: $origin");
    }
}

header('Access-Control-Allow-Methods: ' . implode(', ', CORS_ALLOWED_METHODS));
header('Access-Control-Allow-Headers: ' . implode(', ', CORS_ALLOWED_HEADERS));
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get database connection
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Parse the request URI
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api';
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$pathParts = array_filter(explode('/', $path));

// Basic routing
$resource = $pathParts[0] ?? '';
$id = $pathParts[1] ?? null;
$action = $pathParts[2] ?? null;

try {
    switch ($resource) {
        case 'auth':
            $controller = new AuthController($db);
            handleAuthRoutes($controller, $id);
            break;
        case 'contacts':
            $controller = new ContactController($db);
            handleContactRoutes($controller, $id, $action);
            break;
        case 'campaigns':
            $controller = new EmailCampaignController($db);
            handleCampaignRoutes($controller, $id, $action);
            break;
        case 'templates':
            $controller = new EmailCampaignController($db);
            handleTemplateRoutes($controller, $id);
            break;
        case 'track':
            handleTrackingRoutes($db, $id, $action);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    error_log("API Error: " . $e->getMessage());
}

function handleAuthRoutes($controller, $action) {
    switch ($action) {
        case 'login':
            $controller->login();
            break;
        case 'logout':
            $controller->logout();
            break;
        case 'register':
            $controller->register();
            break;
        case 'profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->getProfile();
            } else {
                $controller->updateProfile();
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Auth endpoint not found']);
    }
}

function handleContactRoutes($controller, $id, $action) {
    if ($id === 'bulk-upload') {
        $controller->bulkUpload();
        return;
    }
    if ($id === 'stats') {
        $controller->getStats();
        return;
    }
    if ($id && !$action) {
        // Single contact operations
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $controller->getContact($id);
                break;
            case 'PUT':
                $controller->updateContact($id);
                break;
            case 'DELETE':
                $controller->deleteContact($id);
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    } else {
        // Collection operations
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $controller->getContacts();
                break;
            case 'POST':
                $controller->createContact();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    }
}

function handleCampaignRoutes($controller, $id, $action) {
    if ($id === 'stats') {
        $controller->getCampaignStats();
        return;
    }
    if ($id && $action) {
        // Campaign actions
        switch ($action) {
            case 'recipients':
                if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                    $controller->getRecipients($id);
                } else {
                    $controller->addRecipients($id);
                }
                break;
            case 'send':
                $controller->sendCampaign($id);
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Campaign action not found']);
        }
    } elseif ($id) {
        // Single campaign operations
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $controller->getCampaign($id);
                break;
            case 'PUT':
                $controller->updateCampaign($id);
                break;
            case 'DELETE':
                $controller->deleteCampaign($id);
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    } else {
        // Collection operations
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $controller->getCampaigns();
                break;
            case 'POST':
                $controller->createCampaign();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    }
}

function handleTemplateRoutes($controller, $id) {
    if ($id) {
        // Single template operations
        http_response_code(404);
        echo json_encode(['error' => 'Template endpoint not implemented']);
    } else {
        // Collection operations
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $controller->getTemplates();
                break;
            case 'POST':
                $controller->createTemplate();
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    }
}

function handleTrackingRoutes($db, $type, $trackingId) {
    $emailService = new EmailService($db);
    switch ($type) {
        case 'open':
            if ($trackingId) {
                $emailService->trackEmailOpen($trackingId);
                // Return 1x1 transparent pixel
                header('Content-Type: image/gif');
                echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
                exit;
            }
            break;
        case 'click':
            if ($trackingId && isset($_GET['url'])) {
                $url = $_GET['url'];
                $emailService->trackEmailClick($trackingId, $url);
                // Redirect to original URL
                header("Location: $url");
                exit;
            }
            break;
        case 'unsubscribe':
            if ($trackingId) {
                $success = $emailService->unsubscribe($trackingId);
                if ($success) {
                    echo '<h1>Unsubscribed Successfully</h1>';
                    echo '<p>You have been unsubscribed from our mailing list.</p>';
                } else {
                    echo '<h1>Error</h1>';
                    echo '<p>Unable to process unsubscribe request.</p>';
                }
                exit;
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Tracking endpoint not found']);
    }
} 