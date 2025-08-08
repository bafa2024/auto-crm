<?php
require_once __DIR__ . '/../config/config.php';
// Load Composer autoloader if available (for PHPMailer and other deps)
$vendorAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}
require_once __DIR__ . '/../config/database.php';

// Include models
require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/Contact.php';
require_once __DIR__ . '/../models/EmailCampaign.php';
require_once __DIR__ . '/../models/User.php';

// Include controllers
require_once __DIR__ . '/../controllers/BaseController.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ContactController.php';
require_once __DIR__ . '/../controllers/ContactHistoryController.php';
require_once __DIR__ . '/../controllers/EmailRecipientController.php';
require_once __DIR__ . '/../controllers/EmailCampaignController.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/InstantEmailController.php';
require_once __DIR__ . '/../services/EmailService.php';

// Set API mode
define('API_MODE', true);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure session user_id is available for API requests
if (!isset($_SESSION['user_id']) && isset($_COOKIE['PHPSESSID'])) {
    // Try to restore session from cookie
    session_id($_COOKIE['PHPSESSID']);
}

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
// Remove the /acrm prefix if present
$requestUri = preg_replace('#^/acrm#', '', $requestUri);
$basePath = '/api';
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$pathParts = array_filter(explode('/', $path));

// Basic routing
$resource = array_shift($pathParts) ?? '';
$id = array_shift($pathParts) ?? null;
$action = array_shift($pathParts) ?? null;

// Set content type
header('Content-Type: application/json');

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
        case 'contact-history':
            $controller = new ContactHistoryController($db);
            handleContactHistoryRoutes($controller, $id, $action);
            break;
        case 'recipients':
            $controller = new EmailRecipientController($db);
            handleRecipientRoutes($controller, $id, $action);
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
        case 'employees':
            require_once __DIR__ . '/../controllers/UserController.php';
            $controller = new UserController($db);
            handleEmployeeRoutes($controller, $id, $action);
            break;
        case 'employee':
            require_once __DIR__ . '/../controllers/UserController.php';
            $controller = new UserController($db);
            handleEmployeeProfileRoutes($controller, $id, $action);
            break;
        case 'settings':
            $controller = new SettingsController($db);
            handleSettingsRoutes($controller, $id, $action);
            break;
        case 'instant-email':
            $controller = new InstantEmailController($db);
            handleInstantEmailRoutes($controller, $id, $action);
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
    error_log("API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("Resource: " . $resource);
    error_log("Path parts: " . json_encode($pathParts));
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
        case 'forgot-password':
            $controller->forgotPassword();
            break;
        case 'reset-password':
            $controller->resetPassword();
            break;
        case 'validate-reset-token':
            $controller->validateResetToken();
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
    if ($id === 'export') {
        $controller->exportContacts();
        return;
    }
    if ($id === 'delete-all') {
        $controller->deleteAllContacts();
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

function handleContactHistoryRoutes($controller, $id, $action) {
    if ($id === 'recent-uploads') {
        $controller->getRecentUploads();
        return;
    }
    if ($id === 'upload-statistics') {
        $controller->getUploadStatistics();
        return;
    }
    if ($id === 'by-batch') {
        $controller->getContactsByBatch();
        return;
    }
    if ($id === 'delete-batch') {
        $controller->deleteContactsByBatch();
        return;
    }
    if ($id === 'archive-batch') {
        $controller->archiveContactsByBatch();
        return;
    }
    if ($id === 'management-stats') {
        $controller->getDataManagementStats();
        return;
    }
    if ($id && !$action) {
        // Get contact history for specific contact
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $controller->getContactHistory($id);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Contact history endpoint not found']);
    }
}

function handleRecipientRoutes($controller, $id, $action) {
    if ($id === 'delete-all' && !$action) {
        // Delete all recipients
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $controller->deleteAllRecipients();
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
    } elseif ($id && !$action) {
        // Single recipient operations
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $controller->getRecipient($id);
                break;
            case 'PUT':
                $controller->updateRecipient($id);
                break;
            case 'DELETE':
                $controller->deleteRecipient($id);
                break;
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Recipient endpoint not found']);
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

function handleSettingsRoutes($controller, $action, $subAction) {
    switch ($action) {
        case 'smtp':
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET':
                    $controller->getSmtpSettings();
                    break;
                case 'POST':
                    $controller->updateSmtpSettings();
                    break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'smtp-test':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->testSmtpConnection();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'test-email':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->sendTestEmail();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Settings endpoint not found']);
    }
}

function handleEmployeeRoutes($controller, $action, $subAction) {
    switch ($action) {
        case 'list':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->listEmployees();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $request = new stdClass();
                $request->body = $input;
                $controller->createEmployee($request);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'edit':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $request = new stdClass();
                $request->body = $input;
                $controller->editEmployee($request);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'delete':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                $request = new stdClass();
                $request->body = $input;
                $controller->deleteEmployee($request);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        default:
            // Handle numeric IDs for /api/employees/{id} and /api/employees/{id}/teams etc
            if (is_numeric($action)) {
                $userId = (int)$action;
                if (!$subAction) {
                    // Handle /api/employees/{id}
                    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                        $controller->getEmployee($userId);
                    } else {
                        http_response_code(405);
                        echo json_encode(['error' => 'Method not allowed']);
                    }
                } else {
                    // Handle /api/employees/{id}/{subAction}
                    switch ($subAction) {
                        case 'teams':
                            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                                $controller->getEmployeeTeams($userId);
                            } else {
                                http_response_code(405);
                                echo json_encode(['error' => 'Method not allowed']);
                            }
                            break;
                        case 'permissions':
                            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                                $controller->getEmployeePermissions($userId);
                            } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                                $input = json_decode(file_get_contents('php://input'), true);
                                $request = new stdClass();
                                $request->body = $input;
                                $controller->updateEmployeePermissions($userId, $request);
                            } else {
                                http_response_code(405);
                                echo json_encode(['error' => 'Method not allowed']);
                            }
                            break;
                        default:
                            http_response_code(404);
                            echo json_encode(['error' => 'Employee endpoint not found']);
                    }
                }
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Employee endpoint not found']);
            }
    }
}

function handleEmployeeProfileRoutes($controller, $action, $subAction) {
    switch ($action) {
        case 'profile':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->getEmployeeProfile();
            } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
                $input = json_decode(file_get_contents('php://input'), true);
                $request = new stdClass();
                $request->body = $input;
                $controller->updateEmployeeProfile($request);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Employee profile endpoint not found']);
    }
}

function handleInstantEmailRoutes($controller, $id, $action) {
    switch ($action) {
        case 'send':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $controller->sendInstantEmail();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'templates':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->getEmailTemplates();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'contacts':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->getContactSuggestions();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        case 'history':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $controller->getSentHistory();
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
            }
            break;
        default:
            http_response_code(404);
            echo json_encode(['error' => 'Instant email endpoint not found']);
    }
} 