<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ContactController.php';
require_once __DIR__ . '/../version.php';

// Start session if not already started and headers not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}

// Set headers for API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple session check
// Temporarily bypass session check for testing
// if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
//     http_response_code(401);
//     echo json_encode(['error' => 'Unauthorized access']);
//     exit();
// }

// Initialize database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Initialize Contact Controller
$contactController = new ContactController($pdo);

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle different API actions
switch ($method) {
    case 'GET':
        handleGetRequest($contactController, $action);
        break;
    case 'POST':
        handlePostRequest($contactController, $action);
        break;
    case 'PUT':
        handlePutRequest($contactController, $action);
        break;
    case 'DELETE':
        handleDeleteRequest($contactController, $action);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

// Handle GET requests
function handleGetRequest($contactController, $action) {
    switch ($action) {
        case 'list':
            getContactsList($contactController);
            break;
        case 'list_all':
            getAllContacts($contactController);
            break;
        case 'view':
            getContactDetails($contactController);
            break;
        case 'history':
            getContactHistory($contactController);
            break;
        case 'stats':
            getContactStats($contactController);
            break;
        case 'search':
            searchContacts($contactController);
            break;
        case 'download_template':
            downloadTemplate($contactController);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Handle POST requests
function handlePostRequest($contactController, $action) {
    switch ($action) {
        case 'create':
        case 'create_contact':
            createContact($contactController);
            break;
        case 'import':
        case 'import_contacts':
            importContacts($contactController);
            break;
        case 'preview_import':
            previewImport($contactController);
            break;
        case 'bulk_delete':
            bulkDeleteContacts($contactController);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Handle PUT requests
function handlePutRequest($contactController, $action) {
    switch ($action) {
        case 'update':
            updateContact($contactController);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Handle DELETE requests
function handleDeleteRequest($contactController, $action) {
    switch ($action) {
        case 'delete':
            deleteContact($contactController);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
}

// Get all contacts using list_all function
function getAllContacts($contactController) {
    try {
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(1, min(100, intval($_GET['per_page'] ?? 50)));
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $company = $_GET['company'] ?? '';
        
        $result = $contactController->list_all($page, $per_page, $search, $status, $company);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch all contacts: ' . $e->getMessage()]);
    }
}

// Get contacts list with pagination and filters
function getContactsList($contactController) {
    try {
        $page = max(1, intval($_GET['page'] ?? 1));
        $per_page = max(1, min(100, intval($_GET['per_page'] ?? 10)));
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $company = $_GET['company'] ?? '';
        $sort_by = $_GET['sort_by'] ?? 'created_at';
        $sort_direction = $_GET['sort_direction'] ?? 'DESC';
        
        $result = $contactController->getContactsList($page, $per_page, $search, $status, $company, $sort_by, $sort_direction);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch contacts: ' . $e->getMessage()]);
    }
}

// Get contact details
function getContactDetails($contactController) {
    try {
        $contact_id = intval($_GET['id'] ?? 0);
        
        if ($contact_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid contact ID']);
            return;
        }
        
        $result = $contactController->getContactById($contact_id);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch contact details: ' . $e->getMessage()]);
    }
}

// Get contact history
function getContactHistory($contactController) {
    try {
        $contact_id = intval($_GET['id'] ?? 0);
        
        if ($contact_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid contact ID']);
            return;
        }
        
        $result = $contactController->getContactHistory($contact_id);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(404);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch contact history: ' . $e->getMessage()]);
    }
}

// Get contact statistics
function getContactStats($contactController) {
    try {
        $result = $contactController->getContactStats();
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch statistics: ' . $e->getMessage()]);
    }
}

// Search contacts
function searchContacts($contactController) {
    try {
        $search_term = $_GET['q'] ?? '';
        
        if (empty($search_term)) {
            echo json_encode(['success' => true, 'data' => []]);
            return;
        }
        
        $result = $contactController->searchContacts($search_term);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to search contacts: ' . $e->getMessage()]);
    }
}

// Create new contact
function createContact($contactController) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        if (empty($input['name']) || empty($input['email'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Name and email are required']);
            return;
        }
        
        $result = $contactController->create_contact($input);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create contact: ' . $e->getMessage()]);
    }
}

// Update contact
function updateContact($contactController) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $contact_id = intval($input['id'] ?? 0);
        
        if ($contact_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid contact ID']);
            return;
        }
        
        $result = $contactController->updateContact($contact_id, $input);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update contact: ' . $e->getMessage()]);
    }
}

// Delete contact
function deleteContact($contactController) {
    try {
        $contact_id = intval($_GET['id'] ?? 0);
        
        if ($contact_id <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid contact ID']);
            return;
        }
        
        $result = $contactController->deleteContact($contact_id);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete contact: ' . $e->getMessage()]);
    }
}

// Bulk delete contacts
function bulkDeleteContacts($contactController) {
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        $contact_ids = $input['ids'] ?? [];
        
        if (empty($contact_ids) || !is_array($contact_ids)) {
            http_response_code(400);
            echo json_encode(['error' => 'No contact IDs provided']);
            return;
        }
        
        $result = $contactController->bulkDeleteContacts($contact_ids);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete contacts: ' . $e->getMessage()]);
    }
}

// Import contacts
function importContacts($contactController) {
    try {
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            return;
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload error: ' . $file['error']]);
            return;
        }
        
        // Simple file extension check
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx', 'xls'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid file type. Please upload CSV or Excel files only.']);
            return;
        }
        
        $skip_header = isset($_POST['skip_header']) && $_POST['skip_header'] === '1';
        
        $result = $contactController->importContacts($file, $skip_header);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to import contacts: ' . $e->getMessage()]);
    }
}

// Preview import data
function previewImport($contactController) {
    try {
        if (!isset($_FILES['file'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No file uploaded']);
            return;
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['error' => 'File upload error: ' . $file['error']]);
            return;
        }
        
        $skip_header = isset($_POST['skip_header']) && $_POST['skip_header'] === '1';
        
        $result = $contactController->previewImport($file, $skip_header);
        
        if ($result['success']) {
            echo json_encode($result);
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to preview import: ' . $e->getMessage()]);
    }
}

// Download template
function downloadTemplate($contactController) {
    try {
        $type = $_GET['type'] ?? 'csv';
        
        if (!in_array($type, ['csv', 'xlsx'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid template type']);
            return;
        }
        
        $result = $contactController->downloadTemplate($type);
        
        if ($result['success']) {
            // Set headers for file download
            header('Content-Type: ' . $result['content_type']);
            header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
            header('Content-Length: ' . strlen($result['content']));
            
            echo $result['content'];
        } else {
            http_response_code(400);
            echo json_encode(['error' => $result['message']]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to download template: ' . $e->getMessage()]);
    }
}
?> 